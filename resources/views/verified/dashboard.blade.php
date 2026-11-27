@extends('layouts.public')

@section('title', $isAdmin ? 'Kelola Event — Inlinesia' : 'Dasbor Peserta — Inlinesia')
@section('section', 'Kelola event')
@section('description', 'Kelola event dan pendaftaran peserta di Inlinesia.')
@section('body-class', 'auth-page guest-dashboard-page')

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

    @if ($cancellationRequests->isNotEmpty())
        <section class="profile-notifications cancellation-requests" aria-label="Notifikasi permintaan pembatalan">
            <h2>Permintaan pembatalan peserta</h2>
            @foreach ($cancellationRequests as $request)
                @php
                    $registration = $request['registration'];
                @endphp
                <article class="cancellation-request-item">
                    <p>
                        <strong>{{ $registration->participant_name }}</strong> ·
                        {{ $request['event']->name }}
                        <small>{{ $registration->cancellation_requested_at?->format('d/m/Y H:i') }}</small>
                    </p>
                    <p>Alasan: {{ $request['reason'] !== '' ? $request['reason'] : 'Tidak diisi.' }}</p>
                    <div class="cancellation-request-actions">
                        <form method="POST" action="{{ route('events.registrations.cancellations.review', [$request['event'], $registration]) }}">
                            @csrf
                            <input type="hidden" name="decision" value="approve">
                            <input type="hidden" name="refund_amount" value="0">
                            <button class="account-edit-link btn-primary" type="submit">Setujui</button>
                        </form>
                        <form method="POST" action="{{ route('events.registrations.cancellations.review', [$request['event'], $registration]) }}">
                            @csrf
                            <input type="hidden" name="decision" value="reject">
                            <button class="account-edit-link btn-berry" type="submit">Tolak</button>
                        </form>
                        <a class="account-edit-link btn-grape" href="{{ route('events.registrations.cancellations.contact', [$request['event'], $registration]) }}">Hubungi official</a>
                    </div>
                </article>
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
                <article class="guest-event-card guest-owned-event-card">
                    <div class="guest-event-date">
                        <span>{{ strtoupper($ownedEvent->start_date->translatedFormat('M')) }}</span>
                        <strong>{{ $ownedEvent->start_date->format('d') }}</strong>
                        <small>{{ $ownedEvent->start_date->format('Y') }}</small>
                    </div>
                    <div class="guest-event-info">
                        @if ($ownedEvent->isAdvertisementOnly())
                            <span class="event-tier-badge">Iklan (Gratis)</span>
                        @else
                            <span>{{ $ownedEvent->registrations_count }} atlet terdaftar</span>
                        @endif
                        <h3>{{ $ownedEvent->name }}</h3>
                        @if ($eventSponsors->get($ownedEvent->id))
                            <span class="sponsor-event-badge">Didukung oleh {{ $eventSponsors->get($ownedEvent->id)->brand_name }}</span>
                        @endif
                        @if ($ownedEvent->short_description)
                            <p class="listing-event-summary">{{ $ownedEvent->short_description }}</p>
                        @endif
                        <p>{{ $ownedEvent->venue }} · {{ $ownedEvent->venue_address }}</p>
                        <div class="guest-event-meta">
                            <small>WhatsApp pengelola: {{ $ownedEvent->organizer_whatsapp ?: 'Belum diisi' }}</small>
                            @unless ($ownedEvent->isAdvertisementOnly())
                                <small>Rekening pembayaran: {{ $ownedEvent->hasPaymentAccount() ? $ownedEvent->payment_account_number.' '.($ownedEvent->payment_bank_name ? '('.$ownedEvent->payment_bank_name.')' : '') : 'Belum diisi' }}</small>
                            @endunless
                            @if ($isAdmin)
                                <small>Pemilik: {{ $ownedEvent->submitter?->name ?? 'Admin Inlinesia' }}</small>
                            @endif
                            @if ($ownedEvent->isAdvertisementOnly())
                                <small>{{ ucfirst($ownedEvent->status) }} · tayang sebagai iklan</small>
                            @else
                                <small>{{ ucfirst($ownedEvent->status) }} · {{ count($ownedEvent->category_rules ?? []) }} KU · {{ count($ownedEvent->races) }} mata lomba</small>
                            @endif
                        </div>
                    </div>
                    <div class="guest-event-actions">
                        @unless ($ownedEvent->isAdvertisementOnly())
                        @if ($ownedEvent->status === 'approved' && ($isAdmin || $ownedEvent->submitted_by === $user->id || $ownedEvent->isParticipantListReleased()))
                            <a class="account-edit-link" href="{{ route('events.registrations.index', $ownedEvent) }}">{{ $isAdmin || $ownedEvent->submitted_by === $user->id ? 'Kelola peserta & pembayaran' : 'Lihat peserta · read-only' }} <span aria-hidden="true">→</span></a>
                            @if ($isAdmin || $ownedEvent->submitted_by === $user->id)
                                <a class="account-edit-link" href="{{ route('events.registrations.index', $ownedEvent) }}">Lihat semua atlet <span aria-hidden="true">→</span></a>
                            @endif
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
                        @endunless
                        @if ($ownedEvent->isAdvertisementOnly())
                            <a class="account-edit-link btn-primary" href="{{ route('event-applications.edit', $ownedEvent) }}">Upgrade ke berbayar <span aria-hidden="true">→</span></a>
                        @endif
                        <a class="account-edit-link" href="{{ route('event-applications.edit', $ownedEvent) }}">Ubah event <span aria-hidden="true">→</span></a>
                        @unless ($ownedEvent->isAdvertisementOnly())
                            <a class="account-edit-link" href="{{ route('sponsor.event.form', ['event' => $ownedEvent->id]) }}">Tambah sponsor <span aria-hidden="true">↗</span></a>
                        @endunless
                        <form method="POST" action="{{ route('event-applications.destroy', $ownedEvent) }}"
                            data-confirm="Hapus event “{{ $ownedEvent->name }}” beserta seluruh pendaftaran, hasil lomba, dan data keuangannya? Tindakan ini tidak dapat dibatalkan.">
                            @csrf
                            @method('DELETE')
                            <button class="account-edit-link event-delete-button" type="submit">Hapus event</button>
                        </form>
                        @unless ($ownedEvent->isAdvertisementOnly())
                        @if ($isAdmin || ($ownedEvent->submitted_by === $user->id && $ownedEvent->status === 'approved'))
                            <a class="account-edit-link" href="{{ route('owner.events.categories.edit', $ownedEvent) }}">Atur KU (Kelompok Usia) &amp; batas peserta <span aria-hidden="true">→</span></a>
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
                        @endunless
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

    @php
        $tambahPesertaEvent = $registrations->first()?->event ?? $events->first();
        $bisaTambahPeserta = $tambahPesertaEvent
            && ! $tambahPesertaEvent->isAdvertisementOnly()
            && $tambahPesertaEvent->status === 'approved'
            && $tambahPesertaEvent->registration_open
            && (! $tambahPesertaEvent->registration_deadline || $tambahPesertaEvent->registration_deadline->endOfDay()->isFuture());
    @endphp
    <section id="pendaftaran-saya" class="guest-dashboard-section" aria-labelledby="guest-registrations-heading">
        <div class="guest-dashboard-section-heading">
            <div>
                <span class="account-eyebrow">AKTIVITAS ANDA</span>
                <h2 id="guest-registrations-heading">Pendaftaran saya</h2>
            </div>
            <div class="guest-dashboard-section-actions">
                @if ($bisaTambahPeserta)
                    <a class="account-edit-link btn-primary" href="{{ route('events.registration.create', $tambahPesertaEvent) }}">+ Tambah peserta</a>
                @endif
                <span class="guest-dashboard-count">{{ $registrations->count() }} peserta</span>
            </div>
        </div>

        @if ($registrations->isNotEmpty())
            @php
                $raceColumns = $registrations->first()->event->races ?? [];
                $raceColumnCount = max(1, count($raceColumns));
            @endphp
            <input type="hidden" data-race-csrf value="{{ csrf_token() }}">
            <div class="account-table-wrap guest-registration-table-wrap">
                <table class="account-table guest-registration-table">
                    <thead>
                        <tr>
                            <th scope="col" rowspan="2">No.</th>
                            <th scope="col" rowspan="2">Aksi</th>
                            <th scope="col" rowspan="2">Nama panggilan</th>
                            <th scope="col" rowspan="2">Nama lengkap</th>
                            <th scope="col" rowspan="2">NIK</th>
                            <th scope="col" rowspan="2">Gender</th>
                            <th scope="col" rowspan="2">Tanggal lahir</th>
                            <th scope="col" rowspan="2" title="KU (Kelompok Usia)">KU</th>
                            <th scope="colgroup" colspan="{{ $raceColumnCount }}">Mata Lomba</th>
                            <th scope="col" rowspan="2">Harga</th>
                            <th scope="col" rowspan="2">Status bayar</th>
                        </tr>
                        <tr>
                            @forelse ($raceColumns as $race)
                                <th scope="col">{{ $race }}</th>
                            @empty
                                <th scope="col">—</th>
                            @endforelse
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($registrations as $registration)
                            @php
                                $wave = $runningWaves[$registration->id] ?? null;
                            @endphp
                            <tr data-race-registration data-update-url="{{ route('events.registration.races.update', [$registration->event, $registration]) }}">
                                <td class="guest-registration-number">{{ $loop->iteration }}</td>
                                <td class="guest-registration-actions">
                                    @if ($registration->cancellation_status !== 'pending' && $registration->cancellation_status !== 'approved' && $registration->participant_change_status !== 'pending')
                                        <button class="account-edit-link btn-primary btn-icon event-registration-modal-trigger" type="button" data-registration-modal-open="ubah-peserta-{{ $registration->id }}" aria-controls="ubah-peserta-{{ $registration->id }}" title="Edit data" aria-label="Edit data">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                        </button>
                                        @if ($registration->event->isCancellationOpen())
                                            <button class="account-edit-link btn-berry btn-icon" type="button" data-registration-modal-open="pembatalan-{{ $registration->id }}" aria-controls="pembatalan-{{ $registration->id }}" title="Ajukan pembatalan" aria-label="Ajukan pembatalan">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v5M14 11v5"/></svg>
                                            </button>
                                        @endif
                                    @endif
                                    @if ($registration->payment_status !== 'paid' && ! in_array($registration->cancellation_status, ['pending', 'approved'], true) && $registration->event->organizer_whatsapp)
                                        @php
                                            $whatsappLabel = $registration->event->is_free ? 'Konfirmasi gratis via WhatsApp' : 'Kirim bukti via WhatsApp';
                                        @endphp
                                        <form method="POST" action="{{ route('events.registration.whatsapp', [$registration->event, $registration]) }}">
                                            @csrf
                                            <button class="account-edit-link btn-grape btn-icon" type="submit" title="{{ $whatsappLabel }}" aria-label="{{ $whatsappLabel }}">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.4 8.4 0 0 1-8.5 8.5 8.5 8.5 0 0 1-3.8-.9L3 21l1.9-5.7a8.5 8.5 0 0 1-.9-3.8A8.4 8.4 0 0 1 12.5 3a8.5 8.5 0 0 1 8.5 8.5Z"/></svg>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                                <td>{{ $registration->nickname ?: '—' }}</td>
                                <td>{{ $registration->participant_name }}</td>
                                <td>{{ $registration->maskedIdentity() }}</td>
                                <td>{{ match ($registration->gender) {
                                    'male' => 'Putra',
                                    'female' => 'Putri',
                                    default => ucfirst((string) $registration->gender) ?: '—',
                                } }}</td>
                                <td>{{ $registration->date_of_birth?->format('d-m-Y') ?? '—' }}</td>
                                <td>{{ $registration->category ?: 'Belum ditetapkan' }}</td>
                                @foreach ($raceColumns as $race)
                                    <td class="guest-registration-race-cell">
                                        @php
                                            $bolehUbahLomba = $registration->payment_status !== 'paid'
                                                && $registration->amountPaid() <= 0
                                                && ! in_array($registration->cancellation_status, ['pending', 'approved'], true)
                                                && $registration->participant_change_status !== 'pending';
                                        @endphp
                                        <input type="checkbox"
                                            @if ($bolehUbahLomba) data-race-toggle @endif
                                            data-race="{{ $race }}"
                                            value="{{ $race }}"
                                            @checked(in_array($race, $registration->races ?? [], true))
                                            @disabled(! $bolehUbahLomba)
                                            aria-label="{{ $race }}"
                                            title="{{ $bolehUbahLomba ? 'Klik untuk mengubah mata lomba' : 'Pendaftaran lunas/menunggu persetujuan — ubah lewat pengelola' }}">
                                    </td>
                                @endforeach
                                @if ($raceColumns === [])
                                    <td class="guest-registration-race-cell">—</td>
                                @endif
                                <td>
                                    @if ($registration->fee_total !== null)
                                        {{ \App\Services\CategoryFeeCalculator::rupiah($registration->fee_total) }}
                                    @elseif (($wave['price'] ?? 0) > 0)
                                        {{ \App\Services\CategoryFeeCalculator::rupiah($wave['price']) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    <span class="event-payment-status payment-{{ $registration->payment_status === 'paid' ? 'paid' : 'unpaid' }}">
                                        {{ $registration->payment_status === 'paid' ? 'Lunas' : 'Belum bayar' }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @foreach ($registrations as $registration)
                @if ($registration->event->isCancellationOpen()
                    && $registration->cancellation_status !== 'pending'
                    && $registration->cancellation_status !== 'approved'
                    && $registration->participant_change_status !== 'pending')
                    <dialog id="pembatalan-{{ $registration->id }}" class="event-registration-modal" aria-labelledby="pembatalan-title-{{ $registration->id }}">
                        <section class="account-edit-card event-registration-edit-card">
                            <header class="event-registration-modal-heading">
                                <div>
                                    <span class="account-eyebrow">{{ $registration->event->name }}</span>
                                    <h2 id="pembatalan-title-{{ $registration->id }}">Ajukan pembatalan</h2>
                                    <p>{{ $registration->participant_name }}</p>
                                </div>
                                <button class="event-registration-modal-close" type="button" data-registration-modal-close aria-label="Tutup">×</button>
                            </header>
                            <form class="account-form event-registration-edit-form" method="POST" action="{{ route('events.registration.destroy-own', [$registration->event, $registration]) }}">
                                @csrf
                                @method('DELETE')
                                <label>Alasan pembatalan
                                    <textarea name="reason" rows="4" maxlength="1000" required placeholder="Tuliskan alasan pembatalan"></textarea>
                                    <small class="field-hint">Setelah dikirim, Anda diarahkan ke WhatsApp pengelola untuk konfirmasi. Pengelola akan menyetujui atau menolak pengajuan ini.</small>
                                </label>
                                <button class="account-submit" type="submit">Kirim pengajuan pembatalan <span aria-hidden="true">→</span></button>
                            </form>
                        </section>
                    </dialog>
                @endif
                @if ($registration->cancellation_status !== 'pending' && $registration->cancellation_status !== 'approved' && $registration->participant_change_status !== 'pending')
                    @include('verified.events.participants.ubah', [
                        'event' => $registration->event,
                        'registration' => $registration,
                        'isParticipant' => true,
                    ])
                @endif
            @endforeach
        @else
            <div class="guest-dashboard-empty">
                <strong>Belum ada pendaftaran.</strong>
                <p>Pilih event yang tersedia di bawah untuk mulai mendaftarkan peserta.</p>
            </div>
        @endif
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
            <article class="guest-event-card guest-open-event-card">
                <div class="guest-event-date">
                    <span>{{ strtoupper($event->start_date->translatedFormat('M')) }}</span>
                    <strong>{{ $event->start_date->format('d') }}</strong>
                    <small>{{ $event->start_date->format('Y') }}</small>
                </div>
                <div class="guest-event-info">
                    <span>{{ $event->isAdvertisementOnly() ? 'Iklan event (Gratis)' : $event->scale.' · '.count($event->races).' mata lomba' }}</span>
                    <h3>{{ $event->name }}</h3>
                    @if ($eventSponsors->get($event->id))
                        <span class="sponsor-event-badge">Didukung oleh {{ $eventSponsors->get($event->id)->brand_name }}</span>
                    @endif
                    @if ($event->short_description)
                        <p class="listing-event-summary">{{ $event->short_description }}</p>
                    @endif
                    <p>{{ $event->venue }} · {{ $event->venue_address }}</p>
                    <div class="guest-event-meta">
                        <small>{{ $event->start_date->format('d/m/Y') }}–{{ $event->end_date->format('d/m/Y') }}
                            @if ($event->registration_deadline)
                                · Batas daftar {{ $event->registration_deadline->format('d/m/Y') }}
                            @endif
                        </small>
                    </div>
                </div>
                <div class="guest-event-actions">
                    @if ($event->isAdvertisementOnly())
                        @if ($event->organizerWhatsappLink())
                            <a class="listing-register-link" href="{{ $event->organizerWhatsappLink() }}" target="_blank" rel="noopener noreferrer">Hubungi pengelola <span aria-hidden="true">↗</span></a>
                        @else
                            <span class="guest-event-closed">Informasi event iklan</span>
                        @endif
                    @elseif (empty($event->category_rules))
                        <span class="guest-event-closed">KU belum disiapkan</span>
                    @elseif ($event->registration_deadline && $event->registration_deadline->endOfDay()->isPast())
                        <span class="guest-event-closed">Pendaftaran ditutup</span>
                    @elseif ($event->registration_open)
                        <a class="listing-register-link" href="{{ route('events.registration.create', $event) }}">Daftar lomba <span aria-hidden="true">→</span></a>
                    @else
                        <span class="guest-event-closed">Pendaftaran segera dibuka</span>
                    @endif
                    @if ($event->whatsapp_group_link)
                        <a class="listing-group-link" href="{{ $event->whatsapp_group_link }}" target="_blank" rel="noopener noreferrer">Gabung grup WA <span aria-hidden="true">↗</span></a>
                    @endif
                </div>
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
