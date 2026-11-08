@extends('layouts.public')

@section('title', 'Peserta — '.$event->name)
@section('description', 'Daftar peserta kejuaraan '.$event->name.'.')
@section('body-class', 'auth-page')

@section('content')
<main class="profile-page event-registration-management">
    <header class="event-registration-heading">
        <div>
            <span class="account-eyebrow">{{ $canManage ? 'PENGELOLAAN KEJUARAAN' : 'DAFTAR PESERTA DIRILIS' }}</span>
            <h1>{{ $canManage ? 'Daftar peserta' : 'Peserta kejuaraan' }}</h1>
            <p>{{ $event->name }} · {{ $event->start_date->format('d/m/Y') }}–{{ $event->end_date->format('d/m/Y') }} · {{ $event->venue }}</p>
        </div>
        <div class="event-registration-heading-actions no-print">
            {{-- Daftar di layar dicetak langsung ke A4 dari printer. Kolom yang
                 hanya berguna di layar disembunyikan supaya tidak ada yang
                 terpotong kertas. --}}
            <button class="account-edit-link" type="button" data-print-page>Cetak A4</button>
            {{-- Export PDF memakai halaman tersendiri: seluruh daftar tanpa
                 paginasi, dan ukuran kertasnya mengikuti lebar tabel. --}}
            <a class="account-edit-link" href="{{ route('events.registrations.export', $event) }}">Export PDF</a>
            @if ($canManage)
                <a class="account-edit-link" href="{{ route('guest.dashboard') }}">← Kembali ke kelola event</a>
            @elseif (auth()->check())
                <a class="account-edit-link" href="{{ route('guest.dashboard') }}">← Dasbor peserta</a>
            @else
                <a class="account-edit-link" href="{{ route('events.index') }}">← Kembali ke daftar lomba</a>
            @endif
        </div>
    </header>

    @if (session('status'))
        <div class="public-flash" role="status">{{ session('status') }}</div>
    @endif

    @if ($errors->any() && ! old('_registration_id'))
        <div class="account-errors" role="alert">
            <strong>Permintaan peserta belum dapat diproses.</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if ($canManage)
        @if ($summary = session('category_summary'))
            <div class="account-note event-category-summary" role="status">
                <strong>Hasil pembagian kategori</strong>
                <ul>
                    <li>{{ $summary['assigned'] }} peserta dipindahkan ke kategori otomatis.</li>
                    <li>{{ $summary['unchanged'] }} peserta sudah berada di kategori yang benar.</li>
                    <li>{{ $summary['awaiting_payment'] }} peserta masih menunggu pembayaran.</li>
                    <li>{{ $summary['incomplete'] }} peserta datanya belum lengkap.</li>
                    <li>{{ $summary['no_match'] }} peserta tidak cocok dengan aturan kategori.</li>
                </ul>
                @if ($summary['unassigned'])
                    <details class="event-category-summary-details">
                        <summary>Lihat {{ count($summary['unassigned']) }} peserta yang belum terkategori</summary>
                        <ul>
                            @foreach ($summary['unassigned'] as $row)
                                <li>{{ $row['participant_name'] }} — {{ $row['reason'] }}</li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </div>
        @endif

        <section class="event-category-assign" aria-label="Pembagian kategori peserta">
            <div class="event-category-assign-copy">
                <span class="account-eyebrow">PEMBAGIAN KATEGORI</span>
                <h2>Siapa masuk kategori mana</h2>
                <p>Aturan kategori memakai tanggal lahir, gender, dan mata lomba yang dipilih atlet — sama persis seperti saat pembayaran dikonfirmasi satu per satu. Hanya peserta yang pembayarannya sudah terkonfirmasi yang ikut dibagi.</p>
            </div>

            @if ($categoryBreakdown)
                <ul class="event-category-distribution" aria-label="Distribusi kategori saat ini">
                    @foreach ($categoryBreakdown as $categoryName => $total)
                        <li>
                            <strong>{{ $categoryName }}</strong>
                            <span>{{ $total }} peserta</span>
                        </li>
                    @endforeach
                </ul>
            @endif

            <form method="POST" action="{{ route('events.registrations.categories.assign', $event) }}" class="event-category-assign-action">
                @csrf
                <button class="submission-approve" type="submit">Bagikan kategori otomatis</button>
            </form>

            @if ($paidWithoutCategory > 0)
                <p class="event-category-assign-warning" role="alert">
                    {{ $paidWithoutCategory }} peserta sudah lunas tetapi belum memiliki kategori. Tekan tombol di atas sebelum melepas daftar peserta ke publik.
                </p>
            @endif
        </section>

        @if ($event->submitted_by === auth()->id())
            <form method="POST" action="{{ route('events.registrations.publish', $event) }}" class="event-roster-publication">
                @csrf
                @method('PATCH')
                <input type="hidden" name="participants_published" value="{{ $event->isParticipantListReleased() ? '0' : '1' }}">
                <p>
                    @if ($event->isParticipantListReleased())
                        Daftar ini dapat dilihat akun terverifikasi.
                    @else
                        Daftar saat ini hanya dapat dilihat oleh pengelola.
                    @endif
                    @if (! $event->participants_published && $event->registrationClosed())
                        <br>Masa pendaftaran sudah habis, jadi daftar ini otomatis terbuka.
                    @endif
                </p>
                <button class="submission-approve" type="submit">{{ $event->isParticipantListReleased() ? 'Tarik rilis daftar' : 'Rilis daftar peserta' }}</button>
            </form>
        @endif
        @php
            // Kolom bertanda 'printable' => false hanya berguna di layar.
            // Saat dicetak ke A4 daftar dirapatkan ke kolom inti, karena
            // keenam belas kolom sekaligus tidak akan muat di satu lembar.
            $rosterColumns = [
                ['key' => 'nomor', 'label' => 'No.', 'sortable' => false, 'printable' => true],
                ['key' => 'nama', 'label' => 'Nama', 'sortable' => true, 'printable' => true],
                ['key' => 'panggilan', 'label' => 'Nama panggilan', 'sortable' => false, 'printable' => true],
                ['key' => 'email', 'label' => 'Email', 'sortable' => false, 'printable' => false],
                ['key' => 'identitas', 'label' => 'NIK / paspor', 'sortable' => false, 'printable' => false],
                ['key' => 'lahir', 'label' => 'Tanggal lahir', 'sortable' => true, 'printable' => false],
                ['key' => 'gender', 'label' => 'Gender', 'sortable' => true, 'printable' => false],
                ['key' => 'domisili', 'label' => 'Asal daerah', 'sortable' => false, 'printable' => false],
                ['key' => 'kontak', 'label' => 'Kontak', 'sortable' => true, 'printable' => false],
                ['key' => 'wali', 'label' => 'Wali', 'sortable' => false, 'printable' => false],
                ['key' => 'klub', 'label' => 'Klub', 'sortable' => true, 'printable' => true],
                ['key' => 'kategori', 'label' => 'Kategori', 'sortable' => true, 'printable' => true],
                ['key' => 'mata-lomba', 'label' => 'Mata lomba', 'sortable' => false, 'printable' => true],
                ['key' => 'tahun', 'label' => 'Tahun lahir', 'sortable' => true, 'printable' => false],
                ['key' => 'pembayaran', 'label' => 'Pembayaran', 'sortable' => true, 'printable' => false],
                ['key' => 'status', 'label' => 'Status', 'sortable' => true, 'printable' => true],
                ['key' => 'biaya', 'label' => 'Biaya', 'sortable' => false, 'printable' => true],
            ];
            $rosterQuery = collect(request()->query())->except(['page', 'event'])->all();
            $hasRosterFilter = collect($filters)->contains(fn ($value) => $value !== '');
            // Kategori yang benar-benar muncul di halaman ini, supaya tombol
            // aktif/nonaktif hanya mencantumkan kategori yang bisa dikendalikan.
            $categoriesOnPage = $registrations->getCollection()
                ->map(fn ($registration) => (string) $registration->category)
                ->unique()
                ->values()
                ->all();
        @endphp

        <section class="account-table-card event-roster-table-card" aria-label="Daftar peserta kejuaraan">
            <div class="account-table-heading">
                <span class="account-eyebrow">PESERTA KEJUARAAN</span>
                <h2>{{ $registrations->total() }} atlet terdaftar</h2>
            </div>

            @if ($canManage)
                <a class="account-edit-link event-roster-group-toggle" href="{{ route('events.registrations.index', array_merge(['event' => $event], $rosterQuery, ['group' => $groupByCategory ? '0' : '1'])) }}">
                    {{ $groupByCategory ? 'Tampilkan sebagai satu tabel' : 'Kelompokkan per kategori' }}
                    <span aria-hidden="true">→</span>
                </a>
            @endif

            <form method="GET" action="{{ route('events.registrations.index', $event) }}" class="event-roster-filters">
                @if ($sort['key'] !== 'nama' || $sort['direction'] !== 'asc')
                    <input type="hidden" name="sort" value="{{ $sort['key'] }}">
                    <input type="hidden" name="direction" value="{{ $sort['direction'] }}">
                @endif

                <label class="event-roster-filter event-roster-filter-search">
                    <span>Cari</span>
                    <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Nama, klub, atau email" autocomplete="off">
                </label>

                <label class="event-roster-filter">
                    <span>Kategori</span>
                    <select name="category">
                        <option value="">Semua</option>
                        <option value="__none__" @selected($filters['category'] === '__none__')>Belum berkategori</option>
                        @foreach ($filterOptions['categories'] as $category)
                            <option value="{{ $category }}" @selected($filters['category'] === $category)>{{ $category }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="event-roster-filter">
                    <span>Mata lomba</span>
                    <select name="race">
                        <option value="">Semua</option>
                        @foreach ($filterOptions['races'] as $race)
                            <option value="{{ $race }}" @selected($filters['race'] === $race)>{{ $race }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="event-roster-filter">
                    <span>Tahun lahir</span>
                    <select name="birth_year">
                        <option value="">Semua</option>
                        @foreach ($filterOptions['birth_years'] as $year)
                            <option value="{{ $year }}" @selected((string) $filters['birth_year'] === (string) $year)>{{ $year }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="event-roster-filter">
                    <span>Gender</span>
                    <select name="gender">
                        <option value="">Semua</option>
                        @foreach ($filterOptions['genders'] ?? [] as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['gender'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="event-roster-filter">
                    <span>Pembayaran</span>
                    <select name="payment">
                        <option value="">Semua</option>
                        @foreach ($filterOptions['payments'] as $value => $label)
                            <option value="{{ $value }}" @selected($filters['payment'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="event-roster-filter">
                    <span>Status</span>
                    <select name="status">
                        <option value="">Semua</option>
                        @foreach ($filterOptions['statuses'] as $value => $label)
                            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="event-roster-filter-actions">
                    <button class="submission-approve" type="submit">Terapkan</button>
                    @if ($hasRosterFilter)
                        <a class="event-roster-filter-reset" href="{{ route('events.registrations.index', $event) }}">Reset</a>
                    @endif
                </div>
            </form>

            @if ($categoriesOnPage !== [])
                {{-- Pengelola memilih kategori mana yang ikut dicetak atau
                     di-export PDF. Kategori nonaktif ikut tersembunyi di layar,
                     jadi yang tampil di kertas memang hanya yang aktif. --}}
                <div class="event-roster-category-toggles no-print" role="group" aria-label="Kategori yang ikut dicetak">
                    <span class="event-roster-category-toggles-label">Kategori dicetak</span>
                    <div class="event-roster-category-toggles-list">
                        @foreach ($categoriesOnPage as $category)
                            <button
                                class="event-roster-category-toggle is-active"
                                type="button"
                                aria-pressed="true"
                                data-category-toggle="{{ $category }}"
                            >{{ $category !== '' ? $category : 'Belum berkategori' }}</button>
                        @endforeach
                    </div>
                    <small>Hanya kategori aktif yang ikut dicetak atau di-export PDF.</small>
                </div>
            @endif

            <div class="event-roster-table-wrap">
                <table class="event-roster-table">
                    <thead>
                        <tr>
                            @foreach ($rosterColumns as $column)
                                @php
                                    $isActiveSort = $column['sortable'] && $sort['key'] === $column['key'];
                                    $ariaSort = ! $column['sortable'] ? 'none' : ($isActiveSort ? ($sort['direction'] === 'asc' ? 'ascending' : 'descending') : 'none');
                                    $sortMark = $isActiveSort ? ($sort['direction'] === 'asc' ? '↑' : '↓') : '↕';
                                    $nextDirection = $isActiveSort && $sort['direction'] === 'asc' ? 'desc' : 'asc';
                                    $sortUrl = route('events.registrations.index', array_merge(['event' => $event], $rosterQuery, ['sort' => $column['key'], 'direction' => $nextDirection]));
                                @endphp
                                <th scope="col" @class(['event-print-optional' => ! $column['printable']]) @if ($column['sortable']) aria-sort="{{ $ariaSort }}" @endif>
                                    @if ($column['sortable'])
                                        <a class="event-roster-sort {{ $isActiveSort ? 'is-active' : '' }}" href="{{ $sortUrl }}">
                                            <span>{{ $column['label'] }}</span>
                                            <span class="event-roster-sort-mark" aria-hidden="true">{{ $sortMark }}</span>
                                        </a>
                                    @else
                                        <span class="event-roster-column-label">{{ $column['label'] }}</span>
                                    @endif
                                </th>
                            @endforeach
                            <th scope="col" class="event-roster-actions-column">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php($previousCategory = null)
                        @forelse ($registrations as $registration)
                            @if ($groupByCategory && $registration->category !== $previousCategory)
                                @php($previousCategory = $registration->category)
                                <tr class="event-roster-group-row" data-category="{{ $registration->category }}">
                                    <th scope="colgroup" colspan="{{ count($rosterColumns) + 1 }}">
                                        {{ $registration->category ?: 'Belum berkategori' }}
                                        <span class="event-roster-group-meta">
                                            {{ $registrations->getCollection()->where('category', $registration->category)->count() }} peserta di halaman ini
                                        </span>
                                    </th>
                                </tr>
                            @endif
                            <tr data-category="{{ $registration->category }}" @class([
                                'event-roster-row-requested' => $registration->participant_change_status === 'pending'
                                    || $registration->cancellation_status === 'pending',
                            ])>
                                <td data-label="No." class="event-roster-number">{{ $registrations->firstItem() + $loop->index }}</td>
                                {{-- Baris nama hanya memuat nama lengkap. Nama
                                     panggilan dan email punya kolomnya sendiri;
                                     nama klub sudah diwakili kolom Klub. --}}
                                <th scope="row" data-label="Nama">
                                    <span class="event-roster-name">
                                        <strong>{{ $registration->participant_name }}</strong>
                                    </span>
                                </th>
                                <td data-label="Nama panggilan">{{ $registration->nickname ?: '—' }}</td>
                                <td data-label="Email" class="event-print-optional">{{ $registration->user->email }}</td>
                                <td data-label="NIK / paspor" class="event-roster-identity event-print-optional">
                                    @if ($registration->isForeignNational())
                                        <span class="event-roster-identity-tag">Paspor</span>
                                        {{ $registration->maskedIdentity() }}
                                    @else
                                        {{ $registration->maskedIdentity() }}
                                    @endif
                                    @if ($registration->nationality)
                                        <small>{{ $registration->nationality }}</small>
                                    @endif
                                </td>
                                <td data-label="Tanggal lahir" class="event-print-optional">{{ $registration->date_of_birth?->format('d/m/Y') ?: '—' }}</td>
                                <td data-label="Gender" class="event-print-optional">{{ $registration->gender === 'male' ? 'Laki-laki' : ($registration->gender === 'female' ? 'Perempuan' : '—') }}</td>
                                <td data-label="Asal daerah" class="event-print-optional">{{ $registration->address ?: '—' }}</td>
                                <td data-label="Kontak" class="event-roster-contact-cell event-print-optional">
                                    @if ($registration->whatsapp)
                                        <a class="event-roster-wa-link" href="https://wa.me/{{ preg_replace('/\D+/', '', preg_replace('/^0/', '62', $registration->whatsapp)) }}" target="_blank" rel="noopener">
                                            {{ $registration->whatsapp }}
                                        </a>
                                    @else
                                        <span class="event-roster-fee-empty">—</span>
                                    @endif
                                </td>
                                <td data-label="Wali" class="event-print-optional">{{ $registration->guardian_name ?: '—' }}</td>
                                <td data-label="Klub">{{ $registration->club_name ?: 'Mandiri' }}</td>
                                <td data-label="Kategori">{{ $registration->category ?: '—' }}</td>
                                <td data-label="Mata lomba">{{ implode(', ', $registration->races) }}</td>
                                <td data-label="Tahun lahir" class="event-print-optional">{{ $registration->birth_year }}</td>
                                <td data-label="Pembayaran" class="event-roster-payment-cell event-print-optional">
                                    <span class="event-payment-status payment-{{ $registration->payment_status }}">
                                        {{ match ($registration->payment_status) {
                                            'pending' => 'Menunggu konfirmasi',
                                            'paid' => $event->is_free ? 'Dikonfirmasi' : 'Terbayar',
                                            default => 'Belum bayar',
                                        } }}
                                    </span>
                                    @if ($registration->payment_channel)
                                        <small>{{ ucfirst($registration->payment_channel) }}</small>
                                    @endif
                                    @if ($registration->payment_proof_path)
                                        <a class="event-roster-proof-link" href="{{ route('events.registrations.proof', [$event, $registration]) }}">Lihat bukti</a>
                                    @endif
                                </td>
                                <td data-label="Status" class="event-roster-status-column">
                                    <div class="event-roster-status-cell">
                                        <span class="guest-registration-status status-{{ $registration->status }}">
                                            {{ match ($registration->status) {
                                                'pending' => 'Menunggu',
                                                'approved' => 'Diterima',
                                                'rejected' => 'Perlu ditinjau',
                                                default => ucfirst($registration->status),
                                            } }}
                                        </span>
                                        @if ($registration->participant_change_status === 'pending')
                                            <div class="event-roster-review-panel is-requested">
                                                <strong>Permintaan perubahan data</strong>
                                                @if ($changes = $registration->participant_change_request)
                                                    <details>
                                                        <summary>Lihat perubahan</summary>
                                                        <span>Nama: {{ $changes['participant_name'] ?? '—' }}</span>
                                                        <span>Gender: {{ ($changes['gender'] ?? '') === 'male' ? 'Laki-laki' : 'Perempuan' }}</span>
                                                        <span>Tanggal lahir: {{ $changes['date_of_birth'] ?? '—' }}</span>
                                                        <span>Mata lomba: {{ implode(', ', $changes['races'] ?? []) }}</span>
                                                    </details>
                                                @endif
                                                <div class="event-roster-review-actions">
                                                    @foreach (['approve' => 'Setujui', 'reject' => 'Tolak'] as $decision => $label)
                                                        <form method="POST" action="{{ route('events.registrations.changes.review', [$event, $registration]) }}">
                                                            @csrf
                                                            <input type="hidden" name="decision" value="{{ $decision }}">
                                                            <button class="event-payment-confirm-button" type="submit">{{ $label }}</button>
                                                        </form>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @elseif ($registration->participant_change_status === 'rejected')
                                            <small class="event-roster-review-outcome is-rejected">Perubahan terakhir ditolak</small>
                                        @elseif ($registration->participant_change_status === 'approved')
                                            <small class="event-roster-review-outcome is-approved">Perubahan terakhir disetujui
                                                @if ($registration->refund_amount > 0)
                                                    · refund tercatat {{ \App\Services\CategoryFeeCalculator::rupiah($registration->refund_amount) }}
                                                @endif
                                            </small>
                                        @endif
                                        @if ($registration->cancellation_status === 'pending')
                                            <div class="event-roster-review-panel is-requested">
                                                <strong>Permintaan pembatalan</strong>
                                                <form method="POST" action="{{ route('events.registrations.cancellations.review', [$event, $registration]) }}" class="event-refund-review-form">
                                                    @csrf
                                                    <input type="hidden" name="decision" value="approve">
                                                    <label>Refund (maks. {{ \App\Services\CategoryFeeCalculator::rupiah($registration->amountPaid()) }}; 0 berarti tanpa refund)
                                                        <input type="number" name="refund_amount" min="0" max="{{ $registration->amountPaid() }}" value="0" required>
                                                    </label>
                                                    <button class="event-payment-confirm-button" type="submit">Setujui pembatalan</button>
                                                </form>
                                                <form method="POST" action="{{ route('events.registrations.cancellations.review', [$event, $registration]) }}">
                                                    @csrf
                                                    <input type="hidden" name="decision" value="reject">
                                                    <button class="event-registration-edit-link" type="submit">Tolak pembatalan</button>
                                                </form>
                                            </div>
                                        @elseif ($registration->cancellation_status === 'approved')
                                            <small class="event-roster-review-outcome is-rejected">Dibatalkan · refund {{ \App\Services\CategoryFeeCalculator::rupiah($registration->refund_amount) }}</small>
                                        @elseif ($registration->cancellation_status === 'rejected')
                                            <small class="event-roster-review-outcome is-rejected">Pembatalan terakhir ditolak</small>
                                        @endif
                                        @if ($registration->payment_status === 'pending'
                                            && ! in_array($registration->cancellation_status, ['pending', 'approved'], true)
                                            && ($event->is_free
                                                ? $registration->payment_channel === 'whatsapp'
                                                : ($registration->payment_proof_path || $registration->payment_channel === 'whatsapp')))
                                            <form method="POST" action="{{ route('events.registrations.payment.confirm', [$event, $registration]) }}">
                                                @csrf
                                                @if (($registration->fee_total ?? 0) > 0)
                                                    @php($choice = $pricingChoices[$registration->id] ?? null)
                                                    @if ($choice && $choice['locked'] !== null && $choice['current'] !== null)
                                                        <label class="event-payment-amount-field">
                                                            <span>Harga yang dipakai</span>
                                                            <select name="pricing_basis">
                                                                <option value="auto">Otomatis · {{ \App\Services\CategoryFeeCalculator::rupiah($registration->fee_total) }}</option>
                                                                <option value="registration">Harga saat daftar · {{ \App\Services\CategoryFeeCalculator::rupiah($choice['locked']) }}</option>
                                                                <option value="current">Harga periode berjalan · {{ \App\Services\CategoryFeeCalculator::rupiah($choice['current']) }}</option>
                                                                <option value="manual">Tentukan sendiri</option>
                                                            </select>
                                                        </label>
                                                        <label class="event-payment-amount-field" data-manual-fee-field hidden>
                                                            <span>Nominal yang ditetapkan</span>
                                                            <input type="number" name="manual_fee" min="0" step="1000" value="{{ $choice['current'] }}">
                                                        </label>
                                                    @else
                                                        <input type="hidden" name="pricing_basis" value="auto">
                                                    @endif
                                                    <label class="event-payment-amount-field">
                                                        <span>Jumlah diterima</span>
                                                        <input type="number" name="paid_amount" min="0" step="1000" max="{{ $registration->amountDue() }}" value="{{ $registration->amountDue() }}">
                                                    </label>
                                                    @if ($registration->amountPaid() > 0)
                                                        <small class="event-payment-amount-hint">Sudah dibayar {{ \App\Services\CategoryFeeCalculator::rupiah($registration->amountPaid()) }}, sisa tagihan {{ \App\Services\CategoryFeeCalculator::rupiah($registration->amountDue()) }}. Isi kurang dari sisa bila peserta belum melunasi.</small>
                                                    @endif
                                                    @if (! empty($registration->fee_breakdown['pricing_wave']['label'] ?? null) && ! empty($registration->fee_breakdown['pricing_lock_expires_on'] ?? null))
                                                        <small class="event-payment-lock-hint">Harga {{ $registration->fee_breakdown['pricing_wave']['label'] }} berlaku sampai {{ \Illuminate\Support\Carbon::parse($registration->fee_breakdown['pricing_lock_expires_on'])->format('d/m/Y') }}</small>
                                                    @endif
                                                @endif
                                                <button class="event-payment-confirm-button" type="submit">{{ $registration->amountPaid() > 0 ? 'Konfirmasi pelunasan' : 'Konfirmasi pembayaran' }}</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                                <td data-label="Biaya" class="event-roster-fee-cell">
                                    @if ($registration->fee_total === null)
                                        <span class="event-roster-fee-empty">{{ $registration->category ? 'Belum ada harga' : 'Menunggu kategori' }}</span>
                                    @else
                                        <strong>{{ \App\Services\CategoryFeeCalculator::rupiah($registration->fee_total) }}</strong>
                                        @if ($registration->amountPaid() > 0 && $registration->amountDue() > 0)
                                            <small>Sudah dibayar {{ \App\Services\CategoryFeeCalculator::rupiah($registration->amountPaid()) }}</small>
                                            <small class="event-roster-fee-due">Sisa tagihan {{ \App\Services\CategoryFeeCalculator::rupiah($registration->amountDue()) }}</small>
                                        @endif
                                        @if (($registration->fee_breakdown['extra_race_total'] ?? 0) > 0)
                                            <small>Tambahan {{ \App\Services\CategoryFeeCalculator::rupiah($registration->fee_breakdown['extra_race_total']) }}</small>
                                        @endif
                                        @if (($registration->fee_breakdown['outside_race_total'] ?? 0) > 0)
                                            <small>Luar kategori {{ \App\Services\CategoryFeeCalculator::rupiah($registration->fee_breakdown['outside_race_total']) }}</small>
                                        @endif
                                        @if (($registration->fee_breakdown['discount_total'] ?? 0) > 0)
                                            <small>Diskon −{{ \App\Services\CategoryFeeCalculator::rupiah($registration->fee_breakdown['discount_total']) }}</small>
                                        @endif
                                    @endif
                                </td>
                                <td data-label="Aksi" class="event-roster-actions-cell">
                                    <button class="event-registration-edit-link event-registration-modal-trigger" type="button" data-registration-modal-open="ubah-peserta-{{ $registration->id }}" aria-controls="ubah-peserta-{{ $registration->id }}">Edit data</button>
                                </td>
                            </tr>
                        @empty
                            <tr class="event-roster-empty-row">
                                <td colspan="{{ count($rosterColumns) + 1 }}">
                                    <div class="guest-dashboard-empty event-registration-empty">
                                        <strong>Belum ada atlet yang terdaftar.</strong>
                                        <p>Pendaftaran atlet akan muncul di sini setelah peserta mengirimkan formulir.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($registrations->hasPages())
                <div class="account-pagination">{{ $registrations->links() }}</div>
            @endif
        </section>

        {{-- Dialog ubah data diletakkan di luar <table> agar struktur tabel tetap valid. --}}
        @foreach ($registrations as $registration)
            @include('verified.events.participants.ubah', ['event' => $event, 'registration' => $registration, 'isParticipant' => false])
        @endforeach
    @else
        <section class="account-table-card event-roster-table-card event-public-roster" aria-label="Daftar peserta kejuaraan">
            <div class="account-table-heading">
                <span class="account-eyebrow">DAFTAR PESERTA DIRILIS</span>
                <h2>{{ $registrations->total() }} peserta terdaftar</h2>
                <p>Informasi kontak, biodata pribadi, dan pembayaran tidak ditampilkan. <span class="event-registration-readonly-label">Read-only</span></p>
            </div>

            {{-- Peserta boleh menyaring daftar yang sudah dirilis, tetapi hanya
                 pada kolom yang memang mereka lihat: kategori, mata lomba, dan
                 gender. Kolom pembayaran tidak ikut disaring karena tidak
                 ditampilkan kepada mereka. --}}
            <form method="GET" action="{{ route('events.registrations.index', $event) }}" class="event-roster-filters no-print">
                <label class="event-roster-filter event-roster-filter-search">
                    <span>Cari peserta</span>
                    <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Nama atlet atau klub" autocomplete="off">
                </label>

                <label class="event-roster-filter">
                    <span>Kategori</span>
                    <select name="category">
                        <option value="">Semua</option>
                        <option value="__none__" @selected($filters['category'] === '__none__')>Belum berkategori</option>
                        @foreach ($filterOptions['categories'] as $category)
                            <option value="{{ $category }}" @selected($filters['category'] === $category)>{{ $category }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="event-roster-filter">
                    <span>Mata lomba</span>
                    <select name="race">
                        <option value="">Semua</option>
                        @foreach ($filterOptions['races'] as $race)
                            <option value="{{ $race }}" @selected($filters['race'] === $race)>{{ $race }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="event-roster-filter">
                    <span>Gender</span>
                    <select name="gender">
                        <option value="">Semua</option>
                        @foreach ($filterOptions['genders'] as $value => $label)
                            <option value="{{ $value }}" @selected($filters['gender'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="event-roster-filter-actions">
                    <button class="submission-approve" type="submit">Terapkan</button>
                    @if ($filters['q'] !== '' || $filters['category'] !== '' || $filters['race'] !== '' || $filters['gender'] !== '')
                        <a class="event-roster-filter-reset" href="{{ route('events.registrations.index', $event) }}">Reset</a>
                    @endif
                </div>
            </form>
            <div class="event-roster-table-wrap">
                <table class="event-roster-table event-roster-table-readonly">
                    <thead>
                        <tr>
                            <th scope="col">Nama</th>
                            <th scope="col">Kategori</th>
                            <th scope="col">Mata lomba</th>
                            <th scope="col">Klub</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php($previousCategory = null)
                        @foreach ($registrations as $registration)
                            @if ($groupByCategory && $registration->category !== $previousCategory)
                                @php($previousCategory = $registration->category)
                                <tr class="event-roster-group-row">
                                    <th scope="colgroup" colspan="4">
                                        {{ $registration->category ?: 'Belum berkategori' }}
                                        <span class="event-roster-group-meta">
                                            {{ $registrations->getCollection()->where('category', $registration->category)->count() }} peserta di halaman ini
                                        </span>
                                    </th>
                                </tr>
                            @endif
                            <tr>
                                <th scope="row" data-label="Nama">{{ $registration->participant_name }}</th>
                                <td data-label="Kategori">{{ $registration->category }}</td>
                                <td data-label="Mata lomba">{{ implode(', ', $registration->races) }}</td>
                                <td data-label="Klub">{{ $registration->club_name ?: 'Mandiri' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($registrations->hasPages())
                <div class="account-pagination">{{ $registrations->links() }}</div>
            @endif
        </section>
    @endif
</main>
@endsection
