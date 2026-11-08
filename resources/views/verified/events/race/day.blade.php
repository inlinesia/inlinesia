@extends($isAdmin ? 'layouts.admin' : 'layouts.public')

@section('title', 'Hari lomba — '.$event->name)
@section('section', 'Kelola event')
@section('description', 'Jalankan perlombaan dari susunan yang sudah dirilis '.$event->name.'.')
@section('body-class', 'auth-page')

@section('content')
<main class="public-application-page event-race-page event-race-day-page">
    <section class="event-form-heading">
        <div>
            <div class="account-eyebrow">{{ $isAdmin ? 'PENGELOLAAN ADMIN' : 'PANITIA LOMBA' }}</div>
            <h1>Hari lomba</h1>
            <p class="page-subtitle">{{ $event->name }} · {{ $event->start_date->format('d/m/Y') }} · {{ $event->venue }}</p>
        </div>
        <div class="event-form-heading-links">
            <a class="form-back-link" href="{{ route('events.race.order', $event) }}">Urutan lomba →</a>
            <a class="form-back-link" href="{{ route('owner.events.race.format', $event) }}">← Atur perlombaan</a>
        </div>
    </section>

    @if (session('status'))
        <div class="public-flash" role="status">{{ session('status') }}</div>
    @endif

    @if (session('warnings'))
        <div class="race-warnings" role="alert">
            <strong>Perlu dibaca panitia.</strong>
            <ul>@foreach (session('warnings', []) as $warning)<li>{{ $warning }}</li>@endforeach</ul>
        </div>
    @endif

    @if ($errors->any())
        <div class="form-errors" role="alert">
            <strong>Hasil belum tersimpan.</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <nav class="race-category-tabs" aria-label="Pilih kategori">
        @foreach ($categories as $categoryName)
            <a class="race-category-tab {{ $categoryName === $category ? 'is-active' : '' }}"
               href="{{ route('owner.events.race.day', [$event, 'kategori' => $categoryName]) }}">{{ $categoryName }}</a>
        @endforeach
    </nav>

    {{-- Babak dibagi per mata lomba, bukan hanya per kategori: atlet yang
         memilih dua mata lomba tidak bisa balapan bersamaan. --}}
    <nav class="race-category-tabs race-mata-lomba-tabs" aria-label="Pilih mata lomba">
        @foreach ($races as $raceName)
            <a class="race-category-tab {{ $raceName === $race ? 'is-active' : '' }}"
               href="{{ route('owner.events.race.day', [$event, 'kategori' => $category, 'mata_lomba' => $raceName]) }}">{{ $raceName }}</a>
        @endforeach
    </nav>

    <section class="race-format-summary" aria-label="Aturan kategori terpilih">
        <span class="race-format-summary-item"><small>Mata lomba</small><strong>{{ $race }}</strong></span>
        <span class="race-format-summary-item"><small>Per babak</small><strong>{{ $setting->effectiveRunnersPerHeat($entrants) }} orang</strong></span>
        <span class="race-format-summary-item"><small>Per grup</small><strong>{{ $setting->effectiveAthletesPerGroup($entrants) }} atlet</strong></span>
        <span class="race-format-summary-item"><small>Lolos per babak</small><strong>{{ $setting->qualify_count }} orang</strong></span>
        <span class="race-format-summary-item"><small>Penilaian</small><strong>{{ $setting->scoringLabel() }}</strong></span>
        <span class="race-format-summary-item"><small>Tahapan</small><strong>{{ $setting->stageLabel() }}</strong></span>
        <span class="race-format-summary-item"><small>Lawan</small><strong>{{ $setting->opponentLabel() }}</strong></span>
        <span class="race-format-summary-item"><small>Juara</small><strong>{{ $setting->champion_count }} orang</strong></span>
    </section>

    @if (! $setting->isReleased())
        <p class="race-warnings" role="status">
            Urutan lomba untuk {{ $category }} · {{ $race }} belum dirilis, jadi peserta dan klub belum bisa melihatnya.
            <a href="{{ route('owner.events.race.format', $event) }}">Rilis di halaman atur perlombaan</a>.
        </p>
    @endif

    @php($arranged = $groups->filter(fn ($group): bool => $group->heats->isNotEmpty()))

    @if ($arranged->isEmpty())
        <div class="listing-empty-state">
            <span aria-hidden="true">🏁</span>
            <h3>Belum ada urutan lomba</h3>
            <p>
                Susun dulu urutan lomba {{ $category }} · {{ $race }} di halaman atur perlombaan,
                lalu rilis supaya peserta bisa melihat lawannya.
            </p>
            <a class="account-edit-link" href="{{ route('owner.events.race.format', $event) }}">Atur perlombaan <span aria-hidden="true">→</span></a>
        </div>
    @else
        <section class="race-control" aria-label="Cara memakai halaman hari lomba">
            <div class="race-control-copy">
                <span class="form-step">01</span>
                <div>
                    <h2>Jalankan grup satu per satu</h2>
                    <p>
                        {{ $entrants }} peserta layak masuk, tersusun menjadi
                        {{ $arranged->count() }} grup dan {{ $arranged->sum(fn ($group): int => $group->heats->count()) }} babak.
                        Tekan “Siapkan peserta” pada grup yang akan dilombakan; tombol hasil
                        (Menang, Tidak muncul, DNF, DSQ, Ulangi, Tunda) baru muncul setelah itu,
                        dan grupnya ditandai sedang berjalan di halaman urutan lomba.
                    </p>
                </div>
            </div>
            <div class="race-control-actions">
                <a class="form-cancel-button" href="{{ route('events.race.order', $event) }}">Lihat tampilan peserta</a>
            </div>
        </section>

        @foreach ($groups as $group)
            @if ($group->heats->isEmpty())
                @php($meta = $stageMeta[$group->stage] ?? null)
                @continue($meta === null)
                <section class="race-stage-next" aria-label="{{ $meta['label'] }}">
                    <div class="race-control-copy">
                        <span class="form-step">{{ str_pad((string) ($loop->iteration + 1), 2, '0', STR_PAD_LEFT) }}</span>
                        <div>
                            <h2>{{ $meta['label'] }}</h2>
                            <p>
                                @if ($meta['progress']['total'] > 0)
                                    {{ $meta['progress']['done'] }} dari {{ $meta['progress']['total'] }} {{ strtolower($meta['label']) }} sudah selesai.
                                @else
                                    {{ $meta['seats'] }} kursi, diisi dari hasil
                                    {{ strtolower(\App\Models\EventRaceHeat::STAGES[$meta['previous']] ?? 'babak sebelumnya') }}.
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="race-control-actions">
                        <form method="POST" action="{{ route('owner.events.race.advance', $event) }}"
                              data-confirm="Susun {{ strtolower($meta['label']) }} untuk {{ $category }} — {{ $race }}?">
                            @csrf
                            <input type="hidden" name="kategori" value="{{ $category }}">
                            <input type="hidden" name="mata_lomba" value="{{ $race }}">
                            <input type="hidden" name="stage" value="{{ $meta['stage'] }}">
                            <button class="button button-primary" type="submit">Susun {{ strtolower($meta['label']) }} <span aria-hidden="true">→</span></button>
                        </form>
                    </div>
                </section>

                @continue
            @endif

            @php($status = $group->status())
            <section class="race-group {{ $status === \App\Models\EventRaceGroup::RUNNING ? 'is-running' : '' }}" aria-label="{{ $group->label() }} {{ $group->stageLabel() }}">
                <div class="race-control">
                    <div class="race-control-copy">
                        <span class="form-step">{{ str_pad((string) ($loop->iteration + 1), 2, '0', STR_PAD_LEFT) }}</span>
                        <div>
                            <h2>{{ $group->label() }} · {{ $group->stageLabel() }}</h2>
                            <p>
                                {{ $group->heats->sum(fn ($heat): int => $heat->entries->count()) }} atlet dalam
                                {{ $group->heats->count() }} babak.
                                <span class="race-badge race-group-status is-{{ $status }}">{{ $group->statusLabel() }}</span>
                            </p>
                        </div>
                    </div>
                    <div class="race-control-actions">
                        @if (! $group->isPrepared())
                            <form method="POST" action="{{ route('owner.events.race.group.prepare', [$event, $group]) }}">
                                @csrf
                                <button class="button button-primary" type="submit">
                                    Siapkan peserta {{ $group->label() }} <span aria-hidden="true">→</span>
                                </button>
                            </form>
                            <small class="field-hint">Tombol hasil baru muncul setelah grup disiapkan.</small>
                        @else
                            <a class="form-cancel-button" href="{{ route('events.race.order', $event) }}">Lihat tampilan umum</a>
                        @endif
                    </div>
                </div>

                <section class="race-heat-list" aria-label="Babak {{ $group->label() }}">
                    @foreach ($group->heats as $heat)
                        @include('verified.events.race.partials.heat', [
                            'heat' => $heat,
                            'setting' => $setting,
                            'event' => $event,
                            'editable' => $group->isPrepared(),
                        ])
                    @endforeach
                </section>
            </section>
        @endforeach
    @endif

    {{-- Papan hasil --}}
    @if ($standings !== [])
        <section class="race-standings" aria-label="Papan hasil">
            <div class="form-section-heading">
                <span class="form-step">{{ str_pad((string) (count($groups) + 1), 2, '0', STR_PAD_LEFT) }}</span>
                <div>
                    <h2>Papan hasil {{ $category }} — {{ $race }}</h2>
                    <p>
                        Diurutkan dengan sistem {{ strtolower($setting->scoringLabel()) }}.
                        Kolom skor berisi {{ $setting->usesPoints() ? 'total poin' : 'jumlah urutan finis (makin kecil makin baik)' }}.
                        {{ $setting->decidingStage() === 'heat'
                            ? 'Aturan tanpa final: juara diumumkan dari tiap babak, jadi juara 1 bisa lebih dari satu anak.'
                            : 'Juara diambil dari urutan akhir babak '.strtolower(\App\Models\EventRaceHeat::STAGES[$setting->decidingStage()] ?? 'final').'.' }}
                    </p>
                </div>
            </div>

            <div class="race-standings-table-wrap">
                <table class="account-table race-standings-table">
                    <caption class="sr-only">Papan hasil kategori {{ $category }} mata lomba {{ $race }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">Atlet</th>
                            <th scope="col">Klub</th>
                            <th scope="col" class="race-numeric">Babak</th>
                            <th scope="col" class="race-numeric">Menang</th>
                            <th scope="col" class="race-numeric">Podium</th>
                            <th scope="col" class="race-numeric">Skor</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($standings as $row)
                            <tr class="{{ $row['is_champion'] ? 'is-champion' : '' }}">
                                <td class="race-numeric">{{ $row['rank'] }}</td>
                                <td data-label="Atlet"><strong>{{ $row['name'] }}</strong></td>
                                <td data-label="Klub">{{ $row['club'] }}</td>
                                <td data-label="Babak" class="race-numeric">{{ $row['heats_finished'] }}/{{ $row['heats_entered'] }}</td>
                                <td data-label="Menang" class="race-numeric">{{ $row['wins'] }}</td>
                                <td data-label="Podium" class="race-numeric">{{ $row['podiums'] }}</td>
                                <td data-label="Skor" class="race-numeric"><strong>{{ $row['score'] }}</strong></td>
                                <td data-label="Status">
                                    @if ($row['is_champion'])
                                        <span class="race-badge is-champion">
                                            {{ $setting->decidingStage() === 'heat'
                                                ? 'Juara'
                                                : 'Juara '.($row['rank'] <= $setting->champion_count ? $row['rank'] : '') }}
                                        </span>
                                    @elseif ($row['qualified'])
                                        <span class="race-badge is-qualified">Lolos</span>
                                    @else
                                        <span class="race-badge">—</span>
                                    @endif
                                    @if ($row['no_shows'] > 0)
                                        <small class="race-row-note">{{ $row['no_shows'] }}× tidak muncul</small>
                                    @endif
                                    @if ($row['dnfs'] > 0)
                                        <small class="race-row-note">{{ $row['dnfs'] }}× DNF/DSQ</small>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</main>
@endsection
