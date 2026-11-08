<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class EventDetailsRequest extends FormRequest
{
    /**
     * Kolom yang diisi pengguna sebagai daftar satu per baris.
     *
     * @var list<string>
     */
    private const LINE_FIELDS = ['categories', 'races', 'schedule'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * True saat permintaan membuat event baru, false saat menyunting event yang sudah ada.
     */
    public function isCreating(): bool
    {
        return $this->route('event') === null;
    }

    /**
     * Satu daftar aturan untuk Pengajuan dan Penyuntingan event alike. Perbedaan
     * antar keduanya hanya soal tanggal: pengajuan baru tidak boleh memakai tanggal
     * yang sudah lewat dan batas pendaftaran wajib diisi, sedangkan penyuntingan
     * memakai event yang bisa saja sudah berjalan.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:180'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'venue' => ['required', 'string', 'max:180'],
            'venue_address' => ['required', 'string', 'max:1000'],
            'organizer_whatsapp' => ['required', 'string', 'max:40', 'regex:/^(?=(?:\D*\d){8,15}\D*$)\+?[0-9\s().-]{8,40}$/'],
            'scale' => ['required', 'string', 'max:120'],
            'regulations' => ['required', 'string', 'max:5000'],
            'officials' => ['required', 'string', 'max:2000'],
            'venue_conditions' => ['required', 'string', 'max:2000'],
            'awards' => ['required', 'string', 'max:3000'],
            'categories' => ['required', 'string', 'max:5000'],
            'races' => ['required', 'string', 'max:5000'],
            'registration_deadline' => ['nullable', 'date', 'before:start_date'],
            'registration_info' => ['required', 'string', 'max:5000'],
            'fees' => ['required', 'string', 'max:5000'],
            'payment_bank_name' => ['nullable', 'string', 'max:80'],
            'payment_account_number' => ['nullable', 'string', 'max:60', 'regex:/^[0-9][0-9\s.-]{5,59}$/'],
            'payment_account_holder' => ['nullable', 'string', 'max:120'],
            'is_free' => ['required', 'boolean'],
            'force_majeure' => ['required', 'string', 'max:5000'],
            'schedule' => ['required', 'string', 'max:8000'],
        ];

        if ($this->isCreating()) {
            $rules['start_date'][] = 'after_or_equal:today';
            $rules['registration_deadline'] = ['required', 'date', 'after_or_equal:today', 'before:start_date'];
        }

        return $rules;
    }

    /**
     * Data event siap disimpan, dengan kolom per-baris diubah menjadi array.
     *
     * @return array<string, mixed>
     */
    public function eventAttributes(): array
    {
        $validated = $this->validated();
        $validated['name'] = trim($validated['name']);
        $validated['venue'] = trim($validated['venue']);
        $validated['organizer_whatsapp'] = trim($validated['organizer_whatsapp']);
        $validated = $this->normalizePaymentAccount($validated);

        foreach (self::LINE_FIELDS as $field) {
            $lines = collect(preg_split('/\R/u', $validated[$field]))
                ->map(fn (string $line) => trim($line))
                ->filter()
                ->values()
                ->all();

            if ($lines === []) {
                throw ValidationException::withMessages([
                    $field => 'Tambahkan minimal satu isian, satu per baris.',
                ]);
            }

            $validated[$field] = $lines;
        }

        return $validated;
    }

    /**
     * Merapikan isian rekening: spasi dibuang, isian kosong menjadi null,
     * dan nomor rekening tanpa nama bank ditolak supaya data yang tersimpan
     * selalu bisa ditampilkan utuh ke peserta.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizePaymentAccount(array $validated): array
    {
        $bankName = trim((string) ($validated['payment_bank_name'] ?? ''));
        $accountNumber = preg_replace('/[\s.-]+/', '', (string) ($validated['payment_account_number'] ?? '')) ?? '';
        $accountHolder = trim((string) ($validated['payment_account_holder'] ?? ''));

        if ($accountNumber !== '' && $bankName === '') {
            throw ValidationException::withMessages([
                'payment_bank_name' => 'Isi nama bank bila nomor rekening dicantumkan.',
            ]);
        }

        $validated['payment_bank_name'] = $bankName === '' ? null : $bankName;
        $validated['payment_account_number'] = $accountNumber === '' ? null : $accountNumber;
        $validated['payment_account_holder'] = $accountHolder === '' ? null : $accountHolder;

        return $validated;
    }
}
