# ADR 0014: Style Checkbox & Radio dari Kids Skate Controls & Form Inputs Library

## Status
Diterima.

## Konteks
Halaman pendaftaran lomba (`daftar.blade.php`) memakai checkbox asli browser
(native) tanpa gaya visual, sehingga tampil berbeda dari tema Ceria yang lainnya
pastel & sticker. Permintaan: eksekusi style "Checkbox & Radio Selectors" dari
Kids Skate Controls & Form Inputs Library (contoh pemilih: `.choice-pill`,
`.radio-cards`/`.radio-card`). Pemilik proyek melaporkan bug: submit pendaftaran
lomba diam-diam tidak merespon — checkbox tanpa `required`, browser tidak
memblok; POST tetap dikirim, lalu server 422 sebagai error generik di reload.

## Keputusan
- Memberdayakan styling kid-skate di aplikasi (global, di `ceria-theme.css`):
  pill bulat 26px, border ink 2.5px, bg canvas, hover mint, checked: border
  coral + bg lemon + checkmark/dot coral (native), invalid focus & disabled
  sama dengan kontoh library.
- Menambah variabel `--ceria-panel`, `--ceria-mint` (alias token).
- Blade pendaftaran lomba: tambah `required` ke checkbox `races[]`.
- JS app.js: submit-guard — `preventDefault` + `setCustomValidity` +
  `reportValidity()` saat minimal 1 mata lomba belum tercentang.
- Server tetap menjalankan validasi (fallback; controller tetap 422).

## Konsekuensi
- Checkbox/radio di login (remember), register (role), categories
  (eligibility/races/outside/registration_open), delete (selected/confirm),
  backup (confirm), preset-picker — semua kid-friendly.
- Pendaftaran lomba: 0 mata lomba tercentang → block di klien, sebelum POST.
- Tidak ada perubahan logika server.
