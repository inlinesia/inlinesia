@extends('layouts.admin')

@section('title', 'Kelola Akun — Inlinesia')
@section('section', 'Kelola akun')

@section('content')
<div class="page-content account-admin-page">
    <section class="welcome-row">
        <div>
            <div class="eyebrow"><span class="eyebrow-line"></span> PENGGUNA INLINESIA</div>
            <h1>Kelola akun</h1>
            <p class="page-subtitle">Buat akun untuk admin, pengelola klub, individu, dan instansi pemerintah.</p>
        </div>
        <div class="welcome-row-actions">
            <button class="button button-primary" type="button" data-print-page>Cetak halaman ini</button>
            <a class="button button-primary" href="{{ route('admin.accounts.create') }}">+ Tambah akun</a>
        </div>
    </section>

    @if ($errors->any())
        <div class="account-errors account-page-errors" role="alert">
            <strong>Perubahan belum dapat disimpan.</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @php
        $hasAccountFilter = $filters['q'] !== '' || $filters['role'] !== '' || $filters['status'] !== '';
    @endphp

    <section class="account-table-card">
        <div class="account-table-heading">
            <div>
                <span class="account-eyebrow">DAFTAR PENGGUNA</span>
                <h2>{{ $users->total() }} akun terdaftar</h2>
                @if ($hasAccountFilter)
                    <p class="account-filter-summary">Menampilkan {{ $users->count() }} dari {{ $users->total() }} akun sesuai filter aktif.</p>
                @endif
            </div>
        </div>

        <form method="GET" action="{{ route('admin.accounts.index') }}" class="event-roster-filters account-table-filters">
            <label class="event-roster-filter event-roster-filter-search">
                <span>Cari</span>
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Nama, email, WhatsApp, atau instansi" autocomplete="off">
            </label>

            <label class="event-roster-filter">
                <span>Jenis akun</span>
                <select name="role">
                    <option value="">Semua</option>
                    @foreach ($roles as $roleValue => $roleLabel)
                        <option value="{{ $roleValue }}" @selected($filters['role'] === $roleValue)>{{ $roleLabel }}</option>
                    @endforeach
                </select>
            </label>

            <label class="event-roster-filter">
                <span>Status email</span>
                <select name="status">
                    <option value="">Semua</option>
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="event-roster-filter">
                <span>Urutkan</span>
                <select name="sort">
                    @foreach ($sortOptions as $value => $label)
                        <option value="{{ $value }}" @selected($sort['key'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="event-roster-filter event-roster-filter-narrow">
                <span>Arah</span>
                <select name="direction">
                    <option value="asc" @selected($sort['direction'] === 'asc')>Naik</option>
                    <option value="desc" @selected($sort['direction'] === 'desc')>Turun</option>
                </select>
            </label>

            <div class="event-roster-filter-actions">
                <button class="submission-approve" type="submit">Terapkan</button>
                @if ($hasAccountFilter || $sort['key'] !== 'terbaru' || $sort['direction'] !== 'desc')
                    <a class="event-roster-filter-reset" href="{{ route('admin.accounts.index') }}">Reset</a>
                @endif
            </div>
        </form>

        <div class="account-table-wrap">
            <table class="account-table account-table-compact">
                @php
                    $accountQuery = collect(request()->query())->except(['page']);
                    $accountSortMark = fn (string $key): string => $sort['key'] !== $key ? '↕' : ($sort['direction'] === 'asc' ? '↑' : '↓');
                    $accountSortUrl = fn (string $key): string => route('admin.accounts.index', $accountQuery->except(['sort', 'direction'])
                        ->put('sort', $key)
                        ->put('direction', $sort['key'] === $key && $sort['direction'] === 'asc' ? 'desc' : 'asc')
                        ->all());
                @endphp
                <thead>
                    <tr>
                        <th>
                            <a class="event-roster-sort {{ $sort['key'] === 'nama' ? 'is-active' : '' }}" href="{{ $accountSortUrl('nama') }}">
                                <span>Nama</span>
                                <span class="event-roster-sort-mark" aria-hidden="true">{{ $accountSortMark('nama') }}</span>
                            </a>
                        </th>
                        <th>
                            <a class="event-roster-sort {{ $sort['key'] === 'email' ? 'is-active' : '' }}" href="{{ $accountSortUrl('email') }}">
                                <span>Email</span>
                                <span class="event-roster-sort-mark" aria-hidden="true">{{ $accountSortMark('email') }}</span>
                            </a>
                        </th>
                        <th>WhatsApp</th>
                        <th>Klub / instansi</th>
                        <th>
                            <a class="event-roster-sort {{ $sort['key'] === 'peran' ? 'is-active' : '' }}" href="{{ $accountSortUrl('peran') }}">
                                <span>Jenis akun</span>
                                <span class="event-roster-sort-mark" aria-hidden="true">{{ $accountSortMark('peran') }}</span>
                            </a>
                        </th>
                        <th>Status email</th>
                        <th>Status admin</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $account)
                        <tr>
                            <td><strong>{{ $account->name }}</strong></td>
                            <td>{{ $account->email }}</td>
                            <td class="account-wa-cell">
                                @if ($account->phone)
                                    {{-- Nomor disimpan dalam berbagai bentuk, tetapi wa.me hanya
                                         mengenali angka dengan kode negara tanpa tanda plus. --}}
                                    <a class="account-wa-link" href="https://wa.me/{{ preg_replace('/\D+/', '', preg_replace('/^0/', '62', $account->phone)) }}" target="_blank" rel="noopener">
                                        {{ $account->phone }}
                                    </a>
                                @else
                                    <span class="account-empty-inline">—</span>
                                @endif
                            </td>
                            <td>{{ $account->organization ?: '—' }}</td>
                            <td><span class="profile-role">{{ $account->roleLabel() }}</span></td>
                            <td><span class="email-verification-badge {{ $account->hasVerifiedEmail() ? 'is-verified' : 'is-unverified' }}">{{ $account->hasVerifiedEmail() ? 'Terverifikasi' : 'Belum verifikasi' }}</span></td>
                            <td>{{ $account->is_primary_admin ? 'Admin utama' : '—' }}</td>
                            <td>
                                <div class="account-row-actions">
                                    {{-- Data akun dikirim lewat atribut data-* agar form di dialog
                                         bisa diisi ulang tanpa memuat ulang halaman. --}}
                                    <button class="account-edit-link" type="button"
                                        data-account-modal-open
                                        data-account-id="{{ $account->id }}"
                                        data-account-name="{{ $account->name }}"
                                        data-account-email="{{ $account->email }}"
                                        data-account-phone="{{ $account->phone }}"
                                        data-account-organization="{{ $account->organization }}"
                                        data-account-address="{{ $account->address }}"
                                        data-account-role="{{ $account->role }}"
                                        data-account-primary="{{ $account->is_primary_admin ? '1' : '0' }}">
                                        Edit akun
                                    </button>
                                    @unless ($account->hasVerifiedEmail())
                                        <form method="POST" action="{{ route('admin.accounts.verification.resend', $account) }}" class="account-inline-form">
                                            @csrf
                                            <button class="account-edit-link" type="submit">Kirim email</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.accounts.verification.verify', $account) }}" class="account-inline-form">
                                            @csrf
                                            <button class="account-verify-link" type="submit">Verifikasi manual</button>
                                        </form>
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="account-empty">
                            @if ($hasAccountFilter)
                                Tidak ada akun yang cocok dengan pencarian atau filter Anda.
                            @else
                                Belum ada akun terdaftar.
                            @endif
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="account-pagination">{{ $users->links() }}</div>
    </section>

    {{-- Satu dialog dipakai seluruh baris; isinya diisi dari data-account milik
         tombol yang ditekan, supaya halaman tidak memuat 50 formulir sekaligus. --}}
    <dialog class="event-registration-modal" data-account-modal aria-labelledby="account-modal-title">
        <div class="event-registration-edit-card">
            <header class="event-registration-modal-heading">
                <h2 id="account-modal-title">Edit akun</h2>
                <button class="event-registration-modal-close" type="button" data-account-modal-close aria-label="Tutup dialog">✕</button>
            </header>

            <div class="account-errors" data-account-modal-errors role="alert" hidden>
                <strong>Periksa kembali data.</strong>
                <ul data-account-modal-error-list></ul>
            </div>

            <form class="account-form account-form-modal" method="POST" action="{{ route('admin.accounts.update', '__ID__') }}" data-account-modal-form>
                @csrf
                <input type="hidden" name="_method" value="PUT">
                <input type="hidden" name="account_id" value="">

                <label>Nama lengkap
                    <input name="name" type="text" maxlength="120" required>
                </label>
                <label>Email
                    <input name="email" type="email" maxlength="255" required>
                </label>
                <label>Nomor WhatsApp
                    <input name="phone" type="tel" maxlength="40">
                </label>
                <label>Nama klub / instansi
                    <input name="organization" type="text" maxlength="180">
                </label>
                <label>Alamat / domisili
                    <textarea name="address" rows="2" maxlength="500"></textarea>
                </label>
                <label>Jenis akun
                    <select name="role" data-account-role-select required>
                        @foreach ($roles as $roleValue => $roleLabel)
                            <option value="{{ $roleValue }}">{{ $roleLabel }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Kata sandi baru <span class="optional-label">kosongkan jika tidak diubah</span>
                    <input name="password" type="password" autocomplete="new-password">
                </label>
                <label>Ulangi kata sandi baru
                    <input name="password_confirmation" type="password" autocomplete="new-password">
                </label>

                <p class="account-admin-note" data-account-primary-note hidden>
                    Email admin utama Inlinesia dikunci ke {{ config('primary-admin.email') }} dan jenis akunnya tidak dapat diubah.
                </p>
                <p class="account-admin-note" data-account-modal-hint>
                    Jika email diganti, akun diminta memverifikasi alamat email yang baru. Kata sandi dikosongkan bila tidak ingin diganti.
                </p>
                <button class="account-submit" type="submit">Simpan perubahan <span aria-hidden="true">→</span></button>
            </form>
        </div>
    </dialog>

    @if ($errors->any() && old('account_id'))
        {{-- Validasi gagal setelah dialog tertutup oleh muat ulang, jadi dialog
             dibuka kembali berisi isian yang tadi dikirim. --}}
        <div hidden data-account-modal-restore
            data-account-id="{{ old('account_id') }}"
            data-account-name="{{ old('name') }}"
            data-account-email="{{ old('email') }}"
            data-account-phone="{{ old('phone') }}"
            data-account-organization="{{ old('organization') }}"
            data-account-address="{{ old('address') }}"
            data-account-role="{{ old('role') }}"
            data-account-primary="0"></div>
    @endif
</div>
@endsection
