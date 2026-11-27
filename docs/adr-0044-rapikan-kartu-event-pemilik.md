# ADR 0044: Rapikan kartu event pemilik di dasbor peserta

## Status
Diterima.

## Konteks
Pada dasbor peserta berperan sebagai pemilik event, tombol aksi sudah berada di posisi yang diinginkan, tetapi blok deskripsi masih berantakan dan memakan banyak ruang vertikal. Kolom tanggal juga belum menyatu dengan kartu: tingginya tidak mengikuti kartu dan sudut kirinya tidak mengikuti lengkung border.

## Keputusan
- Kartu event pemilik diberi modifier `guest-owned-event-card` agar tidak mengubah kartu "Event tersedia" yang hanya berisi satu tautan daftar.
- Kartu memakai grid dua kolom: tanggal di kiri (`grid-area: date`) membentang dari baris deskripsi sampai baris tombol, sehingga tingginya otomatis mengikuti kartu.
- Sudut kiri atas dan kiri bawah kolom tanggal memakai `border-top-left-radius: inherit` / `border-bottom-left-radius: inherit`; sisi kanan memakai border lurus sebagai pemisah.
- Metadata (WhatsApp, rekening, pemilik untuk admin, status/KU/mata lomba) dikumpulkan dalam `.guest-event-meta` yang `flex-wrap`, rata kiri, dan rapat, sehingga deskripsi berada di atas tombol tanpa terlalu banyak baris.
- Tema Ceria memberi kolom tanggal gradien `--grad-sunny`, teks `--text-primary`, font display, dan garis pemisah `--border-ink`; tema lain memakai warna netral bawaan.

## Konsekuensi
- Posisi dan warna tombol aksi tidak diubah; hanya container dan jaraknya yang dirapikan.
- Kartu event di luar area pemilik tetap memakai layout lama.
- Perubahan hanya markup dan CSS; tidak ada perubahan data, rute, atau perilaku server.
