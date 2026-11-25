# ADR 0029: Editor mode tabel pada halaman atur KU

## Status
Diterima.

## Konteks
Halaman atur KU (Kelompok Usia) menampilkan semua fieldset pengaturan secara
berurutan, sehingga pengelola harus menggulir panjang untuk melihat ringkasan
setiap KU. Pengelola meminta editor mode tabel seperti tabel "Pendaftaran saya"
pada dasbor peserta, dengan baris yang bisa dibuka untuk mengedit.

## Keputusan
- Menambahkan tabel ringkasan `.category-editor-table` di atas fieldset:
  No., Aksi, Nama KU, Gender, Kelayakan, Batas peserta, dan Harga.
- Setiap baris mereferensikan fieldset KU yang bersangkutan. Klik baris atau
  tombol **Edit** membuka detail KU itu dan menutup yang lain; klik **Hapus**
  meneruskan klik ke tombol hapus asli di dalam fieldset, sehingga logika
  penghapusan lama (termasuk penghapusan KU turunan) tetap berjalan.
- Fieldset tetap berada di dalam `[data-category-rule-list]` dan memakai
  struktur/name field yang sama — JS lama untuk gelombang tarif, pasangan
  gender, tahun lahir, dan reindex tidak diubah.
- Ringkasan tabel dibangun dan disegarkan dari fieldset lewat
  `MutationObserver` serta event `input`/`change`, sehingga KU baru hasil
  tombol "+ Tambah KU" langsung muncul sebagai baris.
- CSS tabel memakai token Ceria yang sama dengan tabel dasbor (header lemon,
  baris hover sky, baris aktif lemon, pill coral/berry).
- Tes regresi ditambahkan di `CategoryPricingTest` untuk memastikan placeholder
  tabel dan header hadir.

## Konsekuensi
- Satu KU dibuka dalam satu waktu; ringkasan seluruh KU terlihat sekaligus
  tanpa menggulir fieldset.
- Perubahan murni tampilan: tidak ada perubahan controller, rute, validasi,
  atau struktur data `category_rules`.
- Aset JS/CSS berubah sehingga `npm run build` wajib sebelum push.
