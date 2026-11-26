# ADR 0037: Gelombang ditumpuk dalam satu kolom di editor KU

## Status
Diterima.

## Konteks
Tabel editor KU menampilkan tarif paket, tarif tambahan, tarif luar kewajiban,
dan masa berlaku dalam empat kolom terpisah. Setiap gelombang tersebar ke kanan,
sehingga tabel menjadi sangat lebar.

## Keputusan
- Keempat kolom digabung menjadi satu kolom **Gelombang**.
- Di dalamnya, tarif dasar dan setiap gelombang (1, 2, …) ditampilkan sebagai
  blok tersendiri yang ditumpuk atas-bawah; tiap blok berisi nama, tarif paket,
  tarif tambahan, tarif luar, serta tanggal mulai/sampai.
- Handler perubahan tetap memakai `data-wave` dan `data-base-fee`, sehingga
  field tersembunyi di fieldset terus tersinkron.
- Lebar minimum tabel diturunkan dari 2100px menjadi 1720px; CSS blok
  gelombang dan tombol "+ Gelombang" mengikuti token Ceria.
- Tes `CategoryPricingTest` diperbarui untuk header "Gelombang".

## Konsekuensi
- Tabel lebih sempit secara horizontal; informasi per gelombang mudah dibaca
  sebagai satu kesatuan blok.
- Jumlah kolom turun dari 14 ke 11.
- Tidak ada perubahan controller/model/perhitungan.
