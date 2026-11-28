# ADR 0065: Pagination roster memakai Compact Pill Pager Ceria

## Status
Diterima.

## Konteks
Halaman kelola peserta memakai pagination bawaan Laravel yang masih berupa deretan tombol angka bulat. Gaya Ceria yang ada memberi ukuran lingkaran pada semua `a`/`span` di dalam `.account-pagination`, termasuk pembungkus prev/next, sehingga tampilannya tidak rapi. Pemilik meminta mengikuti contoh "4. Pagination B — Compact Pill Pager" dari Kids Skate Navigations.

## Keputusan
- View pagination khusus `resources/views/vendor/pagination/ceria-compact.blade.php` dibuat dengan struktur: `← Prev`, `Page X of Y`, `Next →`.
- Kedua pemanggilan pagination di `verified/events/participants/index.blade.php` memakai `links('vendor.pagination.ceria-compact')`.
- CSS dasar ditambahkan di `account-portal.css`; override Ceria di `ceria-theme.css` memakai token `--border-ink`, `--radius-pill`, `--shadow-sticker-sm`, font display, dan warna lemon.
- Aturan lama yang membuat semua tautan menjadi lingkaran 40px kini tidak lagi berlaku pada elemen di dalam `.compact-pager`.
- Tombol prev/next pada halaman pertama/terakhir dirender sebagai span non-aktif.

## Konsekuensi
- Hanya halaman roster peserta yang berubah; pagination halaman lain tetap memakai tampilan bawaan.
- Tetap ada `rel="prev"`/`rel="next"` dan `aria-disabled` untuk aksesibilitas.
- Tidak ada perubahan query, filter, atau sort daftar peserta.
