# ADR 0067: Tema Ceria dan kontras halaman urutan serta hari lomba

## Status
Diterima.

## Konteks
Halaman `event-race/order` dan `event-race/day` memakai kelas `.event-race-page`, tetapi variabel warna di `event-race.css` masih mengarah ke tema gelap (teks putih/abu di atas latar transparan). Karena itu label grup, meta babak, nomor lane, dan paragraf kontrol sulit dibaca. Tab KU serta tombol hasil juga belum memakai gaya Ceria.

## Keputusan
- `.event-race-page` dimasukkan ke daftar kontainer override teks Ceria sehingga seluruh judul, paragraf, label, span, dan sel tabel memakai `--text-primary`.
- Token `--race-line`, `--race-chip`, `--race-win`, `--race-warn`, `--race-income`, dan `--race-expense` ditimpa dengan token Ceria.
- Kartu ringkasan, kontrol, peringatan, catatan, dan babak memakai latar `--bg-surface` dengan border soft; nomor lane memakai lemon + border ink.
- Tab KU/mata lomba dan tombol hasil (Menang, Tidak muncul, DNF, DSQ, Ulangi, Tunda) memakai border ink, radius pill, shadow stiker, font display, serta warna Ceria per varian.
- Tombol **Lihat tampilan peserta**, **Siapkan peserta**, dan aksi grup lain mengikuti aturan tombol `.event-race-page` yang sudah dibuat sebelumnya.

## Konsekuensi
- Tidak ada perubahan markup, rute, atau logika pertandingan.
- Tema selain Ceria tetap memakai variabel warna bawaan.
- Teks yang tadinya putih kini gelap di atas kartu terang, sehingga kontras membaik.
