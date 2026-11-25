# ADR 0031: Tabel editor KU bisa digeser horizontal

## Status
Diterima.

## Konteks
Tabel editor KU sudah memiliki wrapper `overflow-x: auto`, tetapi lebar tabel
mengikuti container sehingga tidak pernah meluap dan tidak bisa digeser.
Dengan 12 kolom (termasuk gelombang dan cakupan kuota), kolom menjadi sempit.

## Keputusan
- Menetapkan `min-width: 1480px` pada `.category-editor-table` supaya tabel
  meluap dan `overflow-x: auto` pada wrapper benar-benar aktif.
- Menambahkan `overscroll-behavior-x: contain` dan
  `-webkit-overflow-scrolling: touch` agar geser tidak menggulir halaman dan
  halus di perangkat sentuh.
- Teks petunjuk diperbarui: tabel bisa digeser ke kiri/kanan.

## Konsekuensi
- Kolom tetap terbaca pada layar sempit; pengguna menggeser horizontal.
- Tidak ada perubahan logika form, controller, atau data.
