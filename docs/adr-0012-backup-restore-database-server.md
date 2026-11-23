# ADR 0012: Backup/Restore Berbasis Database di Server

## Status
Diterima.

## Konteks
Mekanisme lama mengunduh file JSON dari browser dan memulihkannya lewat
unggah. Pemilik proyek meminta backup/restore berasal dari database yang
tersimpan di server: dump SQL penuh, disimpan di `storage/app/private/backups`,
dipilih dari daftar yang tersedia, menggantikan fitur unduh/unggah JSON, dan
tetap menyertakan file bukti pembayaran.

## Keputusan
- `AdminDataManagementService` tidak lagi menghasilkan JSON. `createBackup()`
  membuat direktori `storage/app/private/backups/inlinesia-YYYYMMDD-HHMMSS-xxxx/` berisi:
  - `database.sql` — pernyataan `DELETE` (urutan anak ke induk) dan `INSERT`
    (urutan induk ke anak) untuk seluruh tabel aplikasi.
  - `payment-proofs/*` — salinan file bukti pembayaran.
  - `meta.json` — waktu, jumlah baris per tabel, hash file bukti, dan tanda
    tangan HMAC.
- Tabel sesi, cache, dan antrian (`sessions`, `cache*`, `jobs*`, `failed_jobs`)
  tidak dimasukkan dump agar admin tetap login setelah pemulihan. Tabel
  aplikasi lain, termasuk `athletes`, `clubs`, dan `migrations`, ikut dibackup.
- `listBackups()` menampilkan daftar backup dari server;
  `restoreBackup(name, ...)` memverifikasi nama, tanda tangan, dan isi SQL,
  menjalankan seluruh pernyataan dalam transaksi, lalu menyamakan file bukti.
- Parser SQL memindai karakter di luar tanda kutip sehingga nilai teks
  mengandung baris baru tetap utuh.
- Controller & rute: `POST /admin/data/backup` membuat backup,
  `POST /admin/data/restore` memilih backup dari daftar,
  `POST /admin/data/backup/hapus` menghapus backup. Rute dan tombol unduh JSON
  dihapus; halaman backup ditulis ulang.

## Konsekuensi
- Backup dibuat dan dipulihkan sepenuhnya di server; tidak lagi ada proses
  unduh/unggah file besar dari browser.
- Pemulihan bersifat destruktif namun dilindungi tanda tangan, checkbox
  konfirmasi, validasi admin aktif/utama, dan transaksi database.
- Tidak ada perubahan rute publik, logika bisnis, atau skema database.
