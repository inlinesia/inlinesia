# ADR 0005: Perbaiki Posisi Stiker Tema Ceria

## Status
Diterima.

## Konteks
Setelah deploy ke `inlinesia.kilatschool.my.id`, verifikasi visual menemukan
satu stiker 🛼 mengambang di pojok kanan atas halaman. Penyebabnya:
`.listing-empty-state` tidak memiliki `position: relative`, sehingga
pseudo-elemen stiker yang bersifat `absolute` diposisikan terhadap leluhur
terdekat (halaman), bukan terhadap kartu kosong tersebut.

## Keputusan
- Menambahkan `position: relative` pada semua induk stiker tema Ceria
  (`.listing-event-card`, `.service-card`, `.public-event-card`,
  `.listing-empty-state`, `.account-card`, `.account-table-card`,
  `.listing-organizer-cta`).
- Membangun ulang aset (`npm run build`) dan mengunggahnya ke server produksi.

## Konsekuensi
- Semua stiker sepatu roda kini melekat pada elemennya masing-masing, tidak ada
  stiker liar di pojok halaman.
- Tidak ada perubahan logika, rute, atau database; hanya CSS presentasional.
