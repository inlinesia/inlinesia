# ADR 0051: Paket event gratis (iklan) dan berbayar (kelola penuh)

## Status
Diterima.

## Konteks
Pemilik ingin pada pengajuan event tersedia dua opsi: gratis untuk memasang iklan/event information, dan berbayar untuk mengelola event penuh. Paket gratis hanya perlu rincian sederhana dan tampil di Jelajahi kejuaraan; paket berbayar memakai seluruh fitur yang ada dengan harga mengikuti halaman lisensi.

## Keputusan
- Migrasi menambah `event_tier` (`advertisement`/`full`, default `full`) dan `short_description` (nullable) pada `events`. Default `full` menjaga event lama dan pengajuan tanpa field paket tetap bisa dikelola.
- `Event` menyediakan konstanta tier, helper `isAdvertisementOnly()`, dan `organizerWhatsappLink()` untuk tombol kontak kartu iklan.
- `EventDetailsRequest` memvalidasi paket secara kondisional: paket iklan hanya mewajibkan data identitas event + deskripsi singkat + WhatsApp; paket penuh tetap memakai semua field lama. Pengajuan tanpa `event_tier` dianggap `full` untuk kompatibilitas.
- Form pengajuan/penyuntingan menampilkan pilihan paket; field lanjutan disembunyikan otomatis oleh `app.js` saat paket iklan dipilih.
- Event iklan tampil di beranda dan `/lomba` dengan deskripsi singkat, tombol **Hubungi pengelola**, dan **Gabung grup WA**; tidak ada tombol daftar lomba.
- Dasbor pemilik menampilkan badge "Iklan (Gratis)", tombol **Upgrade ke berbayar**, **Ubah event**, dan **Hapus event**; seluruh menu kelola disembunyikan.
- Penolakan server-side ditambahkan pada pendaftaran atlet, kelola peserta, KU, perlombaan, hari lomba, keuangan, dan buka/tutup pendaftaran untuk event iklan.
- Halaman lisensi mendapat bagian perbandingan paket: Iklan event (Rp 0) vs Kelola penuh (harga mengikuti daftar yang sudah ada).

## Konsekuensi
- Event iklan tidak bisa menerima pendaftaran atlet sampai di-upgrade; status persetujuan admin tetap diperlukan untuk keduanya.
- Upgrade memakai form edit yang sama dan tidak mengubah status `approved`.
- Field `is_free` tidak diubah maknanya; tetap mengatur biaya pendaftaran atlet pada paket berbayar.
- Migrasi produksi dijalankan otomatis oleh hook deploy yang sudah diperbarui.
