@extends('layouts.public')

@section('title', 'Profil Saya — Inlinesia')
@section('description', 'Kelola profil akun Inlinesia Anda.')
@section('body-class', 'auth-page')

@section('content')
<main class="profile-page">
    <header class="profile-heading">
        <span class="account-eyebrow">AKUN INLINESIA</span>
        <h1>Profil Anda</h1>
        <p>Kelola informasi akun dan data kontak yang digunakan saat mengikuti atau mengelola event.</p>
    </header>

    @if (! $user->hasVerifiedEmail() && $user->role !== 'admin')
        <div class="profile-verification-notice" role="status">
            <div><strong>Email belum diverifikasi</strong><p>Verifikasi email untuk mengajukan event atau mendaftar lomba.</p></div>
            <a class="public-button public-button-dark" href="{{ route('verification.notice') }}">Verifikasi email <span aria-hidden="true">→</span></a>
        </div>
    @endif

    @if ($notifications->isNotEmpty())
        <section class="profile-notifications" aria-label="Notifikasi akun">
            <h2>Notifikasi</h2>
            @foreach ($notifications as $notification)
                <p>{{ $notification->data['message'] ?? 'Ada pembaruan pada akun Anda.' }}</p>
            @endforeach
        </section>
    @endif

    @if ($submissions->isNotEmpty())
        <section class="profile-submissions">
            <h2>Pengajuan event saya</h2>
            @foreach ($submissions as $submission)
                <article><strong>{{ $submission->name }}</strong><span class="profile-submission-status status-{{ $submission->status }}">{{ match ($submission->status) { 'pending' => 'Menunggu persetujuan', 'approved' => 'Disetujui · tampil di daftar lomba', 'rejected' => 'Belum disetujui', default => 'Draf' } }}</span>
                    @if ($submission->decision_note)<p>Catatan Admin: {{ $submission->decision_note }}</p>@endif
                </article>
            @endforeach
        </section>
    @endif

    <div class="profile-grid">
        <aside class="profile-summary">
            <span class="profile-avatar">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($user->name, 0, 1)) }}</span>
            <h2>{{ $user->name }}</h2>
            <p>{{ $user->email }}</p>
            <span class="profile-role">{{ $user->roleLabel() }}</span>
            @if ($user->is_primary_admin)
                <span class="primary-admin-label">Admin utama</span>
            @endif
            @if ($user->role === 'admin')
                <a class="profile-panel-link" href="{{ route('admin.dashboard') }}">Buka panel admin <span aria-hidden="true">↗</span></a>
                <a class="profile-panel-link" href="{{ route('guest.dashboard') }}">Kelola event <span aria-hidden="true">↗</span></a>
            @endif
        </aside>

        <section class="profile-edit-card">
            <div class="account-card-heading">
                <span class="account-eyebrow">INFORMASI AKUN</span>
                <h2>Data profil</h2>
                <p>Email dan jenis akun tidak dapat diubah dari halaman profil. @if ($user->is_primary_admin) Email admin utama Inlinesia bersifat tetap. @endif</p>
            </div>
            @if ($errors->any())
                <div class="account-errors" role="alert">
                    <strong>Periksa kembali data Anda.</strong>
                    <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            <form class="account-form form-grid" method="POST" action="{{ route('profile.update') }}">
                @csrf
                @method('PUT')
                <label class="form-group">
                    <span class="form-label">Nama lengkap</span>
                    <input class="input-field @error('name') is-error @enderror" name="name" type="text" value="{{ old('name', $user->name) }}" maxlength="120" autocomplete="name" required>
                    @error('name')<small class="helper-text is-error">{{ $message }}</small>@enderror
                </label>
                <label class="form-group">
                    <span class="form-label">Email</span>
                    <input class="input-field" type="email" value="{{ $user->email }}" readonly>
                    <small class="helper-text">Email tidak dapat diubah.</small>
                </label>
                <label class="form-group">
                    <span class="form-label">Jenis akun</span>
                    <input class="input-field" type="text" value="{{ $user->roleLabel() }}" readonly>
                </label>
                <label class="form-group">
                    <span class="form-label">Nomor WhatsApp</span>
                    <input class="input-field @error('phone') is-error @enderror" name="phone" type="tel" value="{{ old('phone', $user->phone) }}" maxlength="40" autocomplete="tel">
                    @error('phone')<small class="helper-text is-error">{{ $message }}</small>@enderror
                </label>
                <label class="form-group">
                    <span class="form-label">Nama klub / instansi</span>
                    <input class="input-field @error('organization') is-error @enderror" name="organization" type="text" value="{{ old('organization', $user->organization) }}" maxlength="180">
                    @error('organization')<small class="helper-text is-error">{{ $message }}</small>@enderror
                </label>
                <label class="form-group">
                    <span class="form-label">Alamat / domisili</span>
                    <textarea class="textarea-field @error('address') is-error @enderror" name="address" rows="3" maxlength="500">{{ old('address', $user->address) }}</textarea>
                    @error('address')<small class="helper-text is-error">{{ $message }}</small>@enderror
                </label>
                <fieldset class="password-change-fields">
                    <legend>Ubah kata sandi <span class="optional-label">opsional</span></legend>
                    <p>Kosongkan jika tidak ingin mengubah kata sandi. Kata sandi sementara dari Admin sebaiknya segera diganti.</p>
                    <label class="form-group">
                        <span class="form-label">Kata sandi saat ini</span>
                        <input class="input-field @error('current_password') is-error @enderror" name="current_password" type="password" autocomplete="current-password">
                        @error('current_password')<small class="helper-text is-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="form-group">
                        <span class="form-label">Kata sandi baru</span>
                        <input class="input-field @error('password') is-error @enderror" name="password" type="password" autocomplete="new-password">
                        @error('password')<small class="helper-text is-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="form-group">
                        <span class="form-label">Ulangi kata sandi baru</span>
                        <input class="input-field" name="password_confirmation" type="password" autocomplete="new-password">
                    </label>
                </fieldset>
                <button class="account-submit" type="submit">Simpan perubahan <span aria-hidden="true">→</span></button>
            </form>
        </section>
    </div>
</main>
@endsection
