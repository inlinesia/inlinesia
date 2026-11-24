# ADR 0019: Tabel "Pendaftaran saya" di dasbor peserta

## Status
Diterima.

## Konteks
Bagian "Pendaftaran saya" pada dasbor peserta (`verified/dashboard.blade.php`)
menampilkan satu kartu per atlet dengan informasi dan tombol yang tersebar
di tiga baris. Dengan 100 atlet simulasi, tampilan menjadi panjang dan sulit
dibandingkan. Pengelola meminta tampilan ditata sebagai tabel dengan kolom:
tombol, nama panggilan, nama lengkap, kategori, tahun/usia, mata lomba, harga,
dan status bayar.

## Keputusan
- Mengganti kartu `.guest-registration-card` pada bagian "Pendaftaran saya"
  dengan `<table class="account-table guest-registration-table">` di dalam
  `.account-table-wrap` (sudah mendukung scroll horizontal di layar kecil).
- Delapan kolom sesuai permintaan: **Tombol**, **Nama panggilan**,
  **Nama lengkap**, **Kategori**, **Tahun / usia**, **Mata lomba**, **Harga**,
  **Status bayar**.
- Kolom Tombol mempertahankan seluruh aksi lama: lihat daftar peserta,
  urutan lomba, edit data, ajukan/batalkan pendaftaran, kirim bukti,
  konfirmasi via WhatsApp, dan tambah atlet. Kolom Harga memuat tarif,
  gelombang harga, sisa tagihan, dan rekening transfer. Kolom Status bayar
  memuat status pembayaran, status pendaftaran, serta catatan perubahan dan
  pembatalan.
- Modal edit (`verified.events.participants.ubah`) dipindahkan ke loop
  terpisah setelah `</table>` supaya tidak menghasilkan markup dialog di
  dalam `<tbody>`.
- CSS tambahan di `account-portal.css` membatasi lebar sel tombol dan
  merapikan badge/catatan di dalam tabel; gaya tabel Ceria yang sudah ada
  tetap dipakai.
- Usia dihitung dari tanggal lahir ke tanggal mulai event dan dibulatkan ke
  bilangan bulat (Carbon mengembalikan desimal bila tidak di-cast).
- Tes `GuestDashboardTest` diperbarui untuk memastikan header tabel dan kelas
  tabel tampil.

## Konsekuensi
- Tampilan dasbor peserta lebih padat dan bisa discroll horizontal saat
  kolom tidak muat; struktur data dan semua aksi tidak berubah.
- Perubahan hanya pada Blade dasbor, CSS, dan tes — tidak ada perubahan
  controller, rute, atau schema.
- Build aset perlu diperbarui karena `account-portal.css` berubah.
