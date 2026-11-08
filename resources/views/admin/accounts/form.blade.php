@extends('layouts.admin')

@php
    // Satu halaman untuk membuat akun baru dan menyunting akun yang ada.
    // $account bernilai null saat membuat, berisi model saat menyunting; seluruh
    // perbedaan folgt dari nilai itu, termasuk kasus admin utama yang terkunci.
    $account = $account ?? null;
    $isEditing = $account !== null;
    $isPrimaryAdmin = $isEditing && $account->is_primary_admin;
    $value = fn (string $key) => old($key, $isEditing ? $account->{$key} : null);
@endphp

@section('title', $isEditing ? 'Edit Akun — Inlinesia' : 'Buat Akun — Inlinesia')
@section('section', $isEditing ? 'Edit akun' : 'Buat akun')

@section('content')
<div class="page-content account-admin-page">
    <section class="welcome-row">
        <div>
            <div class="eyebrow"><span class="eyebrow-line"></span> PENGGUNA INLINESIA</div>
            <h1>{{ $isEditing ? 'Edit akun' : 'Buat akun pengguna' }}</h1>
            <p class="page-subtitle">{{ $isEditing
                ? 'Perbarui informasi akun ' . $account->name . '.'
                : 'Akun yang dibuat admin dapat memiliki jenis Admin, pengelola klub, individu, atau pemerintah.' }}</p>
        </div>
        <a class="form-back-link" href="{{ route('admin.accounts.index') }}">← Kembali ke daftar akun</a>
    </section>

    <section class="account-edit-card">
        @if ($errors->any())
            <div class="account-errors" role="alert"><strong>Periksa kembali data.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <form class="account-form" method="POST" action="{{ $isEditing ? route('admin.accounts.update', $account) : route('admin.accounts.store') }}">
            @csrf
            @if ($isEditing) @method('PUT') @endif
            <label>Nama lengkap
                <input name="name" type="text" value="{{ $value('name') }}" maxlength="120" required autofocus>
            </label>
            <label>Email
                <input
                    name="email"
                    type="email"
                    value="{{ $value('email') }}"
                    maxlength="255"
                    @required(! $isPrimaryAdmin)
                    @readonly($isPrimaryAdmin)
                >
            </label>
            <label>Nomor WhatsApp
                <input name="phone" type="tel" value="{{ $value('phone') }}" maxlength="40">
            </label>
            <label>Nama klub / instansi
                <input name="organization" type="text" value="{{ $value('organization') }}" maxlength="180">
            </label>
            <label>Alamat / domisili
                <textarea name="address" rows="3" maxlength="500">{{ $value('address') }}</textarea>
            </label>
            <label>Jenis akun
                @if ($isPrimaryAdmin)
                    <input type="text" value="Admin utama (tetap)" readonly>
                @else
                    <select name="role" required>
                        @foreach ($roles as $roleValue => $roleLabel)
                            <option value="{{ $roleValue }}" @selected(old('role', $isEditing ? $account->role : 'individual') === $roleValue)>{{ $roleLabel }}</option>
                        @endforeach
                    </select>
                @endif
            </label>
            <label>{{ $isEditing ? 'Kata sandi baru' : 'Kata sandi sementara' }} <span class="optional-label">{{ $isEditing ? 'kosongkan jika tidak diubah' : '' }}</span>
                <input name="password" type="password" autocomplete="new-password" @required(! $isEditing)>
            </label>
            <label>{{ $isEditing ? 'Ulangi kata sandi baru' : 'Ulangi kata sandi' }}
                <input name="password_confirmation" type="password" autocomplete="new-password" @required(! $isEditing)>
            </label>
            @if ($isPrimaryAdmin)
                <p class="account-admin-note">Email admin utama Inlinesia dikunci ke {{ config('primary-admin.email') }} dan jenis akunnya tidak dapat diubah.</p>
            @elseif ($isEditing)
                <p class="account-admin-note">Jika email diganti, akun akan diminta memverifikasi alamat email yang baru. Kata sandi dikosongkan jika tidak ingin menggantinya.</p>
            @else
                <p class="account-admin-note">Berikan kata sandi sementara ini kepada pemilik akun secara aman. Fitur pengaturan ulang kata sandi melalui email belum diaktifkan.</p>
            @endif
            <button class="account-submit" type="submit">{{ $isEditing ? 'Simpan perubahan' : 'Buat akun' }} <span aria-hidden="true">→</span></button>
        </form>
    </section>
</div>
@endsection
