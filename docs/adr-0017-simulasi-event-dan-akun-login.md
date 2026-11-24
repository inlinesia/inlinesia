# ADR 0017: Seeder Simulasi Event dan Akun Login

## Status
Diterima.

## Konteks
Pengelola membutuhkan data simulasi di lingkungan produksi untuk mencoba
alur pengelolaan event (approve, kelola peserta, keuangan, babak lomba, dan
hasil) tanpa mengubah akun asli. Data contoh sebelumnya
(`DemoEventRegistrationSeeder`) memakai event ber-ID tertentu dan 300 peserta,
sehingga tidak sesuai untuk simulasi kecil yang bisa diulang dan dibersihkan.

## Keputusan
- Menambah `database/seeders/EventSimulationSeeder.php`: membuat event
  `SIMULASI Kediri Skatefest 2027` berstatus **pending**, 3 kategori, 8 atlet,
  pembayaran, catatan keuangan, pengaturan perlombaan, babak, dan contoh hasil.
- Membuat 9 akun simulasi baru (1 owner `club_manager` + 8 atlet `individual`)
  dengan email diawali `simulasi.` di domain `kilat.com`, `email_verified_at`
  diisi, dan kata sandi **`12345678`**. Akun asli (Inlinesia, DAMAI, Haris)
  tidak diubah. `forceFill()` dipakai karena `email_verified_at` bukan field
  fillable pada `User`.
- `run()` membersihkan simulasi lama lebih dulu sehingga aman dijalankan
  berulang; `purge()` tersedia untuk menghapus seluruh data simulasi.
- Susunan babak dibuat lewat `RaceHeatBuilder` (layanan aplikasi yang sama
  dengan halaman "Atur perlombaan"), bukan SQL mentah.
- Menambah `tests/Feature/EventSimulationSeederTest.php` untuk memverifikasi
  status event, jumlah data, kata sandi/login, idempotensi, dan `purge()`.

## Konsekuensi
- `php artisan db:seed --class=EventSimulationSeeder` dapat dijalankan ulang
  kapan saja; `purge()` menghapus data simulasi tanpa menyentuh akun asli.
- Event simulasi sengaja berstatus pending agar bisa dicoba di halaman
  pengajuan admin. Setelah disetujui, tombol kelola peserta/kategori/perlombaan
  muncul di dasbor owner simulasi.
- Kata sandi `12345678` hanya berlaku untuk akun berprefix `simulasi.` dan
  tidak boleh dipakai untuk akun produksi asli.
- Penanda `SIMULASI ` dipakai pada nama event dan klub; `purge()` menghapus
  klub dengan prefiks tersebut bersama akun simulasi.
- Contoh permintaan ubah biodata sengaja tidak dibuat: kolom
  `participant_change_request` di produksi berupa JSON (`json_valid`),
  sedangkan cast modelnya `encrypted:array` menghasilkan base64, sehingga
  penyimpanan ditolak MySQL. Ini bukan bagian dari simulasi dan sengaja
  tidak diubah di sini.
