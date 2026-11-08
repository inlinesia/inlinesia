@extends('layouts.public')

@section('title', 'Direktori klub dan peserta — Inlinesia')
@section('description', 'Daftar klub inline skate dan peserta yang sudah tercatat di Inlinesia.')
@section('body-class', 'public-directory')

@section('content')
<main class="profile-page public-directory">
    <header class="event-registration-heading">
        <div>
            <span class="account-eyebrow">DIREKTORI INLINESIA</span>
            <h1>Klub &amp; peserta</h1>
            <p>Cari klub yang ingin diikuti, atau klub yang sedang mencari atlet baru. Klub dan peserta bisa terdaftar di sini, termasuk yang belum ikut lomba apa pun.</p>
        </div>
        <div class="public-directory-actions">
            <a class="account-edit-link" href="{{ route('directory.clubs.create') }}">Daftarkan club <span aria-hidden="true">→</span></a>
            @auth
                <a class="account-edit-link" href="{{ route('directory.athletes.create') }}">Daftarkan peserta <span aria-hidden="true">→</span></a>
            @else
                <a class="account-edit-link" href="{{ route('login') }}">Masuk untuk daftar peserta <span aria-hidden="true">→</span></a>
            @endauth
        </div>
    </header>

    @if (session('status'))
        <div class="public-flash" role="status">{{ session('status') }}</div>
    @endif

    <form method="GET" action="{{ route('directory.index') }}" class="public-directory-search" role="search">
        <label for="directory-search">{{ $canSeeAthletes ? 'Cari nama club atau nama peserta' : 'Cari nama club' }}</label>
        <div class="public-directory-search-row">
            <input id="directory-search" type="search" name="q" value="{{ $term }}" placeholder="Contoh: Roller, Skater, Budi…" autocomplete="off">
            <button class="submission-approve" type="submit">Cari <span aria-hidden="true">→</span></button>
            @if ($term !== '')
                <a class="account-edit-link" href="{{ route('directory.index') }}">Reset</a>
            @endif
        </div>
        <small class="field-hint">
            @if ($canSeeAthletes)
                Kolom satu untuk dua hal sekaligus. NIK sengaja tidak bisa dicari dan hanya terlihat oleh pemilik akun atau pemilik klubnya.
            @else
                Daftar peserta tidak dicari dari halaman ini karena hanya pemilik klub, pemilik akun, dan admin Inlinesia yang boleh melihatnya.
            @endif
        </small>
    </form>

    <section class="account-table-card public-directory-card" aria-labelledby="directory-clubs-heading">
        <div class="account-table-heading">
            <span class="account-eyebrow">KLUB</span>
            <h2 id="directory-clubs-heading">{{ $clubs->count() }} club terdaftar</h2>
        </div>

        @forelse ($clubs as $club)
            <article class="public-directory-entry">
                <div class="public-directory-entry-main">
                    <strong>{{ $club->name }}</strong>
                    <small>
                        {{ $club->city ?: 'Kota belum diisi' }}
                        @if ($club->athletes_count > 0)
                            · {{ $club->athletes_count }} peserta tercatat
                        @endif
                    </small>
                    @if ($club->description)
                        <p>{{ $club->description }}</p>
                    @endif
                </div>
                <div class="public-directory-entry-contact">
                    @if ($club->contact_name)
                        <span><strong>{{ $club->contact_name }}</strong></span>
                    @endif
                    @if ($club->contact_phone)
                        {{-- wa.me hanya mengenali angka dengan kode negara; nomor lokal
                             yang diawali 0 harus diganti menjadi 62. --}}
                        <a href="https://wa.me/{{ preg_replace('/\D+/', '', preg_replace('/^0/', '62', $club->contact_phone)) }}">WhatsApp {{ $club->contact_phone }}</a>
                    @endif
                    @if ($club->contact_email)
                        <a href="mailto:{{ $club->contact_email }}">{{ $club->contact_email }}</a>
                    @endif
                    @if ($club->isManagedBy($viewer))
                        {{-- Hanya pemilik klub yang bisa mengubah kontaknya. --}}
                        <a class="public-directory-manage" href="{{ route('directory.clubs.edit', $club) }}">Ubah data klub <span aria-hidden="true">→</span></a>
                    @endif
                </div>
            </article>
        @empty
            <p class="public-directory-empty">
                @if ($term !== '')
                    Tidak ada club bernama “{{ $term }}”.
                @else
                    Belum ada club yang terdaftar.
                @endif
            </p>
        @endforelse
    </section>

    <section class="account-table-card public-directory-card" aria-labelledby="directory-athletes-heading">
        <div class="account-table-heading">
            <span class="account-eyebrow">PESERTA</span>
            <h2 id="directory-athletes-heading">
                @if ($canSeeAthletes)
                    {{ $athletes->count() }} peserta terdaftar
                @else
                    Daftar peserta
                @endif
            </h2>
        </div>

        @if ($canSeeAthletes)
            @forelse ($athletes as $athlete)
                <article class="public-directory-entry">
                    <div class="public-directory-entry-main">
                        <strong>{{ $athlete->name }}</strong>
                        <small>
                            {{ $athlete->club?->name ?? 'Mandiri' }}
                            @if ($athlete->date_of_birth)
                                · {{ $athlete->date_of_birth->format('Y') }}
                            @endif
                            @if ($athlete->gender === 'male')
                                · Putra
                            @elseif ($athlete->gender === 'female')
                                · Putri
                            @endif
                        </small>
                    </div>
                    <div class="public-directory-entry-contact">
                        {{-- NIK hanya dibuka untuk pemilik akunnya sendiri dan pemilik klubnya. --}}
                        <span class="public-directory-nik">
                            NIK
                            @if ($athlete->canViewNik($viewer))
                                {{ $athlete->nik }}
                            @else
                                {{ $athlete->maskedNik() }}
                            @endif
                        </span>
                    </div>
                </article>
            @empty
                <p class="public-directory-empty">
                    @if ($term !== '')
                        Tidak ada peserta bernama “{{ $term }}”.
                    @else
                        Belum ada peserta yang terdaftar.
                    @endif
                </p>
            @endforelse
        @else
            {{-- Daftar peserta bukan konten publik. Tamu tidak melihat satu nama
                 pun, jadi kotak cari pun tidak menelusuri daftar ini. --}}
            <p class="public-directory-empty">
                Daftar peserta hanya terlihat oleh pemilik klub, pemilik akun, dan admin Inlinesia.
            </p>
        @endif
    </section>
</main>
@endsection
