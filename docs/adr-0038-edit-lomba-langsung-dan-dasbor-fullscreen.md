# ADR 0038: Edit mata lomba langsung di dasbor dan tampilan fullscreen

## Status
Diterima.

## Konteks
Di dasbor peserta, kolom grup "KU (Kelompok Usia)" berisi sub-header mata
lomba dengan checkbox yang hanya bisa dilihat. Pengelola meminta header grup
diganti "Mata Lomba", checkbox bisa dicentang/diucentang langsung dari tabel,
serta navbar dan tabel menempel penuh ke pinggir layar.

## Keputusan
- Header grup diubah menjadi **Mata Lomba**; sub-header tetap daftar lomba event.
- Checkbox pada baris pendaftaran **belum bayar** kini aktif. Perubahan dikirim
  lewat `PATCH /lomba/{event}/atlet/{registration}/mata-lomba`
  (`events.registration.races.update`) dengan payload `{ races: [...] }`.
- Endpoint memvalidasi kepemilikan, status event, status pembatalan/perubahan,
  serta minimal satu lomba yang valid; lalu menyimpan `races`, mengosongkan
  `category`, `fee_total`, dan `fee_breakdown` sehingga tarif dihitung ulang.
- Peserta **lunas** atau dengan perubahan/pembatalan tertunda tetap memakai
  checkbox disabled dan alur persetujuan pengelola.
- Halaman dasbor diberi class `guest-dashboard-page`; CSS membuat `.public-site`
  tanpa `max-width`, header tanpa radius di pinggir, `.profile-page` dan wrapper
  tabel selebar layar.

## Konsekuensi
- Perubahan lomba belum bayar langsung tersimpan dan memerlukan konfirmasi
  pembayaran ulang (karena tarif direset).
- Tidak ada perubahan pada peserta lunas.
- Build aset perlu diperbarui karena JS/CSS berubah.
