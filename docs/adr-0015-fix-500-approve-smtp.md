# ADR 0015: Bug 500 Saat Menyetujui Pengajuan

## Status
Diterima.

## Konteks
Saat menyetujui pengajuan event di halaman admin
(`admin/pengajuan-event/{event}/setujui`, `Admin\EventSubmissionController@approve`),
permintaan mengembalikan status 500. Root cause di produksi: di baris
`$event->refresh()->submitter->notify(new EventDecisionNotification($event))`
SMTP Google gagal autentikasi (error 535) saat mengirim email, lalu
exception melempar 500. Langsung dari log produksi, trace menempati
`EventSubmissionController.php:53`.

## Keputusan
- Membuat `EventSubmissionController::approve()` (dan `reject()` yang sama)
  menangkap segala `Throwable` dari `$submitter->notify(...)`: status event
  sudah diperbarui, pesan sukses tetap ditampilkan, dan kesalahan email hanya
  dicatat ke `report($exception)` — approve/reject tetap kembali status success
  meskipun email gagal.
- Menambah tes regresi: `test_admin_can_approve_event_submission_without_500`
  dan `test_admin_can_reject_event_submission` (saat ini tidak ada tes untuk
  approve/reject sebelumnya).
- Menghapus kandidat ADR lama `adr-0015-fix-500-approve-tertangkap-notif.md`.

## Konsekuensi
- Approve/reject selalu mengembalikan status 302 + pesan sukses, meskipun email
  gagal karena SMTP: aksi tetap terjadi.
- Log kerusakan SMTP tersimpan di `storage/logs/laravel.log`; operasional bisa
  mengecek kredensial MAIL_USERNAME/MAIL_PASSWORD di `.env` produksi.
- Tidak ada perubahan kode/logika level lain (tidak ada logika bisnis baru).
