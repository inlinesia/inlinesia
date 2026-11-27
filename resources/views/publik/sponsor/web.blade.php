@extends('layouts.public')

@section('title', 'Sponsor Website — Inlinesia')
@section('description', 'Ajukan kerja sama sponsor website Inlinesia dengan paket banner atau sponsor utama dan tarif transparan per bulan.')
@section('body-class', 'auth-page')

@section('content')
@php
    $sponsorPackages = [
        'web_banner' => [
            'label' => 'Banner website',
            'price' => 250000,
            'priceLabel' => 'Rp 250.000/bulan',
            'benefit' => 'Banner tampil di beranda dan halaman Jelajahi kejuaraan.',
        ],
        'web_main' => [
            'label' => 'Sponsor utama website',
            'price' => 1000000,
            'priceLabel' => 'Rp 1.000.000/bulan',
            'benefit' => 'Logo utama tampil menonjol di header dan footer Inlinesia.',
        ],
    ];
    $selectedPackage = old('package', 'web_banner');
@endphp
<main class="profile-page sponsor-form-page">
    <header class="event-form-heading">
        <div>
            <span class="account-eyebrow">KERJA SAMA SPONSOR</span>
            <h1>Sponsor website Inlinesia</h1>
            <p class="page-subtitle">Dukung platform inline skate Indonesia. Pilih paket, isi data brand, lalu kirim pengajuan untuk ditinjau Admin.</p>
        </div>
        <a class="form-back-link" href="{{ route('home') }}">← Kembali ke beranda</a>
    </header>

    <section class="account-card">
        <div class="account-card-heading">
            <span class="account-eyebrow">PENGAJUAN SPONSOR WEBSITE</span>
            <h2>Formulir sponsor website</h2>
            <p>Estimasi tarif menyesuaikan paket dan lama periode kerja sama. Ajukan kembali sponsor untuk periode berikutnya bila diperlukan.</p>
        </div>

        @if ($errors->any())
            <div class="account-errors" role="alert">
                <strong>Periksa kembali data Anda.</strong>
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form class="account-form sponsor-form" method="POST" action="{{ route('sponsor.web.store') }}" enctype="multipart/form-data">
            @csrf

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
                <p class="sponsor-price-note" data-sponsor-price-note>Estimasi tarif: <strong>{{ $sponsorPackages[$selectedPackage]['priceLabel'] ?? 'Rp 250.000/bulan' }}</strong></p>
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
                <div class="form-field">
                    <label for="start_date">Tanggal mulai <span>*</span></label>
                    <input id="start_date" name="start_date" type="date" value="{{ old('start_date') }}" required>
                    @error('start_date')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <div class="form-field">
                    <label for="end_date">Tanggal selesai <span>*</span></label>
                    <input id="end_date" name="end_date" type="date" value="{{ old('end_date') }}" required>
                    @error('end_date')<small class="field-error">{{ $message }}</small>@enderror
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
                <span>Saya menyetujui penggunaan nama dan logo brand untuk ditampilkan di website Inlinesia sesuai paket yang dipilih. <span>*</span></span>
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
        var dateInputs = document.querySelectorAll('input[name="start_date"], input[name="end_date"]');
        var rupiah = function (value) {
            return 'Rp ' + value.toLocaleString('id-ID');
        };
        var months = function () {
            var start = document.querySelector('input[name="start_date"]');
            var end = document.querySelector('input[name="end_date"]');
            if (!start || !end || !start.value || !end.value) {
                return 0;
            }
            var from = new Date(start.value);
            var to = new Date(end.value);
            if (isNaN(from.getTime()) || isNaN(to.getTime()) || to < from) {
                return 0;
            }
            return (to.getFullYear() - from.getFullYear()) * 12 + to.getMonth() - from.getMonth() + 1;
        };
        var update = function () {
            var selected = document.querySelector('input[name="package"]:checked');
            var detail = selected ? packages[selected.value] : null;
            if (!detail || !note) {
                return;
            }
            var duration = months();
            var text = 'Estimasi tarif: ' + rupiah(detail.price) + ' per bulan';
            if (duration > 0) {
                text += ' · ' + duration + ' bulan ≈ ' + rupiah(detail.price * duration);
            }
            note.textContent = text;
        };

        options.forEach(function (option) {
            option.addEventListener('change', update);
        });
        dateInputs.forEach(function (input) {
            input.addEventListener('change', update);
            input.addEventListener('input', update);
        });
        update();
    })();
</script>
@endsection
