# ADR 0039: Ajukan pembatalan lewat WhatsApp dan notifikasi pengelola

## Status
Diterima.

## Konteks
Tombol "Hapus"/"Ajukan pembatalan" pada dasbor peserta belum punya alasan
dan hanya mengubah status. Pengelola meminta dua fungsi: meneruskan ke WhatsApp
pemilik event dengan pesan yang terisi otomatis, dan memberi notifikasi kepada
pemilik event untuk menyetujui atau menolak.

## Keputusan
- Menambah kolom `cancellation_reason` pada `event_registrations` (migrasi baru).
- `destroyOwn()` kini mewajibkan `reason`, menyimpan status `pending`,
  menyimpan alasan, dan mengirim `CancellationRequestedNotification`.
- Rute baru `events.registration.cancellation.whatsapp` (`cancelViaWhatsapp`)
  menyimpan permintaan pembatalan yang sama, mengirim notifikasi, lalu
  mengarahkan ke `https://wa.me/{nomor}?text=...` dengan pesan:
  "Saya ingin mengajukan pembatalan untuk atlet dengan data ... dengan alasan ... Mohon untuk dipertimbangkan. Terimakasih."
- `CancellationRequestedNotification` memakai kanal `database` dan `mail`;
  kegagalan email ditangkap dengan `report()` agar tidak menggagalkan permintaan.
- Penerima notifikasi: pemilik event dan admin, tanpa duplikat.
- Form dasbor peserta menyediakan textarea alasan dan dua tombol: ajukan
  pembatalan serta ajukan via WhatsApp.

## Konsekuensi
- Semua pembatalan kini melalui status `pending` dan menunggu keputusan
  pengelola; penghapusan langsung tidak dipakai lagi dari sisi peserta.
- Alasan pembatalan tersimpan dan tampil pada notifikasi/email.
- Build aset dan migrasi perlu diterapkan ke server.
