@extends('layouts.public')

@section('title', 'Inlinesia — Indonesia Inline Skate Events')
@section('description', 'Jelajahi kejuaraan inline skate, daftarkan atlet, dan kelola event sepatu roda melalui Inlinesia.')
@section('body-class', 'public-home')

@section('content')
<main id="beranda">
    <section class="public-hero">
        <div class="public-hero-copy">
            <div class="public-overline"><span></span> PLATFORM EVENT INLINE SKATE</div>
            <h1>Kejuaraan sepatu roda,<br><span>semua terhubung.</span></h1>
            <p class="public-hero-lead">Jelajahi agenda kejuaraan, daftarkan atlet, dan kelola event dari satu tempat. Inlinesia menghubungkan kebutuhan peserta dan penyelenggara.</p>
            <div class="public-hero-actions">
                <a class="public-button public-button-orange" href="{{ route('events.index') }}">Jelajahi kejuaraan <span aria-hidden="true">→</span></a>
                <a class="public-text-link" href="#layanan">Lihat fitur platform <span aria-hidden="true">↘</span></a>
            </div>
            <div class="public-hero-directory">
                {{-- Tiga jalur berbeda: melihat direktori, mendaftarkan klub,
                     dan mendaftarkan atlet lewat formulir direktori. --}}
                <a class="public-directory-label" href="{{ route('directory.index') }}">LIHAT DIREKTORI</a>
                <a class="public-directory-link" href="{{ route('directory.clubs.create') }}">Daftarkan club <span aria-hidden="true">→</span></a>
                <a class="public-directory-link" href="{{ route('directory.athletes.create') }}">Daftarkan peserta <span aria-hidden="true">→</span></a>
            </div>
            <div class="public-community-note">
                <div class="organizer-mark" aria-hidden="true">I</div>
                <p><strong>Untuk atlet dan penyelenggara.</strong><br>Agenda · pendaftaran · pengelolaan event</p>
            </div>
        </div>

        <aside class="public-hero-art platform-preview" aria-label="Fitur utama platform Inlinesia">
            <span class="platform-preview-label">FITUR YANG TERSEDIA</span>
            <a class="platform-preview-link" href="{{ route('events.index') }}">
                <span class="platform-preview-number">01</span>
                <span><small>JELAJAHI</small><strong>Agenda kejuaraan</strong><em>Tanggal, lokasi, mata lomba</em></span>
                <span class="platform-preview-arrow" aria-hidden="true">↗</span>
            </a>
            <a class="platform-preview-link" href="{{ route('events.index') }}">
                <span class="platform-preview-number">02</span>
                <span><small>UNTUK ATLET</small><strong>Pendaftaran lomba</strong><em>Atlet, kategori, dan nomor lomba</em></span>
                <span class="platform-preview-arrow" aria-hidden="true">↗</span>
            </a>
            @if (auth()->check() && (auth()->user()->hasVerifiedEmail() || auth()->user()->role === 'admin'))
                <a class="platform-preview-link" href="{{ route('guest.dashboard') }}">
            @elseif (auth()->check())
                <a class="platform-preview-link" href="{{ route('verification.notice') }}">
            @else
                <a class="platform-preview-link" href="{{ route('register') }}">
            @endif
                <span class="platform-preview-number">03</span>
                <span><small>UNTUK PENGELOLA</small><strong>Dasbor event</strong><em>Event, kategori, peserta</em></span>
                <span class="platform-preview-arrow" aria-hidden="true">↗</span>
            </a>
        </aside>
        <div class="hero-bottom-note"><span>01 /</span> JELAJAHI · DAFTAR · KELOLA</div>
    </section>

    <section class="public-championships" id="kejuaraan">
        <div class="public-section-heading">
            <div><span class="public-section-label">AGENDA KOMPETISI</span><h2>Kejuaraan yang akan datang.</h2></div>
            <a class="public-text-link" href="{{ route('events.index') }}">Lihat semua lomba <span aria-hidden="true">↗</span></a>
        </div>
        <div class="public-championship-list">
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
                        <div class="listing-event-meta"><span class="listing-event-type">{{ $event->scale }}</span>
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
                        </div>
                    </div>
                    @if ($registrationIsOpen && auth()->check() && (auth()->user()->hasVerifiedEmail() || auth()->user()->role === 'admin'))
                        <a class="listing-register-link" href="{{ route('events.registration.create', $event) }}">Daftar lomba <span aria-hidden="true">→</span></a>
                    @elseif ($registrationIsOpen && auth()->check())
                        <a class="listing-register-link" href="{{ route('verification.notice') }}">Verifikasi email untuk mendaftar <span aria-hidden="true">→</span></a>
                    @elseif ($registrationIsOpen)
                        <a class="listing-register-link" href="{{ route('login') }}">Masuk untuk mendaftar <span aria-hidden="true">→</span></a>
                    @else
                        <span class="guest-event-closed">{{ empty($event->category_rules) ? 'Kategori disiapkan pengelola' : ($event->registration_deadline && $event->registration_deadline->endOfDay()->isPast() ? 'Pendaftaran ditutup' : 'Pendaftaran segera dibuka') }}</span>
                    @endif
                </article>
            @empty
                <div class="listing-empty-state">
                    <span aria-hidden="true">◎</span>
                    <h3>Belum ada kejuaraan mendatang</h3>
                    <p>Kejuaraan yang disetujui akan tampil di sini. Lihat daftar untuk informasi terbaru.</p>
                </div>
            @endforelse
        </div>
    </section>

    <section class="public-events" id="layanan">
        <div class="public-section-heading">
            <div><span class="public-section-label">FITUR PLATFORM</span><h2>Komponen event dalam satu alur.</h2></div>
            <a class="public-text-link" href="{{ route('events.index') }}">Buka daftar kejuaraan <span aria-hidden="true">↗</span></a>
        </div>
        <div class="service-cards">
            <article class="service-card">
                <span class="service-number">01</span><span class="service-icon">↗</span>
                <div><span class="public-event-tag">AGENDA PUBLIK</span><h3>Temukan kejuaraan</h3><p>Lihat event yang disetujui, jadwal, lokasi, mata lomba, dan status pendaftarannya.</p><a class="public-text-link" href="{{ route('events.index') }}">Jelajahi agenda <span aria-hidden="true">→</span></a></div>
            </article>
            <article class="service-card">
                <span class="service-number">02</span><span class="service-icon">◎</span>
                <div><span class="public-event-tag">PENDAFTARAN ATLET</span><h3>Daftar mata lomba</h3><p>Daftarkan atlet ke kejuaraan yang membuka pendaftaran dan ikuti kategori yang sesuai.</p><a class="public-text-link" href="{{ route('events.index') }}">Cari lomba terbuka <span aria-hidden="true">→</span></a></div>
            </article>
            <article class="service-card">
                <span class="service-number">03</span><span class="service-icon">✳</span>
                <div><span class="public-event-tag">DASBOR PENGELOLA</span><h3>Kelola event</h3><p>Ajukan event, atur kategori dan mata lomba, lalu pantau data peserta dari dasbor.</p>
                    @if (auth()->check() && (auth()->user()->hasVerifiedEmail() || auth()->user()->role === 'admin'))
                        <a class="public-text-link" href="{{ route('guest.dashboard') }}">Buka dasbor <span aria-hidden="true">→</span></a>
                    @elseif (auth()->check())
                        <a class="public-text-link" href="{{ route('verification.notice') }}">Verifikasi akun <span aria-hidden="true">→</span></a>
                    @else
                        <a class="public-text-link" href="{{ route('register') }}">Buat akun pengelola <span aria-hidden="true">→</span></a>
                    @endif
                </div>
            </article>
        </div>
    </section>

    <section class="public-sponsor" id="sponsor" aria-labelledby="sponsor-heading">
        <div class="sponsor-card">
            <div class="sponsor-card-intro">
                <span class="public-section-label">KEMITRAAN &amp; IKLAN</span>
                <h2 id="sponsor-heading">Jangkau komunitas sepatu roda.</h2>
                <p>Promosikan brand Anda melalui website Inlinesia atau dukung kejuaraan sebagai sponsor event.</p>
                <a class="public-button public-button-orange" href="mailto:inlinesia@gmail.com?subject=Informasi%20Sponsor%20Inlinesia">Hubungi Inlinesia <span aria-hidden="true">↗</span></a>
            </div>
            <div class="sponsor-card-options">
                <article>
                    <span class="sponsor-option-number">01</span>
                    <div><h3>Iklan di website</h3><p>Tampilkan brand kepada pengunjung yang mencari agenda dan informasi kejuaraan.</p></div>
                </article>
                <article>
                    <span class="sponsor-option-number">02</span>
                    <div><h3>Sponsor event</h3><p>Dukung penyelenggaraan event dan promosikan brand kepada peserta serta komunitas.</p></div>
                </article>
            </div>
        </div>
    </section>

    <section class="public-community" id="cara-kerja">
        <div class="community-copy">
            <span class="public-section-label">ALUR PESERTA DAN PENGELOLA</span>
            <h2>Dari agenda hingga <span>pendaftaran.</span></h2>
            <p>Informasi kejuaraan tersedia untuk publik. Peserta dapat mendaftar setelah masuk dan memverifikasi akun, sementara pengelola mengatur event melalui dasbor.</p>
            <a class="public-button public-button-dark" href="{{ route('events.index') }}">Lihat kejuaraan <span aria-hidden="true">→</span></a>
        </div>
        <div class="community-values">
            <article><span class="value-number">01</span><div><h3>Jelajahi agenda</h3><p>Temukan kejuaraan berdasarkan jadwal dan lokasi.</p></div><span class="value-mark">↗</span></article>
            <article><span class="value-number">02</span><div><h3>Daftarkan atlet</h3><p>Pilih kejuaraan yang membuka pendaftaran dan mata lomba.</p></div><span class="value-mark">↗</span></article>
            <article><span class="value-number">03</span><div><h3>Kelola kejuaraan</h3><p>Atur kategori, pendaftaran, dan data peserta dari dasbor.</p></div><span class="value-mark">↗</span></article>
        </div>
    </section>

    <section class="club-cta" id="rencana-event">
        <div><span class="public-section-label">PENGELOLA EVENT</span><h2>Siapkan kejuaraan berikutnya.</h2><p>Buat akun untuk mengajukan event dan mengelola kategori, pendaftaran, serta data peserta melalui dasbor Inlinesia.</p></div>
        <div class="club-cta-actions">
            @if (auth()->check() && (auth()->user()->hasVerifiedEmail() || auth()->user()->role === 'admin'))
                <a class="public-button public-button-orange" href="{{ route('event-applications.create') }}">Ajukan event <span aria-hidden="true">→</span></a>
                <a class="public-button public-button-light" href="{{ route('guest.dashboard') }}">Buka dasbor <span aria-hidden="true">↗</span></a>
            @elseif (auth()->check())
                <a class="public-button public-button-orange" href="{{ route('verification.notice') }}">Verifikasi akun <span aria-hidden="true">→</span></a>
            @else
                <a class="public-button public-button-orange" href="{{ route('register') }}">Buat akun pengelola <span aria-hidden="true">→</span></a>
                <a class="public-button public-button-light" href="{{ route('login') }}">Masuk <span aria-hidden="true">↗</span></a>
            @endif
        </div>
        <div class="cta-sun" aria-hidden="true"></div>
    </section>

    <x-contact-card />

    <section class="about-inlinesia" id="tentang">
        <span class="public-section-label">TENTANG INLINESIA</span>
        <p>Inlinesia menyediakan agenda kejuaraan, pendaftaran atlet, dan pengelolaan event inline skate dalam satu platform.</p>
    </section>
</main>
@endsection
