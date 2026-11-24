# ADR 0018: Simulasi 100 peserta dari 8 akun simulasi

## Status
Diterima.

## Konteks
Setelah simulasi awal dibuat (8 pendaftaran dari 8 akun), pengelola ingin
volume peserta yang lebih besar untuk mencoba daftar peserta, kuota kategori,
keuangan, dan susunan babak. Permintaan: tepat 100 pendaftaran, seluruhnya
didaftarkan dari 8 akun atlet simulasi yang sudah ada, pada event simulasi
yang sama, dengan status pembayaran bervariasi dan kuota/babak disesuaikan.

## Keputusan
- Memperluas `EventSimulationSeeder` (bukan membuat seeder baru) supaya
  `run()` selalu menghasilkan tepat **100 pendaftaran**: 8 contoh awal
  ditambah 92 peserta generated, dibagikan merata ke 8 akun `simulasi.atlet*`
  lewat `$users[$index % 8]`.
- Status pendaftaran dan pembayaran disebar: sebagian lunas, sebagian belum
  bayar, sebagian menunggu persetujuan admin; beberapa contoh pembatalan
  pending tetap dipertahankan.
- Kuota tiap kategori dinaikkan dari 6 menjadi **40** (total 3 kategori
  mencukupi 100 peserta), dan pengaturan babak memakai mode manual
  6 atlet per babak.
- `RaceHeatBuilder` dipanggil ulang untuk menyusun seluruh babak dari
  peserta yang lunas dan diterima; contoh hasil finis tetap diisi pada grup
  pertama kategori Pemula U9 · Skate Cross dengan poin mengikuti urutan finis.
- Tes diperbarui untuk menjamin 100 pendaftaran, 8 akun unik, kuota 40,
  babak dan hasil finis tetap ada, serta idempotensi dan `purge()`.

## Konsekuensi
- Menjalankan ulang `php artisan db:seed --class=EventSimulationSeeder --force`
  mengganti seluruh simulasi lama dengan versi 100 peserta — tidak menumpuk.
- Jumlah atlet di direktori bertambah mengikuti pendaftaran (model
  `EventRegistration` tetap menyinkronkan atlet/klub seperti biasa).
- Waktu `run()` di produksi naik menjadi beberapa detik karena 100
  pendaftaran + 7 entri keuangan + susunan babak dibuat dalam satu proses.
- Akun dan password simulasi tidak berubah: `12345678` untuk owner dan
  delapan atlet; akun asli tetap tidak disentuh.
