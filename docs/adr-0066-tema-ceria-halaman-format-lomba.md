# ADR 0066: Tema Ceria dan kontras pada halaman format perlombaan

## Status
Diterima.

## Konteks
Halaman `verified/events/race/format.blade.php` untuk akun non-admin memakai `layouts.public`, sehingga aturan Ceria yang berscope `.app-shell` tidak diterapkan. Akibatnya beberapa tombol masih memakai gaya polos, input berlatar gelap dari tema glass, dan teks catatan berwarna krem di atas latar terang sehingga sulit dibaca.

## Keputusan
- Aturan Ceria untuk halaman ini ditempatkan dengan scope `[data-theme='ceria'] .event-race-page`, bukan `.app-shell`, agar berlaku baik di layout publik maupun admin.
- Input, select, dan textarea memakai `--bg-canvas`, border `--border-soft`, radius `--radius-md`, dan teks `--text-primary`.
- Opsi gruping babak memakai pill dengan border, latar `--bg-canvas`, dan keadaan terpilih berwarna lemon dengan shadow stiker.
- Catatan format (` .race-format-note`) memakai latar `--bg-soft-lemon`, border ink, aksen kiri coral, dan teks `--text-primary` agar kontras.
- Tombol **Terapkan**, **Buka hari lomba**, **Tarik rilis**, **Rilis KU**, **Susun ulang**, **Reset susunan**, **Batal**, dan **Simpan pengaturan** memakai border ink, radius pill, shadow stiker, font display, serta gradien Kids Skate (sunny untuk simpan, sky-roll untuk aksi kelola, surface untuk batal/reset).

## Konsekuensi
- Tidak ada perubahan markup, rute, atau logika format perlombaan.
- Tema selain Ceria tetap memakai gaya bawaan masing-masing.
- Halaman lain yang memakai `.app-shell` tidak terpengaruh.
