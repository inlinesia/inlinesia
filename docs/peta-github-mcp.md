# Peta Mapping GitHub MCP — Inlinesia

> Status MCP saat ini: di `.mcp.json` hanya ada `laravel-boost` (`php artisan boost:mcp`).
> GitHub MCP **belum terpasang**. Dokumen ini adalah peta usulan agar AI
> (dan kamu) langsung tahu rute → controller → area tugas, sehingga penanganan
> rute lebih cepat dan tidak menebak-nebak.
> File ini dibuat atas izin eksplisit pemilik proyek (pengecualian aturan
> "hanya buat dokumentasi bila diminta" di `AGENTS.md`).

## Cara memakai peta ini

1. Cari area/halaman yang ingin diubah di tabel bawah.
2. Langsung buka controller yang tertera — jangan ubah halaman lain di luar
   yang diminta (aturan Larangan).
3. Bila GitHub MCP kelak dipasang, gunakan area yang sama untuk mencari
   issue/PR terkait area tersebut (mis. riwayat perubahan dasbor peserta).

## Ringkasan rute (`routes/web.php`)

Tidak ada `routes/api.php`. Semua rute ada di `routes/web.php`,
dikelompokkan dengan middleware `guest`, `auth`, `auth` + `event-access`,
dan `auth` + `role:admin` dengan prefix `admin`.

| Area | Rute | Controller / Handler | Middleware penting |
|---|---|---|---|
| Beranda publik | `GET /` (`home`) | Closure di `web.php` (query `Event` approved) | publik |
| Daftar lomba | `GET /lomba` (`events.index`) | `EventListingController` | publik |
| Direktori klub | `GET /direktori`, `GET/POST /direktori/daftar-klub` | `DirectoryController@index`, `createClub`, `storeClub` | publik, throttle POST |
| Daftar / masuk | `GET/POST /daftar`, `GET/POST /masuk` | `RegisteredUserController`, `AuthenticatedSessionController` | `guest`, throttle |
| Keluar | `POST /keluar` (`logout`) | `AuthenticatedSessionController@destroy` | `auth` |
| NIK, verifikasi email, profil | `/nik/baca`, `/email/verify/...`, `/profil` | `NikLookupController`, `EmailVerificationController`, `ProfileController` | `auth`, throttle |
| Dasbor peserta | `GET /dasbor-peserta` | `GuestDashboardController` | `auth` + `event-access` |
| Manajemen atlet kejuaraan | `/kejuaraan/{event}/atlet/...` (index, export, publish, assignCategories, reviewParticipantChange, reviewCancellation, update, confirmPayment, proof) | `EventRegistrationManagementController` | `auth` + `event-access`, `scopeBindings` |
| Kategori event | `/dasbor-peserta/kejuaraan/{event}/kategori` | `EventCategoryController` | `auth` + `event-access` |
| Keuangan event | `/dasbor-peserta/kejuaraan/{event}/keuangan/...` | `EventFinanceController` | `auth` + `event-access` (tanpa `scopeBindings` — sengaja, lihat komentar di `web.php`) |
| Format & jadwal lomba | `/dasbor-peserta/kejuaraan/{event}/perlombaan/...` (format, release, hari/babak, grup, tunda, hasil, tahap) | `EventRaceFormatController`, `EventRaceDayController` | `auth` + `event-access` |
| Urutan lomba | `GET /kejuaraan/{event}/urutan-lomba` | `EventRaceOrderController@show` | `auth` + `event-access` |
| Direktori (login) | `/direktori/daftar-atlet`, `/direktori/klub/{club}/ubah` | `DirectoryController` | `auth` + `event-access` |
| Pengajuan event publik | `/ajukan-event...` | `PublicEventApplicationController` | `auth` + `event-access`, throttle |
| Pendaftaran lomba | `/lomba/{event}/daftar`, `/lomba/{event}/atlet/{registration}/...` (update, bukti, whatsapp), `POST /lomba/{event}/resolve-category` | `EventRegistrationController` | `auth` + `event-access`, throttle |
| Admin — dasbor | `GET /admin/` (`admin.dashboard`) | Closure di `web.php` | `auth` + `role:admin` |
| Admin — kepemilikan event | `PATCH /admin/kejuaraan/{event}/pemilik` | `Admin\EventOwnershipController@update` | `role:admin` |
| Admin — pengajuan event | `/admin/pengajuan-event...` | `Admin\EventSubmissionController` | `role:admin` |
| Admin — akun | `/admin/akun...` | `Admin\UserController` | `role:admin` |
| Admin — tema | `/admin/pengaturan/tema` | `Admin\ThemeSettingController` | `role:admin` |
| Admin — data/backup | `/admin/data/backup`, `/admin/data/restore`, `/admin/data/hapus...` | `Admin\DataManagementController` | `role:admin` |

## Pemetaan ke GitHub MCP (usulan, bila dipasang)

| Kebutuhan | Area proyek yang relevan |
|---|---|
| Lihat riwayat perubahan suatu halaman | Cari PR yang menyentuh controller/view area itu |
| Cari bug yang sudah dilaporkan | Cari issue dengan kata kunci area (mis. "keuangan", "pendaftaran", "direktori") |
| Review perubahan sebelum merge | Diff PR pada controller + view area tersebut |
| Menjaga aturan "jangan ubah halaman lain" | Batasi file yang disentuh PR sesuai satu baris area di tabel atas |

## Keputusan pemilik proyek (hasil pilihan konflik)

- K1 (A): Ikuti fakta mesin — PHP terpasang **8.4** (AGENTS.md diperbarui).
- K2 (A): ADR menang atas larangan dokumentasi — ADR wajib otomatis setiap
  perubahan kode (pengecualian resmi, dokumentasi murni tetap dikecualikan).
- K3 (B): Repo Git dibuat (`git init`), sehingga aturan pull/push bisa berlaku.
- K4 (A): Nomor ADR mulai dari **0001** (folder `docs/` sebelumnya tidak ada).
- K5 (A): Buat test selalu dengan `--phpunit`.
- K6 (A): Pint selalu `vendor/bin/pint --dirty --format agent`.
- K7 (A): Balasan ringkas, tetap Bahasa Indonesia.
- K8 (A): Sub-agent paralel hanya untuk tugas besar/multi-halaman.
