# ADR 0011: Ceria untuk Seluruh Panel Admin dan Identitas "Inlinesia"

## Status
Diterima.

## Konteks
Tema Ceria sudah menjadi default, tetapi gaya `glass-theme.css` masih
menggelapkan sidebar, topbar, panel, dan tabel di panel admin. Selain itu,
kartu tema "Tembaga" masih labelled sebagai "Tampilan bawaan Inlinesia",
sehingga identitas default Inlinesia tidak sinkron dengan tema aktif.

## Keputusan
- Menambah blok override pada `resources/css/ceria-theme.css` untuk seluruh
  `.app-shell`: sidebar, nav link aktif, toggle, avatar, topbar, breadcrumb,
  panel/kartu, input/select, tabel, tombol, flash, hero banner, dan status.
  Semua memakai token Ceria (kuning, coral, grape, border tinta, bayangan
  stiker), menggantikan aturan gelap glass-theme.
- Mengubah deskripsi kartu "Tembaga": kata "Tampilan bawaan Inlinesia" dihapus.
- Kartu "Ceria" kini menyebut "tampilan bawaan Inlinesia" dan menyatakan efek
  berlaku di publik **dan panel admin**.
- Menambah assertion pada `ThemeSettingTest` untuk label tersebut.

## Konsekuensi
- Panel admin kini terang dan konsisten dengan halaman publik saat tema Ceria
  aktif (default).
- Karena tema Ceria adalah default dan pengunjung tidak dapat menggantinya,
  seluruh tampilan Inlinesia mengikuti Ceria.
- Tidak ada perubahan rute, logika bisnis, atau database.
