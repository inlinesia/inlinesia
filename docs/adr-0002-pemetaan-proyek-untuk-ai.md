# ADR 0002: Pemetaan Proyek Terpusat untuk AI

## Status
Diterima.

## Konteks
Belum ada pemetaan proyek yang lengkap. `docs/peta-github-mcp.md` hanya berisi
ringkasan rute tingkat area dan ditujukan untuk usulan GitHub MCP. AI yang
mengerjakan proyek ini harus membuka banyak file untuk menemukan rute,
controller, view, dan model, sehingga menghabiskan token besar dan rawan menyentuh
halaman di luar yang diminta. Di sisi lain, `.mcp.json` hanya dipakai Claude
Code, sehingga OpenCode belum memiliki alat Laravel Boost (rute, skema, log).

## Keputusan
- Membuat `docs/peta-proyek.md` sebagai satu referensi ringkas: ringkasan teknis,
  peta area → file, relasi model, layanan inti, jebakan, dan inventaris otomatis
  (81 rute + daftar file dengan kelas dan method publik).
- Membuat `scripts/generate-peta-proyek.php` untuk membangun kembali bagian
  inventaris otomatis di antara penanda `AUTO:START` / `AUTO:END` dengan satu
  perintah: `php scripts/generate-peta-proyek.php`.
- Mendaftarkan MCP `laravel-boost` di `opencode.json` agar OpenCode mendapatkan
  alat Boost seperti daftar rute, skema database, dan log (format V2:
  `mcp.servers`).
- Menambahkan `tests/Feature/ProjectMapTest.php` untuk memastikan peta memuat
  semua nama rute dan penanda auto tetap ada.
- Menambahkan tautan ke peta baru dari `docs/peta-github-mcp.md`.

## Konsekuensi
- AI cukup membaca satu berkas peta untuk memulai, lalu membuka hanya file yang
  relevan; penghematan token paling besar pada pencarian rute dan daftar file.
- Peta bisa basi jika rute/file bertambah tanpa menjalankan generator; tes
  `ProjectMapTest` akan menandainya, lalu jalankan generator untuk memperbarui.
- Bagian catatan manual tidak ditimpa generator karena berada di luar penanda.
- Tidak ada perilaku aplikasi, rute, atau skema database yang diubah.
