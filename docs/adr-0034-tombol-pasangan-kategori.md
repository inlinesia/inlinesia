# ADR 0034: Tombol pasangan KU di tabel editor

## Status
Diterima.

## Konteks
Sebelumnya kartu KU memiliki tombol "Ya, buat KU" untuk membuat KU yang sama
bagi lawan jenis. Kartu kini disembunyikan, sehingga tombol itu tidak bisa
diakses pengelola.

## Keputusan
- Menambahkan tombol **Pasangan** di kolom Aksi tabel editor, hanya untuk KU
  dengan gender **Putra** atau **Putri** (tidak muncul untuk Campuran).
- Klik tombol diteruskan ke tombol `data-create-counterpart` pada fieldset
  tersembunyi, sehingga logika lama dipakai: mengkloning KU, mengganti gender
  ke lawan jenis, dan mencegah duplikat pasangan.
- Tombol bergaya sky (biru) mengikuti token Ceria.
- Tes `CategoryPricingTest` memastikan struktur pendukung tombol hadir.

## Konsekuensi
- Pengelola bisa membuat pasangan KU langsung dari tabel tanpa membuka kartu.
- Tidak ada perubahan controller/model; KU pasangan tetap disimpan sebagai
  aturan `category_rules` baru.
- Build aset perlu diperbarui karena JS/CSS berubah.
