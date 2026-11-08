@extends('layouts.admin')

@section('title', 'Backup & restore — Inlinesia')
@section('section', 'Backup & restore')

@section('content')
    <div class="page-content">
        <section class="welcome-row">
            <div>
                <div class="eyebrow"><span class="eyebrow-line"></span> PENGELOLAAN DATA</div>
                <h1>Backup &amp; restore</h1>
                <p class="page-subtitle">Unduh arsip data Inlinesia atau pulihkan aplikasi dari backup resmi yang dibuat di instalasi ini.</p>
            </div>
        </section>

        @if ($errors->any())
            <div class="admin-flash is-error" role="alert">
                <strong>Permintaan belum dapat diproses.</strong>
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="data-management-grid">
            <section class="panel data-management-card">
                <div class="panel-heading">
                    <div>
                        <span class="section-kicker">SIMPAN SALINAN</span>
                        <h2>Backup data</h2>
                    </div>
                </div>
                <p>File mencakup akun, event, pendaftaran, hasil dan pengaturan lomba, notifikasi, catatan keuangan, serta file bukti pembayaran.</p>
                <p class="data-management-warning">Arsip memuat informasi pribadi peserta dan hash kata sandi akun. Simpan di lokasi aman dan jangan bagikan secara publik.</p>
                <form method="POST" action="{{ route('admin.data.backup.download') }}">
                    @csrf
                    <button class="button button-primary" type="submit">Unduh backup JSON</button>
                </form>
            </section>

            <section class="panel data-management-card is-danger">
                <div class="panel-heading">
                    <div>
                        <span class="section-kicker">GANTI DATA SAAT INI</span>
                        <h2>Restore backup</h2>
                    </div>
                </div>
                <p>Pemulihan mengganti seluruh data aplikasi dengan isi backup, termasuk akun. Backup harus dibuat oleh instalasi ini dan memuat akun admin aktif serta Admin utama.</p>
                <p class="data-management-warning">Sebelum melanjutkan, unduh backup kondisi saat ini. Sesi login admin yang sedang digunakan tetap aktif; sesi login lain dan token reset kata sandi akan dibersihkan.</p>
                <form method="POST" action="{{ route('admin.data.restore') }}" enctype="multipart/form-data" data-confirm="Pemulihan akan mengganti seluruh data aplikasi dengan isi backup. Apakah Anda yakin?">
                    @csrf
                    <label class="data-management-file-field" for="backup-file">File backup Inlinesia (.json)</label>
                    <input id="backup-file" type="file" name="backup" accept=".json,application/json" required>
                    @error('backup')<small class="data-management-field-error">{{ $message }}</small>@enderror
                    <label class="data-management-confirm">
                        <input type="checkbox" name="confirmed" value="1" required>
                        <span>Saya memahami bahwa restore akan mengganti data aplikasi saat ini.</span>
                    </label>
                    @error('confirmed')<small class="data-management-field-error">{{ $message }}</small>@enderror
                    <button class="button button-danger" type="submit">Pulihkan backup</button>
                </form>
            </section>
        </div>

        <p class="data-management-back-link"><a href="{{ route('admin.dashboard') }}">← Kembali ke panel admin</a></p>
    </div>
@endsection
