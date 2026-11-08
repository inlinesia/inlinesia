@extends('layouts.public')

@section('title', 'Daftar Kejuaraan — Inlinesia')
@section('description', 'Jelajahi kejuaraan sepatu roda yang telah disetujui.')
@section('body-class', 'public-home event-listing-page')

@section('content')
<main class="event-listing-main">
    <section class="listing-intro">
        <a class="listing-back-link" href="{{ route('home') }}">← Kembali ke beranda</a>
        <div class="public-overline"><span></span> AGENDA EVENT SEPATU RODA</div>
        <h1>Jelajahi <span>kejuaraan.</span></h1>
        <p>Daftar kejuaraan yang telah disetujui Admin. Pengelolaan event dan pengajuan hanya tersedia dari dasbor akun terverifikasi.</p>
        @if ($events->isEmpty())
            <div class="listing-demo-notice"><span aria-hidden="true">i</span><p><strong>Belum ada kejuaraan yang tersedia.</strong> Kejuaraan akan tampil di sini setelah disetujui.</p></div>
        @endif
    </section>

    <section class="listing-results" aria-labelledby="listing-heading">
        <div class="listing-toolbar">
            <div><span class="public-section-label">KALENDER SEPATU RODA</span><h2 id="listing-heading">Daftar kejuaraan <span>{{ $events->total() }}</span></h2></div>
            @if ($isVerified || $isAdmin)
                <a class="listing-sort-label" href="{{ route('guest.dashboard') }}">Kelola event <span aria-hidden="true">↗</span></a>
            @elseif (auth()->check())
                <a class="listing-sort-label" href="{{ route('verification.notice') }}">Verifikasi email <span aria-hidden="true">↗</span></a>
            @else
                <a class="listing-sort-label" href="{{ route('register') }}">Buat akun untuk mengajukan event <span aria-hidden="true">↗</span></a>
            @endif
        </div>

        @forelse ($events as $event)
            @php
                $registrationIsOpen = $event->registration_open
                    && ! empty($event->category_rules)
                    && (! $event->registration_deadline || $event->registration_deadline->endOfDay()->isFuture());
            @endphp
            <article class="listing-event-card">
                <div class="listing-event-date">
                    <span>{{ strtoupper($event->start_date->translatedFormat('M')) }}</span>
                    <strong>{{ $event->start_date->format('d') }}</strong>
                    <small>{{ $event->start_date->format('Y') }}</small>
                </div>
                <div class="listing-event-body">
                    <div class="listing-event-meta">
                        <span class="listing-event-type">{{ $event->scale }}</span>
                        @if ($event->registration_deadline && $event->registration_deadline->endOfDay()->isPast())
                            <span class="listing-status listing-status-preparing">Pendaftaran ditutup</span>
                        @elseif (empty($event->category_rules))
                            <span class="listing-status listing-status-preparing">Kategori disiapkan</span>
                        @elseif (! $event->registration_open)
                            <span class="listing-status listing-status-preparing">Pendaftaran segera dibuka</span>
                        @else
                            <span class="listing-status listing-status-open">Pendaftaran dibuka</span>
                        @endif
                    </div>
                    <h3>{{ $event->name }}</h3>
                    <p>{{ $event->venue_address }}</p>
                    <div class="listing-event-facts">
                        <span><span aria-hidden="true">⌖</span>{{ $event->venue }}</span>
                        <span><span aria-hidden="true">▤</span>{{ count($event->races) }} mata lomba</span>
                        <span><span aria-hidden="true">◷</span>{{ $event->start_date->format('d/m/Y') }}–{{ $event->end_date->format('d/m/Y') }}</span>
                        @if ($event->organizer_whatsapp)
                            <span><span aria-hidden="true">☎</span>WhatsApp pengelola: {{ $event->organizer_whatsapp }}</span>
                        @endif
                    </div>
                </div>
                <div class="listing-event-actions">
                    @if (($isVerified || $isAdmin) && $registrationIsOpen)
                        <a class="listing-register-link" href="{{ route('events.registration.create', $event) }}">Daftar lomba <span aria-hidden="true">→</span></a>
                    @elseif (auth()->check() && $registrationIsOpen)
                        <a class="listing-register-link" href="{{ route('verification.notice') }}">Verifikasi email untuk mendaftar <span aria-hidden="true">→</span></a>
                    @elseif ($registrationIsOpen)
                        <a class="listing-register-link" href="{{ route('login') }}">Masuk untuk mendaftar <span aria-hidden="true">→</span></a>
                    @else
                        <span class="guest-event-closed">{{ empty($event->category_rules) ? 'Kategori disiapkan pengelola' : ($event->registration_deadline && $event->registration_deadline->endOfDay()->isPast() ? 'Pendaftaran ditutup' : 'Pendaftaran segera dibuka') }}</span>
                    @endif
                    @if (($isVerified || $isAdmin) && $event->isParticipantListReleased())
                        <a class="listing-roster-link" href="{{ route('events.registrations.index', $event) }}">Lihat peserta <span aria-hidden="true">→</span></a>
                    @endif
                </div>
                <span class="listing-card-number" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
            </article>
        @empty
            <div class="listing-empty-state">
                <span aria-hidden="true">◎</span>
                <h3>Belum ada kejuaraan</h3>
                <p>Kejuaraan yang tersedia akan tampil di sini setelah disetujui.</p>
            </div>
        @endforelse

        @if ($events->hasPages())
            <div class="account-pagination">{{ $events->links() }}</div>
        @endif
    </section>

    <section class="listing-organizer-cta">
        <div><span class="public-section-label">ANDA PENYELENGGARA EVENT?</span><h2>Ajukan event Anda di sini.</h2><p>Daftar atau masuk, verifikasi email, lalu ajukan event dari dasbor akun.</p></div>
        @if ($isVerified || $isAdmin)
            <a class="public-button public-button-orange" href="{{ route('event-applications.create') }}">Ajukan event <span aria-hidden="true">→</span></a>
        @elseif (auth()->check())
            <a class="public-button public-button-orange" href="{{ route('verification.notice') }}">Verifikasi email <span aria-hidden="true">→</span></a>
        @else
            <a class="public-button public-button-orange" href="{{ route('register') }}">Buat akun <span aria-hidden="true">→</span></a>
        @endif
    </section>
</main>
@endsection
