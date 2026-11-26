# ADR 0040: Persyaratan pembatalan, form modal, dan konfirmasi WhatsApp

## Status
Diterima.

## Konteks
Tombol pembatalan di dasbor peserta memakan tempat karena textarea dan dua
tombol ditampilkan langsung di sel tabel. Pengelola juga ingin mengatur
persyaratan pembatalan pada pembuatan event dan menyembunyikan tombol bila
syarat belum terpenuhi. Setelah form pembatalan dikirim, peserta wajib
mengonfirmasi ke pemilik event lewat WhatsApp.

## Keputusan
- Migrasi baru menambah kolom `events`: `cancellation_allowed` (default true),
  `cancellation_min_days`, dan `cancellation_deadline`.
- `Event::isCancellationOpen()` mengecek ketiga syarat; dipakai di form event,
  tombol dasbor, dan controller agar aturan tidak bisa dilewati.
- Form pembuatan/penyuntingan event mendapat bagian "Persyaratan pembatalan"
  dengan pilihan boleh/tidak, minimal hari sebelum event, dan batas tanggal.
- Dasbor peserta: tombol pembatalan jadi ikon; klik membuka `<dialog>` berisi
  form alasan. Tombol tidak muncul bila kebijakan event menutup pembatalan.
- `destroyOwn()` menyimpan permintaan ke server terlebih dahulu (status
  `pending` + notifikasi pemilik/admin), lalu mengalihkan ke `wa.me` dengan
  pesan terisi otomatis. Nomor WhatsApp tidak valid tidak membatalkan status.
- `cancelViaWhatsapp()` tetap tersedia dan kini memakai helper redirect yang sama.
- Tes: form event, penyembunyian tombol, penolakan server (409), serta
  batas hari/tanggal.

## Konsekuensi
- Alur pembatalan selalu melewati server dulu; WhatsApp hanya konfirmasi.
- Event lama tanpa pengaturan tetap membolehkan pembatalan (default true).
- Build aset & migrasi perlu diterapkan ke server.
