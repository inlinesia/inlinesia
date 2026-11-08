@extends($isAdmin ? 'layouts.admin' : 'layouts.public')

@section('title', 'Atur Kategori — '.$event->name)
@section('section', 'Kelola event')
@section('description', 'Atur metode kelayakan tanggal lahir, tahun lahir, atau usia atlet untuk '.$event->name.'.')
@section('body-class', 'auth-page')

@section('content')
<main class="public-application-page event-category-page">
    <section class="event-form-heading">
        <div>
            <div class="account-eyebrow">{{ $isAdmin ? 'PENGELOLAAN ADMIN' : 'PENGATURAN PENGELOLA' }}</div>
            <h1>Atur kategori kejuaraan</h1>
            <p class="page-subtitle">{{ $event->name }} · Aturan ini menentukan kategori atlet saat pendaftaran.</p>
        </div>
        <a class="form-back-link" href="{{ route('owner.events.race.format', $event) }}">Atur perlombaan →</a>
        <a class="form-back-link" href="{{ route('guest.dashboard') }}">← Kembali ke kelola event</a>
    </section>

    @if ($errors->any())
        <div class="form-errors" role="alert">
            <strong>Periksa kembali aturan kategori.</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form class="event-form" method="POST" action="{{ route('owner.events.categories.update', $event) }}" data-category-rules-form>
        @csrf
        @method('PUT')
        <section class="form-section">
            <div class="form-section-heading">
                <span class="form-step">01</span>
                <div><h2>Kategori dan kelayakan atlet</h2><p>Pilih tepat satu metode kelayakan: rentang tanggal lahir, tahun lahir, atau usia pada hari pertama kejuaraan.</p></div>
            </div>
            <div class="category-rule-list" data-category-rule-list>
                @foreach (old('category_rules', $rules) as $index => $rule)
                    @php($eligibilityTypes = $rule['eligibility_types'] ?? ['birth_date'])
                    @php($baseCategoryName = old("category_rules.{$index}.base_name", $rule['base_name'] ?? ''))
                    @php($genderGroup = old("category_rules.{$index}.gender_group", $rule['gender_group'] ?? 'male'))
                    <fieldset class="category-rule-card" data-category-rule data-category-index="{{ $index }}" data-outside-obligation="{{ ! empty($rule['outside_obligation']) ? 'true' : 'false' }}">
                        <legend>Kategori<span data-category-type-label>@if (! empty($rule['outside_obligation'])) tambahan di luar kewajiban @endif</span> <span data-category-number>{{ $loop->iteration }}</span></legend>
                        <button class="category-rule-remove" type="button" data-remove-category aria-label="Hapus kategori {{ $loop->iteration }}">Hapus</button>
                        <input type="hidden" name="category_rules[{{ $index }}][outside_obligation]" value="{{ ! empty($rule['outside_obligation']) ? '1' : '0' }}" data-outside-obligation-input>
                        <div class="form-fields">
                            <div class="category-name-controls">
                                <div class="form-field">
                                    <label for="category-{{ $index }}-base-name">Nama kategori <span>*</span></label>
                                    <select id="category-{{ $index }}-base-name" name="category_rules[{{ $index }}][base_name]" data-category-base-name required>
                                        @foreach ($categoryNames as $categoryName)
                                            <option value="{{ $categoryName }}" @selected($baseCategoryName === $categoryName)>{{ $categoryName }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-field">
                                    <label for="category-{{ $index }}-gender-group">Gender yang diizinkan <span>*</span></label>
                                    <select id="category-{{ $index }}-gender-group" name="category_rules[{{ $index }}][gender_group]" data-category-gender-group required>
                                        <option value="male" @selected($genderGroup === 'male')>Putra</option>
                                        <option value="female" @selected($genderGroup === 'female')>Putri</option>
                                        <option value="mixed" @selected($genderGroup === 'mixed')>Campuran</option>
                                    </select>
                                    <small class="field-hint">Campuran mengizinkan putra dan putri; komposisi tim bebas.</small>
                                </div>
                                <div class="form-field">
                                    <label for="category-{{ $index }}-name-preview">Nama kategori akhir</label>
                                    <input id="category-{{ $index }}-name-preview" type="text" value="{{ $baseCategoryName }} {{ ['male' => 'Putra', 'female' => 'Putri', 'mixed' => 'Campuran'][$genderGroup] ?? 'Putra' }}{{ ! empty($rule['outside_obligation']) ? ' — di luar kewajiban' : '' }}" data-category-name-preview readonly>
                                </div>
                            </div>
                            <fieldset class="eligibility-methods form-field-full" data-eligibility-picker>
                                <legend>Metode kelayakan <span>* pilih satu</span></legend>
                                <div class="eligibility-method-list">
                                    <label class="eligibility-choice">
                                        <input type="checkbox" name="category_rules[{{ $index }}][eligibility_types][]" value="birth_date" data-eligibility-type="birth_date" @checked(in_array('birth_date', $eligibilityTypes, true))>
                                        <span><strong>Rentang tanggal lahir</strong><small>Tentukan tanggal lahir paling awal dan paling akhir.</small></span>
                                    </label>
                                    <label class="eligibility-choice">
                                        <input type="checkbox" name="category_rules[{{ $index }}][eligibility_types][]" value="birth_year" data-eligibility-type="birth_year" @checked(in_array('birth_year', $eligibilityTypes, true))>
                                        <span><strong>Tahun lahir</strong><small>Pilih satu atau beberapa tahun kelahiran.</small></span>
                                    </label>
                                    <label class="eligibility-choice">
                                        <input type="checkbox" name="category_rules[{{ $index }}][eligibility_types][]" value="age" data-eligibility-type="age" @checked(in_array('age', $eligibilityTypes, true))>
                                        <span><strong>Rentang usia</strong><small>Usia dihitung pada hari pertama kejuaraan.</small></span>
                                    </label>
                                </div>
                            </fieldset>
                            <div class="eligibility-fields form-fields" data-eligibility-panel="birth_date" @if (! in_array('birth_date', $eligibilityTypes, true)) hidden @endif>
                                <div class="form-field">
                                    <label for="category-{{ $index }}-from">Tanggal lahir dari <span>*</span></label>
                                    <input id="category-{{ $index }}-from" name="category_rules[{{ $index }}][birth_date_from]" type="date" data-required-when-active value="{{ old("category_rules.{$index}.birth_date_from", $rule['birth_date_from'] ?? '') }}" max="{{ today()->format('Y-m-d') }}">
                                </div>
                                <div class="form-field">
                                    <label for="category-{{ $index }}-to">Tanggal lahir sampai <span>*</span></label>
                                    <input id="category-{{ $index }}-to" name="category_rules[{{ $index }}][birth_date_to]" type="date" data-required-when-active value="{{ old("category_rules.{$index}.birth_date_to", $rule['birth_date_to'] ?? '') }}" max="{{ today()->format('Y-m-d') }}">
                                </div>
                            </div>
                            <fieldset class="category-rule-options category-birth-years eligibility-fields" data-eligibility-panel="birth_year" @if (! in_array('birth_year', $eligibilityTypes, true)) hidden @endif>
                                <legend>Tahun lahir <span>*</span></legend>
                                <div class="birth-year-picker">
                                    <select id="category-{{ $index }}-birth-year" data-birth-year-select data-input-name="category_rules[{{ $index }}][birth_years][]">
                                        <option value="">Pilih tahun lahir</option>
                                        @foreach ($birthYears as $year)
                                            <option value="{{ $year }}">{{ $year }}</option>
                                        @endforeach
                                    </select>
                                    <button class="category-rule-add-year" type="button" data-add-birth-year>Tambah tahun</button>
                                </div>
                                <div class="birth-year-list" data-birth-year-list>
                                    @foreach ($rule['birth_years'] ?? [] as $year)
                                        <span class="birth-year-chip">
                                            <input type="hidden" name="category_rules[{{ $index }}][birth_years][]" value="{{ $year }}">
                                            <span>{{ $year }}</span>
                                            <button type="button" data-remove-birth-year aria-label="Hapus tahun {{ $year }}">×</button>
                                        </span>
                                    @endforeach
                                </div>
                                <small class="field-hint">Pilih minimal satu tahun lahir. Rentang tanggal dan usia tidak digunakan.</small>
                            </fieldset>
                            <div class="eligibility-fields form-fields" data-eligibility-panel="age" @if (! in_array('age', $eligibilityTypes, true)) hidden @endif>
                                <div class="form-field">
                                    <label for="category-{{ $index }}-minimum-age">Usia minimal <span class="optional-label">opsional</span></label>
                                    <input id="category-{{ $index }}-minimum-age" name="category_rules[{{ $index }}][minimum_age]" type="number" min="0" max="120" value="{{ old("category_rules.{$index}.minimum_age", $rule['minimum_age'] ?? '') }}">
                                    <small class="field-hint">Kosongkan jika tidak ada batas minimal.</small>
                                </div>
                                <div class="form-field">
                                    <label for="category-{{ $index }}-maximum-age">Usia maksimal <span class="optional-label">opsional</span></label>
                                    <input id="category-{{ $index }}-maximum-age" name="category_rules[{{ $index }}][maximum_age]" type="number" min="0" max="120" value="{{ old("category_rules.{$index}.maximum_age", $rule['maximum_age'] ?? '') }}">
                                    <small class="field-hint">Isi usia minimal atau maksimal.</small>
                                </div>
                            </div>
                            <fieldset class="category-rule-options">
                                <legend>Mata lomba yang diizinkan <span>*</span></legend>
                                @foreach ($event->races as $race)
                                    <label><input type="checkbox" name="category_rules[{{ $index }}][races][]" value="{{ $race }}" data-allowed-race @checked(in_array($race, $rule['races'] ?? [], true))><span>{{ $race }}</span></label>
                                @endforeach
                            </fieldset>
                            @unless (! empty($rule['outside_obligation']))
                                <fieldset class="category-rule-options category-outside-race-picker" data-outside-race-picker hidden>
                                    <legend>Mata lomba di luar paket</legend>
                                    <p class="field-hint">Pilih mata lomba lain untuk membuat kategori terpisah di luar kewajiban paket. Setiap mata lomba dikenai biaya luar kategori.</p>
                                    @foreach ($event->races as $race)
                                        <label><input type="checkbox" value="{{ $race }}" data-outside-race-option><span>{{ $race }}</span></label>
                                    @endforeach
                                </fieldset>
                            @endunless
                            <fieldset class="category-pricing-fields form-field-full">
                                <legend>Wajib ikut &amp; harga tiket</legend>
                                @if ($event->is_free)
                                    <p class="field-hint">Event ini gratis, jadi semua harga otomatis Rp 0.</p>
                                @endif
                                <div class="form-fields">
                                    <div class="form-field">
                                        <label for="category-{{ $index }}-required-races">Wajib ikut berapa mata lomba <span>*</span></label>
                                        <input id="category-{{ $index }}-required-races" name="category_rules[{{ $index }}][required_races]" type="number" min="1" max="{{ max(1, count($event->races)) }}" value="{{ old("category_rules.{$index}.required_races", $rule['required_races'] ?? 1) }}" data-required-races required>
                                        <small class="field-hint">Jumlah mata lomba minimal yang wajib diikuti atlet pada kategori ini.</small>
                                    </div>
                                    <div class="form-field form-field-full category-quota-fields">
                                        <span class="field-group-label">Batas peserta</span>
                                        <div class="form-fields">
                                            <div class="form-field">
                                                <label for="category-{{ $index }}-max-registrants">Maksimal calon pendaftar <span class="optional-label">opsional</span></label>
                                                <input id="category-{{ $index }}-max-registrants" name="category_rules[{{ $index }}][max_registrants]" type="number" min="1" step="1" value="{{ old("category_rules.{$index}.max_registrants", $rule['max_registrants'] ?? '') }}" placeholder="Tanpa batas" data-max-registrants>
                                                <small class="field-hint">Kosongkan bila kategori ini tidak dibatasi. Batas dihitung dari atlet yang sudah lunas dan sudah diterima.</small>
                                                @error("category_rules.{$index}.max_registrants")<small class="field-error">{{ $message }}</small>@enderror
                                            </div>
                                            <div class="form-field">
                                                <label for="category-{{ $index }}-registration-override">Status pendaftaran kategori</label>
                                                <select id="category-{{ $index }}-registration-override" name="category_rules[{{ $index }}][registration_override]" data-registration-override>
                                                    @foreach (['auto' => 'Otomatis mengikuti batas peserta', 'open' => 'Dibuka paksa', 'closed' => 'Ditutup paksa'] as $value => $label)
                                                        <option value="{{ $value }}" @selected(old("category_rules.{$index}.registration_override", $rule['registration_override'] ?? 'auto') === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                                <small class="field-hint">Pilih "Ditutup paksa" untuk menutup kategori tanpa batas peserta, atau "Dibuka paksa" untuk membukanya kembali walau sudah penuh.</small>
                                            </div>
                                        </div>
                                        @if (($rule['name'] ?? null) && isset($categoryStatuses[$rule['name']]))
                                            <p class="field-hint category-quota-status">{{ $categoryStatuses[$rule['name']] }}</p>
                                        @endif
                                    </div>
                                    <div class="form-field form-field-full category-base-pricing-range">
                                        <span class="field-group-label">Masa berlaku tarif utama</span>
                                        <div class="form-fields">
                                            <div class="form-field">
                                                <label for="category-{{ $index }}-base-starts-on">Berlaku mulai <span class="optional-label">opsional</span></label>
                                                <input id="category-{{ $index }}-base-starts-on" name="category_rules[{{ $index }}][base_starts_on]" type="date" value="{{ old("category_rules.{$index}.base_starts_on", $rule['base_starts_on'] ?? '') }}" data-base-starts-on>
                                            </div>
                                            <div class="form-field">
                                                <label for="category-{{ $index }}-base-ends-on">Berlaku sampai <span class="optional-label">opsional</span></label>
                                                <input id="category-{{ $index }}-base-ends-on" name="category_rules[{{ $index }}][base_ends_on]" type="date" value="{{ old("category_rules.{$index}.base_ends_on", $rule['base_ends_on'] ?? '') }}" data-base-ends-on @error("category_rules.{$index}.base_ends_on") aria-invalid="true" @enderror>
                                            </div>
                                        </div>
                                        <small class="field-hint">Kosongkan keduanya bila tarif utama berlaku tanpa batas tanggal. Bila diisi, tarif utama hanya dipakai di antara kedua tanggal itu dan tidak boleh tumpang-tindih dengan rentang gelombang di bawah.</small>
                                        @error("category_rules.{$index}.base_starts_on")<small class="field-error">{{ $message }}</small>@enderror
                                        @error("category_rules.{$index}.base_ends_on")<small class="field-error">{{ $message }}</small>@enderror
                                    </div>
                                    <div class="form-field">
                                        <label for="category-{{ $index }}-package-fee">Harga paket <span class="optional-label">Rp</span></label>
                                        <input id="category-{{ $index }}-package-fee" name="category_rules[{{ $index }}][package_fee]" type="number" min="0" step="1000" value="{{ old("category_rules.{$index}.package_fee", $rule['package_fee'] ?? 0) }}" data-package-fee @disabled($event->is_free)>
                                        <small class="field-hint">Harga untuk wajib ikut di atas, sudah termasuk dalam satu transaksi.</small>
                                    </div>
                                    <div class="form-field">
                                        <label for="category-{{ $index }}-extra-race-fee">Biaya per mata lomba tambahan <span class="optional-label">Rp, opsional</span></label>
                                        <input id="category-{{ $index }}-extra-race-fee" name="category_rules[{{ $index }}][extra_race_fee]" type="number" min="0" step="1000" value="{{ old("category_rules.{$index}.extra_race_fee", $rule['extra_race_fee'] ?? 0) }}" data-extra-race-fee @disabled($event->is_free)>
                                        <small class="field-hint">Dikenakan untuk setiap mata lomba dalam kategori melebihi jumlah wajib ikut.</small>
                                    </div>
                                    <div class="form-field">
                                        <label for="category-{{ $index }}-outside-race-fee">{{ ! empty($rule['outside_obligation']) ? 'Biaya kategori tambahan per mata lomba' : 'Biaya per mata lomba di luar kategori' }} <span class="optional-label">Rp, opsional</span></label>
                                        <input id="category-{{ $index }}-outside-race-fee" name="category_rules[{{ $index }}][outside_race_fee]" type="number" min="0" step="1000" value="{{ old("category_rules.{$index}.outside_race_fee", $rule['outside_race_fee'] ?? 0) }}" data-outside-race-fee @disabled($event->is_free)>
                                        <small class="field-hint">{{ ! empty($rule['outside_obligation']) ? 'Tarif ini dikenakan untuk setiap mata lomba dalam kategori tambahan ini.' : 'Untuk mata lomba event yang dipilih di luar kategori utama. (Jika ini diisi maka akan muncul pilihan kategori tambahan diluar kategori utama)' }}</small>
                                    </div>
                                    <div class="form-field">
                                        <label for="category-{{ $index }}-discount-percent">Diskon persen <span class="optional-label">opsional</span></label>
                                        <input id="category-{{ $index }}-discount-percent" name="category_rules[{{ $index }}][discount_percent]" type="number" min="0" max="100" value="{{ old("category_rules.{$index}.discount_percent", $rule['discount_percent'] ?? '') }}" data-discount-percent @disabled($event->is_free)>
                                    </div>
                                    <div class="form-field">
                                        <label for="category-{{ $index }}-discount-amount">Potongan tetap <span class="optional-label">Rp, opsional</span></label>
                                        <input id="category-{{ $index }}-discount-amount" name="category_rules[{{ $index }}][discount_amount]" type="number" min="0" step="1000" value="{{ old("category_rules.{$index}.discount_amount", $rule['discount_amount'] ?? '') }}" data-discount-amount @disabled($event->is_free)>
                                    </div>
                                </div>
                                @unless ($event->is_free)
                                    {{-- Gelombang tarif: harga berbeda menurut rentang tanggal pendaftaran. --}}
                                    <div class="category-pricing-waves" data-pricing-waves>
                                        <div class="category-pricing-waves-head">
                                            <div>
                                                <strong>Gelombang tarif</strong>
                                                <small>Opsional. Buat tarif berbeda per rentang tanggal pendaftaran, misalnya gelombang awal lebih murah. Kosongkan berarti memakai harga di atas.</small>
                                            </div>
                                            <div class="category-wave-actions">
                                                <button class="category-wave-add" type="button" data-add-pricing-wave>+ Tambah gelombang</button>
                                                <button class="category-wave-apply" type="button" data-apply-wave-to-all title="Salin gelombang ini ke semua kategori">Terapkan ke semua kategori</button>
                                            </div>
                                        </div>
                                        <div class="category-wave-list" data-pricing-wave-list>
                                            @foreach (($rule['pricing_waves'] ?? []) as $waveIndex => $wave)
                                                <div class="category-wave-row" data-pricing-wave>
                                                    <div class="form-field">
                                                        <label>Nama gelombang</label>
                                                        <input name="category_rules[{{ $index }}][pricing_waves][{{ $waveIndex }}][label]" value="{{ old("category_rules.{$index}.pricing_waves.{$waveIndex}.label", $wave['label'] ?? '') }}" maxlength="80" placeholder="Contoh: Early bird">
                                                    </div>
                                                    <div class="form-field">
                                                        <label>Mulai</label>
                                                        <input type="date" name="category_rules[{{ $index }}][pricing_waves][{{ $waveIndex }}][starts_on]" value="{{ old("category_rules.{$index}.pricing_waves.{$waveIndex}.starts_on", $wave['starts_on'] ?? '') }}">
                                                    </div>
                                                    <div class="form-field">
                                                        <label>Berakhir</label>
                                                        <input type="date" name="category_rules[{{ $index }}][pricing_waves][{{ $waveIndex }}][ends_on]" value="{{ old("category_rules.{$index}.pricing_waves.{$waveIndex}.ends_on", $wave['ends_on'] ?? '') }}">
                                                    </div>
                                                    <div class="form-field">
                                                        <label>Harga paket <span class="optional-label">Rp</span></label>
                                                        <input type="number" min="0" step="1000" name="category_rules[{{ $index }}][pricing_waves][{{ $waveIndex }}][package_fee]" value="{{ old("category_rules.{$index}.pricing_waves.{$waveIndex}.package_fee", $wave['package_fee'] ?? '') }}" placeholder="Ikuti harga dasar">
                                                    </div>
                                                    <div class="form-field">
                                                        <label>Biaya tambahan <span class="optional-label">Rp</span></label>
                                                        <input type="number" min="0" step="1000" name="category_rules[{{ $index }}][pricing_waves][{{ $waveIndex }}][extra_race_fee]" value="{{ old("category_rules.{$index}.pricing_waves.{$waveIndex}.extra_race_fee", $wave['extra_race_fee'] ?? '') }}" placeholder="Ikuti harga dasar">
                                                    </div>
                                                    <div class="form-field">
                                                        <label>Luar kategori <span class="optional-label">Rp</span></label>
                                                        <input type="number" min="0" step="1000" name="category_rules[{{ $index }}][pricing_waves][{{ $waveIndex }}][outside_race_fee]" value="{{ old("category_rules.{$index}.pricing_waves.{$waveIndex}.outside_race_fee", $wave['outside_race_fee'] ?? '') }}" placeholder="Ikuti harga dasar">
                                                    </div>
                                                    <div class="form-field">
                                                        <label>Diskon % <span class="optional-label">opsional</span></label>
                                                        <input type="number" min="0" max="100" name="category_rules[{{ $index }}][pricing_waves][{{ $waveIndex }}][discount_percent]" value="{{ old("category_rules.{$index}.pricing_waves.{$waveIndex}.discount_percent", $wave['discount_percent'] ?? '') }}" placeholder="Ikuti harga dasar">
                                                    </div>
                                                    <div class="form-field">
                                                        <label>Potongan <span class="optional-label">Rp, opsional</span></label>
                                                        <input type="number" min="0" step="1000" name="category_rules[{{ $index }}][pricing_waves][{{ $waveIndex }}][discount_amount]" value="{{ old("category_rules.{$index}.pricing_waves.{$waveIndex}.discount_amount", $wave['discount_amount'] ?? '') }}" placeholder="Ikuti harga dasar">
                                                    </div>
                                                    <button class="category-wave-remove" type="button" data-remove-pricing-wave aria-label="Hapus gelombang">×</button>
                                                </div>
                                            @endforeach
                                        </div>
                                        @error("category_rules.{$index}.pricing_waves.*.ends_on")<small class="field-error">{{ $message }}</small>@enderror
                                            @error("category_rules.{$index}.pricing_waves.*.starts_on")<small class="field-error">{{ $message }}</small>@enderror
                                    </div>
                                @endunless
                                <p class="field-hint">Contoh: wajib 2 mata lomba, paket Rp 150.000, tambahan Rp 50.000 per mata lomba, luar kategori Rp 5.000, diskon 10% + Rp 10.000 → 2 mata lomba Rp 125.000, 3 mata lomba Rp 170.000.</p>
                                @error("category_rules.{$index}.required_races")<small class="field-error">{{ $message }}</small>@enderror
                            </fieldset>
                        </div>
                        <div class="category-pair-prompt" data-category-pair-prompt hidden>
                            <p>Apakah ingin membuat kategori yang sama untuk <strong data-counterpart-gender-label></strong>?</p>
                            <div>
                                <button class="category-pair-confirm" type="button" data-create-counterpart>Ya, buat kategori</button>
                                <button class="category-pair-dismiss" type="button" data-dismiss-counterpart>Tidak</button>
                            </div>
                        </div>
                    </fieldset>
                @endforeach
            </div>
            <button class="category-rule-add" type="button" data-add-category>+ Tambah kategori</button>
        </section>

        @if ($event->status === 'approved')
            <section class="form-section registration-readiness-section">
                <div class="form-section-heading">
                    <span class="form-step">02</span>
                    <div><h2>Kesiapan pendaftaran</h2><p>Kejuaraan tetap tampil untuk publik setelah disetujui Admin. Aktifkan opsi ini saat siap menerima pendaftaran atlet.</p></div>
                </div>
                <input type="hidden" name="registration_open" value="0">
                <label class="registration-readiness-option">
                    <input type="checkbox" name="registration_open" value="1" @checked(old('registration_open', $event->registration_open))>
                    <span><strong>Buka pendaftaran kejuaraan</strong><small>Tombol daftar akan muncul di beranda dan daftar lomba setelah aturan kategori disimpan.</small></span>
                </label>
                @error('registration_open')<small class="field-error">{{ $message }}</small>@enderror
            </section>
        @endif

        <div class="event-form-actions">
            <p>{{ $isAdmin && $event->status === 'draft' ? 'Setelah kategori lengkap, setujui kejuaraan dari halaman Pengajuan event agar tampil ke publik.' : 'Aturan kategori langsung digunakan untuk pendaftaran kejuaraan ini.' }}</p>
            <div>
                <a class="form-cancel-button" href="{{ route('guest.dashboard') }}">Batal</a>
                <button class="button button-primary" type="submit">Simpan aturan <span aria-hidden="true">→</span></button>
            </div>
        </div>
    </form>
</main>

<template data-category-rule-template>
    <fieldset class="category-rule-card" data-category-rule data-category-index="__INDEX__" data-outside-obligation="false">
        <legend>Kategori<span data-category-type-label></span> <span data-category-number></span></legend>
        <button class="category-rule-remove" type="button" data-remove-category aria-label="Hapus kategori">Hapus</button>
        <input type="hidden" name="category_rules[__INDEX__][outside_obligation]" value="0" data-outside-obligation-input>
        <div class="form-fields">
            <div class="category-name-controls">
                <div class="form-field">
                    <label for="category-__INDEX__-base-name">Nama kategori <span>*</span></label>
                    <select id="category-__INDEX__-base-name" name="category_rules[__INDEX__][base_name]" data-category-base-name required>
                        @foreach ($categoryNames as $categoryName)
                            <option value="{{ $categoryName }}">{{ $categoryName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-field">
                    <label for="category-__INDEX__-gender-group">Gender yang diizinkan <span>*</span></label>
                    <select id="category-__INDEX__-gender-group" name="category_rules[__INDEX__][gender_group]" data-category-gender-group required>
                        <option value="male">Putra</option>
                        <option value="female">Putri</option>
                        <option value="mixed">Campuran</option>
                    </select>
                    <small class="field-hint">Campuran mengizinkan putra dan putri; komposisi tim bebas.</small>
                </div>
                <div class="form-field">
                    <label for="category-__INDEX__-name-preview">Nama kategori akhir</label>
                    <input id="category-__INDEX__-name-preview" type="text" value="{{ ($categoryNames[0] ?? 'Kategori Umum').' Putra' }}" data-category-name-preview readonly>
                </div>
            </div>
            <fieldset class="eligibility-methods form-field-full" data-eligibility-picker>
                <legend>Metode kelayakan <span>* pilih satu</span></legend>
                <div class="eligibility-method-list">
                    <label class="eligibility-choice">
                        <input type="checkbox" name="category_rules[__INDEX__][eligibility_types][]" value="birth_date" data-eligibility-type="birth_date" checked>
                        <span><strong>Rentang tanggal lahir</strong><small>Tentukan tanggal lahir paling awal dan paling akhir.</small></span>
                    </label>
                    <label class="eligibility-choice">
                        <input type="checkbox" name="category_rules[__INDEX__][eligibility_types][]" value="birth_year" data-eligibility-type="birth_year">
                        <span><strong>Tahun lahir</strong><small>Pilih satu atau beberapa tahun kelahiran.</small></span>
                    </label>
                    <label class="eligibility-choice">
                        <input type="checkbox" name="category_rules[__INDEX__][eligibility_types][]" value="age" data-eligibility-type="age">
                        <span><strong>Rentang usia</strong><small>Usia dihitung pada hari pertama kejuaraan.</small></span>
                    </label>
                </div>
            </fieldset>
            <div class="eligibility-fields form-fields" data-eligibility-panel="birth_date">
                <div class="form-field">
                    <label for="category-__INDEX__-from">Tanggal lahir dari <span>*</span></label>
                    <input id="category-__INDEX__-from" name="category_rules[__INDEX__][birth_date_from]" type="date" data-required-when-active max="{{ today()->format('Y-m-d') }}">
                </div>
                <div class="form-field">
                    <label for="category-__INDEX__-to">Tanggal lahir sampai <span>*</span></label>
                    <input id="category-__INDEX__-to" name="category_rules[__INDEX__][birth_date_to]" type="date" data-required-when-active max="{{ today()->format('Y-m-d') }}">
                </div>
            </div>
            <fieldset class="category-rule-options category-birth-years eligibility-fields" data-eligibility-panel="birth_year" hidden>
                <legend>Tahun lahir <span>*</span></legend>
                <div class="birth-year-picker">
                    <select id="category-__INDEX__-birth-year" data-birth-year-select data-input-name="category_rules[__INDEX__][birth_years][]">
                        <option value="">Pilih tahun lahir</option>
                        @foreach ($birthYears as $year)
                            <option value="{{ $year }}">{{ $year }}</option>
                        @endforeach
                    </select>
                    <button class="category-rule-add-year" type="button" data-add-birth-year>Tambah tahun</button>
                </div>
                <div class="birth-year-list" data-birth-year-list></div>
                <small class="field-hint">Pilih minimal satu tahun lahir. Rentang tanggal dan usia tidak digunakan.</small>
            </fieldset>
            <div class="eligibility-fields form-fields" data-eligibility-panel="age" hidden>
                <div class="form-field">
                    <label for="category-__INDEX__-minimum-age">Usia minimal <span class="optional-label">opsional</span></label>
                    <input id="category-__INDEX__-minimum-age" name="category_rules[__INDEX__][minimum_age]" type="number" min="0" max="120">
                    <small class="field-hint">Kosongkan jika tidak ada batas minimal.</small>
                </div>
                <div class="form-field">
                    <label for="category-__INDEX__-maximum-age">Usia maksimal <span class="optional-label">opsional</span></label>
                    <input id="category-__INDEX__-maximum-age" name="category_rules[__INDEX__][maximum_age]" type="number" min="0" max="120">
                    <small class="field-hint">Isi usia minimal atau maksimal.</small>
                </div>
            </div>
            <fieldset class="category-rule-options">
                <legend>Mata lomba yang diizinkan <span>*</span></legend>
                @foreach ($event->races as $race)
                    <label><input type="checkbox" name="category_rules[__INDEX__][races][]" value="{{ $race }}" data-allowed-race><span>{{ $race }}</span></label>
                @endforeach
            </fieldset>
            <fieldset class="category-rule-options category-outside-race-picker" data-outside-race-picker hidden>
                <legend>Mata lomba di luar paket</legend>
                <p class="field-hint">Pilih mata lomba lain untuk membuat kategori terpisah di luar kewajiban paket. Setiap mata lomba dikenai biaya luar kategori.</p>
                @foreach ($event->races as $race)
                    <label><input type="checkbox" value="{{ $race }}" data-outside-race-option><span>{{ $race }}</span></label>
                @endforeach
            </fieldset>
            <fieldset class="category-pricing-fields form-field-full">
                <legend>Wajib ikut &amp; harga tiket</legend>
                @if ($event->is_free)
                    <p class="field-hint">Event ini gratis, jadi semua harga otomatis Rp 0.</p>
                @endif
                <div class="form-fields">
                    <div class="form-field">
                        <label for="category-__INDEX__-required-races">Wajib ikut berapa mata lomba <span>*</span></label>
                        <input id="category-__INDEX__-required-races" name="category_rules[__INDEX__][required_races]" type="number" min="1" max="{{ max(1, count($event->races)) }}" value="1" data-required-races required>
                        <small class="field-hint">Jumlah mata lomba minimal yang wajib diikuti atlet pada kategori ini.</small>
                    </div>
                    <div class="form-field form-field-full category-quota-fields">
                        <span class="field-group-label">Batas peserta</span>
                        <div class="form-fields">
                            <div class="form-field">
                                <label for="category-__INDEX__-max-registrants">Maksimal calon pendaftar <span class="optional-label">opsional</span></label>
                                <input id="category-__INDEX__-max-registrants" name="category_rules[__INDEX__][max_registrants]" type="number" min="1" step="1" placeholder="Tanpa batas" data-max-registrants>
                                <small class="field-hint">Kosongkan bila kategori ini tidak dibatasi. Batas dihitung dari atlet yang sudah lunas dan sudah diterima.</small>
                            </div>
                            <div class="form-field">
                                <label for="category-__INDEX__-registration-override">Status pendaftaran kategori</label>
                                <select id="category-__INDEX__-registration-override" name="category_rules[__INDEX__][registration_override]" data-registration-override>
                                    <option value="auto" selected>Otomatis mengikuti batas peserta</option>
                                    <option value="open">Dibuka paksa</option>
                                    <option value="closed">Ditutup paksa</option>
                                </select>
                                <small class="field-hint">Pilih "Ditutup paksa" untuk menutup kategori tanpa batas peserta, atau "Dibuka paksa" untuk membukanya kembali walau sudah penuh.</small>
                            </div>
                        </div>
                    </div>
                    <div class="form-field form-field-full category-base-pricing-range">
                        <span class="field-group-label">Masa berlaku tarif utama</span>
                        <div class="form-fields">
                            <div class="form-field">
                                <label for="category-__INDEX__-base-starts-on">Berlaku mulai <span class="optional-label">opsional</span></label>
                                <input id="category-__INDEX__-base-starts-on" name="category_rules[__INDEX__][base_starts_on]" type="date" data-base-starts-on>
                            </div>
                            <div class="form-field">
                                <label for="category-__INDEX__-base-ends-on">Berlaku sampai <span class="optional-label">opsional</span></label>
                                <input id="category-__INDEX__-base-ends-on" name="category_rules[__INDEX__][base_ends_on]" type="date" data-base-ends-on>
                            </div>
                        </div>
                        <small class="field-hint">Kosongkan keduanya bila tarif utama berlaku tanpa batas tanggal. Bila diisi, tarif utama hanya dipakai di antara kedua tanggal itu dan tidak boleh tumpang-tindih dengan rentang gelombang.</small>
                    </div>
                    <div class="form-field">
                        <label for="category-__INDEX__-package-fee">Harga paket <span class="optional-label">Rp</span></label>
                        <input id="category-__INDEX__-package-fee" name="category_rules[__INDEX__][package_fee]" type="number" min="0" step="1000" value="0" data-package-fee @disabled($event->is_free)>
                    </div>
                    <div class="form-field">
                        <label for="category-__INDEX__-extra-race-fee">Biaya per mata lomba tambahan <span class="optional-label">Rp, opsional</span></label>
                        <input id="category-__INDEX__-extra-race-fee" name="category_rules[__INDEX__][extra_race_fee]" type="number" min="0" step="1000" value="0" data-extra-race-fee @disabled($event->is_free)>
                    </div>
                    <div class="form-field">
                        <label for="category-__INDEX__-outside-race-fee">Biaya per mata lomba di luar kategori <span class="optional-label">Rp, opsional</span></label>
                        <input id="category-__INDEX__-outside-race-fee" name="category_rules[__INDEX__][outside_race_fee]" type="number" min="0" step="1000" value="0" data-outside-race-fee @disabled($event->is_free)>
                        <small class="field-hint">Untuk mata lomba event yang dipilih di luar kategori utama. (Jika ini diisi maka akan muncul pilihan kategori tambahan diluar kategori utama)</small>
                    </div>
                    <div class="form-field">
                        <label for="category-__INDEX__-discount-percent">Diskon persen <span class="optional-label">opsional</span></label>
                        <input id="category-__INDEX__-discount-percent" name="category_rules[__INDEX__][discount_percent]" type="number" min="0" max="100" data-discount-percent @disabled($event->is_free)>
                    </div>
                    <div class="form-field">
                        <label for="category-__INDEX__-discount-amount">Potongan tetap <span class="optional-label">Rp, opsional</span></label>
                        <input id="category-__INDEX__-discount-amount" name="category_rules[__INDEX__][discount_amount]" type="number" min="0" step="1000" data-discount-amount @disabled($event->is_free)>
                    </div>
                </div>
                @unless ($event->is_free)
                    <div class="category-pricing-waves" data-pricing-waves>
                        <div class="category-pricing-waves-head">
                            <div>
                                <strong>Gelombang tarif</strong>
                                <small>Opsional. Buat tarif berbeda per rentang tanggal pendaftaran, misalnya gelombang awal lebih murah. Kosongkan berarti memakai harga di atas.</small>
                            </div>
                            <div class="category-wave-actions">
                                                <button class="category-wave-add" type="button" data-add-pricing-wave>+ Tambah gelombang</button>
                                                <button class="category-wave-apply" type="button" data-apply-wave-to-all title="Salin gelombang ini ke semua kategori">Terapkan ke semua kategori</button>
                                            </div>
                        </div>
                        <div class="category-wave-list" data-pricing-wave-list></div>
                    </div>
                @endunless
            </fieldset>
        </div>
        <div class="category-pair-prompt" data-category-pair-prompt hidden>
            <p>Apakah ingin membuat kategori yang sama untuk <strong data-counterpart-gender-label></strong>?</p>
            <div>
                <button class="category-pair-confirm" type="button" data-create-counterpart>Ya, buat kategori</button>
                <button class="category-pair-dismiss" type="button" data-dismiss-counterpart>Tidak</button>
            </div>
        </div>
    </fieldset>
</template>

<template data-pricing-wave-template>
    <div class="category-wave-row" data-pricing-wave>
        <div class="form-field">
            <label>Nama gelombang</label>
            <input name="category_rules[__INDEX__][pricing_waves][__WAVE__][label]" maxlength="80" placeholder="Contoh: Early bird">
        </div>
        <div class="form-field">
            <label>Mulai</label>
            <input type="date" name="category_rules[__INDEX__][pricing_waves][__WAVE__][starts_on]">
        </div>
        <div class="form-field">
            <label>Berakhir</label>
            <input type="date" name="category_rules[__INDEX__][pricing_waves][__WAVE__][ends_on]">
        </div>
        <div class="form-field">
            <label>Harga paket <span class="optional-label">Rp</span></label>
            <input type="number" min="0" step="1000" name="category_rules[__INDEX__][pricing_waves][__WAVE__][package_fee]" placeholder="Ikuti harga dasar">
        </div>
        <div class="form-field">
            <label>Biaya tambahan <span class="optional-label">Rp</span></label>
            <input type="number" min="0" step="1000" name="category_rules[__INDEX__][pricing_waves][__WAVE__][extra_race_fee]" placeholder="Ikuti harga dasar">
        </div>
        <div class="form-field">
            <label>Luar kategori <span class="optional-label">Rp</span></label>
            <input type="number" min="0" step="1000" name="category_rules[__INDEX__][pricing_waves][__WAVE__][outside_race_fee]" placeholder="Ikuti harga dasar">
        </div>
        <div class="form-field">
            <label>Diskon % <span class="optional-label">opsional</span></label>
            <input type="number" min="0" max="100" name="category_rules[__INDEX__][pricing_waves][__WAVE__][discount_percent]" placeholder="Ikuti harga dasar">
        </div>
        <div class="form-field">
            <label>Potongan <span class="optional-label">Rp, opsional</span></label>
            <input type="number" min="0" step="1000" name="category_rules[__INDEX__][pricing_waves][__WAVE__][discount_amount]" placeholder="Ikuti harga dasar">
        </div>
        <button class="category-wave-remove" type="button" data-remove-pricing-wave aria-label="Hapus gelombang">×</button>
    </div>
</template>
@endsection
