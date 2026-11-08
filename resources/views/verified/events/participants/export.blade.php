@extends('layouts.public')

@section('title', 'Seluruh daftar peserta — '.$event->name)
@section('description', 'Daftar lengkap peserta kejuaraan '.$event->name.', siap disimpan sebagai PDF.')
@section('body-class', 'auth-page event-roster-export')

@section('content')
{{-- Halaman khusus simpan-PDF. Lebar kertasnya dihitung dari lebar tabel di
     app.js, jadi tidak perlu dirapatkan ke A4 dan tidak ada kolom yang
     terpotong kertas. --}}
<main class="profile-page event-registration-management" data-export-page>
    <header class="event-registration-heading">
        <div>
            <span class="account-eyebrow">SIMPAN SEBAGAI PDF</span>
            <h1>{{ $canManage ? 'Seluruh daftar peserta' : 'Daftar peserta' }}</h1>
            <p>
                {{ $event->name }} · {{ $registrations->count() }} atlet ·
                {{ $event->start_date->format('d/m/Y') }}–{{ $event->end_date->format('d/m/Y') }} · {{ $event->venue }}
            </p>
        </div>
        <div class="event-registration-heading-actions no-print">
            <button class="account-edit-link" type="button" data-print-page>Simpan sebagai PDF</button>
            <a class="account-edit-link" href="{{ route('events.registrations.index', $event) }}">← Kembali ke daftar</a>
        </div>
    </header>

    <section class="account-table-card event-roster-table-card" aria-label="Seluruh daftar peserta">
        <div class="account-table-heading">
            <span class="account-eyebrow">DAFTAR LENGKAP</span>
            <h2>{{ $registrations->count() }} atlet</h2>
            <p class="no-print">
                Seluruh peserta ikut terbuka di halaman ini, tanpa halaman 1, 2, 3.
                Ukuran kertasnya mengikuti lebar tabel, jadi kolom tidak pernah terpotong.
            </p>
        </div>

        @if ($canManage && $categories !== [])
            {{-- Hanya kategori aktif yang ikut tercetak atau disimpan sebagai PDF. --}}
            <div class="event-roster-category-toggles no-print" role="group" aria-label="Kategori yang ikut dicetak">
                <span class="event-roster-category-toggles-label">Kategori dicetak</span>
                <div class="event-roster-category-toggles-list">
                    @foreach ($categories as $category)
                        <button
                            class="event-roster-category-toggle is-active"
                            type="button"
                            aria-pressed="true"
                            data-category-toggle="{{ $category }}"
                        >{{ $category !== '' ? $category : 'Belum berkategori' }}</button>
                    @endforeach
                </div>
                <small>Hanya kategori aktif yang ikut dicetak atau di-export PDF.</small>
            </div>
        @endif

        <div class="event-roster-table-wrap">
            <table class="event-roster-table">
                <thead>
                    <tr>
                        <th scope="col">No.</th>
                        <th scope="col">Nama</th>
                        @if ($canManage)
                            <th scope="col">Nama panggilan</th>
                            <th scope="col">Email</th>
                            <th scope="col">NIK / paspor</th>
                            <th scope="col">Tanggal lahir</th>
                            <th scope="col">Gender</th>
                            <th scope="col">Asal daerah</th>
                            <th scope="col">Kontak</th>
                            <th scope="col">Wali</th>
                        @endif
                        <th scope="col">Klub</th>
                        <th scope="col">Kategori</th>
                        <th scope="col">Mata lomba</th>
                        @if ($canManage)
                            <th scope="col">Tahun lahir</th>
                            <th scope="col">Pembayaran</th>
                        @endif
                        <th scope="col">Status</th>
                        @if ($canManage)
                            <th scope="col">Biaya</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @php($previousCategory = null)
                    @foreach ($registrations as $registration)
                        @if ($registration->category !== $previousCategory)
                            @php($previousCategory = $registration->category)
                            <tr class="event-roster-group-row" data-category="{{ $registration->category }}">
                                <th scope="colgroup" colspan="{{ $canManage ? 17 : 6 }}">
                                    {{ $registration->category ?: 'Belum berkategori' }}
                                    <span class="event-roster-group-meta">
                                        {{ $registrations->where('category', $registration->category)->count() }} peserta
                                    </span>
                                </th>
                            </tr>
                        @endif
                        <tr data-category="{{ $registration->category }}">
                            <td data-label="No." class="event-roster-number">{{ $loop->iteration }}</td>
                            {{-- Baris nama hanya memuat nama lengkap; nama
                                 panggilan dan email punya kolomnya sendiri. --}}
                            <th scope="row" data-label="Nama">
                                <span class="event-roster-name">
                                    <strong>{{ $registration->participant_name }}</strong>
                                </span>
                            </th>
                            @if ($canManage)
                                <td data-label="Nama panggilan">{{ $registration->nickname ?: '—' }}</td>
                                <td data-label="Email">{{ $registration->user->email }}</td>
                                <td data-label="NIK / paspor" class="event-roster-identity">
                                    @if ($registration->isForeignNational())
                                        <span class="event-roster-identity-tag">Paspor</span>
                                    @endif
                                    {{ $registration->maskedIdentity() }}
                                    @if ($registration->nationality)
                                        <small>{{ $registration->nationality }}</small>
                                    @endif
                                </td>
                                <td data-label="Tanggal lahir">{{ $registration->date_of_birth?->format('d/m/Y') ?: '—' }}</td>
                                <td data-label="Gender">{{ $registration->gender === 'male' ? 'Laki-laki' : ($registration->gender === 'female' ? 'Perempuan' : '—') }}</td>
                                <td data-label="Asal daerah">{{ $registration->address ?: '—' }}</td>
                                <td data-label="Kontak" class="event-roster-contact-cell">
                                    @if ($registration->whatsapp)
                                        <a class="event-roster-wa-link" href="https://wa.me/{{ preg_replace('/\D+/', '', preg_replace('/^0/', '62', $registration->whatsapp)) }}" target="_blank" rel="noopener">
                                            {{ $registration->whatsapp }}
                                        </a>
                                    @else
                                        <span class="event-roster-fee-empty">—</span>
                                    @endif
                                </td>
                                <td data-label="Wali">{{ $registration->guardian_name ?: '—' }}</td>
                            @endif
                            <td data-label="Klub">{{ $registration->club_name ?: 'Mandiri' }}</td>
                            <td data-label="Kategori">{{ $registration->category ?: '—' }}</td>
                            <td data-label="Mata lomba">{{ implode(', ', $registration->races) }}</td>
                            @if ($canManage)
                                <td data-label="Tahun lahir">{{ $registration->birth_year }}</td>
                                <td data-label="Pembayaran" class="event-roster-payment-cell">
                                    <span class="event-payment-status payment-{{ $registration->payment_status }}">
                                        {{ match ($registration->payment_status) {
                                            'pending' => 'Menunggu konfirmasi',
                                            'paid' => $event->is_free ? 'Dikonfirmasi' : 'Terbayar',
                                            default => 'Belum bayar',
                                        } }}
                                    </span>
                                    @if ($registration->payment_channel)
                                        <small>{{ ucfirst($registration->payment_channel) }}</small>
                                    @endif
                                </td>
                            @endif
                            <td data-label="Status">
                                <span class="guest-registration-status status-{{ $registration->status }}">
                                    {{ match ($registration->status) {
                                        'pending' => 'Menunggu',
                                        'approved' => 'Diterima',
                                        'rejected' => 'Perlu ditinjau',
                                        default => ucfirst($registration->status),
                                    } }}
                                </span>
                            </td>
                            @if ($canManage)
                                <td data-label="Biaya" class="event-roster-fee-cell">
                                    {{ $registration->fee_total === null ? '—' : \App\Services\CategoryFeeCalculator::rupiah($registration->fee_total) }}
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</main>
@endsection
