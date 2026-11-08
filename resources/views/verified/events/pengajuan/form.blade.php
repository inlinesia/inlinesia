@extends($isAdmin ? 'layouts.admin' : 'layouts.public')

@php
    // Halaman ini melayani dua mode sekaligus: pengajuan event baru ($event null)
    // dan penyuntingan event yang sudah ada. Semua perbedaan diturunkan dari
    // $isEditing dan $isAdmin supaya perbaikannya cukup di satu berkas.
    $presets = config('event-field-presets');
    $isAdmin = $isAdmin ?? false;
    $isEditing = $event !== null;
    $stored = fn (string $key) => $event?->{$key};
    $value = fn (string $key) => old($key, $stored($key));
    $lines = fn (string $key) => old($key, $isEditing ? implode("\n", $event->{$key} ?? []) : '');
    $backUrl = $isEditing ? route('guest.dashboard') : route('events.index');
    $statusLabel = match ($event?->status) {
        'approved' => 'Disetujui dan tayang publik',
        'pending' => 'Menunggu persetujuan Admin',
        'rejected' => 'Belum disetujui',
        default => 'Draf',
    };
@endphp

@section('title', $isEditing ? 'Ubah pengajuan — Inlinesia' : 'Ajukan Event dari Dasbor — Inlinesia')
@section('section', 'Kelola event')
@section('description', $isEditing
    ? 'Perbarui informasi pengajuan event sepatu roda Anda.'
    : 'Ajukan informasi lengkap event sepatu roda untuk ditinjau Admin Inlinesia.')
@section('body-class', 'auth-page')

@section('content')
<main class="public-application-page event-form-page">
    <section class="event-form-heading">
        <div>
            <div class="account-eyebrow">{{ match (true) {
                ! $isEditing => 'PENGAJUAN EVENT',
                $isAdmin => 'PENGELOLAAN ADMIN',
                default => 'PENGELOLAAN EVENT',
            } }}</div>
            <h1>{{ $isEditing ? 'Edit ' . $event->name : 'Daftarkan event baru' }}</h1>
            <p class="page-subtitle">{{ $isEditing
                ? ($isAdmin
                    ? 'Perubahan disimpan langsung dari dasbor bersama.'
                    : 'Perubahan akan dikirimkan ke Admin untuk ditinjau.') . ' Status event tidak berubah otomatis.'
                : 'Isi informasi event secara lengkap. Event hanya akan ditampilkan setelah disetujui Admin.' }}</p>
        </div>
        <a class="form-back-link" href="{{ $backUrl }}">← {{ $isEditing ? 'Kembali ke dasbor' : 'Kembali ke daftar lomba' }}</a>
    </section>

    @if (! $isEditing && ! auth()->user()->hasVerifiedEmail() && ! $isAdmin)
        <div class="form-example-notice" role="status">
            <span class="notice-icon">!</span>
            <p><strong>Verifikasi email diperlukan.</strong> Periksa kotak masuk Anda dan verifikasi alamat email sebelum mengajukan event.</p>
            <form method="POST" action="{{ route('verification.send') }}">@csrf<button class="inline-action" type="submit">Kirim ulang verifikasi</button></form>
        </div>
    @endif

    @if ($isEditing)
        <div class="form-example-notice" role="status">
            <span class="notice-icon">i</span>
            <p><strong>Status saat ini: {{ $statusLabel }}.</strong>
                @if (! $isAdmin) Admin menerima notifikasi setiap kali Anda menyimpan perubahan. @endif</p>
        </div>
    @endif

    @if ($errors->any())
        <div class="form-errors" role="alert">
            <strong>Lengkapi atau perbaiki informasi berikut:</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form class="event-form" method="POST" action="{{ $isEditing ? route('event-applications.update', $event) : route('event-applications.store') }}">
        @csrf
        @if ($isEditing) @method('PUT') @endif

        <section class="form-section">
            <div class="form-section-heading"><span class="form-step">01</span><div><h2>Identitas event</h2><p>Nama, tanggal, lokasi, serta jenis penyelenggaraan.</p></div></div>
            <div class="form-fields">
                <div class="form-field form-field-full"><label for="name">Nama event <span>*</span></label><input id="name" name="name" value="{{ $value('name') }}" maxlength="180" required>@error('name')<small class="field-error">{{ $message }}</small>@enderror</div>
                <div class="form-field"><label for="start_date">Tanggal mulai <span>*</span></label><input id="start_date" name="start_date" type="date" value="{{ old('start_date', $stored('start_date')?->format('Y-m-d')) }}" min="{{ today()->format('Y-m-d') }}" required>@error('start_date')<small class="field-error">{{ $message }}</small>@enderror</div>
                <div class="form-field"><label for="end_date">Tanggal selesai <span>*</span></label><input id="end_date" name="end_date" type="date" value="{{ old('end_date', $stored('end_date')?->format('Y-m-d')) }}" min="{{ today()->format('Y-m-d') }}" required>@error('end_date')<small class="field-error">{{ $message }}</small>@enderror</div>
                <div class="form-field"><label for="scale">Skala event <span>*</span></label><input id="scale" name="scale" value="{{ $value('scale') }}" placeholder="Kota, provinsi, nasional" maxlength="120" required>@error('scale')<small class="field-error">{{ $message }}</small>@enderror</div>
                <div class="form-field"><label for="registration_deadline">Batas pendaftaran @if (! $isEditing)<span>*</span>@endif</label><input id="registration_deadline" name="registration_deadline" type="date" value="{{ old('registration_deadline', $stored('registration_deadline')?->format('Y-m-d')) }}" min="{{ today()->format('Y-m-d') }}" @required(! $isEditing)>@error('registration_deadline')<small class="field-error">{{ $message }}</small>@enderror</div>
                <div class="form-field"><label for="venue">Nama venue <span>*</span></label><input id="venue" name="venue" value="{{ $value('venue') }}" maxlength="180" required>@error('venue')<small class="field-error">{{ $message }}</small>@enderror</div>
                <div class="form-field"><label for="venue_address">Alamat lengkap venue <span>*</span></label><input id="venue_address" name="venue_address" value="{{ $value('venue_address') }}" maxlength="1000" required>@error('venue_address')<small class="field-error">{{ $message }}</small>@enderror</div>
                <div class="form-field"><label for="organizer_whatsapp">WhatsApp pengelola kejuaraan <span>*</span></label><input id="organizer_whatsapp" name="organizer_whatsapp" type="tel" value="{{ $value('organizer_whatsapp') }}" autocomplete="tel" maxlength="40" placeholder="08xx atau +62xx" pattern="\+?[0-9\s().-]{8,40}" required><small class="field-hint">Nomor ini ditampilkan pada informasi kejuaraan dan menjadi tujuan konfirmasi melalui WhatsApp.</small>@error('organizer_whatsapp')<small class="field-error">{{ $message }}</small>@enderror</div>
            </div>
        </section>

        <section class="form-section">
            <div class="form-section-heading"><span class="form-step">02</span><div><h2>Teknis pertandingan</h2><p>Peraturan, official, dan kondisi lokasi.</p></div></div>
            <div class="form-fields">
                <div class="form-field">
                    <x-preset-picker name="regulations" label="Peraturan pertandingan" :rows="4" :maxlength="5000"
                        :value="$value('regulations')" :options="$presets['regulations']['options']"
                        :custom="$presets['regulations']['custom']" />
                </div>
                <div class="form-field">
                    <x-preset-picker name="officials" label="Official pertandingan" :rows="4" :maxlength="2000"
                        :value="$value('officials')" :options="$presets['officials']['options']"
                        :custom="$presets['officials']['custom']" />
                </div>
                <div class="form-field form-field-full">
                    <x-preset-picker name="venue_conditions" label="Kondisi venue dan lintasan" :rows="3"
                        :maxlength="2000" :value="$value('venue_conditions')"
                        :options="$presets['venue_conditions']['options']"
                        :custom="$presets['venue_conditions']['custom']" />
                </div>
            </div>
        </section>

        <section class="form-section">
            <div class="form-section-heading"><span class="form-step">03</span><div><h2>Kategori &amp; nomor lomba</h2><p>Tulis satu pilihan per baris. Rentang usia tiap kategori diatur di halaman pengaturan kategori.</p></div></div>
            <div class="form-fields">
                <div class="form-field form-field-full">
                    <x-preset-picker name="categories" label="Kategori peserta" :rows="6" :maxlength="5000"
                        :value="$lines('categories')" :options="$presets['categories']['options']"
                        :custom="$presets['categories']['custom']"
                        hint="Contoh: Beginner putra/putri." />
                </div>
                <div class="form-field form-field-full">
                    <x-preset-picker name="races" label="Mata lomba" :rows="5" :maxlength="5000"
                        :value="$lines('races')" :options="$presets['races']['options']"
                        :custom="$presets['races']['custom']"
                        hint="Contoh: Classic Slalom, Speed Slalom, Skate Cross." />
                </div>
            </div>
        </section>

        <section class="form-section">
            <div class="form-section-heading"><span class="form-step">04</span><div><h2>Pendaftaran &amp; hadiah</h2><p>Informasi yang akan dibaca calon peserta.</p></div></div>
            <div class="form-fields">
                <div class="form-field">
                    <x-preset-picker name="registration_info" label="Informasi pendaftaran" :rows="5"
                        :maxlength="5000" :value="$value('registration_info')"
                        :options="$presets['registration_info']['options']"
                        :custom="$presets['registration_info']['custom']" />
                </div>
                <div class="form-field">
                    <x-preset-picker name="fees" label="Biaya & ketentuan pembayaran" :rows="5" :maxlength="5000"
                        :value="$value('fees')" :options="$presets['fees']['options']"
                        :custom="$presets['fees']['custom']" />
                </div>
                <div class="form-field">
                    <label for="is_free">Jenis biaya pendaftaran <span>*</span></label>
                    @php($selectedFee = old('is_free', $isEditing && $event->is_free ? '1' : '0'))
                    <select id="is_free" name="is_free" required>
                        <option value="0" @selected($selectedFee === '0')>Berbayar</option>
                        <option value="1" @selected($selectedFee === '1')>Gratis</option>
                    </select>
                    @error('is_free')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <div class="form-field"><label for="payment_bank_name">Nama bank <span class="optional-label">opsional</span></label><input id="payment_bank_name" name="payment_bank_name" value="{{ $value('payment_bank_name') }}" maxlength="80" placeholder="BCA, Mandiri, BNI" autocomplete="off">@error('payment_bank_name')<small class="field-error">{{ $message }}</small>@enderror</div>
                <div class="form-field"><label for="payment_account_number">Nomor rekening pembayaran <span class="optional-label">opsional</span></label><input id="payment_account_number" name="payment_account_number" type="tel" value="{{ $value('payment_account_number') }}" maxlength="60" inputmode="numeric" autocomplete="off" placeholder="1234567890" pattern="[0-9][0-9\s.-]{5,59}"><small class="field-hint">Ditampilkan ke peserta di halaman pendaftaran. Kosongkan bila pembayaran dikonfirmasi lewat WhatsApp.</small>@error('payment_account_number')<small class="field-error">{{ $message }}</small>@enderror</div>
                <div class="form-field"><label for="payment_account_holder">Atas nama pemilik rekening <span class="optional-label">opsional</span></label><input id="payment_account_holder" name="payment_account_holder" value="{{ $value('payment_account_holder') }}" maxlength="120" autocomplete="off">@error('payment_account_holder')<small class="field-error">{{ $message }}</small>@enderror</div>
                <div class="form-field form-field-full">
                    <x-preset-picker name="awards" label="Hadiah / penghargaan" :rows="3" :maxlength="3000"
                        :value="$value('awards')" :options="$presets['awards']['options']"
                        :custom="$presets['awards']['custom']" />
                </div>
            </div>
        </section>

        <section class="form-section">
            <div class="form-section-heading"><span class="form-step">05</span><div><h2>Ketentuan &amp; jadwal</h2><p>Jelaskan keadaan kahar dan susunan kegiatan.</p></div></div>
            <div class="form-fields">
                <div class="form-field form-field-full">
                    <x-preset-picker name="force_majeure" label="Keadaan kahar" :rows="4" :maxlength="5000"
                        :value="$value('force_majeure')" :options="$presets['force_majeure']['options']"
                        :custom="$presets['force_majeure']['custom']" />
                </div>
                <div class="form-field form-field-full">
                    <x-preset-picker name="schedule" label="Jadwal" :rows="6" :maxlength="8000"
                        :value="$lines('schedule')" :options="$presets['schedule']['options']"
                        :custom="$presets['schedule']['custom']"
                        hint="Satu agenda per baris." />
                </div>
            </div>
        </section>

        <div class="event-form-actions public-application-actions">
            <p>{!! $isEditing
                ? 'Setelah disimpan, Admin menerima notifikasi untuk meninjau kembali pengajuan Anda.'
                : 'Pengajuan akan berstatus <strong>menunggu persetujuan</strong> dan belum terlihat publik.' !!}</p>
            <div><a class="form-cancel-button" href="{{ $backUrl }}">Batal</a><button class="button button-primary" type="submit">{{ $isEditing ? 'Simpan perubahan' : 'Kirim pengajuan' }} <span aria-hidden="true">→</span></button></div>
        </div>
    </form>
</main>
@endsection
