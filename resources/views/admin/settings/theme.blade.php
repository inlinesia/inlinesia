@extends('layouts.admin')

@section('title', 'Setting — Inlinesia')
@section('section', 'Setting')

@section('content')
    <div class="page-content">
        <section class="welcome-row">
            <div>
                <div class="eyebrow"><span class="eyebrow-line"></span> SETTING</div>
                <h1>Tema tampilan</h1>
                <p class="page-subtitle">Pilih tema warna yang dipakai di seluruh halaman Inlinesia. Pilihan langsung berlaku
                    dan tersimpan di peramban ini.</p>
            </div>
        </section>

        <form class="panel theme-settings-card" data-theme-form method="POST"
            action="{{ route('admin.settings.theme.update') }}">
            @csrf
            @method('PATCH')

            <div class="panel-heading">
                <div>
                    <span class="section-kicker">THEME</span>
                    <h2>Pilih tema</h2>
                </div>
            </div>

            <div class="theme-option-grid">
                <label class="theme-option" data-theme-option>
                    <input type="radio" name="theme" value="glass" @checked(old('theme') === 'glass')>
                    <span class="theme-option-preview theme-preview-glass">
                        <span class="theme-preview-chrome">
                            <span class="theme-preview-pill"></span>
                            <span class="theme-preview-pill"></span>
                        </span>
                        <span class="theme-preview-cards">
                            <span class="theme-preview-card"></span>
                            <span class="theme-preview-card"></span>
                        </span>
                    </span>
                    <span class="theme-option-meta">
                        <span>
                            <strong>Glass</strong>
                            <small>Panel transparan, warna hijau zamrud. Untuk suasana terang.</small>
                        </span>
                        <span class="theme-option-check" aria-hidden="true">&#10003;</span>
                    </span>
                </label>

                <label class="theme-option" data-theme-option>
                    <input type="radio" name="theme" value="copper" @checked(old('theme', 'copper') === 'copper')>
                    <span class="theme-option-preview theme-preview-copper">
                        <span class="theme-preview-chrome">
                            <span class="theme-preview-pill"></span>
                            <span class="theme-preview-pill"></span>
                        </span>
                        <span class="theme-preview-cards">
                            <span class="theme-preview-card"></span>
                            <span class="theme-preview-card"></span>
                        </span>
                    </span>
                    <span class="theme-option-meta">
                        <span>
                            <strong>Tembaga</strong>
                            <small>Panel gelap, aksen tembaga, sudut organik. Tampilan bawaan Inlinesia.</small>
                        </span>
                        <span class="theme-option-check" aria-hidden="true">&#10003;</span>
                    </span>
                </label>

                <label class="theme-option" data-theme-option>
                    <input type="radio" name="theme" value="neural" @checked(old('theme') === 'neural')>
                    <span class="theme-option-preview theme-preview-neural">
                        <span class="theme-preview-chrome">
                            <span class="theme-preview-pill"></span>
                            <span class="theme-preview-pill"></span>
                        </span>
                        <span class="theme-preview-cards">
                            <span class="theme-preview-card"></span>
                            <span class="theme-preview-card"></span>
                        </span>
                    </span>
                    <span class="theme-option-meta">
                        <span>
                            <strong>Neural Glass</strong>
                            <small>Efek kaca neon ungu–biru terinspirasi TemplateMo Neural Glass.</small>
                        </span>
                        <span class="theme-option-check" aria-hidden="true">&#10003;</span>
                    </span>
                </label>

                <label class="theme-option" data-theme-option>
                    <input type="radio" name="theme" value="topic" @checked(old('theme') === 'topic')>
                    <span class="theme-option-preview theme-preview-topic">
                        <span class="theme-preview-chrome">
                            <span class="theme-preview-pill"></span>
                            <span class="theme-preview-pill"></span>
                        </span>
                        <span class="theme-preview-cards">
                            <span class="theme-preview-card"></span>
                            <span class="theme-preview-card"></span>
                        </span>
                    </span>
                    <span class="theme-option-meta">
                        <span>
                            <strong>Topic Listing</strong>
                            <small>Nuansa profesional biru–mint terinspirasi TemplateMo Topic Listing.</small>
                        </span>
                        <span class="theme-option-check" aria-hidden="true">&#10003;</span>
                    </span>
                </label>
            </div>

            <div class="theme-settings-footer">
                <button class="button button-primary" type="submit">Simpan tema</button>
                <span class="theme-settings-status" data-theme-status role="status">
                    Perubahan langsung diterapkan di semua halaman.
                </span>
            </div>
        </form>
    </div>
@endsection
