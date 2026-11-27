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
            <p>Desain dan aplikasi Inlinesia adalah karya © 2026 Damai digital creative. Halaman ini berisi aturan pakai, pilihan paket event, dan ruang kerja sama sponsor. Nominal harga akan diumumkan ketika sudah ditetapkan.</p>
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
                <p class="license-price">Harga akan diumumkan</p>
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
                <p class="license-tier-note">Nominal dan skema pembayaran paket kelola penuh akan diumumkan pada halaman ini.</p>
            </article>
        </div>
    </section>

    <section class="account-table-card license-section" aria-labelledby="license-price-heading">
        <h2 id="license-price-heading">Harga &amp; kerja sama</h2>
        <div class="license-price-grid">
            <article class="license-price-card">
                <span class="license-price-tag">BERBAYAR</span>
                <h3>Kelola penuh</h3>
                <p class="license-price">Harga akan diumumkan</p>
                <p>Nominal paket pengelolaan event masih disiapkan. Hubungi kami untuk penawaran dan ketersediaan.</p>
            </article>
            <article class="license-price-card">
                <span class="license-price-tag">SPONSOR</span>
                <h3>Sponsor website &amp; event</h3>
                <p class="license-price">Konsep — harga belum ditetapkan</p>
                <p>Ruang sponsor bagi brand yang ingin mendukung platform Inlinesia dan/atau kejuaraan tertentu.</p>
                <ul class="license-tier-list license-tier-list--yes">
                    <li>Logo pada halaman beranda dan Jelajahi kejuaraan.</li>
                    <li>Penempatan pada kartu event yang disponsori.</li>
                    <li>Pencantuman nama sponsor pada materi dan daftar peserta event.</li>
                    <li>Kerja sama dapat berlaku per event atau per periode tertentu.</li>
                </ul>
            </article>
        </div>
        <p class="license-note">Harga akhir paket kelola penuh maupun sponsor akan diumumkan melalui halaman ini. Untuk sementara, gunakan kontak di bawah untuk mendiskusikan kerja sama.</p>
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

    <section class="account-table-card license-section" aria-labelledby="license-ad-heading">
        <h2 id="license-ad-heading">Iklan &amp; sponsor</h2>
        <div class="license-price-grid">
            <article class="license-price-card">
                <span class="license-price-tag">WEBSITE</span>
                <h3>Sponsor website</h3>
                <p class="license-price">Mulai Rp 250.000</p>
                <p>Pasang banner atau logo brand Anda di beranda dan halaman Jelajahi kejuaraan.</p>
                <a class="account-edit-link" href="{{ route('sponsor.web.form') }}">Ajukan sponsor website <span aria-hidden="true">↗</span></a>
            </article>
            <article class="license-price-card">
                <span class="license-price-tag">EVENT</span>
                <h3>Sponsor event</h3>
                <p class="license-price">Mulai Rp 150.000/event</p>
                <p>Tampil pada kartu event dan halaman pendaftaran atlet untuk kejuaraan tertentu.</p>
                <a class="account-edit-link" href="{{ route('sponsor.event.form') }}">Ajukan sponsor event <span aria-hidden="true">↗</span></a>
            </article>
        </div>
    </section>

    <section class="account-table-card license-section" aria-labelledby="license-contact-heading">
        <h2 id="license-contact-heading">Kontak lisensi</h2>
        <p>Untuk pertanyaan lisensi, harga, atau kerja sama pengelolaan event, hubungi <a class="license-contact-link" href="https://wa.me/6285800006248?text={{ rawurlencode('Halo Damai digital creative, saya ingin bertanya tentang lisensi Inlinesia.') }}">Damai digital creative</a> di 085800006248.</p>
    </section>
</main>
@endsection
