@extends('layouts.admin')

@section('title', 'Hapus data — Inlinesia')
@section('section', 'Hapus data')

@section('content')
    <div class="page-content">
        <section class="welcome-row">
            <div>
                <div class="eyebrow"><span class="eyebrow-line"></span> PENGELOLAAN DATA</div>
                <h1>Hapus data</h1>
                <p class="page-subtitle">Pilih katalog penyimpanan yang akan dikosongkan. Isi tiap katalog dan dampak relasinya akan ditinjau sebelum penghapusan final.</p>
            </div>
        </section>

        @if ($errors->any())
            <div class="admin-flash is-error" role="alert">
                <strong>Penghapusan belum dapat diproses.</strong>
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="panel data-management-card is-danger">
            <div class="panel-heading">
                <div>
                    <span class="section-kicker">PILIH KATALOG</span>
                    <h2>Data yang akan dihapus</h2>
                </div>
            </div>
            <p class="data-management-warning">Penghapusan tidak dapat dibatalkan. Sebaiknya buat backup terlebih dahulu. Relasi database dapat menghapus data turunan yang ditampilkan pada keterangan tiap katalog.</p>

            <form method="POST" action="{{ route('admin.data.delete.review') }}">
                @csrf
                <div class="data-catalog-list">
                    @foreach ($catalogs as $key => $catalog)
                        <label class="data-catalog-option">
                            <input type="checkbox" name="selected[]" value="{{ $key }}" @checked(in_array($key, (array) old('selected', []), true))>
                            <span class="data-catalog-copy">
                                <strong>{{ $catalog['label'] }}</strong>
                                <small>{{ $catalog['description'] }}</small>
                            </span>
                            <span class="data-catalog-count">{{ number_format($catalog['count'], 0, ',', '.') }} data</span>
                        </label>
                    @endforeach
                </div>
                @error('selected')<small class="data-management-field-error">{{ $message }}</small>@enderror
                @error('selected.*')<small class="data-management-field-error">{{ $message }}</small>@enderror
                <div class="data-management-actions">
                    <a class="text-link" href="{{ route('admin.data.backup') }}">Buat backup terlebih dahulu</a>
                    <button class="button button-danger" type="submit">Tinjau penghapusan</button>
                </div>
            </form>
        </section>

        <p class="data-management-back-link"><a href="{{ route('admin.dashboard') }}">← Kembali ke panel admin</a></p>
    </div>
@endsection
