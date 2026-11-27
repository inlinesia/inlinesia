# ADR 0064: Tombol lihat atlet untuk admin pada event milik pengelola lain

## Status
Diterima.

## Konteks
Di dasbor bersama, event dengan 0 atlet milik admin menampilkan tombol kelola peserta, tetapi event dengan 100 atlet milik akun pengelola lain tidak menampilkan tombol "Lihat semua atlet" sebelum daftar dirilis. Penyebabnya: syarat tombol hanya memeriksa kepemilikan atau status rilis, padahal admin berhak mengelola semua event.

## Keputusan
- Syarat tombol pada kartu dasbor diperluas dengan `$isAdmin`, sehingga admin selalu melihat **Kelola peserta & pembayaran** dan **Lihat semua atlet** untuk event berstatus `approved` milik siapa pun.
- Label read-only hanya dipakai untuk akun bukan pemilik dan bukan admin yang mengakses daftar yang sudah dirilis.
- Tombol **Rilis daftar peserta** tetap hanya untuk pemilik event.
- Tes admin ditambahkan untuk event milik pengelola lain yang belum dirilis.

## Konsekuensi
- Tidak ada perubahan izin di controller; `canManage()` sudah mengizinkan admin sejak awal.
- Peserta biasa tetap tidak bisa melihat daftar sebelum dirilis.
- Perubahan hanya di view dasbor dan tes terkait.
