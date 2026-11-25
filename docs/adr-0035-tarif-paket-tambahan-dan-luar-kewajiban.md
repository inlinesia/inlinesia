# ADR 0035: Tarif paket, tambahan, dan lomba luar kewajiban

## Status
Diterima.

## Konteks
Pengelola meminta kosakata dan tampilan tarif diperjelas: daftar lomba dalam
KU disebut "mata lomba yang diwajibkan", jumlah minimal disebut "minimal mata
lomba", dan tarif tambahan/luar kewajiban ditampilkan sebagai kolom tersendiri.
Aturan harga: paket mencakup `minimal mata lomba` (sekali, termasuk pada setiap
gelombang); kelebihan mata lomba wajib dikenakan tarif tambahan per lomba; lomba
di luar daftar wajib dikenakan tarif luar per lomba. Setiap tarif boleh punya
nilai gelombang sendiri; bila kosong, tarif dasar dipakai walaupun tarif lain
menggunakan gelombang.

## Keputusan
- Header tabel editor diubah: "Mata lomba yang diwajibkan", "Minimal mata
  lomba", "Mata lomba di luar kewajiban", "Tarif paket", "Tarif tambahan", dan
  "Tarif mata lomba di luar kewajiban".
- Kolom "Mata lomba di luar kewajiban" berisi checkbox untuk semua lomba event
  yang tidak termasuk daftar wajib KU; perubahan diteruskan ke field lama
  `data-outside-race-option`.
- Kolom tarif tambahan dan tarif luar memuat nilai dasar serta nilai per
  gelombang (`data-wave="extra_race_fee"` / `outside_race_fee`) yang disalin ke
  field tersembunyi. `PricingWaveResolver::apply` sudah menimpa hanya field yang
  diisi, jadi tarif tanpa nilai gelombang tetap memakai tarif dasar.
- Perhitungan tidak diubah: paket sekali, kelebihan wajib per lomba, lomba luar
  per lomba. Tes baru mengunci perilaku ini pada gelombang aktif.
- Tes lama yang memakai label lama diperbarui.

## Konsekuensi
- Tabel lebih lebar (14 kolom) dan tetap bisa digeser horizontal.
- Tidak ada perubahan controller/model/skema; hanya Blade, JS, CSS, dan tes.
