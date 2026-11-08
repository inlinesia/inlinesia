@extends('layouts.public')

@section('title', $isAdmin ? 'Kelola Event — Inlinesia' : 'Dasbor Peserta — Inlinesia')
@section('section', 'Kelola event')
@section('description', 'Kelola event dan pendaftaran peserta di Inlinesia.')
@section('body-class', 'auth-page')

@section('content')
<main class="profile-page guest-dashboard">
    <header class="profile-heading">
        <span class="account-eyebrow">{{ $isAdmin ? 'PENGELOLAAN EVENT ADMIN' : 'DASBOR AKUN TERVERIFIKASI' }}</span>
        <h1>{{ $isAdmin ? 'Kelola event.' : 'Halo, '.$user->name.'.' }}</h1>
        <p>{{ $isAdmin ? 'Kelola event dan pendaftaran dari halaman bersama ini. Tinjauan pengajuan tersedia di notifikasi Admin.' : 'Pantau pendaftaran Anda dan temukan kejuaraan sepatu roda yang sedang membuka pendaftaran.' }}</p>
        <a class="account-back-link" href="{{ route('profile.show') }}">← Kembali ke profil</a>
        <a class="public-button public-button-orange" href="{{ route('event-applications.create') }}">Ajukan event <span aria-hidden="true">→</span></a>
    </header>

    @if ($errors->any())
        <div class="account-errors" role="alert">
            <strong>Periksa kembali permintaan atau data pendaftaran.</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if ($decisionNotifications->isNotEmpty())
        <section class="profile-notifications" aria-label="Notifikasi keputusan event">
            <h2>Notifikasi pengajuan event</h2>
            @foreach ($decisionNotifications as $notification)
                <p>
                    <strong>{{ $notification->data['event_name'] ?? 'Pengajuan event' }}:</strong>
                    {{ $notification->data['message'] ?? 'Ada pembaruan pada pengajuan event Anda.' }}
                    <small>{{ $notification->created_at->format('d/m/Y H:i') }}</small>
                </p>
            @endforeach
        </section>
    @endif

    @if ($submissions->isNotEmpty())
        <section class="profile-submissions" aria-label="Status pengajuan event">
            <h2>Status pengajuan event Anda</h2>
            @foreach ($submissions as $submission)
                <article>
                    <strong>{{ $submission->name }}</strong>
                    <span class="profile-submission-status status-{{ $submission->status }}">
                        {{ match ($submission->status) {
                            'pending' => 'Menunggu persetujuan Admin',
                            'approved' => 'Disetujui · tampil di daftar lomba',
                            'rejected' => 'Belum disetujui',
                            default => 'Draf',
                        } }}
                    </span>
                    @if ($submission->status === 'pending')
                        <p>Pengajuan Anda sudah diterima dan sedang ditinjau Admin.</p>
                    @elseif ($submission->decision_note)
                        <p>Catatan Admin: {{ $submission->decision_note }}</p>
                    @endif
                    @if ($submission->status === 'approved')
                        <p>Event ini sudah tersedia untuk calon peserta di <a href="{{ route('events.index') }}">daftar lomba</a>.</p>
                    @endif
                    <div class="profile-submission-actions">
                        <a class="account-edit-link" href="{{ route('event-applications.edit', $submission) }}">Ubah event <span aria-hidden="true">↗</span></a>
                    </div>
                </article>
            @endforeach
        </section>
    @endif

    @if ($ownedEvents->isNotEmpty())
        <section class="guest-dashboard-section" aria-labelledby="owned-events-heading">
            <div class="guest-dashboard-section-heading">
                <div>
                    <span class="account-eyebrow">{{ $isAdmin ? 'PENGELOLAAN TERPUSAT' : 'PENGELOLAAN KEJUARAAN' }}</span>
                    <h2 id="owned-events-heading">{{ $isAdmin ? 'Semua event' : 'Event saya' }}</h2>
                </div>
                <span class="guest-dashboard-count">{{ $ownedEvents->count() }} event</span>
            </div>
            @foreach ($ownedEvents as $ownedEvent)
                <article class="guest-event-card">
                    <div class="guest-event-date">
                        <span>{{ strtoupper($ownedEvent->start_date->translatedFormat('M')) }}</span>
                        <strong>{{ $ownedEvent->start_date->format('d') }}</strong>
                        <small>{{ $ownedEvent->start_date->format('Y') }}</small>
                    </div>
                    <div class="guest-event-info">
                        <span>{{ $ownedEvent->registrations_count }} atlet terdaftar</span>
                        <h3>{{ $ownedEvent->name }}</h3>
                        <p>{{ $ownedEvent->venue }} · {{ $ownedEvent->venue_address }}</p>
                        <small>WhatsApp pengelola: {{ $ownedEvent->organizer_whatsapp ?: 'Belum diisi' }}</small>
                        <small>Rekening pembayaran: {{ $ownedEvent->hasPaymentAccount() ? $ownedEvent->payment_account_number.' '.($ownedEvent->payment_bank_name ? '('.$ownedEvent->payment_bank_name.')' : '') : 'Belum diisi' }}</small>
                        @if ($isAdmin)
                            <small>Pemilik: {{ $ownedEvent->submitter?->name ?? 'Admin Inlinesia' }}</small>
                        @endif
                        <small>{{ ucfirst($ownedEvent->status) }} · {{ count($ownedEvent->category_rules ?? []) }} kategori · {{ count($ownedEvent->races) }} mata lomba</small>
                    </div>
                    <div class="guest-event-actions">
                        @if ($ownedEvent->status === 'approved' && ($ownedEvent->submitted_by === $user->id || $ownedEvent->isParticipantListReleased()))
                            <a class="account-edit-link" href="{{ route('events.registrations.index', $ownedEvent) }}">{{ $ownedEvent->submitted_by === $user->id ? 'Kelola peserta & pembayaran' : 'Lihat peserta · read-only' }} <span aria-hidden="true">→</span></a>
                        @endif
                        @if ($ownedEvent->status === 'approved' && $ownedEvent->submitted_by === $user->id && ! $ownedEvent->isParticipantListReleased())
                            <form method="POST" action="{{ route('events.registrations.publish', $ownedEvent) }}"
                                data-confirm="Rilis daftar peserta {{ $ownedEvent->name }} sekarang? Daftar akan dapat dilihat oleh semua akun terverifikasi.">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="participants_published" value="1">
                                <button class="account-edit-link" type="submit">Rilis daftar peserta sekarang <span aria-hidden="true">→</span></button>
                            </form>
                        @endif
                        @if ($ownedEvent->status === 'approved' && ($isAdmin || $ownedEvent->submitted_by === $user->id))
                            <form method="POST" action="{{ route('event-applications.toggle-registration', $ownedEvent) }}"
                                data-confirm="{{ $ownedEvent->registration_open
                                    ? 'Tutup pendaftaran '.$ownedEvent->name.' sekarang?'.
                                    'Tidak ada akun lain yang bisa mengirim pendaftaran baru sampai dibuka kembali.'
                                    : 'Buka pendaftaran '.$ownedEvent->name.' kembali?' }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="registration_open" value="{{ $ownedEvent->registration_open ? '0' : '1' }}">
                                <button class="account-edit-link" type="submit">{{ $ownedEvent->registration_open ? 'Tutup pendaftaran event' : 'Buka pendaftaran event' }} <span aria-hidden="true">→</span></button>
                            </form>
                        @endif
                        <a class="account-edit-link" href="{{ route('event-applications.edit', $ownedEvent) }}">Ubah event <span aria-hidden="true">→</span></a>
                        <form method="POST" action="{{ route('event-applications.destroy', $ownedEvent) }}"
                            data-confirm="Hapus event “{{ $ownedEvent->name }}” beserta seluruh pendaftaran, hasil lomba, dan data keuangannya? Tindakan ini tidak dapat dibatalkan.">
                            @csrf
                            @method('DELETE')
                            <button class="account-edit-link event-delete-button" type="submit">Hapus event</button>
                        </form>
                        @if ($isAdmin || ($ownedEvent->submitted_by === $user->id && $ownedEvent->status === 'approved'))
                            <a class="account-edit-link" href="{{ route('owner.events.categories.edit', $ownedEvent) }}">Atur kategori &amp; batas peserta <span aria-hidden="true">→</span></a>
                        @endif
                        @if ($isAdmin || $ownedEvent->status === 'approved')
                            <a class="account-edit-link" href="{{ route('owner.events.race.format', $ownedEvent) }}">Atur perlombaan <span aria-hidden="true">→</span></a>
                        @endif
                        @if ($isAdmin || ($ownedEvent->submitted_by === $user->id && $ownedEvent->status === 'approved'))
                            <a class="account-edit-link" href="{{ route('owner.events.race.day', $ownedEvent) }}">Hari lomba <span aria-hidden="true">→</span></a>
                        @endif
                        @if ($ownedEvent->hasReleasedRaceOrder())
                            <a class="account-edit-link" href="{{ route('events.race.order', $ownedEvent) }}">Urutan lomba <span aria-hidden="true">→</span></a>
                        @endif
                        @if ($isAdmin || ($ownedEvent->submitted_by === $user->id && $ownedEvent->status === 'approved'))
                            <a class="account-edit-link" href="{{ route('owner.events.finance.index', $ownedEvent) }}">Kelola keuangan <span aria-hidden="true">→</span></a>
                        @endif
                        @if ($isAdmin && ($ownedEvent->submitted_by === null || $ownedEvent->submitter?->role === 'admin'))
                            <form class="event-ownership-transfer" method="POST" action="{{ route('admin.events.ownership.update', $ownedEvent) }}">
                                @csrf
                                @method('PATCH')
                                <label for="event-owner-{{ $ownedEvent->id }}">Alihkan kepemilikan</label>
                                <select id="event-owner-{{ $ownedEvent->id }}" name="owner_id" required>
                                    <option value="">Pilih akun penerima</option>
                                    @foreach ($transferableEventOwners as $transferableOwner)
                                        <option value="{{ $transferableOwner->id }}">{{ $transferableOwner->name }} · {{ $transferableOwner->roleLabel() }} ({{ $transferableOwner->email }})</option>
                                    @endforeach
                                </select>
                                @error('owner_id')
                                    <small class="event-ownership-error" role="alert">{{ $message }}</small>
                                @enderror
                                <button class="account-edit-link" type="submit">Alihkan event</button>
                            </form>
                        @endif
                    </div>
                </article>
            @endforeach
        </section>
    @endif

    <section id="pendaftaran-saya" class="guest-dashboard-section" aria-labelledby="guest-registrations-heading">
        <div class="guest-dashboard-section-heading">
            <div>
                <span class="account-eyebrow">AKTIVITAS ANDA</span>
                <h2 id="guest-registrations-heading">Pendaftaran saya</h2>
            </div>
            <span class="guest-dashboard-count">{{ $registrations->count() }} peserta</span>
        </div>

        @forelse ($registrations as $registration)
            <article class="guest-registration-card">
                <div class="guest-registration-info">
                    <p class="guest-registration-headline">
                        <span class="guest-registration-event">{{ $registration->event->name }}</span>
                        <strong>{{ $registration->participant_name }}</strong>
                        <span>{{ $registration->category ?: 'Kategori setelah konfirmasi pembayaran' }} · {{ $registration->birth_year }} · {{ implode(', ', $registration->races) }}</span>
                        @php
                            $amountDue = $registration->amountDue();
                            $wave = $runningWaves[$registration->id] ?? null;
                            $waveLabel = $wave
                                ? ($wave['label']
                                    ? 'Gelombang '.$wave['label'].' · '.$wave['range']
                                    : 'Harga kategori')
                                : null;
                            $wavePrice = match (true) {
                                $amountDue > 0 => 'harus dibayar '.\App\Services\CategoryFeeCalculator::rupiah($amountDue),
                                $registration->fee_total !== null => 'lunas '.\App\Services\CategoryFeeCalculator::rupiah($registration->fee_total),
                                default => \App\Services\CategoryFeeCalculator::rupiah($wave['price'] ?? 0),
                            };
                        @endphp
                        @if ($wave && $wave['price'] > 0)
                            <span class="guest-registration-wave">{{ $waveLabel }} · {{ $wavePrice }}</span>
                        @endif
                    </p>
                </div>
                <div class="guest-registration-actions">
                    <span class="guest-registration-status status-{{ $registration->status }}">
                        {{ match ($registration->status) {
                            'pending' => 'Menunggu panitia',
                            'approved' => 'Diterima',
                            'rejected' => 'Perlu ditinjau',
                            default => ucfirst($registration->status),
                        } }}
                    </span>
                    <span class="event-payment-status payment-{{ $registration->payment_status }}">
                        {{ match ($registration->payment_status) {
                            'pending' => 'Menunggu konfirmasi',
                            'paid' => $registration->event->is_free ? 'Dikonfirmasi' : 'Terbayar',
                            default => 'Belum bayar',
                        } }}
                    </span>
                    @if ($registration->event->isParticipantListReleased())
                        <a class="account-edit-link" href="{{ route('events.registrations.index', $registration->event) }}">Lihat daftar peserta <span aria-hidden="true">→</span></a>
                    @endif
                    @if ($registration->event->hasReleasedRaceOrder())
                        <a class="account-edit-link" href="{{ route('events.race.order', $registration->event) }}">Urutan lomba <span aria-hidden="true">→</span></a>
                    @endif
                    @if ($registration->cancellation_status !== 'pending' && $registration->cancellation_status !== 'approved')
                        @if ($registration->participant_change_status !== 'pending')
                            <button class="account-edit-link event-registration-modal-trigger" type="button" data-registration-modal-open="ubah-peserta-{{ $registration->id }}" aria-controls="ubah-peserta-{{ $registration->id }}">Edit data</button>
                        @endif
                        @if ($registration->participant_change_status !== 'pending')
                            <form method="POST" action="{{ route('events.registration.destroy-own', [$registration->event, $registration]) }}"
                                data-confirm="{{ $registration->payment_status === 'paid' || $registration->amountPaid() > 0
                                    ? 'Ajukan pembatalan pendaftaran ini? Pengelola akan menentukan persetujuan dan nominal refund.'
                                    : 'Batalkan pendaftaran atlet ini? Data dan bukti pembayaran akan dihapus.' }}">
                                @csrf
                                @method('DELETE')
                                <button class="account-edit-link guest-registration-cancel-button" type="submit">
                                    {{ $registration->payment_status === 'paid' || $registration->amountPaid() > 0 ? 'Ajukan pembatalan' : 'Batalkan pendaftaran' }}
                                </button>
                            </form>
                        @endif
                    @endif
                    @if ($registration->payment_status !== 'paid' && ! in_array($registration->cancellation_status, ['pending', 'approved'], true))
                        @if (! $registration->event->is_free)
                            <form method="POST" action="{{ route('events.registration.proof.store', [$registration->event, $registration]) }}" enctype="multipart/form-data" class="guest-payment-proof-form">
                                @csrf
                                <label>Bukti pembayaran
                                    <input type="file" name="payment_proof" accept=".jpg,.jpeg,.png,.pdf" required>
                                </label>
                                <button class="account-edit-link" type="submit">Kirim bukti</button>
                            </form>
                        @endif
                        @if ($registration->event->organizer_whatsapp)
                            <form method="POST" action="{{ route('events.registration.whatsapp', [$registration->event, $registration]) }}">
                                @csrf
                                <button class="account-edit-link" type="submit">{{ $registration->event->is_free ? 'Konfirmasi gratis via WhatsApp' : 'Kirim bukti via WhatsApp' }}</button>
                            </form>
                        @else
                            <small>WhatsApp pengelola kejuaraan belum tersedia.</small>
                        @endif
                    @else
                        @if ($registration->event->is_free)
                            <span class="event-payment-status payment-paid">Pendaftaran dikonfirmasi</span>
                        @endif
                    @endif
                    @if ($registration->event->status === 'approved' && $registration->event->registration_open && (! $registration->event->registration_deadline || $registration->event->registration_deadline->endOfDay()->isFuture()))
                        <a class="account-edit-link" href="{{ route('events.registration.create', $registration->event) }}">Tambah atlet <span aria-hidden="true">→</span></a>
                    @endif
                </div>

                <div class="guest-registration-notes">
                    <small>{{ $registration->event->fees }}</small>
                    @if ($registration->amountPaid() > 0 && $registration->amountDue() > 0)
                        <small>Sudah dibayar {{ \App\Services\CategoryFeeCalculator::rupiah($registration->amountPaid()) }} · sisa tagihan <strong>{{ \App\Services\CategoryFeeCalculator::rupiah($registration->amountDue()) }}</strong></small>
                    @endif
                    @if ($registration->payment_status !== 'paid' && ! $registration->event->is_free && $registration->event->hasPaymentAccount())
                        <small>Transfer ke {{ $registration->event->payment_bank_name }} <strong>{{ $registration->event->payment_account_number }}</strong>@if ($registration->event->payment_account_holder) a.n. {{ $registration->event->payment_account_holder }}@endif</small>
                    @endif
                    @if ($registration->participant_change_status === 'pending')
                        <small class="guest-registration-request-status is-pending">Permintaan perubahan menunggu keputusan pengelola.</small>
                    @elseif ($registration->participant_change_status === 'rejected')
                        <small class="guest-registration-request-status is-rejected">Permintaan perubahan terakhir ditolak.</small>
                    @elseif ($registration->participant_change_status === 'approved')
                        <small class="guest-registration-request-status is-approved">Perubahan terakhir disetujui
                            @if ($registration->refund_amount > 0)
                                · total refund tercatat {{ \App\Services\CategoryFeeCalculator::rupiah($registration->refund_amount) }}
                            @endif
                        </small>
                    @endif
                    @if ($registration->cancellation_status === 'pending')
                        <small class="guest-registration-request-status is-pending">Permintaan pembatalan menunggu keputusan pengelola.</small>
                    @elseif ($registration->cancellation_status === 'approved')
                        <small class="guest-registration-request-status is-rejected">Pendaftaran dibatalkan
                            @if ($registration->refund_amount > 0)
                                · refund {{ \App\Services\CategoryFeeCalculator::rupiah($registration->refund_amount) }}
                            @else
                                · tanpa refund
                            @endif
                        </small>
                    @elseif ($registration->cancellation_status === 'rejected')
                        <small class="guest-registration-request-status is-rejected">Permintaan pembatalan terakhir ditolak.</small>
                    @endif
                </div>
            </article>
            @if ($registration->cancellation_status !== 'pending' && $registration->cancellation_status !== 'approved' && $registration->participant_change_status !== 'pending')
                @include('verified.events.participants.ubah', [
                    'event' => $registration->event,
                    'registration' => $registration,
                    'isParticipant' => true,
                ])
            @endif
        @empty
            <div class="guest-dashboard-empty">
                <strong>Belum ada pendaftaran.</strong>
                <p>Pilih event yang tersedia di bawah untuk mulai mendaftarkan peserta.</p>
            </div>
        @endforelse
    </section>

    <section class="guest-dashboard-section" aria-labelledby="guest-events-heading">
        <div class="guest-dashboard-section-heading">
            <div>
                <span class="account-eyebrow">AGENDA TERBUKA</span>
                <h2 id="guest-events-heading">Event tersedia</h2>
            </div>
            <a class="guest-dashboard-all-events" href="{{ route('events.index') }}">Lihat semua event <span aria-hidden="true">↗</span></a>
        </div>

        @forelse ($events as $event)
            <article class="guest-event-card">
                <div class="guest-event-date">
                    <span>{{ strtoupper($event->start_date->translatedFormat('M')) }}</span>
                    <strong>{{ $event->start_date->format('d') }}</strong>
                    <small>{{ $event->start_date->format('Y') }}</small>
                </div>
                <div class="guest-event-info">
                    <span>{{ $event->scale }} · {{ count($event->races) }} mata lomba</span>
                    <h3>{{ $event->name }}</h3>
                    <p>{{ $event->venue }} · {{ $event->venue_address }}</p>
                    <small>{{ $event->start_date->format('d/m/Y') }}–{{ $event->end_date->format('d/m/Y') }}
                        @if ($event->registration_deadline)
                            · Batas daftar {{ $event->registration_deadline->format('d/m/Y') }}
                        @endif
                    </small>
                </div>
                @if (empty($event->category_rules))
                    <span class="guest-event-closed">Kategori belum disiapkan</span>
                @elseif ($event->registration_deadline && $event->registration_deadline->endOfDay()->isPast())
                    <span class="guest-event-closed">Pendaftaran ditutup</span>
                @elseif ($event->registration_open)
                    <a class="listing-register-link" href="{{ route('events.registration.create', $event) }}">Daftar lomba <span aria-hidden="true">→</span></a>
                @else
                    <span class="guest-event-closed">Pendaftaran segera dibuka</span>
                @endif
            </article>
        @empty
            <div class="guest-dashboard-empty">
                <strong>Belum ada event yang membuka pendaftaran.</strong>
                <p>Event baru akan muncul di sini setelah disetujui Admin.</p>
            </div>
        @endforelse
    </section>
</main>
@endsection
