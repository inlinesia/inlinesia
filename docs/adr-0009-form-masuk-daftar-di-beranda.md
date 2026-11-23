# ADR 0009: Form Masuk/Daftar di Halaman Depan

## Status
Diterima.

## Konteks
Halaman depan hanya menampilkan tombol "Masuk" dan "Buat akun" di navbar serta
CTA di bagian bawah. Pemilik proyek meminta form login/pendaftaran ditampilkan
langsung di bawah judul hero agar pengunjung baru bisa langsung bergabung.

## Keputusan
- Menambahkan kartu form di `resources/views/publik/beranda.blade.php`, tepat di
  bawah judul/deskripsi hero, hanya untuk `@guest` (pengguna yang sudah masuk
  tidak melihatnya).
- Kartu memakai **dua tab radio CSS murni** (Masuk / Daftar) tanpa JavaScript:
  radio tersembunyi + label sebagai tab, dan `:checked ~ ...` untuk menampilkan
  panel yang sesuai. Tab aktif otomatis mengikuti `old('_form')` bila validasi
  gagal.
- Form **Masuk** mengirim ke `login.store` (email, kata sandi, ingat saya).
  Form **Daftar** mengirim ke `register.store` (nama, email, WhatsApp opsional,
  peran selain admin, kata sandi + konfirmasi). Keduanya memakai `@csrf`.
- Blok error hanya muncul pada panel yang sesuai, dibedakan lewat input
  tersembunyi `_form`.
- Membuat `resources/css/home-auth.css` dengan gaya Kartu Stitch (border tebal,
  bayangan stiker, pill, focus ring) dan token dengan fallback untuk tema lain;
  diimpor dari `app.css`.
- Menambah `tests/Feature/HomeAuthFormTest.php` (3 tes) untuk keberadaan form
  tamu, penyembunyian pada pengguna terautentikasi, dan tampilan error login.

## Konsekuensi
- Pengunjung bisa masuk/daftar langsung dari beranda tanpa membuka halaman
  terpisah; halaman `auth/login` dan `auth/register` tetap tersedia.
- Tidak ada controller atau rute baru — form memakai endpoint yang sudah ada.
- Tabs bergantung pada CSS, tetap bekerja meskipun JavaScript dimatikan.
