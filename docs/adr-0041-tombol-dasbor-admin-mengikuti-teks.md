# ADR 0041: Tombol dasbor peserta/admin mengikuti ukuran teks

## Status
Diterima.

## Konteks
Tombol aksi di kartu kejuaraan pada dasbor peserta mode admin memakai
`min-height` dan `padding` tetap dalam pixel, serta `.guest-event-actions`
berupa grid lepas. Saat ukuran font diperbesar, tombol bisa keluar dari kartu.

## Keputusan
- `.guest-event-actions` diubah dari grid menjadi flex-wrap dengan
  `min-width: 0; max-width: 100%`; anak-anaknya dibatasi `max-width: 100%`.
- Tombol Ceria memakai `padding: 0.4em 0.9em`, `gap: 0.4em`, `min-height: 0`,
  `line-height: 1.25`, `white-space: normal`, dan `overflow-wrap: anywhere`.
- Tombol di header "Pendaftaran saya" juga memakai saturan `em` dan boleh
  membungkus bila teks kepanjangan.

## Konsekuensi
- Tinggi dan lebar tombol mengikuti ukuran tulisan; teks panjang dibungkus di
  dalam tombol, bukan menembus kartu.
- Hanya CSS; tidak ada perubahan Blade/controller.
- Build aset diperbarui ke `app-BsKnxQK2.css`.
