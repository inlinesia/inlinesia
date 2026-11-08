@extends('layouts.public')

@section('title', 'Daftar peserta — Inlinesia')
@section('description', 'Daftarkan atlet ke direktori Inlinesia tanpa harus ada lomba yang sedang dibuka.')
@section('body-class', 'public-directory')

@section('content')
<main class="profile-page public-directory">
    <header class="event-registration-heading">
        <div>
            <span class="account-eyebrow">DIREKTORI INLINESIA</span>
            <h1>Daftar peserta</h1>
            <p>Isi sekali saja, lalu pakai lagi setiap kali mendaftar lomba. Data ini tidak bergantung pada event mana pun, jadi tetap tersimpan walau sedang tidak ada lomba yang dibuka.</p>
        </div>
        <a class="account-edit-link" href="{{ route('directory.index') }}">← Kembali ke direktori</a>
    </header>

    @if ($errors->any())
        <div class="account-errors" role="alert">
            <strong>Data peserta belum dapat disimpan.</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="account-table-card">
        <form class="account-form athlete-registration-form" method="POST" action="{{ route('directory.athletes.store') }}"
            data-nik-autofill data-nik-autofill-endpoint="{{ route('nik.lookup') }}">
            @csrf

            <label>Kewarganegaraan
                <select name="is_foreign_national" data-foreign-toggle>
                    <option value="0" @selected(! (old('passport_number') || old('nationality')))>Warga negara Indonesia (punya NIK)</option>
                    <option value="1" @selected(old('passport_number') || old('nationality'))>Warga negara lain (punya paspor)</option>
                </select>
            </label>
            <label>
                <span data-identity-label>{{ (old('passport_number') || old('nationality')) ? 'Nomor paspor' : 'NIK' }}</span>
                <input data-identity-input name="{{ (old('passport_number') || old('nationality')) ? 'passport_number' : 'nik' }}" autocomplete="off"
                    value="{{ (old('passport_number') || old('nationality')) ? old('passport_number') : old('nik') }}" maxlength="60" required
                    @unless (old('passport_number') || old('nationality')) inputmode="numeric" minlength="16" maxlength="16" pattern="[0-9]{16}" @endunless>
                <small class="field-hint" data-identity-hint>{{ (old('passport_number') || old('nationality'))
                    ? 'Nomor paspor sesuai dokumen yang Anda miliki. Tanpa NIK, jenis kelamin dan tanggal lahir diisi sendiri di bawah.'
                    : '16 digit. Jenis kelamin dan tanggal lahir akan terisi otomatis dari NIK.' }}</small>
            </label>
            <p class="nik-feedback" data-nik-feedback aria-live="polite" hidden></p>
            <label data-identity-foreign @if (! (old('passport_number') || old('nationality'))) hidden @endif>Negara asal
                <input name="nationality" value="{{ old('nationality') }}" maxlength="80" placeholder="Contoh: Singapura" @if (old('passport_number') || old('nationality')) required @endif>
            </label>

            <label>Nama lengkap
                <input name="name" value="{{ old('name', $viewer->name) }}" maxlength="120" required>
            </label>
            <label>Jenis kelamin
                <select name="gender" required id="directory-gender">
                    <option value="">Pilih jenis kelamin</option>
                    <option value="male" @selected(old('gender') === 'male')>Laki-laki</option>
                    <option value="female" @selected(old('gender') === 'female')>Perempuan</option>
                </select>
                <small class="field-hint" data-identity-local>Terisi otomatis dari NIK dan dikunci.</small>
                <small class="field-hint" data-identity-foreign hidden>Warga negara lain mengisi sendiri, karena tidak ada NIK.</small>
            </label>
            <label>Tanggal lahir
                <input name="date_of_birth" type="date" value="{{ old('date_of_birth') }}" max="{{ today()->format('Y-m-d') }}" required id="directory-dob">
                <small class="field-hint" data-identity-local>Terisi otomatis dari NIK dan dikunci.</small>
                <small class="field-hint" data-identity-foreign hidden>Tanggal lahir ini yang menentukan kategori umur saat mendaftar lomba.</small>
            </label>
            <label>Alamat <span class="optional-label">opsional</span>
                <textarea name="address" rows="2" maxlength="1000">{{ old('address') }}</textarea>
            </label>
            <label>Nomor WhatsApp
                <input name="phone" type="tel" value="{{ old('phone', $viewer->phone) }}" maxlength="40" required>
            </label>
            <label>Nama orang tua / wali <span class="optional-label">opsional</span>
                <input name="guardian_name" value="{{ old('guardian_name') }}" maxlength="120">
            </label>
            <label>Klub
                <select name="club_id">
                    <option value="">Mandiri (tanpa klub)</option>
                    @foreach ($clubs as $club)
                        <option value="{{ $club->id }}" @selected((string) old('club_id') === (string) $club->id)>{{ $club->name }}</option>
                    @endforeach
                </select>
                <small class="field-hint">Belum punya klub? Pilih "Mandiri" dulu, lalu pindahkan ke klub nanti.</small>
            </label>

            <button class="account-submit" type="submit">Simpan peserta <span aria-hidden="true">→</span></button>
        </form>
    </section>
</main>
@endsection