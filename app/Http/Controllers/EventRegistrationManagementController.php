<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateEventRegistrationRequest;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\EventRegistrationFinancialEntry;
use App\Services\AssignRegistrationCategories;
use App\Services\CategoryFeeCalculator;
use App\Services\ReconcileRegistrationFee;
use App\Services\RegistrationIdentity;
use App\Services\ResolveEventRegistrationCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventRegistrationManagementController extends Controller
{
    /**
     * Kolom daftar peserta yang boleh dipakai untuk sortir. Nilai array adalah
     * nama kolom di database, jadi tidak ada nilai dari pengguna yang masuk
     * langsung ke orderBy().
     */
    private const ROSTER_SORTABLE_COLUMNS = [
        'nama' => 'participant_name',
        'kategori' => 'category',
        'tahun' => 'birth_year',
        'lahir' => 'date_of_birth',
        'gender' => 'gender',
        'kontak' => 'whatsapp',
        'klub' => 'club_name',
        'pembayaran' => 'payment_status',
        'status' => 'status',
    ];

    /**
     * Baris per halaman daftar peserta.
     *
     * Dibatasi 50 supaya satu halaman muat di kertas A4 ketika dicetak,
     * tanpa memotong satu peserta di tengah halaman.
     */
    private const ROSTER_PER_PAGE = 50;

    public function index(Request $request, Event $event): View
    {
        $this->abortUnlessRosterIsReadable($request, $event);
        $canManage = $this->canManage($request, $event);

        $categoryBreakdown = [];
        $paidWithoutCategory = 0;
        $filterOptions = [];

        if ($canManage) {
            $filters = $this->rosterFilters($request);
            $sort = $this->rosterSort($request);
            $groupByCategory = $request->boolean('group', '1');

            $registrations = $event->registrations()
                ->with(['user:id,name,email', 'financialEntries'])
                ->when($filters['q'] !== '', function ($query) use ($filters): void {
                    $term = '%'.$filters['q'].'%';
                    $query->where(function ($inner) use ($term): void {
                        $inner->where('participant_name', 'like', $term)
                            ->orWhere('nickname', 'like', $term)
                            ->orWhere('club_name', 'like', $term)
                            ->orWhere('whatsapp', 'like', $term)
                            ->orWhereHas('user', function ($user) use ($term): void {
                                $user->where('name', 'like', $term)
                                    ->orWhere('email', 'like', $term);
                            });
                    });
                })
                ->when($filters['category'] !== '', fn ($query) => $filters['category'] === '__none__'
                    ? $query->whereNull('category')
                    : $query->where('category', $filters['category']))
                ->when($filters['race'] !== '', fn ($query) => $query->where('races', 'like', '%"'.$filters['race'].'"%'))
                ->when($filters['birth_year'] !== '', fn ($query) => $query->where('birth_year', (int) $filters['birth_year']))
                ->when($filters['gender'] !== '', fn ($query) => $query->where('gender', $filters['gender']))
                ->when($filters['payment'] !== '', fn ($query) => $query->where('payment_status', $filters['payment']))
                ->when($filters['status'] !== '', fn ($query) => $query->where('status', $filters['status']))
                // Pengelompokan menuntut urutan kategori, jadi urut kategori
                // ditulis lebih dulu dan pilihan sort hanya berlaku di dalam
                // masing-masing kategori.
                ->when($groupByCategory, fn ($query) => $query
                    ->orderByRaw('category IS NULL')
                    ->orderBy('category'))
                ->orderBy($sort['column'], $sort['direction'])
                ->orderBy('id')
                ->paginate(self::ROSTER_PER_PAGE)
                ->withQueryString();

            $categoryBreakdown = $event->registrations()
                ->whereNotNull('category')
                ->selectRaw('category, count(*) as total')
                ->groupBy('category')
                ->pluck('total', 'category')
                ->all();

            arsort($categoryBreakdown);

            $paidWithoutCategory = $event->registrations()
                ->where('payment_status', 'paid')
                ->whereNull('category')
                ->count();

            $filterOptions = [
                'categories' => $event->registrations()
                    ->whereNotNull('category')
                    ->distinct()
                    ->orderBy('category')
                    ->pluck('category')
                    ->all(),
                'races' => $event->races ?? [],
                'birth_years' => $event->registrations()
                    ->whereNotNull('birth_year')
                    ->distinct()
                    ->orderByDesc('birth_year')
                    ->pluck('birth_year')
                    ->all(),
                'payments' => ['unpaid' => 'Belum bayar', 'pending' => 'Menunggu konfirmasi', 'paid' => 'Terbayar'],
                'statuses' => ['pending' => 'Menunggu', 'approved' => 'Diterima', 'rejected' => 'Perlu ditinjau'],
                'genders' => ['male' => 'Laki-laki', 'female' => 'Perempuan'],
            ];
        } else {
            // Peserta biasa tetap boleh menyaring daftar yang sudah dirilis,
            // tetapi hanya pada kolom yang memang ditampilkan: kategori, mata
            // lomba, dan gender. Kolom pembayaran dan status tidak dipakai
            // untuk menyaring karena tidak ditampilkan kepada mereka.
            $filters = [
                'q' => trim((string) $request->query('q', '')),
                'category' => (string) $request->query('category', ''),
                'race' => (string) $request->query('race', ''),
                'gender' => (string) $request->query('gender', ''),
                'birth_year' => '',
                'payment' => '',
                'status' => '',
            ];
            $sort = ['key' => 'nama', 'column' => 'participant_name', 'direction' => 'asc'];
            $groupByCategory = true;

            // Pencarian dibatasi pada nama dan klub supaya data pribadi
            // tidak bisa ditebak lewat kotak cari.
            $registrations = $event->registrations()
                ->where('payment_status', 'paid')
                ->where(function ($query): void {
                    $query->whereNull('cancellation_status')
                        ->orWhere('cancellation_status', '!=', 'approved');
                })
                ->when($filters['q'] !== '', function ($query) use ($filters): void {
                    $term = '%'.$filters['q'].'%';
                    $query->where(function ($inner) use ($term): void {
                        $inner->where('participant_name', 'like', $term)
                            ->orWhere('club_name', 'like', $term);
                    });
                })
                ->when($filters['category'] !== '', fn ($query) => $filters['category'] === '__none__'
                    ? $query->whereNull('category')
                    : $query->where('category', $filters['category']))
                ->when($filters['race'] !== '', fn ($query) => $query->where('races', 'like', '%"'.$filters['race'].'"%'))
                ->when($filters['gender'] !== '', fn ($query) => $query->where('gender', $filters['gender']))
                // fee_total tidak ikut dipilih: biaya bukan konsumsi peserta.
                ->select(['id', 'event_id', 'participant_name', 'club_name', 'category', 'races', 'gender'])
                ->orderByRaw('category IS NULL')
                ->orderBy('category')
                ->orderBy('participant_name')
                ->paginate(self::ROSTER_PER_PAGE)
                ->withQueryString();

            $filterOptions = [
                'categories' => $event->registrations()
                    ->whereNotNull('category')
                    ->distinct()
                    ->orderBy('category')
                    ->pluck('category')
                    ->all(),
                'races' => $event->races ?? [],
                'genders' => ['male' => 'Laki-laki', 'female' => 'Perempuan'],
            ];
        }

        return view('verified.events.participants.index', [
            'event' => $event,
            'registrations' => $registrations,
            'canManage' => $canManage,
            'categoryBreakdown' => $categoryBreakdown,
            'paidWithoutCategory' => $paidWithoutCategory,
            'filters' => $filters,
            'filterOptions' => $filterOptions,
            'sort' => $sort,
            'groupByCategory' => $groupByCategory,
            'pricingChoices' => $this->paymentPricingChoices($event, $registrations),
        ]);
    }

    /**
     * Seluruh daftar peserta untuk disimpan sebagai PDF lewat dialog cetak
     * browser.
     *
     * Berbeda dengan daftar di layar, halaman ini tidak dipaginasi: semua
     * peserta ikut terbuka. Halaman ini juga tidak terikat ukuran A4 — lebar
     * kertasnya dihitung sendiri dari lebar tabel di sisi klien, sehingga
     * kolom apa pun tidak pernah terpotong. Karena itu tombol "Export PDF"
     * mengarah ke sini, sementara tombol "Cetak A4" tetap memakai daftar
     * biasa yang sudah dirapatkan ke kolom inti.
     */
    public function export(Request $request, Event $event): View
    {
        $this->abortUnlessRosterIsReadable($request, $event);
        $canManage = $this->canManage($request, $event);

        $registrations = $event->registrations()
            ->with(['user:id,name,email'])
            // Peserta biasa hanya melihat daftar yang sudah dirilis dan sudah
            // dibayar; pengelola melihat semuanya.
            ->unless($canManage, function ($query): void {
                $query->where('payment_status', 'paid')
                    ->where(function ($inner): void {
                        $inner->whereNull('cancellation_status')
                            ->orWhere('cancellation_status', '!=', 'approved');
                    });
            })
            ->orderByRaw('category IS NULL')
            ->orderBy('category')
            ->orderBy('participant_name')
            ->get();

        return view('verified.events.participants.export', [
            'event' => $event,
            'registrations' => $registrations,
            'canManage' => $canManage,
            'categories' => $registrations
                ->map(fn (EventRegistration $registration) => (string) $registration->category)
                ->unique()
                ->values()
                ->all(),
        ]);
    }

    /**
     * Kandidat harga untuk setiap peserta yang menunggu konfirmasi.
     *
     * Pengelola memilih sendiri harga mana yang dipakai ketika mengonfirmasi
     * lewat WhatsApp, jadi pilihan priced perlu menampilkan nominalnya supaya
     * tidak perlu menghitung ulang di kepala.
     *
     * @param  LengthAwarePaginator<int, EventRegistration>  $registrations
     * @return array<int, array{registration: int, locked: ?int, current: ?int, expires_on: ?string}>
     */
    private function paymentPricingChoices(Event $event, $registrations): array
    {
        $calculator = app(CategoryFeeCalculator::class);
        $choices = [];

        foreach ($registrations as $registration) {
            if ($registration->payment_status !== 'pending' || ! $registration->category) {
                continue;
            }

            $locked = $calculator->breakdownForPayment($event, $registration, null, 'registration');
            $current = $calculator->breakdownForPayment($event, $registration, null, 'current');

            $choices[$registration->id] = [
                'registration' => $registration->id,
                'locked' => $locked === [] ? null : $locked['total'],
                'current' => $current === [] ? null : $current['total'],
                'expires_on' => $locked['pricing_lock_expires_on'] ?? null,
            ];
        }

        return $choices;
    }

    /**
     * @return array{q: string, category: string, race: string, birth_year: string, gender: string, payment: string, status: string}
     */
    private function rosterFilters(Request $request): array
    {
        return [
            'q' => trim((string) $request->query('q', '')),
            'category' => (string) $request->query('category', ''),
            'race' => (string) $request->query('race', ''),
            'birth_year' => (string) $request->query('birth_year', ''),
            'gender' => (string) $request->query('gender', ''),
            'payment' => (string) $request->query('payment', ''),
            'status' => (string) $request->query('status', ''),
        ];
    }

    /**
     * @return array{key: string, column: string, direction: string}
     */
    private function rosterSort(Request $request): array
    {
        $key = (string) $request->query('sort', 'nama');
        if (! array_key_exists($key, self::ROSTER_SORTABLE_COLUMNS)) {
            $key = 'nama';
        }

        return [
            'key' => $key,
            'column' => self::ROSTER_SORTABLE_COLUMNS[$key],
            'direction' => strtolower((string) $request->query('direction', 'asc')) === 'desc' ? 'desc' : 'asc',
        ];
    }

    /**
     * Bagi kategori seluruh peserta yang pembayarannya sudah terkonfirmasi.
     *
     * Dipakai pengelola sebelum melepas daftar peserta ke publik, supaya
     * tidak ada peserta yang tayang tanpa kategori.
     */
    public function assignCategories(Request $request, Event $event): RedirectResponse
    {
        abort_unless($this->canManage($request, $event), 403);
        abort_unless($event->status === 'approved', 409, 'Kategori peserta hanya dapat dibagikan untuk event yang disetujui.');
        abort_if(empty($event->category_rules), 409, 'Pengelola kejuaraan belum mengatur kategori peserta.');

        $summary = app(AssignRegistrationCategories::class)->assign($event);

        $status = $summary['assigned'] > 0
            ? "{$summary['assigned']} peserta berhasil dipindahkan ke kategori otomatis."
            : 'Semua peserta yang memenuhi syarat sudah berada di kategori yang benar.';

        $unassigned = $summary['awaiting_payment'] + $summary['incomplete'] + $summary['no_match'];
        if ($unassigned > 0) {
            $status .= " {$unassigned} peserta belum dapat dikategorikan.";
        }

        return redirect()
            ->route('events.registrations.index', $event)
            ->with('status', $status)
            ->with('category_summary', $summary);
    }

    public function publish(Request $request, Event $event): RedirectResponse
    {
        abort_unless($this->canPublish($request, $event), 403);
        abort_unless($event->status === 'approved', 409, 'Daftar peserta hanya dapat dirilis setelah event disetujui.');

        $validated = $request->validate([
            'participants_published' => ['required', 'boolean'],
        ]);

        $released = (bool) $validated['participants_published'];
        $event->releaseParticipantList($released);

        return redirect()->route('events.registrations.index', $event)
            ->with('status', $released
                ? 'Daftar peserta berhasil dirilis untuk akun terverifikasi.'
                : 'Daftar peserta tidak lagi dapat dilihat oleh akun lain.');
    }

    public function update(
        UpdateEventRegistrationRequest $request,
        Event $event,
        EventRegistration $registration,
        ResolveEventRegistrationCategory $categoryResolver,
        CategoryFeeCalculator $feeCalculator,
        ReconcileRegistrationFee $feeReconciler,
    ): RedirectResponse {
        $validated = $request->validated();
        $category = null;
        if ($registration->payment_status === 'paid') {
            $eligibleCategories = $categoryResolver->eligibleCategories(
                $event,
                $validated['date_of_birth'],
                $validated['gender'],
            );
            $category = $categoryResolver->matchingCategory(
                $event,
                $validated['date_of_birth'],
                $validated['gender'],
                $validated['races'],
            );

            if (! $category) {
                $field = $eligibleCategories->isEmpty() ? 'date_of_birth' : 'races';
                $message = $eligibleCategories->isEmpty()
                    ? 'Tanggal lahir dan gender atlet tidak memenuhi aturan kategori kejuaraan.'
                    : 'Mata lomba yang dipilih tidak tersedia untuk kategori atlet berdasarkan tanggal lahir dan gender.';

                throw ValidationException::withMessages([$field => $message]);
            }
        }

        $identityService = app(RegistrationIdentity::class);
        $identity = $identityService->normalize($validated);
        $identityService->ensureNotDuplicate($event, $registration->user_id, $identity, $registration->id);

        DB::transaction(function () use ($registration, $validated, $identity, $category, $event, $feeCalculator, $request, $feeReconciler): void {
            $registration = EventRegistration::query()
                ->whereKey($registration->id)
                ->lockForUpdate()
                ->firstOrFail();
            $wasPaid = $registration->payment_status === 'paid';
            if ($wasPaid) {
                $feeReconciler->preserveHistoricalPayment($registration);
            }

            $registration->update([
                ...$validated,
                ...$identity,
                'birth_year' => (int) substr($validated['date_of_birth'], 0, 4),
                'category' => $category['name'] ?? null,
                'races' => array_values($validated['races']),
            ]);
            $feeCalculator->sync($event, $registration);

            if ($wasPaid) {
                $feeReconciler->reconcile($event, $registration, $request->user()->id);
            } else {
                $registration->save();
            }
        });

        return redirect()
            ->route('events.registrations.index', $event)
            ->with('status', "Data atlet {$registration->participant_name} berhasil diperbarui.");
    }

    public function confirmPayment(
        Request $request,
        Event $event,
        EventRegistration $registration,
        ResolveEventRegistrationCategory $categoryResolver,
        CategoryFeeCalculator $feeCalculator,
        ReconcileRegistrationFee $feeReconciler,
    ): RedirectResponse {
        abort_unless($this->canManage($request, $event), 403);
        abort_unless($event->status === 'approved', 409, 'Pembayaran hanya dapat dikonfirmasi untuk event yang disetujui.');
        abort_if(in_array($registration->cancellation_status, ['pending', 'approved'], true), 409, 'Pendaftaran yang sedang atau sudah dibatalkan tidak dapat dikonfirmasi.');

        DB::transaction(function () use ($event, $registration, $categoryResolver, $feeCalculator, $request): void {
            $registration = EventRegistration::query()
                ->whereKey($registration->id)
                ->lockForUpdate()
                ->firstOrFail();
            abort_if(in_array($registration->cancellation_status, ['pending', 'approved'], true), 409, 'Pendaftaran yang sedang atau sudah dibatalkan tidak dapat dikonfirmasi.');

            abort_unless($registration->payment_status === 'pending', 409, 'Pendaftaran ini belum menunggu konfirmasi pembayaran.');

            $validated = $request->validate([
                'pricing_basis' => ['sometimes', 'required', Rule::in(['auto', 'registration', 'current', 'manual'])],
                'manual_fee' => ['nullable', 'required_if:pricing_basis,manual', 'integer', 'min:0'],
                'paid_amount' => ['sometimes', 'nullable', 'integer', 'min:0'],
            ]);

            if ($event->is_free) {
                abort_unless($registration->payment_channel === 'whatsapp', 409, 'Konfirmasi pendaftaran gratis harus dimulai melalui WhatsApp.');
            } else {
                abort_unless(
                    $registration->payment_proof_path || $registration->payment_channel === 'whatsapp',
                    409,
                    'Peserta belum mengirim bukti atau konfirmasi pembayaran.',
                );
            }

            if (! $registration->date_of_birth || ! $registration->gender || empty($registration->races)) {
                throw ValidationException::withMessages([
                    'payment_status' => 'Data tanggal lahir, gender, dan mata lomba peserta harus lengkap sebelum konfirmasi.',
                ]);
            }

            $match = $categoryResolver->bestMatch(
                $event,
                $registration->date_of_birth->format('Y-m-d'),
                $registration->gender,
                $registration->races,
            );

            if (! $match) {
                throw ValidationException::withMessages([
                    'payment_status' => 'Data peserta tidak sesuai dengan kategori dan mata lomba event. Periksa data peserta sebelum konfirmasi.',
                ]);
            }

            $category = $match['rule'];

            $registration->update([
                'category' => $category['name'],
            ]);

            // Pengelola memilih harga mana yang dipakai. Default-nya otomatis:
            // harga daftar selama masih berlaku, lalu tarif periode berjalan
            // bila sudah lewat. Pengelola bisa memaksa harga daftar untuk
            // peserta yang telat sebagai kebijakan baik, atau sebaliknya
            // memaksa harga periode berjalan untuk menagih kekurangan.
            $basis = $validated['pricing_basis'] ?? 'auto';
            $breakdown = $feeCalculator->breakdownForPayment($event, $registration, null, $basis);

            if ($basis === 'manual') {
                $breakdown = $breakdown !== []
                    ? [...$breakdown, 'total' => (int) $validated['manual_fee'], 'pricing_basis' => 'manual']
                    : [];
            } elseif ($breakdown !== []) {
                $breakdown['pricing_basis'] = $basis;
            }

            if ($breakdown !== []) {
                $registration->fee_total = $breakdown['total'];
                $registration->fee_breakdown = $breakdown;
                $registration->save();
            }

            $amountDue = $registration->amountDue();
            // Pengelola mencatat jumlah yang benar-benar diterima. Kalau masih
            // kurang, pendaftaran tetap menunggu pelunasan.
            $paidAmount = $amountDue;
            $requestedAmount = $validated['paid_amount'] ?? null;

            if ($requestedAmount !== null && $requestedAmount !== '') {
                $paidAmount = min((int) $requestedAmount, $amountDue);
            }

            if ($paidAmount > 0) {
                EventRegistrationFinancialEntry::create([
                    'event_id' => $event->id,
                    'event_registration_id' => $registration->id,
                    'type' => 'payment',
                    'amount' => $paidAmount,
                    'description' => $registration->confirmed_paid_at
                        ? 'Pelunasan selisih biaya pendaftaran'
                        : 'Pembayaran pendaftaran',
                    'occurred_at' => now(),
                    'recorded_by' => $request->user()->id,
                ]);
            }

            $remaining = $registration->amountDue();
            $registration->payment_status = $remaining > 0 ? 'pending' : 'paid';
            $registration->confirmed_paid_at ??= now();
            $registration->save();
        });

        $remaining = $registration->fresh()->amountDue();

        return redirect()
            ->route('events.registrations.index', $event)
            ->with(
                'status',
                $remaining > 0
                    ? "Pembayaran {$registration->participant_name} dicatat, masih kurang ".CategoryFeeCalculator::rupiah($remaining).'.'
                    : "Pembayaran {$registration->participant_name} berhasil dikonfirmasi.",
            );
    }

    public function reviewParticipantChange(
        Request $request,
        Event $event,
        EventRegistration $registration,
        ResolveEventRegistrationCategory $categoryResolver,
        CategoryFeeCalculator $feeCalculator,
        ReconcileRegistrationFee $feeReconciler,
    ): RedirectResponse {
        abort_unless($this->canManage($request, $event), 403);
        abort_unless($registration->event_id === $event->id, 404);

        $validated = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
        ]);

        DB::transaction(function () use ($request, $event, $registration, $validated, $categoryResolver, $feeCalculator, $feeReconciler): void {
            $registration = EventRegistration::query()
                ->whereKey($registration->id)
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless(
                $registration->participant_change_status === 'pending'
                    && is_array($registration->participant_change_request),
                409,
                'Tidak ada permintaan perubahan yang menunggu keputusan.',
            );

            if ($validated['decision'] === 'reject') {
                $registration->update([
                    'participant_change_request' => null,
                    'participant_change_status' => 'rejected',
                    'participant_change_requested_at' => null,
                ]);

                return;
            }

            $changes = Validator::make($registration->participant_change_request, [
                ...app(RegistrationIdentity::class)->fieldRules(),
                'participant_name' => ['required', 'string', 'max:120'],
                'nickname' => ['nullable', 'string', 'max:80'],
                'gender' => ['required', 'in:male,female'],
                'date_of_birth' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
                'address' => ['required', 'string', 'max:1000'],
                'guardian_name' => ['required', 'string', 'max:120'],
                'whatsapp' => ['required', 'string', 'max:40'],
                'club_name' => ['nullable', 'string', 'max:180'],
                'races' => ['required', 'array', 'min:1', 'distinct'],
                'races.*' => ['required', 'string', Rule::in($event->races)],
            ])->validate();
            app(RegistrationIdentity::class)->assertComplete($changes);

            $match = $categoryResolver->bestMatch(
                $event,
                $changes['date_of_birth'],
                $changes['gender'],
                $changes['races'],
            );
            if (! $match) {
                throw ValidationException::withMessages([
                    'participant_change_request' => 'Perubahan tidak lagi sesuai dengan kategori event yang tersedia.',
                ]);
            }

            if (count($match['in_category_races']) < $feeCalculator->requiredRaces($match['rule'])) {
                throw ValidationException::withMessages([
                    'participant_change_request' => 'Perubahan tidak memenuhi jumlah mata lomba wajib pada kategori terbaru.',
                ]);
            }

            $identityService = app(RegistrationIdentity::class);

            try {
                $identityService->ensureNotDuplicate($event, $registration->user_id, $identityService->normalize($changes), $registration->id);
            } catch (ValidationException $exception) {
                throw ValidationException::withMessages([
                    'participant_change_request' => 'Identitas pada permintaan ini sudah digunakan oleh atlet lain di event.',
                ]);
            }

            $feeReconciler->preserveHistoricalPayment($registration);
            $registration->update([
                ...$changes,
                ...$identityService->normalize($changes),
                'birth_year' => (int) substr($changes['date_of_birth'], 0, 4),
                'category' => $match['rule']['name'],
                'races' => array_values($changes['races']),
                'participant_change_request' => null,
                'participant_change_status' => 'approved',
                'participant_change_requested_at' => null,
            ]);
            $feeCalculator->sync($event, $registration);
            $feeReconciler->reconcile($event, $registration, $request->user()->id);
        });

        $decision = $validated['decision'] === 'approve' ? 'disetujui' : 'ditolak';

        return redirect()->route('events.registrations.index', $event)
            ->with('status', "Permintaan perubahan data peserta {$decision}.");
    }

    public function reviewCancellation(
        Request $request,
        Event $event,
        EventRegistration $registration,
        ReconcileRegistrationFee $feeReconciler,
    ): RedirectResponse {
        abort_unless($this->canManage($request, $event), 403);
        abort_unless($registration->event_id === $event->id, 404);

        $availableRefund = $registration->amountPaid();
        $validated = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'refund_amount' => [
                'required_if:decision,approve',
                'nullable',
                'integer',
                'min:0',
                'max:'.$availableRefund,
            ],
        ]);

        DB::transaction(function () use ($request, $event, $registration, $validated, $feeReconciler): void {
            $registration = EventRegistration::query()
                ->whereKey($registration->id)
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($registration->cancellation_status === 'pending', 409, 'Tidak ada permintaan pembatalan yang menunggu keputusan.');

            $isApproved = $validated['decision'] === 'approve';
            $refundAmount = $isApproved ? (int) $validated['refund_amount'] : 0;
            abort_if($refundAmount > $registration->amountPaid(), 422, 'Nominal refund melebihi pembayaran bersih peserta.');

            $feeReconciler->preserveHistoricalPayment($registration);
            $registration->update([
                'cancellation_status' => $isApproved ? 'approved' : 'rejected',
                'cancellation_decided_at' => now(),
                'refund_amount' => (int) $registration->refund_amount + $refundAmount,
                'refunded_at' => $refundAmount > 0 ? now() : $registration->refunded_at,
            ]);

            if ($refundAmount > 0) {
                EventRegistrationFinancialEntry::create([
                    'event_id' => $event->id,
                    'event_registration_id' => $registration->id,
                    'type' => 'refund',
                    'amount' => $refundAmount,
                    'description' => 'Pengembalian dana pembatalan pendaftaran',
                    'occurred_at' => now(),
                    'recorded_by' => $request->user()->id,
                ]);
            }
        });

        $message = $validated['decision'] === 'approve'
            ? 'Pembatalan disetujui dan refund dicatat.'
            : 'Permintaan pembatalan ditolak.';

        return redirect()->route('events.registrations.index', $event)->with('status', $message);
    }

    public function proof(Request $request, Event $event, EventRegistration $registration): StreamedResponse
    {
        abort_unless(
            $request->user()->id === $registration->user_id || $this->canManage($request, $event),
            403,
        );

        abort_unless($registration->payment_proof_path, 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($registration->payment_proof_path), 404);

        return $disk->download($registration->payment_proof_path, 'bukti-pembayaran');
    }

    /**
     * Admin selalu boleh membuka pengaturan peserta; pengelola lain hanya
     * untuk event miliknya sendiri.
     */
    private function canManage(Request $request, Event $event): bool
    {
        return $event->isManagedBy($request->user());
    }

    /**
     * Daftar peserta hanya terbuka setelah event disetujui; bagi pihak luar
     * pengelola, daftarnya juga harus sudah dirilis lebih dulu. Dipakai oleh
     * daftar di layar dan halaman export supaya keduanya tidak pernah
     * menyimpang — celah akses di satu sisi akan langsung terbuka di sisi
     * lainnya kalau aturannya ditulis terpisah.
     */
    private function abortUnlessRosterIsReadable(Request $request, Event $event): void
    {
        abort_unless(
            $event->status === 'approved'
                && ($this->canManage($request, $event) || $event->isParticipantListReleased()),
            404,
        );
    }

    /**
     * Peran yang diizinkan menyunting peserta: akun pemilik event dan admin
     * Inlinesia. Akun lain hanya bisa melihat daftar yang sudah dirilis.
     */
    private function canPublish(Request $request, Event $event): bool
    {
        return $event->isOwnedBy($request->user());
    }
}
