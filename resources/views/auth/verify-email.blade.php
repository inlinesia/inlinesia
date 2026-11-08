@extends('layouts.public')

@section('title', 'Verifikasi Email — Inlinesia')
@section('body-class', 'auth-page')

@section('content')
<main class="account-page account-page-login">
    <section class="account-intro">
        <span class="account-eyebrow">VERIFIKASI AKUN</span>
        <h1>Satu langkah lagi <span>sebelum mulai.</span></h1>
        <p>Untuk mengajukan event atau mendaftar lomba, verifikasi alamat email akun Anda terlebih dahulu.</p>
    </section>
    <section class="account-card">
        <div class="account-card-heading"><span class="account-eyebrow">TAUTAN VERIFIKASI</span><h2>Periksa email Anda</h2><p>Kami mengirim tautan verifikasi ke <strong>{{ auth()->user()->email }}</strong>. Buka email itu untuk menyelesaikan verifikasi. Tautan hanya berlaku sementara.</p></div>
        @if (session('status'))<div class="account-success" role="status">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="account-errors" role="alert">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('verification.send') }}">@csrf<button class="account-submit" type="submit">Kirim ulang tautan <span aria-hidden="true">→</span></button></form>
        <p class="account-switch"><a href="{{ route('profile.show') }}">Kembali ke profil</a></p>
    </section>
</main>
@endsection
