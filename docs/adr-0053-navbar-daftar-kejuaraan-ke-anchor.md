# ADR 0053: Navbar "Daftar kejuaraan" menggulir ke agenda beranda

## Status
Diterima.

## Konteks
Tombol navbar "Daftar kejuaraan" mengarah ke halaman `/lomba`, padahal di beranda sudah ada bagian "AGENDA KOMPETISI — Kejuaraan yang akan datang". Pemilik ingin tombol navbar menggulir ke bagian tersebut, sementara halaman lengkap `/lomba` tetap dicapai lewat tombol "Lihat semua lomba" di bagian agenda.

## Keputusan
- Tautan navbar "Daftar kejuaraan" diubah menjadi `route('home').'#kejuaraan'`.
- Tombol "Lihat semua lomba" pada heading agenda tetap memakai `route('events.index')`.
- Bagian `#kejuaraan` diberi `scroll-margin-top: 96px` supaya judulnya tidak tertutup navbar sticky saat anchor diklik.

## Konsekuensi
- Navbar tetap menampilkan tautan publik lengkap hanya di halaman beranda, sesuai keputusan sebelumnya.
- Halaman `/lomba` tidak hilang; aksesnya sejalan dengan tombol eksplisit "Lihat semua lomba".
- Tidak ada perubahan rute, controller, atau data.
