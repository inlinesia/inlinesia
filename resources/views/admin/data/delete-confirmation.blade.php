@extends('layouts.admin')

@section('title', 'Konfirmasi penghapusan — Inlinesia')
@section('section', 'Konfirmasi penghapusan')

@section('content')
    <div class="page-content">
        <section class="welcome-row">
            <div>
                <div class="eyebrow"><span class="eyebrow-line"></span> TINJAUAN TERAKHIR</div>
                <h1>Konfirmasi penghapusan</h1>
                <p class="page-subtitle">Periksa kembali katalog dan jumlah data. Jika jumlah berubah sebelum tindakan final, halaman akan meminta Anda meninjau ulang.</p>
            </div>
        </section>

        <section class="panel data-management-card is-danger">
            <div class="panel-heading">
                <div>
                    <span class="section-kicker">AKSI PERMANEN</span>
                    <h2>Ringkasan penghapusan</h2>
                </div>
            </div>
            <ul class="data-delete-summary">
                @foreach ($snapshot as $key => $catalog)
                    <li>
                        <span class="data-delete-summary-copy">
                            <strong>{{ $catalog['label'] }}</strong>
                            <small>{{ $catalog['description'] }}</small>
                        </span>
                        <span class="data-delete-summary-count">{{ number_format($catalog['count'], 0, ',', '.') }} data</span>
                        <input type="hidden" form="confirm-delete-form" name="selected[]" value="{{ $key }}">
                    </li>
                @endforeach
            </ul>
            <p class="data-management-warning">Tindakan ini permanen dan tidak bisa dibatalkan. Kembali untuk membuat backup jika Anda belum memilikinya.</p>

            <div class="data-management-actions">
                <a class="text-link" href="{{ route('admin.data.delete') }}">Kembali dan ubah pilihan</a>
                <form id="confirm-delete-form" method="POST" action="{{ route('admin.data.delete.destroy') }}" data-confirm="Penghapusan akan dilakukan sekarang dan tidak dapat dibatalkan. Lanjutkan?">
                    @csrf
                    <label class="data-management-confirm">
                        <input type="checkbox" name="confirmed" value="1" required>
                        <span>Saya sudah memeriksa daftar dan menyetujui penghapusan permanen.</span>
                    </label>
                    <button class="button button-danger" type="submit">Hapus data terpilih</button>
                </form>
            </div>
        </section>
    </div>
@endsection
