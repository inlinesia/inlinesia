# ADR 0057: Kartu kemitraan memakai WhatsApp dan form sponsor

## Status
Diterima.

## Konteks
Kartu kontak landing masih memuat ajakan "sebutkan jenis paket agar kami kirim pricelist", yang sudah tidak relevan karena harga kini diatur di halaman lisensi. Tombol "Hubungi Inlinesia" pada kartu Kemitraan & Iklan masih memakai email, dan belum ada jalur langsung ke form pengajuan iklan.

## Keputusan
- Blok `.contact-foot` pada komponen kartu kontak dihapus, sehingga teks pricelist dan tombol "Tanya sponsorship" tidak lagi tampil.
- Kartu **KEMITRAAN & IKLAN** mendapat tiga tombol:
  - **Hubungi Inlinesia** → WhatsApp dari `config('contact.whatsapp')` dengan pesan bawaan; bila nomor kosong, sementara kembali ke email.
  - **Form iklan website** → `sponsor.web.form`.
  - **Form sponsor event** → `sponsor.event.form`.
- Normalisasi nomor WhatsApp dilakukan sekali di `AppServiceProvider` (`08xx` → `62…`, `8xx` → `62…`) dan dibagikan ke view beranda.
- Nomor diisi melalui `CONTACT_WHATSAPP` di `.env`; tidak ada nomor baru yang diarang.

## Konsekuensi
- Jika `CONTACT_WHATSAPP` belum diisi, tombol kontak masih memakai email sampai env diperbarui.
- Tautan form sponsor memakai rute yang sudah dibuat sebelumnya; tidak ada rute baru.
- Halaman dan komponen lain tidak terpengaruh karena blok kontak hanya dipakai di beranda.
