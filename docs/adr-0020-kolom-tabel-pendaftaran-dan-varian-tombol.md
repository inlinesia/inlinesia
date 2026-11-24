# ADR 0020: Kolom tabel pendaftaran dan varian tombol Kids Skate

## Status
Diterima.

## Konteks
Tabel "Pendaftaran saya" baru saja dibuat dengan delapan kolom. Pengelola
meminta penyesuaian: header "Tombol" menjadi "Aksi" dengan tema dari
`kids_skate_controls_form_inputs_library` (`controls-1.button-A.color
variants`), penambahan kolom No./NIK/Gender, tanggal lahir format `hh-bb-tttt`,
kategori usia disingkat "KU", serta kolom mata lomba diubah menjadi grup
"Kategori" dengan sub-header mata lomba dari event pertama dan centang pada
baris data.

## Keputusan
- Header menjadi dua baris: **No.**, **Aksi**, **Nama panggilan**,
  **Nama lengkap**, **NIK**, **Gender**, **Tanggal lahir**, **KU**
  (kategori usia), grup **Kategori** yang berisi satu sub-header per
  mata lomba milik event pertama pada daftar pendaftaran, **Harga**, dan
  **Status bayar**.
- Baris data menampilkan nomor urut, `maskedIdentity()` untuk NIK (privasi),
  gender Putra/Putri, tanggal lahir `d-m-Y`, KU, serta checkbox centang
  (read-only) untuk tiap mata lomba yang diikuti peserta.
- Tombol aksi memakai varian warna `controls-1.button-A`:
  `btn-primary` coral (Edit data), `btn-secondary` sky (Lihat daftar peserta,
  Urutan lomba, Tambah atlet), `btn-accent` lemon (Kirim bukti),
  `btn-grape` grape (WhatsApp), dan `btn-berry` berry (pembatalan).
- Gaya diterapkan di `ceria-theme.css` dengan token tema yang sudah ada;
  `account-portal.css` merapikan sel nomor dan sel centang.
- Tes `GuestDashboardTest` diperbarui untuk header baru, checkbox, dan
  memastikan header lama tidak muncul.

## Konsekuensi
- Tabel lebih lebar; wrapper `account-table-wrap` tetap menyediakan scroll
  horizontal pada layar kecil.
- Sub-header lomba berasal dari event pertama saja, sesuai permintaan; bila
  ada pendaftaran dari event lain dengan mata lomba berbeda, kolom centangnya
  mengikuti daftar event pertama.
- Checkbox bersifat tampilan (disabled); pengubahan mata lomba tetap lewat
  tombol Edit data.
- Hanya Blade, CSS, tes, dan build aset yang berubah.
