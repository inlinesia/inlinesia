# ADR 0036: Kontras teks hero dasbor admin tema Ceria

## Status
Diterima.

## Konteks
Kalimat "Kelola kejuaraan, atlet, dan hasil lomba dengan lebih rapi—dari
pendaftaran sampai podium." pada `.hero-copy p` memakai warna `#c5c0dd` dari
`dashboard.css`, sehingga sulit dibaca di atas gradien `--grad-sunny`
(lemon→coral) tema Ceria.

## Keputusan
- Menambah aturan Ceria untuk `.app-shell .hero-kicker`, `.hero-copy p`,
  `.hero-link`, `.hero-copy h2` memakai `var(--text-primary)` dengan `!important`.
- Dot kicker diganti grape; tautan "Jelajahi kejuaraan" ditebalkan dan
  digarisbawahi agar terlihat sebagai tautan.
- Menambah `tests/Feature/AdminDashboardThemeTest.php` untuk memastikan struktur
  hero dan kalimat tersebut hadir.

## Konsekuensi
- Teks hero menjadi gelap di atas latar cerah; kontras naik.
- Hanya CSS + tes; tidak ada perubahan Blade/controller.
- Aset build `app-DCqETDxD.css` sudah disalin ke app dan docroot server.
