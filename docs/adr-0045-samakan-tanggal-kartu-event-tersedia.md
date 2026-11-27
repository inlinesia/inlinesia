# ADR 0045: Samakan tampilan tanggal kartu "Event tersedia"

## Status
Diterima.

## Konteks
Setelah kartu event pemilik di dasbor peserta diperbarui, kartu pada bagian "AGENDA TERBUKA / Event tersedia" masih memakai tampilan tanggal lama. Pemilik meminta tampilan tanggalnya disamakan dengan kartu pemilik.

## Keputusan
- Kartu "Event tersedia" diberi modifier `guest-open-event-card` dan memakai aturan layout yang sama dengan `guest-owned-event-card` (grid dua kolom, tanggal penuh tinggi di kiri, sudut kiri mengikuti kartu, garis kanan lurus).
- Tautan/status pendaftaran dibungkus `.guest-event-actions` agar masuk ke baris aksi di bawah deskripsi; rentang tanggal dan batas daftar dibungkus `.guest-event-meta` agar rapat.
- Aturan tema Ceria untuk kolom tanggal (gradien `--grad-sunny`, font display, garis `--border-ink`) diperluas ke kedua jenis kartu.
- Tema selain Ceria memakai warna netral bawaan yang sama.

## Konsekuensi
- Seluruh kartu event di dasbor peserta kini konsisten untuk bagian tanggal dan deskripsi.
- Tombol/tautan pada kartu "Event tersedia" tidak diubah warna atau teksnya, hanya pindah ke baris aksi bawah.
- Tidak ada perubahan data, rute, atau perilaku server.
