# ADR 0007: Ceria sebagai Tema Default

## Status
Diterima.

## Konteks
Setelah tema Ceria diganti dengan Stitch Kids Roller Skate Design System,
pemilik proyek meminta Ceria menjadi tema yang tampil secara default di semua
halaman. Sebelumnya fallback tema adalah `copper` di tiga tempat: skrip
anti-kedip pada kedua layout dan `theme-switcher.js`, sehingga pengunjung baru
(melocalStorage kosong) melihat tema tembaga.

## Keputusan
- `DEFAULT_THEME` di `resources/js/theme-switcher.js` diubah dari `copper`
  menjadi `ceria`.
- Fallback pada skrip anti-kedip di `layouts/public.blade.php` dan
  `layouts/admin.blade.php` diubah menjadi `ceria`.
- Radio "Ceria" di halaman setting tema menjadi pilihan awal saat tidak ada
  `old('theme')`; radio "Tembaga" tidak lagi di-default-kan.
- Daftar tema yang tersedia tidak berubah: glass, copper, neural, topic, ceria.

## Konsekuensi
- Pengunjung baru dan sesi tanpa preferensi tersimpan langsung melihat tema
  Ceria di halaman publik maupun panel.
- Pengunjung yang sudah menyimpan pilihan lain di `localStorage` tetap
  memakai pilihannya sampai mereka mengubahnya atau menghapus preferensi.
- Tidak ada perubahan rute, logika bisnis, atau database.
