# ADR 0008: Judul Halaman Warna-warni dengan Efek Pop

## Status
Diterima.

## Konteks
Sistem desain Stitch memiliki judul `title-rainbow`: setiap kata berada dalam
`<span>` dengan warna bergilir (kuning, biru, mint, oranye, ungu, pink), garis
tebal berwarna tinta, bayangan 3D, dan efek "pop" saat pointer diarahkan.
Implementasi sebelumnya hanya menerapkan gaya ini pada hero beranda, sehingga
judul halaman lain masih polos. Pemilik proyek meminta efek tersebut diterapkan
di **setiap judul halaman**.

## Keputusan
- Membuat `resources/js/rainbow-titles.js` yang membungkus setiap kata dalam
  `h1`, `h2#listing-heading`, dan elemen `[data-rainbow-title]` dengan
  `<span class="skate-word c-...">` warna bergilir. Berjalan di kedua layout,
  aman dijalankan ulang (penanda `data-rainbow-processed`), dan hanya
  memodifikasi node teks sehingga markup Blade yang sudah ada tetap utuh.
- Mendaftarkan skrip tersebut ke `vite.config.js` dan kedua layout.
- Menambahkan gaya di `ceria-theme.css`: warna per kata, `-webkit-text-stroke`,
  bayangan 3D, dan transisi `transform` saat hover (`translateY(-5px)
  rotate(-3deg) scale(1.1)` dengan easing bounce). `prefers-reduced-motion`
  tetap dihormati.
- Mengecualikan `.skate-word` dari dua aturan lama yang memaksa warna seragam
  (judul hero dan teks di dalam kartu) agar warna per kata tidak tertimpa.

## Konsekuensi
- Seluruh judul halaman, publik maupun panel, sekarang warna-warni dan merespons
  hover. Tidak perlu menyunting puluhan file Blade satu per satu.
- Halaman yang tidak memakai tema Ceria tidak terpengaruh secara visual.
- Efek bergantung pada JavaScript; bila JS mati, judul tetap tampil sebagai
  teks biasa yang terbaca.
