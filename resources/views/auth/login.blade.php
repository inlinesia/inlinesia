@extends('layouts.public')

@section('title', 'Masuk — Inlinesia')
@section('description', 'Masuk ke akun Inlinesia Anda.')
@section('body-class', 'auth-page')

@section('content')
<main class="account-page account-page-login">
    <section class="account-intro">
        <span class="account-eyebrow">SENANG MELIHAT ANDA KEMBALI</span>
        <h1>Semua kegiatan, <span>dalam kendali.</span></h1>
        <p>Masuk untuk melanjutkan pengelolaan event, informasi klub, dan profil Anda.</p>
        <a class="account-back-link" href="{{ route('events.index') }}">Lihat daftar lomba <span aria-hidden="true">↗</span></a>
    </section>

    <section class="account-card">
        <div class="account-card-heading">
            <span class="account-eyebrow">AKUN INLINESIA</span>
            <h2>Masuk</h2>
            <p>Gunakan email dan kata sandi akun Anda.</p>
        </div>
        @if ($errors->any())
            <div class="account-errors" role="alert">
                <strong>Email atau kata sandi perlu diperiksa.</strong>
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        <form class="account-form" method="POST" action="{{ route('login.store') }}">
            @csrf
            <label>Email
                <input name="email" type="email" value="{{ old('email') }}" autocomplete="username" maxlength="255" required autofocus>
            </label>
            <label>Kata sandi
                <input name="password" type="password" autocomplete="current-password" required>
            </label>
            <label class="remember-option"><input type="checkbox" name="remember" value="1"> Tetap masuk di perangkat ini</label>
            <button class="account-submit" type="submit">Masuk ke akun <span aria-hidden="true">→</span></button>
        </form>
        <p class="account-switch">Belum memiliki akun? <a href="{{ route('register') }}">Daftar sekarang</a></p>
    </section>
</main>
@endsection
