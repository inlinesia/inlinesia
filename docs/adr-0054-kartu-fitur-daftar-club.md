# ADR 0054: Kartu fitur daftar club di beranda

## Status
Diterima.

## Konteks
Tautan "LIHAT DIREKTORI", "Daftarkan club", dan "Daftarkan peserta" berada di bawah form login beranda sehingga terkesan di luar kartu fitur. Pemilik ingin tautan tersebut dipindahkan ke dalam kartu "FITUR YANG TERSEDIA" sebagai satu fitur "Daftar club & peserta", dengan gaya Ceria, tanpa menghilangkan latar titik-titik pada kartu.

## Keputusan
- Blok `.public-hero-directory` di bawah form login dihapus.
- Kartu fitur keempat ditambahkan: **04 — DIREKTORI / Daftar club & peserta**, mengarah ke `route('directory.index')`. Dari halaman direktori, pengunjung bisa mendaftarkan klub maupun peserta ke server.
- Gaya Ceria diterapkan pada label dan empat kartu fitur: border ink, radius, shadow stiker, font display, serta warna pastel per kartu (lemon, mint, coral, grape).
- Latar titik-titik `.public-hero-art::before` sengaja tidak diubah.
- Tes directory dan homepage diperbarui: homepage cukup memuat kartu direktori, tombol "Daftarkan club"/"Daftarkan peserta" diharapkan ada di halaman direktori.

## Konsekuensi
- Beranda lebih rapi; seluruh jalur fitur berkumpul di satu kartu.
- Tidak ada rute baru; direktori dan form pendaftaran klub/atlet tetap seperti semula.
- Tema selain Ceria memakai gaya kartu bawaan yang sudah ada.
