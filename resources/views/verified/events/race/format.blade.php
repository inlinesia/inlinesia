@extends($isAdmin ? 'layouts.admin' : 'layouts.public')

@section('title', 'Atur perlombaan — '.$event->name)
@section('section', 'Kelola event')
@section('description', 'Atur jumlah peserta per babak, sistem penilaian, dan format tahapan '.$event->name.'.')
@section('body-class', 'auth-page')

@section('content')
<main class="public-application-page event-race-page">
    <section class="event-form-heading">
        <div>
            <div class="account-eyebrow">{{ $isAdmin ? 'PENGELOLAAN ADMIN' : 'PENGATURAN PENGELOLA' }}</div>
            <h1>Atur perlombaan</h1>
            <p class="page-subtitle">{{ $event->name }} · Aturan ini dipakai saat membuat babak dan menghitung pemenang.</p>
        </div>
        <div class="event-form-heading-links">
            @php($firstCard = $cards->first())
            <a class="form-back-link" href="{{ route('events.race.order', $event) }}">Urutan lomba →</a>
            <a class="form-back-link" href="{{ route('owner.events.race.day', array_filter([
                    $event,
                    'kategori' => $firstCard['category'] ?? null,
                    'mata_lomba' => $firstCard['race'] ?? null,
                ])) }}">Hari lomba →</a>
            <a class="form-back-link" href="{{ route('owner.events.categories.edit', $event) }}">← Atur kategori</a>
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
            <strong>Pengaturan perlombaan belum tersimpan.</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if ($cards->isEmpty())
        <div class="listing-empty-state">
            <span aria-hidden="true">⚙</span>
            <h3>Kategori belum disiapkan</h3>
            <p>Aturan perlombaan mengikuti kategori, jadi siapkan kategorinya terlebih dahulu.</p>
            <a class="account-edit-link" href="{{ route('owner.events.categories.edit', $event) }}">Atur kategori <span aria-hidden="true">→</span></a>
        </div>
    @else
        <script>
            window.RACE_STAGE_HINTS = @js(\App\Models\EventCategoryRaceSetting::STAGE_HINTS);
        </script>

        @if ($hasHeats)
            <p class="race-format-note">
                Urutan lomba sudah pernah disusun. Mengubah angka di bawah tidak menyusun ulang
                susunan yang sudah ada — tekan “Susun ulang” pada kartu yang bersangkutan.
            </p>
        @endif

        <form class="event-form" method="POST" action="{{ route('owner.events.race.format.update', $event) }}" data-race-format-form>
            @csrf
            @method('PUT')

            <section class="form-section">
                <div class="form-section-heading">
                    <span class="form-step">01</span>
                    <div>
                        <h2>Cara satu kategori dan mata lomba dilombakan</h2>
                        <p>
                            Tiap mata lomba punya aturan sendiri, karena jumlah peserta dan cara
                            membagi babaknya bisa berbeda. Tombol “Terapkan ke mata lomba yang sama”
                            menyalin aturan satu kartu ke kategori lain yang memakai mata lomba serupa.
                        </p>
                    </div>
                </div>

                <div class="race-format-list">
                    @foreach ($cards as $index => $card)
                        @php($setting = $card['setting'])
                        <fieldset class="race-format-card" data-race-format-card
                            data-category="{{ $card['category'] }}" data-race="{{ $card['race'] }}"
                            data-entrants="{{ $card['entrants'] }}"
                            data-scoring="{{ $setting->scoring_system }}" data-stages="{{ $setting->stages }}"
                            data-champion-count="{{ $setting->champion_count ?? 3 }}">
                            <input type="hidden" name="race_settings[{{ $index }}][category]" value="{{ $card['category'] }}">
                            <input type="hidden" name="race_settings[{{ $index }}][race]" value="{{ $card['race'] }}">

                            <legend>
                                {{ $card['category'] }} · {{ $card['race'] }}
                                <span class="race-format-card-meta">
                                    {{ $card['entrants'] }} peserta masuk
                                    @if ($card['group_count'] > 0)
                                        · {{ $card['group_count'] }} grup · {{ $card['heat_count'] }} babak sudah disusun
                                    @elseif ($card['projected_groups'] > 0)
                                        · ±{{ $card['projected_groups'] }} grup · ±{{ $card['projected_heats'] }} babak
                                    @endif
                                </span>
                            </legend>

                            <div class="form-fields">
                                <div class="form-field form-field-full" data-race-grouping-field>
                                    <label>Gruping babak <span>*</span></label>
                                    <div class="race-mode-options">
                                        @foreach (\App\Models\EventCategoryRaceSetting::GROUPING_MODES as $mode => $modeLabel)
                                            <label class="race-mode-option">
                                                <input type="radio" name="race_settings[{{ $index }}][grouping_mode]" value="{{ $mode }}"
                                                    @checked(old("race_settings.{$index}.grouping_mode", $setting->grouping_mode ?? 'manual') === $mode)
                                                    data-race-grouping>
                                                <span>{{ $modeLabel }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    <small class="field-hint" data-race-grouping-hint>
                                        @if (($setting->grouping_mode ?? 'manual') === 'auto')
                                            Angka di bawah dihitung dari {{ $card['entrants'] }} peserta yang masuk:
                                            {{ $card['auto']['runners_per_heat'] }} anak per babak.
                                        @else
                                            Semua angka di bawah diisi sendiri. Pilih “Otomatis” supaya sistem
                                            menghitungnya dari jumlah peserta yang benar-benar masuk.
                                        @endif
                                    </small>
                                </div>

                                <div class="form-field">
                                    <label for="race-{{ $index }}-runners">Berapa orang dalam 1 babak <span>*</span></label>
                                    <input id="race-{{ $index }}-runners" name="race_settings[{{ $index }}][runners_per_heat]" type="number" min="2" max="24"
                                        value="{{ old("race_settings.{$index}.runners_per_heat", $setting->runners_per_heat ?? 6) }}" required data-race-runners>
                                    <small class="field-hint">Jumlah atlet yang start bersamaan. Sisanya masuk babak berikutnya.</small>
                                </div>

                                <div class="form-field">
                                    <label for="race-{{ $index }}-group">Berapa atlet dalam 1 grup <span>*</span></label>
                                    <input id="race-{{ $index }}-group" name="race_settings[{{ $index }}][athletes_per_group]" type="number" min="1" max="500"
                                        value="{{ old("race_settings.{$index}.athletes_per_group", $setting->athletes_per_group ?? 15) }}" required data-race-group-size>
                                    <small class="field-hint">
                                        Grup adalah blok yang disiapkan panitia sekaligus pada hari lomba, sekaligus yang dilihat peserta.
                                        Contoh: 15 atlet per grup dengan 3 atlet per babak menjadi 5 babak per grup.
                                    </small>
                                </div>

                                <div class="form-field">
                                    <label for="race-{{ $index }}-qualify">Berapa orang yang lolos dari tiap babak <span>*</span></label>
                                    <input id="race-{{ $index }}-qualify" name="race_settings[{{ $index }}][qualify_count]" type="number" min="1" max="24"
                                        value="{{ old("race_settings.{$index}.qualify_count", $setting->qualify_count ?? 3) }}" required data-race-qualify>
                                    <small class="field-hint">Pemenang tiap babak otomatis lolos ke tahap berikutnya.</small>
                                </div>

                                <div class="form-field form-field-full">
                                    <label for="race-{{ $index }}-scoring">Sistem penilaian <span>*</span></label>
                                    <select id="race-{{ $index }}-scoring" name="race_settings[{{ $index }}][scoring_system]" data-race-scoring required>
                                        @foreach (\App\Models\EventCategoryRaceSetting::SCORING_SYSTEMS as $value => $label)
                                            <option value="{{ $value }}" @selected(old("race_settings.{$index}.scoring_system", $setting->scoring_system) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <small class="field-hint" data-race-scoring-hint>
                                        @if (($setting->scoring_system ?? 'qualification') === 'points')
                                            Tiap finis diberi poin sesuai urutan (misal 6,5,4,3,2,1) dan pemenang ditentukan dari total poin.
                                        @else
                                            Kualifikasi: yang lolos ke tahap berikutnya adalah pemenang tiap babak, dan papan hasil memakai jumlah urutan finis.
                                        @endif
                                    </small>
                                </div>

                                <div class="form-field form-field-full">
                                    <label for="race-{{ $index }}-stages">Format tahap <span>*</span></label>
                                    <select id="race-{{ $index }}-stages" name="race_settings[{{ $index }}][stages]" data-race-stages required>
                                        @foreach (\App\Models\EventCategoryRaceSetting::STAGES as $value => $label)
                                            <option value="{{ $value }}" @selected(old("race_settings.{$index}.stages", $setting->stages) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <small class="field-hint" data-race-stages-hint>{{ $setting->stageHint() }}</small>
                                </div>

                                <div class="form-field race-field-semi" data-race-semi-field
                                     @if (! in_array($setting->stages ?? 'direct_final', ['semi_final', 'qualification_round'], true)) hidden @endif>
                                    <label for="race-{{ $index }}-semi">Berapa orang di semifinal <span>*</span></label>
                                    <input id="race-{{ $index }}-semi" name="race_settings[{{ $index }}][semi_finalists]" type="number" min="2" max="48"
                                        value="{{ old("race_settings.{$index}.semi_finalists", $setting->semi_finalists ?: 4) }}" data-race-semi>
                                    <small class="field-hint">Hanya dipakai kalau format tahap memakai semifinal.</small>
                                </div>

                                <div class="form-field">
                                    <label for="race-{{ $index }}-finalists">Berapa orang di final <span>*</span></label>
                                    <input id="race-{{ $index }}-finalists" name="race_settings[{{ $index }}][finalists]" type="number" min="2" max="48"
                                        value="{{ old("race_settings.{$index}.finalists", $setting->finalists ?? 8) }}" required data-race-finalists>
                                </div>

                                <div class="form-field">
                                    <label for="race-{{ $index }}-champion">Berapa juara yang diumumkan <span>*</span></label>
                                    <input id="race-{{ $index }}-champion" name="race_settings[{{ $index }}][champion_count]" type="number" min="1" max="12"
                                        value="{{ old("race_settings.{$index}.champion_count", $setting->champion_count ?? 3) }}" required data-race-champion>
                                    <small class="field-hint">Peringkat pertama sampai jumlah ini di babak penentu yang ditandai sebagai juara.</small>
                                </div>

                                <div class="form-field form-field-full">
                                    <label for="race-{{ $index }}-opponent">Lawan dari mana <span>*</span></label>
                                    <select id="race-{{ $index }}-opponent" name="race_settings[{{ $index }}][opponent_scope]" required>
                                        @foreach (\App\Models\EventCategoryRaceSetting::OPPONENT_SCOPES as $value => $label)
                                            <option value="{{ $value }}" @selected(old("race_settings.{$index}.opponent_scope", $setting->opponent_scope) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <small class="field-hint" data-race-opponent-hint>
                                        @switch($setting->opponent_scope ?? 'mixed')
                                            @case('cross_club')
                                                Tiap babak harus berisi atlet dari minimal dua klub. Kalau tidak memungkinkan, sistem memberi tahu saat babak dibuat.
                                                @break
                                            @case('same_club')
                                                Satu babak hanya berisi satu klub, jadi klub yang sama tidak saling bertanding sejak awal.
                                                @break
                                            @default
                                                Bebas bercampur antar klub; pembagian babak membuat tiap klub tersebar merata.
                                        @endswitch
                                    </small>
                                </div>
                            </div>

                            <div class="race-format-links">
                                <button class="account-edit-link" type="button" data-apply-race>
                                    Terapkan ke mata lomba yang sama <span aria-hidden="true">→</span>
                                </button>
                                <span class="race-apply-status" data-apply-status role="status"></span>
                                <a class="account-edit-link" href="{{ route('owner.events.race.day', [
                                        $event,
                                        'kategori' => $card['category'],
                                        'mata_lomba' => $card['race'],
                                    ]) }}">Buka hari lomba <span aria-hidden="true">→</span></a>
                            </div>
                            <p class="race-format-note">
                                “Terapkan ke mata lomba yang sama” menyalin aturan kartu ini ke kategori lain
                                yang memakai mata lomba {{ $card['race'] }}. Tekan “Simpan pengaturan” setelahnya
                                supaya salinannya tersimpan.
                            </p>
                        </fieldset>
                    @endforeach
                </div>
            </section>

            <div class="event-form-actions">
                <p>Aturan ini dipakai saat tombol “Susun urutan lomba” ditekan di bawah. Menyimpan aturan tidak mengubah susunan yang sudah ada — susun ulang dulu kalau mau angka baru dipakai.</p>
                <div>
                    <a class="form-cancel-button" href="{{ route('guest.dashboard') }}">Batal</a>
                    <button class="button button-primary" type="submit">Simpan pengaturan <span aria-hidden="true">→</span></button>
                </div>
            </div>
        </form>

        {{-- Urutan lomba disusun dan dirilis per kategori + mata lomba, jadi
             peserta bisa melihat siapa lawannya jauh sebelum hari lomba. --}}
        <section class="form-section race-plan-section">
            <div class="form-section-heading">
                <span class="form-step">02</span>
                <div>
                    <h2>Susunan urutan lomba</h2>
                    <p>
                        Bagi peserta menjadi grup, lalu tiap grup dipecah menjadi beberapa babak.
                        Setelah disusun, tekan “Rilis kategori ini” supaya susunannya tampil di
                        halaman urutan lomba untuk semua peserta dan klub yang masuk.
                    </p>
                </div>
            </div>

            <div class="race-format-list">
                @foreach ($cards as $card)
                    @php($setting = $card['setting'])
                    @php($arrangedGroups = $card['groups']->filter(fn ($group): bool => $group->heats->isNotEmpty()))
                    <article class="race-format-card race-plan-card">
                        <h3>
                            {{ $card['category'] }} · {{ $card['race'] }}
                            <span class="race-format-card-meta">
                                @if ($setting->isReleased())
                                    <span class="race-badge is-qualified">Sudah dirilis</span>
                                @else
                                    <span class="race-badge">Belum dirilis</span>
                                @endif
                            </span>
                        </h3>

                        @if ($arrangedGroups->isEmpty())
                            <p class="race-format-note">
                                Belum ada susunan. {{ $card['entrants'] }} peserta akan terbagi menjadi
                                ±{{ $card['projected_groups'] }} grup berisi ±{{ $card['projected_heats'] }} babak.
                            </p>
                            <div class="race-format-links">
                                <form method="POST" action="{{ route('owner.events.race.heats.build', $event) }}"
                                      data-confirm="Susun urutan lomba {{ $card['category'] }} — {{ $card['race'] }} dari {{ $card['entrants'] }} peserta?">
                                    @csrf
                                    <input type="hidden" name="kategori" value="{{ $card['category'] }}">
                                    <input type="hidden" name="mata_lomba" value="{{ $card['race'] }}">
                                    <button class="button button-primary" type="submit">Susun urutan lomba <span aria-hidden="true">→</span></button>
                                </form>
                            </div>
                        @else
                            <ol class="race-plan-groups">
                                @foreach ($arrangedGroups as $group)
                                    <li>
                                        <details class="race-heat">
                                            <summary>
                                                <span class="race-heat-label">{{ $group->label() }} · {{ $group->stageLabel() }}</span>
                                                <span class="race-heat-meta">
                                                    {{ $group->heats->sum(fn ($heat): int => $heat->entries->count()) }} atlet ·
                                                    {{ $group->heats->count() }} babak
                                                </span>
                                                <span class="race-heat-toggle" aria-hidden="true">▸</span>
                                            </summary>
                                            <ol class="race-plan-heats">
                                                @foreach ($group->heats as $heat)
                                                    <li>
                                                        <strong>{{ $heat->label ?? $group->stageLabel().' '.$heat->number }}</strong>
                                                        <span>
                                                            {{ $heat->entries->sortBy('lane')->map(fn ($entry): string => trim((string) ($entry->registration?->participant_name)))->filter()->implode(', ') }}
                                                        </span>
                                                    </li>
                                                @endforeach
                                            </ol>
                                        </details>
                                    </li>
                                @endforeach
                            </ol>

                            <div class="race-format-links">
                                @if ($setting->isReleased())
                                    <form method="POST" action="{{ route('owner.events.race.unrelease', $event) }}">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="kategori" value="{{ $card['category'] }}">
                                        <input type="hidden" name="mata_lomba" value="{{ $card['race'] }}">
                                        <button class="account-edit-link" type="submit">Tarik rilis <span aria-hidden="true">→</span></button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('owner.events.race.release', $event) }}">
                                        @csrf
                                        <input type="hidden" name="kategori" value="{{ $card['category'] }}">
                                        <input type="hidden" name="mata_lomba" value="{{ $card['race'] }}">
                                        <button class="account-edit-link" type="submit">Rilis kategori ini <span aria-hidden="true">→</span></button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('owner.events.race.heats.build', $event) }}"
                                      data-confirm="Susun ulang urutan lomba {{ $card['category'] }} — {{ $card['race'] }}?">
                                    @csrf
                                    <input type="hidden" name="kategori" value="{{ $card['category'] }}">
                                    <input type="hidden" name="mata_lomba" value="{{ $card['race'] }}">
                                    <button class="account-edit-link" type="submit">Susun ulang <span aria-hidden="true">→</span></button>
                                </form>
                                <form method="POST" action="{{ route('owner.events.race.heats.reset', $event) }}"
                                      data-confirm="Hapus seluruh susunan dan hasil {{ $card['category'] }} — {{ $card['race'] }}?">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="kategori" value="{{ $card['category'] }}">
                                    <input type="hidden" name="mata_lomba" value="{{ $card['race'] }}">
                                    <button class="form-cancel-button" type="submit">Reset susunan</button>
                                </form>
                                <a class="account-edit-link" href="{{ route('owner.events.race.day', [
                                        $event,
                                        'kategori' => $card['category'],
                                        'mata_lomba' => $card['race'],
                                    ]) }}">Buka hari lomba <span aria-hidden="true">→</span></a>
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    @endif
</main>
@endsection
