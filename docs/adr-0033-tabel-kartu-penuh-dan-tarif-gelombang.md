# ADR 0033: Kartu tabel lebar penuh dan tarif gelombang

## Status
Diterima.

## Konteks
Kartu tabel editor KU masih mengikuti `max-width: 1120px` dari
`.public-application-page`, jadi tidak memenuhi lebar layar seperti navbar.
Selain itu, kolom "Harga" menggandakan informasi tarif yang sudah ada di
gelombang, header "Gelombang"/"Tarif gelombang" dua tingkat dianggap berlebih,
dan nama gelombang belum bisa diedit dari tabel.

## Keputusan
- `.event-category-page` memakai `max-width: none; width: 100%` dengan padding
  20px, form dan section ikut lebar penuh — kartu tabel mengikuti lebar layar.
- Header disederhanakan menjadi dua kolom utama: **Tarif** dan
  **Masa berlaku gelombang** (masing-masing `rowspan=2`); header grup
  "Gelombang" dan sub-header "Tarif gelombang" dihapus.
- Kolom **Harga** dihapus dari tabel karena tarif sudah diwakili gelombang;
  harga dasar tetap tersimpan di field tersembunyi.
- Nama gelombang kini input teks yang langsung menyalin ke input asli di
  fieldset tersembunyi lewat `data-wave="label"`.
- Tes `CategoryPricingTest` disesuaikan: header "Tarif" menggantikan "Harga".

## Konsekuensi
- Tabel lebih lebar dan bisa digeser horizontal; kartu memenuhi layar.
- Harga dasar tetap dikirim ke server tetapi tidak diedit langsung dari tabel.
- Tidak ada perubahan controller/model/skema.
