# ADR 0004: Tema Ceria Ramah Anak dengan Stiker Sepatu Roda

## Status
Diterima.

## Konteks
Halaman setting tema hanya menyediakan empat tema (glass, copper, neural, topic).
Pemilik proyek meminta tema baru yang ramah anak: cerah, banyak stiker sepatu
roda, dan hanya tampil di halaman yang bisa diakses publik. Hasil diskusi:
tema baru bernama "Ceria" (bukan mengganti tema lama), palet pastel cerah,
stiker berupa emoji + CSS, tanpa animasi, dan tidak tampil di area
terautentikasi/admin.

## Keputusan
- Menambah tema kelima `ceria` ke `ThemeSettingController`, daftar tema di
  `theme-switcher.js`, dan daftar warna di kedua layout (script anti-kedip).
- Menambah kartu opsi "Ceria" beserta pratinjau di
  `resources/views/admin/settings/theme.blade.php` dan `theme-settings.css`.
- Membuat `resources/css/ceria-theme.css`: palet pastel (kuning, biru langit,
  pink, mint, ungu), sudut kartu membulat, teks gelap agar tetap terbaca, serta
  override terhadap aturan global `glass-theme.css` yang menggelapkan hero dan
  memutihkan teks kartu.
- Stiker emoji (🛼 ⛑️ 🏅 ✨) ditempatkan lewat pseudo-elemen CSS di header,
  logo, hero, kartu event, kartu fitur, kartu direktori/akun, dan footer.
  **Statis** — tidak ada `@keyframes` maupun animasi.
- Stiker hanya aktif pada `body.ceria-sticker-page`, yang ditambahkan
  `layouts/public.blade.php` untuk rute publik saja (beranda, daftar lomba,
  direktori publik, masuk, daftar). Dasbor, pendaftaran, dan panel admin
  tidak mendapat stiker.
- Menambah `tests/Feature/ThemeSettingTest.php` (4 tes) untuk opsi tema,
  validasi simpan, penolakan tema asing, dan pembatasan stiker ke halaman publik.

## Konsekuensi
- Pengguna dapat memilih "Ceria" di halaman setting tema; pilihan tersimpan di
  `localStorage` seperti tema lain (tidak ada perubahan skema database).
- Warna tema berlaku di seluruh halaman seperti tema lain; hanya stiker yang
  dibatasi ke halaman publik sesuai permintaan.
- Perubahan bersifat presentasional; tidak ada logika bisnis, rute, atau data
  yang diubah.
- Perlu `npm run build` agar aset CSS/JS terbaru ikut terpakai (public/build
  tidak disimpan di Git).
