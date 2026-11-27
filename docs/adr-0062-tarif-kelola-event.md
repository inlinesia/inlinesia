# ADR 0062: Tarif paket kelola event diterapkan

## Status
Diterima.

## Konteks
Setelah nominal harga proyek lain dihapus, halaman lisensi masih menampilkan "Harga akan diumumkan" untuk paket kelola penuh. Pemilik menegaskan tarif yang sebelumnya disetujui belum diterapkan.

## Keputusan
- Tarif paket **Kelola penuh** ditetapkan **Rp 149.000/event** dan ditampilkan di kartu perbandingan maupun kartu "Harga & kerja sama" pada halaman lisensi.
- Catatan lisensi menyebut pembayaran dikonfirmasi manual lewat WhatsApp dan upgrade dari paket iklan tidak perlu mengajukan event baru.
- Form pengajuan event menampilkan label **Berbayar — Kelola penuh (Rp 149.000/event)** dengan tautan ke halaman lisensi.
- Tarif sponsor tetap: website mulai Rp 250.000/bulan, event mulai Rp 150.000/event.
- Tes lisensi dan form diperbarui.

## Konsekuensi
- Tidak ada payment gateway; tarif berfungsi sebagai informasi dan dasar konfirmasi manual.
- Tidak ada perubahan pada logika `event_tier`, validasi, atau endpoints.
- Nominal bisa diubah di masa depan dengan menyunting teks halaman lisensi dan label form.
