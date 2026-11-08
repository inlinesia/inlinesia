@extends('layouts.public')

@section('title', 'Ubah data club — Inlinesia')
@section('description', 'Perbarui data dan kontak klub di direktori Inlinesia.')
@section('body-class', 'public-directory')

@section('content')
<main class="profile-page public-directory">
    <header class="event-registration-heading">
        <div>
            <span class="account-eyebrow">DIREKTORI INLINESIA</span>
            <h1>Ubah {{ $club->name }}</h1>
            <p>Kontak yang Anda isi di halaman ini bisa dilihat siapa pun yang membuka direktori. Kosongkan kolom kontak bila klub belum mau dihubungi lewat sini.</p>
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
        <form method="POST" action="{{ route('directory.clubs.update', $club) }}" class="account-form">
            @csrf
            @method('PATCH')

            <label>Nama club
                <input name="name" value="{{ old('name', $club->name) }}" maxlength="180" required>
            </label>
            <label>Kota <span class="optional-label">opsional</span>
                <input name="city" value="{{ old('city', $club->city) }}" maxlength="120" placeholder="Contoh: Kediri">
            </label>
            <label>Nama pengelola / kontak <span class="optional-label">opsional</span>
                <input name="contact_name" value="{{ old('contact_name', $club->contact_name) }}" maxlength="120" placeholder="Contoh: Coach Anwar">
            </label>
            <label>Nomor WhatsApp <span class="optional-label">opsional</span>
                <input name="contact_phone" type="tel" value="{{ old('contact_phone', $club->contact_phone) }}" maxlength="40" placeholder="Contoh: 081234567890">
            </label>
            <label>Email <span class="optional-label">opsional</span>
                <input name="contact_email" type="email" value="{{ old('contact_email', $club->contact_email) }}" maxlength="180">
            </label>
            <label>Ceritakan singkat klub <span class="optional-label">opsional</span>
                <textarea name="description" rows="3" maxlength="1000">{{ old('description', $club->description) }}</textarea>
            </label>

            <button class="account-submit" type="submit">Simpan perubahan <span aria-hidden="true">→</span></button>
        </form>
    </section>
</main>
@endsection
