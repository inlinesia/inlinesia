@extends('layouts.public')

@section('title', 'Lisensi & Harga — Inlinesia')
@section('description', 'Ketentuan lisensi penggunaan dan pilihan harga Inlinesia, platform pengelolaan event sepatu roda.')
@section('body-class', 'public-license')

@section('content')
<main class="profile-page public-license-page">
    <header class="event-registration-heading">
        <div>
            <span class="account-eyebrow">INFORMASI LISENSI</span>
            <h1>Lisensi &amp; Harga</h1>
            <p>Desain dan aplikasi Inlinesia adalah karya © 2026 Damai digital creative. Halaman ini berisi aturan pakai dan pilihan harga untuk pembeli yang ingin memakai sistem ini untuk kejuaraan sepatu roda mereka.</p>
        </div>
        <a class="account-edit-link" href="{{ route('home') }}">← Kembali ke beranda</a>
    </header>

    <section class="account-table-card license-section" aria-labelledby="license-comparison-heading">
        <h2 id="license-comparison-heading">Perbandingan paket event</h2>
        <div class="license-price-grid license-comparison-grid">
            <article class="license-price-card license-tier-card">
                <span class="license-price-tag">GRATIS</span>
                <h3>Iklan event — Gratis (Rp 0)</h3>
                <p class="license-price">Rp 0</p>
                <p>Untuk pengelola yang hanya ingin memasang iklan dan menyebarkan informasi kejuaraan.</p>
                <p class="license-tier-label">Yang bisa dilakukan</p>
                <ul class="license-tier-list license-tier-list--yes">
                    <li>Tampil di beranda pada Agenda kejuaraan.</li>
                    <li>Tampil di halaman Jelajahi kejuaraan (/lomba) sebagai informasi event.</li>
                    <li>Rincian event sederhana: nama, tanggal, venue, alamat, skala, deskripsi singkat, WhatsApp pengelola, dan tautan grup WA.</li>
                    <li>Event ditampilkan setelah disetujui admin.</li>
                </ul>
                <p class="license-tier-label">Pembatasan</p>
                <ul class="license-tier-list license-tier-list--no">
                    <li>Tidak dapat membuka pendaftaran atlet.</li>
                    <li>Tidak dapat mengatur KU (kriteria usia).</li>
                    <li>Tidak dapat mengatur perlombaan.</li>
                    <li>Tidak dapat mengelola peserta.</li>
                    <li>Tidak dapat mengelola keuangan.</li>
                </ul>
            </article>
            <article class="license-price-card license-tier-card">
                <span class="license-price-tag">BERBAYAR</span>
                <h3>Kelola penuh — Berbayar</h3>
                <p class="license-price">Harganya mengikuti pilihan di bawah</p>
                <p>Untuk organizer yang menjalankan kejuaraan lengkap dengan sistem Inlinesia.</p>
                <p class="license-tier-label">Yang bisa dilakukan</p>
                <ul class="license-tier-list license-tier-list--yes">
                    <li>Semua fitur iklan event pada paket gratis.</li>
                    <li>Membuka dan mengelola pendaftaran atlet.</li>
                    <li>Mengatur KU (kriteria usia) dan kategori.</li>
                    <li>Mengatur perlombaan dan jadwalnya.</li>
                    <li>Mengelola data peserta.</li>
                    <li>Mengelola keuangan dan pembayaran.</li>
                    <li>Aktif setelah event disetujui admin.</li>
                </ul>
                <p class="license-tier-note">Pilih harga sekali beli (Starter–Semua Lisensi) atau langganan (Basic–Organisasi/Cabang) pada bagian di bawah.</p>
            </article>
        </div>
    </section>

    <section class="account-table-card license-section" aria-labelledby="license-price-heading">
        <h2 id="license-price-heading">Pilih cara pakai &amp; harga</h2>
        <div class="license-price-grid">
            <article class="license-price-card">
                <span class="license-price-tag">1X BELI</span>
                <h3>Starter</h3>
                <p class="license-price">Rp 3.500.000–7.500.000</p>
                <p>Untuk satu event atau klub kecil yang baru mulai.</p>
            </article>
            <article class="license-price-card">
                <span class="license-price-tag">1X BELI</span>
                <h3>Standard</h3>
                <p class="license-price">Rp 7.500.000–15.000.000</p>
                <p>Untuk organizer dengan beberapa kejuaraan dalam satu tahun.</p>
            </article>
            <article class="license-price-card">
                <span class="license-price-tag">1X BELI</span>
                <h3>Plus</h3>
                <p class="license-price">Rp 15.000.000–30.000.000+</p>
                <p>Untuk kebutuhan yang lebih luas dengan dukungan lanjutan.</p>
            </article>
            <article class="license-price-card">
                <span class="license-price-tag">1X BELI</span>
                <h3>Semua Lisensi (Full)</h3>
                <p class="license-price">Rp 49.000.000</p>
                <p>Sekali bayar untuk seluruh paket lisensi.</p>
            </article>
            <article class="license-price-card">
                <span class="license-price-tag">LANGGANAN</span>
                <h3>Basic</h3>
                <p class="license-price">Rp 149.000–299.000/bln</p>
                <p>atau Rp 1.490.000–2.990.000/thn.</p>
            </article>
            <article class="license-price-card">
                <span class="license-price-tag">LANGGANAN</span>
                <h3>Pro</h3>
                <p class="license-price">Rp 299.000–599.000/bln</p>
                <p>atau Rp 2.990.000–5.990.000/thn.</p>
            </article>
            <article class="license-price-card">
                <span class="license-price-tag">LANGGANAN</span>
                <h3>Organisasi/Cabang</h3>
                <p class="license-price">Rp 599.000–1.500.000+/bln</p>
                <p>atau Rp 5.990.000–15.000.000+/thn.</p>
            </article>
        </div>
        <p class="license-note">Langganan tahunan hemat sekitar 15–20%. Support dan update tahunan Rp 1.500.000–5.000.000. Tersedia add-on onboarding, migrasi data, dan kustomisasi sesuai kebutuhan.</p>
    </section>

    <section class="account-table-card license-section" aria-labelledby="license-terms-heading">
        <h2 id="license-terms-heading">Lisensi penggunaan</h2>
        <ol class="license-terms">
            <li>Seluruh desain, kode program, teks, dan gambar dalam aplikasi Inlinesia adalah hak cipta © 2026 Damai digital creative, dilindungi Undang-Undang Hak Cipta No. 28 Tahun 2014.</li>
            <li><strong>Lisensi 1x Beli (Source Code):</strong> Pembeli boleh memakai dan mengubah untuk 1 (satu) website di organisasinya sendiri. Pembeli DILARANG menyalin, menjual kembali, menyebar, atau memberikan source code kepada pihak ketiga.</li>
            <li><strong>Lisensi Langganan (SaaS):</strong> Pembeli hanya boleh mengakses aplikasi selama langganan aktif dan dibayar. Jika berhenti membayar, akses akan dihentikan.</li>
            <li>Pembeli DILARANG menghilangkan tulisan copyright © 2026 Damai digital creative dan mengubah nama pembuat.</li>
            <li>Untuk website ke-2 dan seterusnya, pembeli wajib membeli lisensi tambahan.</li>
            <li>Pelanggaran lisensi dapat dikenakan sanksi sesuai Undang-Undang Hak Cipta No. 28 Tahun 2014.</li>
        </ol>
    </section>

    <section class="account-table-card license-section" aria-labelledby="license-contact-heading">
        <h2 id="license-contact-heading">Kontak lisensi</h2>
        <p>Untuk pertanyaan lisensi, harga, atau kerja sama pengelolaan event, hubungi <a class="license-contact-link" href="https://wa.me/6285800006248?text={{ rawurlencode('Halo Damai digital creative, saya ingin bertanya tentang lisensi Inlinesia.') }}">Damai digital creative</a> di 085800006248.</p>
    </section>
</main>
@endsection
