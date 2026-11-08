@extends('layouts.public')

@section('title', 'Daftar club — Inlinesia')
@section('description', 'Daftarkan klub inline skate ke direktori Inlinesia agar bisa ditemukan calon atlet.')
@section('body-class', 'public-directory')

@section('content')
<main class="profile-page public-directory">
    <header class="event-registration-heading">
        <div>
            <span class="account-eyebrow">DIREKTORI INLINESIA</span>
            <h1>Daftar club</h1>
            <p>Klub yang terdaftar akan muncul di direktori bersama kontak pengelolanya, sehingga orang yang ingin bergabung atau berpindah klub bisa menghubungi langsung.</p>
        </div>
        <a class="account-edit-link" href="{{ route('directory.index') }}">← Kembali ke direktori</a>
    </header>

    @if ($errors->any())
        <div class="account-errors" role="alert">
            <strong>Data klub belum dapat disimpan.</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="account-table-card">
        <form method="POST" action="{{ route('directory.clubs.store') }}" class="account-form">
            @csrf

            <label>Nama club
                <input name="name" value="{{ old('name') }}" maxlength="180" required>
            </label>
            <label>Kota <span class="optional-label">opsional</span>
                <input name="city" value="{{ old('city') }}" maxlength="120" placeholder="Contoh: Kediri">
            </label>
            <label>Nama pengeloga / kontak
                <input name="contact_name" value="{{ old('contact_name', optional($viewer)->name) }}" maxlength="120" required>
            </label>
            <label>Nomor WhatsApp
                <input name="contact_phone" type="tel" value="{{ old('contact_phone', optional($viewer)->phone) }}" maxlength="40" required>
            </label>
            <label>Email <span class="optional-label">opsional</span>
                <input name="contact_email" type="email" value="{{ old('contact_email', optional($viewer)->email) }}" maxlength="180">
            </label>
            <label>Ceritakan singkat klub <span class="optional-label">opsional</span>
                <textarea name="description" rows="3" maxlength="1000">{{ old('description') }}</textarea>
            </label>

            <button class="account-submit" type="submit">Simpan club <span aria-hidden="true">→</span></button>
        </form>
    </section>
</main>
@endsection