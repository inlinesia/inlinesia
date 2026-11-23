@extends('layouts.admin')

@section('title', 'Backup & restore — Inlinesia')
@section('section', 'Backup & restore')

@section('content')
    <div class="page-content">
        <section class="welcome-row">
            <div>
                <div class="eyebrow"><span class="eyebrow-line"></span> PENGELOLAAN DATA</div>
                <h1>Backup &amp; restore</h1>
                <p class="page-subtitle">Backup database dibuat langsung di server dan disimpan di <code>storage/app/private/backups</code>. Pilih salah satu backup di bawah untuk memulihkan data.</p>
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
                        <h2>Buat backup baru</h2>
                    </div>
                </div>
                <p>Dump SQL seluruh tabel database server dibuat bersama salinan file bukti pembayaran. Tabel sesi dan antrian tidak disertakan agar admin tetap masuk setelah pemulihan.</p>
                <p class="data-management-warning">Arsip memuat informasi pribadi peserta dan hash kata sandi akun. Jangan bagikan backup ini secara publik.</p>
                <form method="POST" action="{{ route('admin.data.backup.create') }}">
                    @csrf
                    <button class="button button-primary" type="submit">Buat backup di server</button>
                </form>
            </section>

            <section class="panel data-management-card is-danger">
                <div class="panel-heading">
                    <div>
                        <span class="section-kicker">DAFTAR BACKUP</span>
                        <h2>Backup tersimpan ({{ count($backups) }})</h2>
                    </div>
                </div>

                @if ($backups === [])
                    <p>Belum ada backup di server. Buat backup baru terlebih dahulu.</p>
                @else
                    <div class="backup-list">
                        @foreach ($backups as $backup)
                            <article class="backup-item">
                                <div class="backup-item-meta">
                                    <strong>{{ $backup['name'] }}</strong>
                                    <small>
                                        {{ $backup['created_at'] ?? 'Waktu tidak diketahui' }}
                                        · {{ number_format($backup['size'] / 1024, 1) }} KB
                                        · {{ count($backup['tables']) }} tabel
                                        · {{ $backup['files'] }} file bukti
                                    </small>
                                </div>

                                <div class="backup-item-actions">
                                    <form method="POST" action="{{ route('admin.data.restore') }}" data-confirm="Pemulihan akan mengganti seluruh data aplikasi dengan backup {{ $backup['name'] }}. Apakah Anda yakin?">
                                        @csrf
                                        <input type="hidden" name="backup" value="{{ $backup['name'] }}">
                                        <label class="data-management-confirm">
                                            <input type="checkbox" name="confirmed" value="1" required>
                                            <span>Saya memahami data saat ini akan diganti.</span>
                                        </label>
                                        <button class="button button-danger" type="submit">Pulihkan</button>
                                    </form>

                                    <form method="POST" action="{{ route('admin.data.backup.destroy') }}" data-confirm="Hapus backup {{ $backup['name'] }} dari server?">
                                        @csrf
                                        <input type="hidden" name="backup" value="{{ $backup['name'] }}">
                                        <label class="data-management-confirm">
                                            <input type="checkbox" name="confirmed" value="1" required>
                                            <span>Saya ingin menghapus backup ini.</span>
                                        </label>
                                        <button class="button" type="submit">Hapus</button>
                                    </form>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif

                <p class="data-management-warning">Pemulihan mengganti seluruh data aplikasi dengan isi backup. Sesi login admin yang sedang digunakan tetap aktif; sesi lain dan token reset kata sandi dibersihkan.</p>
            </section>
        </div>

        <p class="data-management-back-link"><a href="{{ route('admin.dashboard') }}">← Kembali ke panel admin</a></p>
    </div>
@endsection
