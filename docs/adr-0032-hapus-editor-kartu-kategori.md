# ADR 0032: Hapus editor kartu, tabel saja

## Status
Diterima.

## Konteks
Halaman atur KU memiliki dua editor: tabel ringkasan yang bisa diedit langsung
dan kartu fieldset yang dibuka saat baris diklik. Pengelola meminta kartu lama
dihapus dan tombol "+ Tambah KU" dipindah ke kanan atas tabel.

## Keputusan
- Kartu fieldset diberi atribut `hidden`: tidak ditampilkan dan tidak bisa
  diedit, tetapi tetap ada di DOM sebagai penyimpan field tersembunyi supaya
  semua data (status pendaftaran, diskon, KU tambahan, dll.) tetap dikirim.
- Tombol "+ Tambah KU" dipindah ke toolbar kanan atas tabel; tombol lama di
  bawah daftar dihapus sehingga hanya ada satu tombol.
- Baris tabel tidak lagi membuka panel detail; tombol "Edit" dihapus karena
  pengeditan dilakukan langsung di tabel. Tombol "Hapus" dan "Tambah gelombang"
  tetap berfungsi.
- toolbar dan petunjuk diperbarui; tidak ada perubahan controller/model.

## Konsekuensi
- Satu-satunya editor yang terlihat adalah tabel; semua kontrol tetap
  bersinkron dengan field tersembunyi yang dikirim ke server.
- Build aset perlu diperbarui karena CSS/JS berubah.
