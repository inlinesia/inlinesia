# ADR 0016: Gradien playful pada tombol aksi pengelolaan event di dasbor

## Status
Diterima.

## Konteks
Tombol aksi di halaman dasbor peserta (`verified/dashboard.blade.php`,
`.guest-event-actions`) — Kelola peserta & pembayaran, Rilis daftar peserta,
Buka/Tutup pendaftaran, Ubah/Hapus event, Atur kategori & batas peserta,
Atur perlombaan, Hari lomba, dan Kelola keuangan — semuanya memakai kelas
`.account-edit-link`. Aturan ceria yang ada hanya mewarnai teks ungu, tanpa
latar gradien, sehingga permintaan tema Kids Skate (design token library 1.b:
`--grad-sunny`, `--grad-sky-roll`, `--grad-berry-pop`) tidak terpenuhi.
Gradient yang ditambahkan sebelumnya menyasar `.button-primary` dan
`.submission-approve`, bukan `.account-edit-link`.

## Keputusan
- Menambah blok CSS di `resources/css/ceria-theme.css` yang mensasar
  `.guest-event-actions :is(.account-edit-link, .event-delete-button)`:
  pil shaped, border ink, shadow sticker, teks putih, font display — agar
  tampil sebagai tombol, bukan tautan teks.
- Membedakan gradien per aksi lewat selector atribut `href`/`action` (tanpa
  mengubah markup Blade): `--grad-sunny` (lemon→coral) untuk Rilis daftar,
  Ubah event, Atur kategori, Kelola keuangan; `--grad-sky-roll` (sky→mint)
  untuk Kelola peserta, Buka/Tutup pendaftaran, Atur perlombaan;
  `--grad-berry-pop` (berry→grape) untuk Hapus event dan Hari lomba.
- Menambah efek hover/focus (brightness + sticker shadow + sedikit naik).
- Build aset (`npm run build`) lalu menyalin `public/build` ke server pada
  direktori aplikasi dan docroot (keduanya dipakai produksi).

## Konsekuensi
- Tombol aksi pengelolaan event di dasbor tampil warna-warni sesuai token
  Kids Skate; verifikasi computed style di browser produksi menunjukkan
  ketiga gradien terpasang dengan teks putih dan radius pill.
- Tidak ada perubahan markup/Blade, logika controller, atau rute; hanya CSS.
- Build hash CSS berubah menjadi `app-DEo9FpEG.css`, sehingga docroot build
  wajib diperbarui bersamaan dengan source CSS.
