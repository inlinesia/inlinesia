# ADR 0056: Form pengajuan sponsor dan penempatan iklan

## Status
Diterima.

## Konteks
Pemilik menyetujui tarif: kelola penuh Rp 149.000/event, banner website Rp 250.000/bulan, sponsor event Rp 150.000/event (utama Rp 300.000). Perlu wadah pengajuan sponsor website dan event, alur tinjau admin, serta penempatan iklan yang disetujui tanpa pembayaran online.

## Keputusan
- Tabel `sponsors` menyimpan tipe (`web`/`event`), paket, event terkait, kontak, URL, logo, periode, nominal, status, dan reviewer.
- Dua form publik: `/sponsor/web` dan `/sponsor/event` (query `?event=ID` mengisi event otomatis). Tarif dihitung otomatis: web per bulan, event sekali per event.
- Pengajuan masuk status `pending`; notifikasi `NewSponsorRequestNotification` dikirim ke admin, dan untuk sponsor event juga ke pemilik event. Kegagalan mail dicatat, tidak menggagalkan penyimpanan.
- Admin meninjau melalui menu **Sponsor** di sidebar (`/admin/sponsor`) dengan tombol setujui/tolak. Sponsor aktif hanya bila disetujui dan masih dalam periode.
- Penempatan:
  - Banner web: strip antara hero dan "Kejuaraan yang akan datang" di beranda serta atas daftar `/lomba`.
  - Sponsor utama web: logo di navbar dan footer publik.
  - Sponsor event: badge "Didukung oleh" pada kartu event (beranda, `/lomba`, dasbor) dan banner di halaman pendaftaran atlet.
  - Tombol "Sponsori event ini" di kartu `/lomba`, "Tambah sponsor" di dasbor pemilik, serta tautan form di halaman lisensi.
- Logo disimpan di disk `public` dan tautan storage dibuat otomatis saat deploy.
- Hook deploy juga membersihkan cache rute agar rute baru segera dikenal.

## Konsekuensi
- Pembayaran masih manual lewat WhatsApp; belum ada gateway pembayaran.
- Sponsor yang ditolak atau lewat periode tidak tampil lagi otomatis.
- Tidak ada perubahan pada paket event gratis/berbayar yang sudah ada.
