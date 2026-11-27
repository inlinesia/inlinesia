# ADR 0060: Tombol eksplisit "Lihat semua atlet" untuk pemilik event

## Status
Diterima.

## Konteks
Pemilik event sebenarnya sudah bisa membuka daftar peserta lewat tombol "Kelola peserta & pembayaran" di dasbor, tetapi label itu tidak jelas sebagai tempat melihat seluruh atlet. Pada halaman Jelajahi kejuaraan, tautan "Lihat peserta" juga baru muncul setelah daftar dirilis, sehingga pemilik tidak menemukan jalur langsung sebelum rilis.

## Keputusan
- Dasbor pemilik menambah tombol eksplisit **Lihat semua atlet** yang mengarah ke `events.registrations.index`, berdampingan dengan "Kelola peserta & pembayaran".
- Halaman Jelajahi kejuaraan menampilkan tautan **Lihat semua atlet** untuk pemilik event dan admin walau daftar belum dirilis; untuk akun lain tetap mengikuti aturan rilis dengan label "Lihat peserta".
- Tombol hanya tersedia untuk event paket berbayar karena event iklan tidak mengelola atlet.
- Tes dasbor dan listing diperbarui.

## Konsekuensi
- Tidak ada endpoint atau izin baru; tombol memakai rute dan authorization yang sudah ada.
- Peserta lain tetap tidak bisa melihat daftar sebelum pemilik merilisnya.
- Perubahan hanya di dua view dan tes terkait.
