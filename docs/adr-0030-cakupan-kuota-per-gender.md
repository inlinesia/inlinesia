# ADR 0030: Cakupan kuota Putri / Putra / Putra+Putri

## Status
Diterima.

## Konteks
Sebelumnya kuota sebuah KU menghitung semua atlet terkonfirmasi tanpa
membedakan gender. Pengelola meminta tiga opsi cakupan: Putri saja, Putra saja,
atau Putra dan Putri dalam **satu** kuota bersama. Istilah "Campuran" pada
`gender_group` tidak boleh dipakai untuk ini — campuran berarti putra dan putri
boleh saling bertanding, bukan kuota gabungan.

## Keputusan
- Menambah `quota_scope` pada `category_rules` dengan nilai `female`, `male`,
  atau `both` (default `both`).
- `Event::categoryQuotaScope()` menentukan cakupan; kategori satu gender
  otomatis memakai gender itu, kategori campuran boleh memilih.
- `Event::confirmedCategoryCount()` memfilter `gender` sesuai cakupan, sehingga
  kuota, sisa slot, dan status penuh mengikuti pilihan pengelola.
- Controller menyimpan/normalisasi `quota_scope` dan memaksanya mengikuti
  gender untuk KU Putra atau Putri. Validasi hanya menerima tiga nilai itu.
- Form detail KU dan tabel editor mendapat dropdown "Cakupan batas peserta"
  dengan tiga pilihan di atas; tabel menambah kolom "Cakupan kuota".
- Tes `CategoryQuotaTest` memverifikasi penghitungan untuk ketiga cakupan dan
  default KU satu gender.

## Konsekuensi
- Satu KU campuran dengan kuota bersama kini benar-benar membatasi total
  putra+putri, bukan dua kuota terpisah.
- Aturan lama tanpa `quota_scope` tetap kompatibel (kategori satu gender
  memakai gender itu, campuran memakai `both`).
- Tidak ada perubahan skema database; `quota_scope` disimpan di kolom JSON
  `category_rules`.
