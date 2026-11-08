@extends('layouts.admin')

@section('title', 'Pengajuan Event — Inlinesia')
@section('section', 'Pengajuan event')

@section('content')
<div class="page-content account-admin-page">
    <section class="welcome-row">
        <div><div class="eyebrow"><span class="eyebrow-line"></span> NOTIFIKASI ADMIN</div><h1>Pengajuan event</h1><p class="page-subtitle">Tinjau pengajuan event. Pengelolaan event berlangsung di dasbor bersama.</p></div>
        <a class="form-back-link" href="{{ route('admin.dashboard') }}">← Kembali ke panel</a>
    </section>
    <div class="submission-list">
        @forelse ($events as $event)
            <article class="submission-card">
                <header class="submission-heading">
                    <div>
                        <span class="account-eyebrow">DIAJUKAN {{ $event->created_at->format('d/m/Y H:i') }}</span>
                        <h2>{{ $event->name }}</h2>
                        <p>Pengaju: <strong>{{ $event->submitter->name }}</strong> · {{ $event->submitter->email }} @if ($event->submitter->phone)· {{ $event->submitter->phone }}@endif</p>
                    </div>
                    <span class="submission-pending">Menunggu</span>
                </header>
                <div class="submission-facts">
                    <span><strong>Tanggal</strong>{{ $event->start_date->format('d/m/Y') }}–{{ $event->end_date->format('d/m/Y') }}</span>
                    <span><strong>Skala</strong>{{ $event->scale }}</span>
                    <span><strong>Venue</strong>{{ $event->venue }} · {{ $event->venue_address }}</span>
                    <span><strong>Batas pendaftaran</strong>{{ $event->registration_deadline?->format('d/m/Y') ?? 'Belum ditentukan' }}</span>
                </div>
                <details class="submission-details"><summary>Lihat seluruh informasi pengajuan</summary>
                    <div class="submission-description">
                        <p><strong>Peraturan:</strong> {{ $event->regulations }}</p><p><strong>Official:</strong> {{ $event->officials }}</p><p><strong>Kondisi venue:</strong> {{ $event->venue_conditions }}</p>
                        <p><strong>Kategori:</strong> {{ implode(', ', $event->categories) }}</p><p><strong>Mata lomba:</strong> {{ implode(', ', $event->races) }}</p>
                        <p><strong>Pendaftaran:</strong> {{ $event->registration_info }}</p><p><strong>Biaya:</strong> {{ $event->fees }}</p><p><strong>Hadiah:</strong> {{ $event->awards }}</p><p><strong>Force majeure:</strong> {{ $event->force_majeure }}</p>
                        <p><strong>Jadwal:</strong></p><ul>@foreach ($event->schedule as $item)<li>{{ $item }}</li>@endforeach</ul>
                    </div>
                </details>
                <div class="submission-actions">
                    <form method="POST" action="{{ route('admin.event-submissions.approve', $event) }}">@csrf @method('PATCH')<button class="submission-approve" type="submit">Setujui &amp; tampilkan</button></form>
                    <form class="submission-reject-form" method="POST" action="{{ route('admin.event-submissions.reject', $event) }}">@csrf @method('PATCH')<label class="visually-hidden" for="note-{{ $event->id }}">Catatan penolakan</label><input id="note-{{ $event->id }}" name="decision_note" maxlength="2000" placeholder="Catatan jika ditolak" required><button class="submission-reject" type="submit">Tolak pengajuan</button></form>
                </div>
            </article>
        @empty
            <div class="listing-empty-state"><h3>Tidak ada pengajuan yang menunggu</h3><p>Pengajuan baru akan muncul di sini untuk ditinjau.</p></div>
        @endforelse
    </div>
    <div class="account-pagination">{{ $events->links() }}</div>
</div>
@endsection
