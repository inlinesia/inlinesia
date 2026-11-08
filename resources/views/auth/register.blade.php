@extends('layouts.public')

@section('title', 'Buat Akun — Inlinesia')
@section('description', 'Buat akun Inlinesia sebagai pengelola klub, individu, atau perwakilan pemerintah.')
@section('body-class', 'auth-page')

@section('content')
<main class="account-page">
    <section class="account-intro">
        <span class="account-eyebrow">BERGABUNG DENGAN INLINESIA</span>
        <h1>Mulai kelola kegiatan <span>sepatu roda.</span></h1>
        <p>Buat akun untuk menyiapkan event, mengatur informasi klub, atau mengikuti kegiatan sepatu roda bersama Inlinesia.</p>
        <div class="account-note"><strong>Akun Admin</strong><span>Dibuat khusus oleh admin utama melalui panel pengelolaan akun.</span></div>
    </section>

    <section class="account-card">
        <div class="account-card-heading">
            <span class="account-eyebrow">AKUN BARU</span>
            <h2>Daftar sekarang</h2>
            <p>Isi data dasar Anda. Informasi lain dapat dilengkapi di halaman profil.</p>
        </div>

        @if ($errors->any())
            <div class="account-errors" role="alert">
                <strong>Periksa kembali data Anda.</strong>
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form class="account-form" method="POST" action="{{ route('register.store') }}">
            @csrf
            <label>Nama lengkap
                <input name="name" type="text" value="{{ old('name') }}" autocomplete="name" maxlength="120" required autofocus>
            </label>
            <label>Email
                <input name="email" type="email" value="{{ old('email') }}" autocomplete="email" maxlength="255" required>
            </label>
            <label>Nomor WhatsApp <span class="optional-label">opsional</span>
                <input name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" maxlength="40" placeholder="08xx xxxx xxxx">
            </label>
            <label>Nama klub / instansi <span class="optional-label">opsional</span>
                <input name="organization" type="text" value="{{ old('organization') }}" maxlength="180" placeholder="Nama klub atau instansi">
            </label>

            <fieldset class="role-options">
                <legend>Daftar sebagai</legend>
                @foreach ($roles as $value => $label)
                    <label class="role-option">
                        <input type="radio" name="role" value="{{ $value }}" @checked(old('role', 'individual') === $value) required>
                        <span><strong>{{ $label }}</strong><small>
                            @switch($value)
                                @case('club_manager') Mengelola pendaftaran atas nama klub. @break
                                @case('government') Mewakili instansi pemerintah. @break
                                @default Mengikuti kegiatan sebagai peserta atau pengguna.
                            @endswitch
                        </small></span>
                    </label>
                @endforeach
            </fieldset>

            <label>Kata sandi
                <input name="password" type="password" autocomplete="new-password" required>
            </label>
            <label>Ulangi kata sandi
                <input name="password_confirmation" type="password" autocomplete="new-password" required>
            </label>

            <button class="account-submit" type="submit">Buat akun <span aria-hidden="true">→</span></button>
        </form>
        <p class="account-switch">Sudah memiliki akun? <a href="{{ route('login') }}">Masuk di sini</a></p>
    </section>
</main>
@endsection
