@extends('layouts.admin')

@section('title', 'Sponsor — Inlinesia')
@section('section', 'Sponsor')

@section('content')
<div class="page-content account-admin-page">
    <section class="welcome-row">
        <div><div class="eyebrow"><span class="eyebrow-line"></span> NOTIFIKASI ADMIN</div><h1>Pengajuan sponsor</h1><p class="page-subtitle">Tinjau iklan sponsor website dan event. Sponsor yang disetujui tampil otomatis sesuai periode.</p></div>
        <a class="form-back-link" href="{{ route('admin.dashboard') }}">← Kembali ke panel</a>
    </section>
    <div class="submission-list">
        @forelse ($sponsors as $sponsor)
            <article class="submission-card">
                <header class="submission-heading">
                    <div>
                        <span class="account-eyebrow">DIAJUKAN {{ $sponsor->created_at->format('d/m/Y H:i') }}</span>
                        <h2>{{ $sponsor->brand_name }}</h2>
                        <p>{{ $sponsor->type === \App\Models\Sponsor::TYPE_WEB ? 'Sponsor website' : 'Sponsor event' }} · {{ $sponsor->priceLabel() }} @if ($sponsor->amount)· total Rp {{ number_format($sponsor->amount, 0, ',', '.') }}@endif</p>
                        <p>Kontak: <strong>{{ $sponsor->contact_name }}</strong> · {{ $sponsor->phone }} @if ($sponsor->email)· {{ $sponsor->email }}@endif</p>
                    </div>
                    <span class="event-admin-status status-{{ $sponsor->status }}">{{ ucfirst($sponsor->status) }}</span>
                </header>
                <div class="submission-facts">
                    <span><strong>Paket</strong>{{ $sponsor->package }}</span>
                    @if ($sponsor->event)<span><strong>Event</strong>{{ $sponsor->event->name }}</span>@endif
                    @if ($sponsor->start_date)<span><strong>Periode</strong>{{ $sponsor->start_date->format('d/m/Y') }}–{{ $sponsor->end_date?->format('d/m/Y') }}</span>@endif
                    <span><strong>Link</strong>{{ $sponsor->website_url }}</span>
                </div>
                @if ($sponsor->logo_path)
                    <details class="submission-details"><summary>Lihat logo/banner</summary><div class="submission-description"><img src="{{ \Illuminate\Support\Facades\Storage::url($sponsor->logo_path) }}" alt="Logo {{ $sponsor->brand_name }}" style="max-width:100%;max-height:180px;"></div></details>
                @endif
                @if ($sponsor->message)
                    <p class="submission-description"><strong>Catatan:</strong> {{ $sponsor->message }}</p>
                @endif
                @if ($sponsor->status === \App\Models\Sponsor::STATUS_PENDING)
                    <div class="submission-actions">
                        <form method="POST" action="{{ route('admin.sponsors.approve', $sponsor) }}">@csrf<button class="submission-approve" type="submit">Setujui &amp; tampilkan</button></form>
                        <form class="submission-reject-form" method="POST" action="{{ route('admin.sponsors.reject', $sponsor) }}">@csrf<button class="submission-reject" type="submit">Tolak pengajuan</button></form>
                    </div>
                @else
                    <p class="submission-description">Ditinjau oleh {{ $sponsor->reviewer?->name ?? 'Admin' }} pada {{ $sponsor->reviewed_at?->format('d/m/Y H:i') }}.</p>
                @endif
            </article>
        @empty
            <div class="listing-empty-state"><h3>Belum ada pengajuan sponsor</h3><p>Pengajuan baru akan muncul di sini untuk ditinjau.</p></div>
        @endforelse
    </div>
</div>
@endsection
