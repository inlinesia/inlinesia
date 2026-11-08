@php
    /** @var \App\Models\EventRaceHeat $heat */
    /** @var \App\Models\EventCategoryRaceSetting $setting */
    /** @var \App\Models\Event $event */
    $entries = $heat->entries->sortBy('lane')->values();
    $maxPlace = max(1, $entries->count());

    /**
     * Grup yang belum disiapkan panitia hanya menampilkan susunan, tanpa
     * tombol hasil. Tombol Menang / Tidak muncul / DNF / DSQ / Ulangi / Tunda
     * baru muncul setelah "Siapkan peserta" ditekan.
     */
    $editable = $editable ?? true;

    /* Berapa orang dari babak ini yang lanjut, dan ke tahap mana. */
    $quota = match ($heat->stage) {
        'semi_final' => min($setting->finalists, $entries->count()),
        'final' => min($setting->champion_count, $entries->count()),
        default => min($setting->qualify_count, $entries->count()),
    };
    $nextStage = $setting->stageAfter($heat->stage);
    $destination = $nextStage === null
        ? 'diumumkan sebagai juara'
        : 'lolos ke '.strtolower(\App\Models\EventRaceHeat::STAGES[$nextStage] ?? 'babak berikutnya');
    $isDeciding = $heat->stage === $setting->decidingStage();
    $note = 'orang pertama sampai dengan urutan ke-'.$quota.' dari babak ini '.$destination.'.';
@endphp

{{-- Grup yang sedang disiapkan langsung membuka seluruh babaknya, jadi
     panitia tidak perlu mengklik satu per satu saat balapan berjalan. --}}
<details class="race-heat" data-race-heat @if ($heat->status === 'done' || $editable) open @endif>
    <summary>
        <span class="race-heat-label">{{ $heat->label ?? $heat->stageLabel().' '.$heat->number }}</span>
        <span class="race-heat-meta">
            {{ $entries->count() }} atlet · {{ $heat->statusLabel() }}
            @if ($heat->status === 'done')
                · selesai {{ $heat->finished_at?->format('H:i') }}
            @endif
        </span>
        <span class="race-heat-toggle" aria-hidden="true">▸</span>
    </summary>

    @if ($editable)
        <form class="race-heat-form" method="POST" action="{{ route('owner.events.race.heat.update', [$event, $heat]) }}">
            @csrf
            @method('PATCH')

            <div class="race-heat-table-wrap">
                <table class="account-table race-heat-table">
                    <caption class="sr-only">Hasil {{ $heat->label }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">Lane</th>
                            <th scope="col">Atlet</th>
                            <th scope="col">Klub</th>
                            <th scope="col">Hasil</th>
                            <th scope="col" class="race-numeric">Urutan</th>
                            <th scope="col">Waktu</th>
                            <th scope="col">Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($entries as $entry)
                            @php($registration = $entry->registration)
                            @php($field = 'entries['.$entry->id.']')
                            @php($isFinished = $entry->status === \App\Models\EventRaceEntry::STATUS_FINISHED)
                            <tr class="race-lane-row" data-race-lane
                                data-status-select="{{ $field }}[status]"
                                data-place-input="{{ $field }}[place]"
                                data-heat-size="{{ $maxPlace }}">
                                <td data-label="Lane" class="race-numeric"><span class="race-lane-number">{{ $entry->lane }}</span></td>
                                <td data-label="Atlet">
                                    <strong>{{ $registration?->participant_name ?? 'Peserta tidak ditemukan' }}</strong>
                                    @if ($entry->is_qualified)<span class="race-badge is-qualified">Lolos</span>@endif
                                    {{-- Pada aturan "langsung menang tanpa final" tiap
                                         babak memilih podiumnya sendiri, jadi juara
                                         ditandai langsung di sini. --}}
                                    @if ($isDeciding && $isFinished && $entry->place !== null && $entry->place <= $setting->champion_count)
                                        <span class="race-badge is-champion">Juara {{ $entry->place }}</span>
                                    @endif
                                </td>
                                <td data-label="Klub">{{ trim((string) ($registration?->club_name)) ?: 'Tanpa klub' }}</td>
                                <td data-label="Hasil">
                                    <select name="{{ $field }}[status]" aria-label="Hasil {{ $registration?->participant_name }}">
                                        @foreach (\App\Models\EventRaceEntry::STATUSES as $value => $label)
                                            <option value="{{ $value }}" @selected($entry->status === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <div class="race-quick-actions">
                                        <button type="button" class="race-quick is-win" data-race-quick="win">Menang</button>
                                        <button type="button" class="race-quick is-no-show" data-race-quick="no_show">Tidak muncul</button>
                                        <button type="button" class="race-quick is-dnf" data-race-quick="dnf">DNF</button>
                                        <button type="button" class="race-quick is-dsq" data-race-quick="dsq">DSQ</button>
                                        <button type="button" class="race-quick is-clear" data-race-quick="pending">Ulangi</button>
                                        {{-- Tombol Tunda mengganti atlet di lane ini dengan
                                             atlet berikutnya dalam antrean, jadi formulirnya
                                             terpisah dan memakai referensi form eksternal. --}}
                                        <button type="submit" class="race-quick is-postpone" form="race-postpone-{{ $entry->id }}">Tunda</button>
                                    </div>
                                </td>
                                <td data-label="Urutan" class="race-numeric">
                                    <input name="{{ $field }}[place]" type="number" min="1" max="{{ $maxPlace }}" inputmode="numeric"
                                        value="{{ $isFinished ? $entry->place : '' }}" placeholder="kosong"
                                        aria-label="Urutan finis {{ $registration?->participant_name }}">
                                </td>
                                <td data-label="Waktu">
                                    <input name="{{ $field }}[time]" value="{{ \App\Models\EventRaceEntry::formatTime($entry->time_ms) }}"
                                        placeholder="1:23.45" maxlength="20" inputmode="numeric"
                                        aria-label="Waktu finis {{ $registration?->participant_name }}">
                                </td>
                                <td data-label="Catatan">
                                    <input name="{{ $field }}[note]" value="{{ $entry->note }}" maxlength="255" placeholder="opsional"
                                        aria-label="Catatan {{ $registration?->participant_name }}">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="race-heat-actions">
                <p>
                    Tombol cepat mengisi status tanpa perlu mengetik. Setelah semua lane terisi,
                    {{ $note }}
                </p>
                <button class="account-submit" type="submit">Simpan hasil babak</button>
            </div>
        </form>

        {{-- Atlet yang ditunda dipindahkan ke urutan paling akhir kategori dan
             mata lomba yang sama; seluruh atlet di belakangnya maju satu per satu. --}}
        @foreach ($entries as $entry)
            <form id="race-postpone-{{ $entry->id }}" method="POST"
                action="{{ route('owner.events.race.entry.postpone', [$event, $entry]) }}"
                data-confirm="Tunda {{ $entry->registration?->participant_name ?? 'atlet ini' }}? Dia akan balapan di urutan paling akhir {{ $heat->category }} · {{ $heat->race }}, dan atlet lain maju mengisi tempatnya.">
                @csrf
            </form>
        @endforeach
    @else
        <div class="race-heat-table-wrap">
            <table class="account-table race-heat-table">
                <caption class="sr-only">Susunan {{ $heat->label }}</caption>
                <thead>
                    <tr>
                        <th scope="col">Lane</th>
                        <th scope="col">Atlet</th>
                        <th scope="col">Klub</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($entries as $entry)
                        @php($registration = $entry->registration)
                        @php($isFinished = $entry->status === \App\Models\EventRaceEntry::STATUS_FINISHED)
                        <tr>
                            <td data-label="Lane" class="race-numeric"><span class="race-lane-number">{{ $entry->lane }}</span></td>
                            <td data-label="Atlet">
                                <strong>{{ $registration?->participant_name ?? 'Peserta tidak ditemukan' }}</strong>
                                @if ($entry->is_qualified)<span class="race-badge is-qualified">Lolos</span>@endif
                                @if ($isDeciding && $isFinished && $entry->place !== null && $entry->place <= $setting->champion_count)
                                    <span class="race-badge is-champion">Juara {{ $entry->place }}</span>
                                @endif
                            </td>
                            <td data-label="Klub">{{ trim((string) ($registration?->club_name)) ?: 'Tanpa klub' }}</td>
                            <td data-label="Status">
                                <span class="race-badge">{{ $entry->statusLabel() }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</details>
