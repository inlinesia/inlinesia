@extends('layouts.public')

@section('title', 'Sponsor Event — Inlinesia')
@section('description', 'Ajukan kerja sama sponsor event sepatu roda di Inlinesia dengan paket logo event atau sponsor utama event.')
@section('body-class', 'auth-page')

@section('content')
@php
    $sponsorEvents = $events ?? collect();
    $queryEventId = $selectedEventId ?? request()->query('event');
    $selectedEventId = (string) old('event_id', is_scalar($queryEventId) ? $queryEventId : '');
    $listedEventIds = [];
    $sponsorPackages = [
        'event_logo' => [
            'label' => 'Logo event',
            'price' => 150000,
            'priceLabel' => 'Rp 150.000/event',
            'benefit' => 'Logo tampil sebagai badge "Didukung oleh" pada kartu event.',
        ],
        'event_main' => [
            'label' => 'Sponsor utama event',
            'price' => 300000,
            'priceLabel' => 'Rp 300.000/event',
            'benefit' => 'Banner tampil pada halaman pendaftaran event dan materi peserta.',
        ],
    ];
    $selectedPackage = old('package', 'event_logo');
@endphp
<main class="profile-page sponsor-form-page">
    <header class="event-form-heading">
        <div>
            <span class="account-eyebrow">KERJA SAMA SPONSOR</span>
            <h1>Sponsor event Inlinesia</h1>
            <p class="page-subtitle">Dukung sebuah kejuaraan tertentu. Pilih event dan paket, isi data brand, lalu kirim pengajuan untuk ditinjau Admin.</p>
        </div>
        <a class="form-back-link" href="{{ route('events.index') }}">← Kembali ke daftar lomba</a>
    </header>

    <section class="account-card">
        <div class="account-card-heading">
            <span class="account-eyebrow">PENGAJUAN SPONSOR EVENT</span>
            <h2>Formulir sponsor event</h2>
            <p>Tarif dihitung per event. Jika Anda membuka formulir dari tautan event tertentu, pilihan event sudah terisi otomatis.</p>
        </div>

        @if ($errors->any())
            <div class="account-errors" role="alert">
                <strong>Periksa kembali data Anda.</strong>
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form class="account-form sponsor-form" method="POST" action="{{ route('sponsor.event.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="form-fields">
                <div class="form-field form-field-full">
                    <label for="event_id">Event yang didukung <span>*</span></label>
                    <select id="event_id" name="event_id" required>
                        <option value="">Pilih event</option>
                        @foreach ($sponsorEvents as $sponsorEvent)
                            @php($listedEventIds[] = (string) $sponsorEvent->id)
                            <option value="{{ $sponsorEvent->id }}" @selected($selectedEventId === (string) $sponsorEvent->id)>{{ $sponsorEvent->name }}</option>
                        @endforeach
                        @if ($selectedEventId !== '' && ! in_array($selectedEventId, $listedEventIds, true))
                            <option value="{{ $selectedEventId }}" selected>Event #{{ $selectedEventId }} (dari tautan)</option>
                        @endif
                    </select>
                    <small class="field-hint">Event hanya dapat disponsori setelah disetujui Admin.</small>
                    @error('event_id')<small class="field-error">{{ $message }}</small>@enderror
                </div>
            </div>

            <fieldset class="form-field sponsor-package-field">
                <legend>Paket sponsor <span>*</span></legend>
                <div class="sponsor-package-options">
                    @foreach ($sponsorPackages as $value => $detail)
                        <label class="sponsor-package-option">
                            <input type="radio" name="package" value="{{ $value }}" @checked($selectedPackage === $value) required>
                            <span>
                                <strong>{{ $detail['label'] }}</strong>
                                <small>{{ $detail['priceLabel'] }} — {{ $detail['benefit'] }}</small>
                            </span>
                        </label>
                    @endforeach
                </div>
                <p class="sponsor-price-note" data-sponsor-price-note>Estimasi tarif: <strong>{{ $sponsorPackages[$selectedPackage]['priceLabel'] ?? 'Rp 150.000/event' }}</strong></p>
                @error('package')<small class="field-error">{{ $message }}</small>@enderror
            </fieldset>

            <div class="form-fields">
                <div class="form-field">
                    <label for="brand_name">Nama brand <span>*</span></label>
                    <input id="brand_name" name="brand_name" type="text" value="{{ old('brand_name') }}" maxlength="120" placeholder="Nama brand atau perusahaan" autocomplete="organization" required autofocus>
                    @error('brand_name')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <div class="form-field">
                    <label for="contact_name">Nama kontak <span>*</span></label>
                    <input id="contact_name" name="contact_name" type="text" value="{{ old('contact_name') }}" maxlength="120" placeholder="Nama penanggung jawab" autocomplete="name" required>
                    @error('contact_name')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <div class="form-field">
                    <label for="phone">Nomor WhatsApp <span>*</span></label>
                    <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" maxlength="40" placeholder="08xx xxxx xxxx" autocomplete="tel" pattern="\+?[0-9\s().-]{8,40}" required>
                    @error('phone')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <div class="form-field">
                    <label for="email">Email <span>*</span></label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="255" placeholder="nama@perusahaan.com" autocomplete="email" required>
                    @error('email')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <div class="form-field form-field-full">
                    <label for="website_url">Tautan website brand <span>*</span></label>
                    <input id="website_url" name="website_url" type="url" value="{{ old('website_url') }}" maxlength="500" placeholder="https://..." autocomplete="url" required>
                    @error('website_url')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <div class="form-field form-field-full">
                    <label for="logo">Logo brand <span>*</span></label>
                    <input id="logo" name="logo" type="file" accept="image/*" required>
                    <small class="field-hint">Unggah berkas gambar (PNG, JPG, atau WebP). Logo akan ditampilkan sesuai paket yang dipilih.</small>
                    @error('logo')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <div class="form-field form-field-full">
                    <label for="message">Pesan / catatan <span>*</span></label>
                    <textarea id="message" name="message" rows="4" maxlength="500" placeholder="Ceritakan brand Anda dan harapan kerja sama." required>{{ old('message') }}</textarea>
                    @error('message')<small class="field-error">{{ $message }}</small>@enderror
                </div>
            </div>

            <label class="form-field sponsor-agreement">
                <input type="checkbox" name="agreement" value="1" @checked(old('agreement')) required>
                <span>Saya menyetujui penggunaan nama dan logo brand pada event yang dipilih sesuai paket sponsor. <span>*</span></span>
            </label>
            @error('agreement')<small class="field-error">{{ $message }}</small>@enderror

            <p class="sponsor-payment-note"><strong>Pembayaran dikonfirmasi manual lewat WhatsApp.</strong> Setelah pengajuan disetujui Admin, kami menghubungi nomor WhatsApp di atas untuk mengonfirmasi paket, tarif, dan pembayaran.</p>

            <button class="account-submit" type="submit">Kirim pengajuan sponsor <span aria-hidden="true">→</span></button>
        </form>
    </section>
</main>

<script>
    (function () {
        var packages = @json($sponsorPackages);
        var note = document.querySelector('[data-sponsor-price-note]');
        var options = document.querySelectorAll('input[name="package"]');
        var rupiah = function (value) {
            return 'Rp ' + value.toLocaleString('id-ID');
        };
        var update = function () {
            var selected = document.querySelector('input[name="package"]:checked');
            var detail = selected ? packages[selected.value] : null;
            if (!detail || !note) {
                return;
            }
            note.textContent = 'Estimasi tarif: ' + rupiah(detail.price) + ' per event';
        };

        options.forEach(function (option) {
            option.addEventListener('change', update);
        });
        update();
    })();
</script>
@endsection
