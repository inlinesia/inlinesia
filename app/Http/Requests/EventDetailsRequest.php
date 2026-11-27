<?php

namespace App\Http\Requests;

use App\Models\Event;
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
        $tierRule = ['sometimes', 'in:'.Event::TIER_ADVERTISEMENT.','.Event::TIER_FULL];
        $nameRule = ['required', 'string', 'max:180'];
        $startRule = ['required', 'date'];
        $endRule = ['required', 'date', 'after_or_equal:start_date'];
        $venueRule = ['required', 'string', 'max:180'];
        $addressRule = ['required', 'string', 'max:1000'];
        $whatsappRule = ['required', 'string', 'max:40', 'regex:/^(?=(?:\D*\d){8,15}\D*$)\+?[0-9\s().-]{8,40}$/'];
        $groupRule = ['nullable', 'url', 'max:500', 'regex:/^https:\/\/(chat\.whatsapp\.com|wa\.me)\//i'];

        if ($this->input('event_tier') === Event::TIER_ADVERTISEMENT) {
            $rules = [
                'event_tier' => $tierRule,
                'name' => $nameRule,
                'start_date' => $startRule,
                'end_date' => $endRule,
                'venue' => $venueRule,
                'venue_address' => $addressRule,
                'scale' => ['required', 'string', 'max:120'],
                'short_description' => ['required', 'string', 'max:500'],
                'organizer_whatsapp' => $whatsappRule,
                'whatsapp_group_link' => $groupRule,
            ];

            if ($this->isCreating()) {
                $rules['start_date'][] = 'after_or_equal:today';
            }

            return $rules;
        }

        $rules = [
            'event_tier' => $tierRule,
            'name' => $nameRule,
            'start_date' => $startRule,
            'end_date' => $endRule,
            'venue' => $venueRule,
            'venue_address' => $addressRule,
            'short_description' => ['nullable', 'string', 'max:500'],
            'organizer_whatsapp' => $whatsappRule,
            'whatsapp_group_link' => $groupRule,
            'cancellation_allowed' => ['boolean'],
            'cancellation_min_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'cancellation_deadline' => ['nullable', 'date'],
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
        // Pengajuan lama/API tanpa field paket tetap dianggap berbayar.
        $validated['event_tier'] ??= Event::TIER_FULL;

        if ($validated['event_tier'] === Event::TIER_ADVERTISEMENT) {
            // Kolom daftar ini NOT NULL pada skema events; event iklan tidak
            // mengisi KU/mata lomba/jadwal, jadi disimpan sebagai array kosong.
            $validated += [
                'categories' => [],
                'races' => [],
                'schedule' => [],
            ];
        }

        $validated['name'] = trim($validated['name']);
        $validated['venue'] = trim($validated['venue']);
        $validated['organizer_whatsapp'] = trim($validated['organizer_whatsapp']);
        $groupLink = trim((string) ($validated['whatsapp_group_link'] ?? ''));
        $validated['whatsapp_group_link'] = $groupLink === '' ? null : $groupLink;

        if (array_key_exists('short_description', $validated)) {
            $shortDescription = trim((string) $validated['short_description']);
            $validated['short_description'] = $shortDescription === '' ? null : $shortDescription;
        }

        $validated = $this->normalizePaymentAccount($validated);

        foreach (self::LINE_FIELDS as $field) {
            if (! array_key_exists($field, $validated) || ! is_string($validated[$field])) {
                continue;
            }

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
