# ADR 0050: Tautan grup WhatsApp tanya jawab per event

## Status
Diterima.

## Konteks
Panitia atau pemilik event biasanya membuat grup WhatsApp untuk tanya jawab peserta. Inlinesia belum menyediakan tempat untuk menyimpan tautan undangan grup itu, sehingga peserta tidak punya jalur resmi untuk bergabung.

## Keputusan
- Migrasi baru menambahkan kolom nullable `whatsapp_group_link` (maks. 500 karakter) pada tabel `events`, setelah `organizer_whatsapp`.
- `Event` memasukkan kolom tersebut ke `$fillable`; `EventDetailsRequest` memvalidasinya sebagai URL opsional dengan host `chat.whatsapp.com` atau `wa.me`, lalu mengubah isian kosong menjadi `null`.
- Form pengajuan/penyuntingan event (halaman pemilik) mendapat field "Tautan grup WhatsApp tanya jawab" di dekat nomor WhatsApp pengelola.
- Kartu event pada halaman beranda, daftar kejuaraan, dan "Event tersedia" di dasbor menampilkan tombol **Gabung grup WA** hanya bila tautan tersedia. Tombol dibuka di tab baru dengan `rel="noopener noreferrer"`.
- Gaya tombol memakai hijau Kids Skate dengan border ink dan shadow stiker pada tema Ceria.
- `deploy/post-receive` kini menjalankan `php artisan migrate --force --no-interaction` setelah menarik commit agar skema produksi selalu sinkron.

## Konsekuensi
- Pemilik dapat mengelola tautan grup dari halaman yang sama dengan data event lain, tanpa fitur baru di luar alur pengajuan.
- Tautan non-WhatsApp ditolak; peserta tidak diarahkan ke URL sembarangan.
- Event tanpa grup tidak menampilkan tombol apa pun, sehingga kartu tetap ringkas.
- Perubahan skema memerlukan migrasi produksi yang kini otomatis saat deploy.
