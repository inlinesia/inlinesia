# ADR 0048: Sembunyikan status pengajuan event yang sudah disetujui

## Status
Diterima.

## Konteks
Di dasbor peserta, bagian "Status pengajuan event Anda" tetap menampilkan event yang sudah disetujui Admin dengan label "Disetujui · tampil di daftar lomba". Pemilik menilai informasi itu redundan karena keberadaan event di bagian "Pengelolaan event" sudah menandakan pengajuan disetujui.

## Keputusan
- Query `$submissions` di `GuestDashboardController` kini mengecualikan event berstatus `approved`.
- Bagian "Status pengajuan event Anda" otomatis hilang bila tidak ada pengajuan berstatus `pending`, `rejected`, atau `draft` yang tersisa.
- Event yang disetujui tetap muncul di bagian "Event saya" / "Semua event" dengan seluruh tombol pengelolaannya.
- Tes approval diperbarui: notifikasi keputusan tetap muncul, status "Disetujui" dan judul bagian tidak lagi muncul, tautan "Atur KU" tetap tersedia.

## Konsekuensi
- Status pengajuan hanya dipakai untuk pengajuan yang masih memerlukan perhatian pemilik atau Admin.
- Tidak ada perubahan pada alur persetujuan, notifikasi, maupun data event.
- Aturan ini berlaku juga untuk Admin yang memakai dasbor bersama.
