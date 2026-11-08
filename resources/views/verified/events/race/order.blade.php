@extends('layouts.public')

@section('title', 'Urutan lomba — '.$event->name)
@section('description', 'Susunan grup dan babak perlombaan '.$event->name.' yang sudah dirilis panitia.')
@section('body-class', 'auth-page')

@section('content')
<main class="public-application-page event-race-page event-race-order-page">
    <header class="event-registration-heading">
        <div>
            <span class="account-eyebrow">DAFTAR URUTAN LOMBA</span>
            <h1>Urutan lomba</h1>
            <p>{{ $event->name }} · {{ $event->start_date->format('d/m/Y') }}–{{ $event->end_date->format('d/m/Y') }} · {{ $event->venue }}</p>
        </div>
        <div class="event-registration-heading-actions">
            <a class="account-edit-link" href="{{ route('guest.dashboard') }}">← Dasbor</a>
        </div>
    </header>

    @if (session('status'))
        <div class="public-flash" role="status">{{ session('status') }}</div>
    @endif

    <p class="race-format-note">
        Susunan ini dirilis panitia sebelum hari lomba, jadi kamu bisa melihat siapa lawanmu
        dan di babak keberapa kamu start. Grup yang bertanda
        <span class="race-badge is-running">Sedang berlangsung</span> sedang dilombakan sekarang.
    </p>

    @if ($sections->isEmpty())
        <div class="listing-empty-state">
            <span aria-hidden="true">🏁</span>
            <h3>Belum ada urutan lomba yang dirilis</h3>
            <p>Panitia akan menampilkan urutan lomba di sini setelah kategori disusun dan dirilis.</p>
        </div>
    @else
        @foreach ($sections as $section)
            @php($setting = $section['setting'])
            <section class="race-order-section" aria-label="{{ $section['category'] }} {{ $section['race'] }}">
                <div class="form-section-heading">
                    <span class="form-step">01</span>
                    <div>
                        <h2>{{ $section['category'] }} · {{ $section['race'] }}</h2>
                        <p>
                            {{ $section['groups']->sum(fn ($group) => $group->heats->count()) }} babak ·
                            {{ $section['groups']->count() }} grup ·
                            {{ $section['groups']->sum(fn ($group) => $group->heats->sum(fn ($heat) => $heat->entries->count())) }} atlet ·
                            {{ $setting->stageLabel() }}
                            @if ($section['running'])
                                · <strong>sedang berlangsung</strong>
                            @endif
                        </p>
                    </div>
                </div>

                <div class="race-order-groups">
                    @foreach ($section['groups'] as $group)
                        @php($status = $group->status())
                        <details class="race-heat race-order-group {{ $status === \App\Models\EventRaceGroup::RUNNING ? 'is-running' : '' }}"
                            @if ($status === \App\Models\EventRaceGroup::RUNNING) open @endif>
                            <summary>
                                <span class="race-heat-label">{{ $group->label() }}</span>
                                <span class="race-heat-meta">
                                    {{ $group->stageLabel() }} · {{ $group->heats->sum(fn ($heat) => $heat->entries->count()) }} atlet
                                </span>
                                <span class="race-badge race-group-status is-{{ $status }}">{{ $group->statusLabel() }}</span>
                                <span class="race-heat-toggle" aria-hidden="true">▸</span>
                            </summary>

                            <ol class="race-order-heats">
                                @foreach ($group->heats as $heat)
                                    <li class="race-order-heat">
                                        <strong>{{ $heat->label ?? $group->stageLabel().' '.$heat->number }}</strong>
                                        <ol class="race-order-lanes">
                                            @foreach ($heat->entries->sortBy('lane')->values() as $entry)
                                                <li>
                                                    <span class="race-lane-number">{{ $entry->lane }}</span>
                                                    <strong>{{ $entry->registration?->participant_name ?? 'Peserta tidak ditemukan' }}</strong>
                                                    <span class="race-order-club">{{ trim((string) ($entry->registration?->club_name)) ?: 'Tanpa klub' }}</span>
                                                </li>
                                            @endforeach
                                        </ol>
                                    </li>
                                @endforeach
                            </ol>
                        </details>
                    @endforeach
                </div>
            </section>
        @endforeach
    @endif
</main>
@endsection
