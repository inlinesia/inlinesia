<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Services\CategoryFeeCalculator;
use App\Services\RegistrationIdentity;
use App\Services\ResolveEventRegistrationCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class EventRegistrationController extends Controller
{
    public function create(Event $event): View
    {
        abort_unless($event->status === 'approved', 404);
        abort_unless($event->registration_open, 409, 'Pengelola kejuaraan belum membuka pendaftaran.');
        abort_if($event->registration_deadline && $event->registration_deadline->endOfDay()->isPast(), 410, 'Pendaftaran event ini telah ditutup.');
        abort_if(empty($event->category_rules), 409, 'Pengelola kejuaraan belum mengatur kategori peserta.');

        return view('verified.events.pendaftaran.daftar', [
            'event' => $event,
            'registrations' => EventRegistration::query()
                ->where('event_id', $event->id)
                ->where('user_id', request()->user()->id)
                ->latest()
                ->get(),
        ]);
    }

    public function store(
        Request $request,
        Event $event,
        ResolveEventRegistrationCategory $categoryResolver,
        CategoryFeeCalculator $feeCalculator,
    ): RedirectResponse {
        abort_unless($event->status === 'approved', 404);
        abort_unless($event->registration_open, 409, 'Pengelola kejuaraan belum membuka pendaftaran.');
        abort_if($event->registration_deadline && $event->registration_deadline->endOfDay()->isPast(), 410, 'Pendaftaran event ini telah ditutup.');
        abort_if(empty($event->category_rules), 409, 'Pengelola kejuaraan belum mengatur kategori peserta.');

        $identityService = app(RegistrationIdentity::class);
        $validated = $request->validate([
            ...$identityService->fieldRules(),
            'participant_name' => ['required', 'string', 'max:120'],
            'nickname' => ['nullable', 'string', 'max:80'],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'date_of_birth' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'address' => ['required', 'string', 'max:1000'],
            'guardian_name' => ['required', 'string', 'max:120'],
            'whatsapp' => ['required', 'string', 'max:40'],
            'club_name' => ['nullable', 'string', 'max:180'],
            'races' => ['required', 'array', 'min:1', 'distinct'],
            'races.*' => ['required', 'string', Rule::in($event->races)],
        ], [
            'nik.regex' => 'NIK harus terdiri dari 16 digit angka.',
        ]);

        $identityService->assertComplete($validated);

        $eligibleCategories = $categoryResolver->eligibleCategories(
            $event,
            $validated['date_of_birth'],
            $validated['gender'],
        );
        $match = $categoryResolver->bestMatch(
            $event,
            $validated['date_of_birth'],
            $validated['gender'],
            $validated['races'],
        );

        if (! $match) {
            $field = $eligibleCategories->isEmpty() ? 'date_of_birth' : 'races';
            $message = $eligibleCategories->isEmpty()
                ? 'Tanggal lahir, usia pada hari pertama kejuaraan, dan gender atlet tidak memenuhi aturan kategori kejuaraan.'
                : 'Mata lomba yang dipilih tidak tersedia untuk kategori atlet berdasarkan tanggal lahir, usia, dan gender.';

            throw ValidationException::withMessages([$field => $message]);
        }

        $requiredRaces = $feeCalculator->requiredRaces($match['rule']);
        if (count($match['in_category_races']) < $requiredRaces) {
            throw ValidationException::withMessages([
                'races' => "Kategori {$match['rule']['name']} mewajibkan {$requiredRaces} mata lomba. Pilih minimal {$requiredRaces} mata lomba yang tersedia pada kategori ini.",
            ]);
        }

        $identity = $identityService->normalize($validated);
        $identityService->ensureNotDuplicate($event, $request->user()->id, $identity);

        EventRegistration::create([
            ...$validated,
            ...$identity,
            'event_id' => $event->id,
            'user_id' => $request->user()->id,
            'birth_year' => (int) substr($validated['date_of_birth'], 0, 4),
            'category' => null,
            'races' => array_values($validated['races']),
            'status' => 'pending',
        ]);

        return redirect()->route('guest.dashboard')
            ->with('status', 'Data atlet berhasil disimpan. Lanjutkan konfirmasi pembayaran untuk menetapkan kategori.');
    }

    public function updateOwn(
        Request $request,
        Event $event,
        EventRegistration $registration,
        ResolveEventRegistrationCategory $categoryResolver,
    ): RedirectResponse {
        abort_unless($registration->user_id === $request->user()->id, 403);
        abort_unless($event->status === 'approved', 404);
        abort_if($registration->cancellation_status === 'pending' || $registration->cancellation_status === 'approved', 409, 'Pendaftaran yang sedang atau sudah dibatalkan tidak dapat diedit.');
        abort_if($registration->participant_change_status === 'pending', 409, 'Permintaan perubahan sebelumnya masih menunggu keputusan pengelola.');

        $identityService = app(RegistrationIdentity::class);
        $validated = $request->validate([
            ...$identityService->fieldRules(),
            'participant_name' => ['required', 'string', 'max:120'],
            'nickname' => ['nullable', 'string', 'max:80'],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'date_of_birth' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'address' => ['required', 'string', 'max:1000'],
            'guardian_name' => ['required', 'string', 'max:120'],
            'whatsapp' => ['required', 'string', 'max:40'],
            'club_name' => ['nullable', 'string', 'max:180'],
            'races' => ['required', 'array', 'min:1', 'distinct'],
            'races.*' => ['required', 'string', Rule::in($event->races)],
        ], [
            'nik.regex' => 'NIK harus terdiri dari 16 digit angka.',
        ]);

        $identityService->assertComplete($validated);

        $match = $categoryResolver->bestMatch(
            $event,
            $validated['date_of_birth'],
            $validated['gender'],
            $validated['races'],
        );
        if (! $match) {
            throw ValidationException::withMessages([
                'races' => 'Data peserta dan mata lomba tidak sesuai dengan kategori yang tersedia.',
            ]);
        }

        if (count($match['in_category_races']) < max(1, (int) ($match['rule']['required_races'] ?? 1))) {
            throw ValidationException::withMessages([
                'races' => "Kategori {$match['rule']['name']} mewajibkan minimal {$match['rule']['required_races']} mata lomba.",
            ]);
        }

        $identity = $identityService->normalize($validated);
        $identityService->ensureNotDuplicate($event, $request->user()->id, $identity, $registration->id);

        if ($registration->payment_status === 'paid' || $registration->amountPaid() > 0) {
            $registration->update([
                'participant_change_request' => $validated,
                'participant_change_status' => 'pending',
                'participant_change_requested_at' => now(),
            ]);

            return redirect()->route('guest.dashboard')
                ->with('status', 'Permintaan perubahan dikirim dan menunggu persetujuan pengelola. Tarif akan dihitung ulang setelah diterima.');
        }

        $oldProofPath = $registration->payment_proof_path;
        $registration->update([
            ...$validated,
            ...$identity,
            'birth_year' => (int) substr($validated['date_of_birth'], 0, 4),
            'races' => array_values($validated['races']),
            'category' => null,
            'fee_total' => null,
            'fee_breakdown' => null,
            'payment_status' => 'unpaid',
            'payment_channel' => null,
            'payment_proof_path' => null,
            'confirmed_paid_at' => null,
        ]);

        if ($oldProofPath) {
            Storage::disk('local')->delete($oldProofPath);
        }

        return redirect()->route('guest.dashboard')
            ->with('status', 'Data pendaftaran berhasil diperbarui. Konfirmasi pembayaran perlu dikirim kembali.');
    }

    public function destroyOwn(Request $request, Event $event, EventRegistration $registration): RedirectResponse
    {
        abort_unless($registration->user_id === $request->user()->id, 403);
        abort_if($registration->cancellation_status === 'pending', 409, 'Permintaan pembatalan sedang menunggu keputusan pengelola.');
        abort_if($registration->cancellation_status === 'approved', 409, 'Pendaftaran ini sudah dibatalkan.');
        abort_if($registration->participant_change_status === 'pending', 409, 'Permintaan perubahan masih menunggu keputusan pengelola.');

        if ($registration->payment_status === 'paid' || $registration->amountPaid() > 0) {
            $registration->update([
                'cancellation_status' => 'pending',
                'cancellation_requested_at' => now(),
                'cancellation_decided_at' => null,
            ]);

            return redirect()->route('guest.dashboard')
                ->with('status', 'Permintaan pembatalan dikirim. Pengelola akan memutuskan persetujuan dan nominal pengembalian dana.');
        }

        $proofPath = $registration->payment_proof_path;
        $registration->delete();

        if ($proofPath) {
            Storage::disk('local')->delete($proofPath);
        }

        return redirect()->route('guest.dashboard')
            ->with('status', 'Pendaftaran berhasil dibatalkan.');
    }

    public function uploadProof(Request $request, Event $event, EventRegistration $registration): RedirectResponse
    {
        abort_unless($registration->user_id === $request->user()->id, 403);
        abort_unless($event->status === 'approved', 404);
        abort_if(in_array($registration->cancellation_status, ['pending', 'approved'], true), 409, 'Pendaftaran yang sedang atau sudah dibatalkan tidak dapat menerima pembayaran.');
        abort_if($event->is_free, 409, 'Event gratis tidak memerlukan bukti pembayaran.');
        abort_if($registration->payment_status === 'paid', 409, 'Pembayaran peserta sudah dikonfirmasi.');

        $validated = $request->validate([
            'payment_proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $path = $validated['payment_proof']->store("payment-proofs/{$event->id}/{$registration->id}", 'local');
        if (! $path) {
            throw new RuntimeException('Bukti pembayaran gagal disimpan.');
        }

        $oldPath = $registration->payment_proof_path;
        $registration->update([
            'payment_proof_path' => $path,
            'payment_channel' => 'upload',
            'payment_status' => 'pending',
            'category' => null,
        ]);

        if ($oldPath && $oldPath !== $path) {
            Storage::disk('local')->delete($oldPath);
        }

        return back()->with('status', 'Bukti pembayaran berhasil dikirim dan menunggu konfirmasi pemilik event.');
    }

    public function confirmViaWhatsapp(Request $request, Event $event, EventRegistration $registration): RedirectResponse
    {
        abort_unless($registration->user_id === $request->user()->id, 403);
        abort_unless($event->status === 'approved', 404);
        abort_if(in_array($registration->cancellation_status, ['pending', 'approved'], true), 409, 'Pendaftaran yang sedang atau sudah dibatalkan tidak dapat menerima pembayaran.');
        abort_if($registration->payment_status === 'paid', 409, 'Pendaftaran peserta sudah dikonfirmasi.');

        $phone = preg_replace('/\D+/', '', (string) $event->organizer_whatsapp);
        if (! $phone) {
            throw ValidationException::withMessages([
                'payment' => 'Nomor WhatsApp pengelola kejuaraan belum tersedia. Hubungi Admin.',
            ]);
        }
        if (str_starts_with($phone, '0')) {
            $phone = '62'.substr($phone, 1);
        } elseif (str_starts_with($phone, '8')) {
            $phone = '62'.$phone;
        }
        if (strlen($phone) < 8 || strlen($phone) > 15) {
            throw ValidationException::withMessages([
                'payment' => 'Nomor WhatsApp pengelola kejuaraan belum valid. Hubungi Admin.',
            ]);
        }

        $registration->update([
            'payment_channel' => 'whatsapp',
            'payment_status' => 'pending',
            'category' => null,
        ]);

        $message = $event->is_free
            ? "Halo, saya mengonfirmasi pendaftaran gratis atlet {$registration->participant_name} pada event {$event->name}."
            : "Halo, saya mengonfirmasi pembayaran atlet {$registration->participant_name} pada event {$event->name}. Saya akan mengirimkan bukti transfer melalui percakapan ini.";

        return redirect()->away('https://wa.me/'.$phone.'?text='.rawurlencode($message));
    }

    /**
     * API endpoint untuk mendapatkan informasi kategori otomatis
     * dipanggil setelah peserta memilih gender dan usia di form pendaftaran.
     */
    public function resolveCategory(Request $request, Event $event): JsonResponse
    {
        $request->validate([
            'date_of_birth' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'races' => ['required', 'array', 'min:1', Rule::each(Rule::in($event->races))],
        ]);

        $categoryResolver = app(ResolveEventRegistrationCategory::class);

        $eligibleCategories = $categoryResolver->eligibleCategories(
            $event,
            $request->date_of_birth,
            $request->gender,
        );

        $match = $categoryResolver->bestMatch(
            $event,
            $request->date_of_birth,
            $request->gender,
            $request->races,
        );

        if (! $match) {
            return response()->json([
                'success' => false,
                'message' => $eligibleCategories->isEmpty()
                    ? 'Tanggal lahir, usia pada hari pertama kejuaraan, dan gender atlet tidak memenuhi aturan kategori kejuaraan.'
                    : 'Mata lomba yang dipilih tidak tersedia untuk kategori atlet berdasarkan tanggal lahir, usia, dan gender.',
                'eligible_categories' => $eligibleCategories->map(fn ($rule) => [
                    'name' => $rule['name'] ?? 'Tanpa nama',
                    'minimum_age' => $rule['minimum_age'] ?? null,
                    'maximum_age' => $rule['maximum_age'] ?? null,
                    'genders' => $rule['genders'] ?? [],
                ])->values()->all(),
                'no_match' => true,
            ], 422);
        }

        $calculator = app(CategoryFeeCalculator::class);
        $requiredRaces = $calculator->requiredRaces($match['rule']);

        $inCategoryRaces = $match['in_category_races'];
        $outsideCategoryRaces = $match['outside_races'] ?? [];

        $selectedRaces = array_values(array_merge($inCategoryRaces, $outsideCategoryRaces));
        $breakdown = $calculator->breakdown($match['rule'], $selectedRaces);

        return response()->json([
            'success' => true,
            'category' => [
                'name' => $match['rule']['name'] ?? 'Tanpa nama',
                'minimum_age' => $match['rule']['minimum_age'] ?? null,
                'maximum_age' => $match['rule']['maximum_age'] ?? null,
                'required_races' => $breakdown['required_races'],
                'package_fee' => $breakdown['package_fee'],
                'extra_race_fee' => $breakdown['extra_race_fee'],
                'outside_race_fee' => $breakdown['outside_race_fee'],
                'discount_percent' => $breakdown['discount_percent'],
                'discount_amount' => $breakdown['discount_amount'],
                'extra_races' => $breakdown['extra_races'],
                'extra_race_total' => $breakdown['extra_race_total'],
                'outside_race_total' => $breakdown['outside_race_total'],
                'subtotal' => $breakdown['subtotal'],
                'discount_total' => $breakdown['discount_total'],
                'total' => $breakdown['total'],
            ],
            'in_category_races' => $inCategoryRaces,
            'outside_category_races' => $outsideCategoryRaces,
            'eligible_categories' => $eligibleCategories->map(fn ($rule) => [
                'name' => $rule['name'] ?? 'Tanpa nama',
                'minimum_age' => $rule['minimum_age'] ?? null,
                'maximum_age' => $rule['maximum_age'] ?? null,
                'genders' => $rule['genders'] ?? [],
            ])->values()->all(),
            'selected_races' => array_map('strval', $request->races),
        ]);
    }
}
