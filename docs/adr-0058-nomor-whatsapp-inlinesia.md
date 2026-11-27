# ADR 0058: Nomor WhatsApp Inlinesia untuk tombol kontak

## Status
Diterima.

## Konteks
Tombol "Hubungi Inlinesia" pada kartu Kemitraan & Iklan belum mengarah ke WhatsApp karena `CONTACT_WHATSAPP` kosong di lokal dan produksi. Pemilik memilih memakai nomor yang sudah dipakai untuk kontak lisensi, yaitu 085800006248.

## Keputusan
- `config/contact.php` memberi nilai bawaan `085800006248` untuk `contact.whatsapp`.
- `.env` lokal dan `.env.example` diisi `CONTACT_WHATSAPP=085800006248`; `.env` produksi juga diperbarui.
- Normalisasi di `AppServiceProvider` menghasilkan `https://wa.me/6285800006248?text=...` dengan pesan bawaan tentang sponsorship dan iklan.
- Fallback email tetap ada hanya jika konfigurasi sengaja dikosongkan.

## Konsekuensi
- Tombol "Hubungi Inlinesia" langsung membuka WhatsApp tanpa perlu mengubah Blade lagi.
- Nomor bisa diganti kapan saja lewat `CONTACT_WHATSAPP` di `.env` produksi.
- Cache konfigurasi produksi perlu dibersihkan setelah perubahan env agar nilai baru terbaca.
