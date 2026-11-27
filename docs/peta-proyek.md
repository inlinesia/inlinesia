# Peta Proyek Inlinesia — Referensi Cepat untuk AI

> Tujuan: satu berkas untuk menemukan **rute → controller → view → model/service**
> tanpa membuka puluhan file. Bagian "Inventaris otomatis" dibuat oleh
> `php scripts/generate-peta-proyek.php`; **jangan sunting bagian itu dengan tangan**.
> Untuk peta khusus GitHub MCP, lihat `docs/peta-github-mcp.md`.

## 0. Cara pakai

1. Cari area/halaman di tabel **Peta area → file** (bagian 2).
2. Baca satu baris itu saja untuk tahu file mana yang perlu dibuka.
3. Butuh daftar rute lengkap + inventaris file? Baca bagian 8 (otomatis).
4. Setelah menambah/menghapus rute atau file, jalankan
   `php scripts/generate-peta-proyek.php` supaya peta tidak basi.
5. Aturan tetap berlaku: jangan ubah halaman/file di luar yang diminta.

## 1. Ringkasan teknis

| Item | Nilai |
|---|---|
| Framework | Laravel **13.33.0** (composer: `^13.17`) |
| PHP | **8.4.25** |
| Frontend | Vite 8, Tailwind CSS 4, vanilla JS (tanpa Livewire/Inertia) |
| Test | PHPUnit 12, mayoritas *feature test*, trait `RefreshDatabase` |
| Rute | Hanya `routes/web.php` (81 rute) + `routes/console.php`. **Tidak ada API route.** |
| Middleware kustom | `event-access` (login + email terverifikasi), `role:...` |
| Susunan | `app/{Http,Models,Services,Console,Notifications,Rules,Providers}` · `resources/views/{layouts,components,auth,publik,verified,admin}` |
| MCP | `laravel-boost` terdaftar di `.mcp.json` (Claude Code) dan `opencode.json` (OpenCode) |

## 2. Peta area → file (baca ini dulu)

| Area | Rute utama | Controller | View | Model / Service kunci |
|---|---|---|---|---|
| Beranda & daftar lomba | `home`, `events.index` | Closure `web.php:28`, `EventListingController` | `publik/beranda.blade.php`, `publik/lomba/index.blade.php` | `Event` |
| Direktori klub/atlet | `directory.*` | `DirectoryController` | `publik/direktori/{index,klub,klub-edit,atlet}.blade.php` | `Club`, `Athlete`, `NikParser`, `SyncRegistrationToDirectory` |
| Akun (daftar/masuk/verifikasi/profil) | `register.*`, `login.*`, `verification.*`, `profile.*` | `Auth\{RegisteredUser,AuthenticatedSession,EmailVerification}Controller`, `ProfileController` | `auth/{login,register,verify-email,profile}.blade.php` | `User` |
| Dasbor peserta | `guest.dashboard` | `GuestDashboardController` (`__invoke`) | `verified/dashboard.blade.php` | `Event`, `EventRegistration` |
| Pengajuan event | `event-applications.*` | `PublicEventApplicationController` | `verified/events/pengajuan/form.blade.php` | `Event`, `EventDetailsRequest`, `EventSubmittedNotification` |
| Kategori & harga | `owner.events.categories.*` | `EventCategoryController` | `verified/events/categories.blade.php` | `EventCategoryRaceSetting`, `CategoryFeeCalculator`, `PricingWaveResolver` |
| Keuangan event | `owner.events.finance.*` | `EventFinanceController` | `verified/events/finance/index.blade.php` | `EventFinanceTransaction`, `EventRegistrationFinancialEntry` |
| Format/perlombaan | `owner.events.race.format*`, `.release`, `.unrelease` | `EventRaceFormatController` | `verified/events/race/format.blade.php` | `EventCategoryRaceSetting` |
| Hari lomba & hasil | `owner.events.race.{day,heats.*,group.*,heat.*,advance,entry.*}` | `EventRaceDayController` | `verified/events/race/{day.blade.php,partials/heat.blade.php}` | `EventRaceHeat`, `EventRaceEntry`, `EventRaceGroup`, `RaceHeatBuilder`, `RaceStandings` |
| Urutan lomba | `events.race.order` | `EventRaceOrderController` | `verified/events/race/order.blade.php` | `EventCategoryRaceSetting` |
| Kelola peserta & bayar | `events.registrations.*` | `EventRegistrationManagementController` | `verified/events/participants/{index,ubah,export}.blade.php` | `EventRegistration`, `AssignRegistrationCategories`, `ReconcileRegistrationFee` |
| Pendaftaran sisi peserta | `events.registration.*` | `EventRegistrationController` | `verified/events/pendaftaran/daftar.blade.php` | `EventRegistration`, `ResolveEventRegistrationCategory`, `RegistrationIdentity` |
| Admin — akun | `admin.accounts.*` | `Admin\UserController` | `admin/accounts/{index,form}.blade.php` | `User` |
| Admin — pengajuan masuk | `admin.event-submissions.*` | `Admin\EventSubmissionController` | `admin/event-submissions/index.blade.php` | `EventDecisionNotification` |
| Admin — kepemilikan event | `admin.events.ownership.update` | `Admin\EventOwnershipController` | — | `Event` |
| Admin — tema | `admin.settings.theme.*` | `Admin\ThemeSettingController` | `admin/settings/theme.blade.php` | flash sesi/browser (tanpa DB) |
| Admin — backup/hapus data | `admin.data.*` | `Admin\DataManagementController` | `admin/data/{backup,delete,delete-confirmation}.blade.php` | `AdminDataManagementService` |

Ringkasan grup akses: **publik** (`web`) · **guest** (`guest`) · **auth dasar** (`auth`) ·
**auth + event-access** (semua rute `owner.*`, `events.registration*`, `ajukan-event`,
`kejuaraan/*`, sebagian `direktori/*`) · **admin** (`auth, role:admin`, prefix `admin`, nama `admin.*`).

## 3. Model & relasi utama

| Model | Tabel | Relasi / helper penting |
|---|---|---|
| `User` | `users` | `isAdmin()` (role `admin` **atau** `is_primary_admin`), `roleLabel()`; email & role admin utama dikunci di `booted()` |
| `Event` | `events` | `submitter()`, `reviewer()`, `registrations()`, `financeTransactions()`, `registrationFinancialEntries()`, `raceSettings()`, `raceHeats()`, `raceEntries()`, `raceGroups()`; banyak helper kuota/harga/publikasi |
| `EventRegistration` | `event_registrations` | `event()`, `user()`, `financialEntries()`; NIK/passport terenkripsi; `amountPaid()`, `amountDue()`, `maskedIdentity()`; `booted()` → sinkron ke direktori |
| `EventRegistrationFinancialEntry` | `event_registration_financial_entries` | `event()`, `registration()`, `recorder()`; ledger bayar/refund (`amount` integer) |
| `EventFinanceTransaction` | `event_finance_transactions` | `event()`, `author()`; catatan pemasukan/pengeluaran manual (`amount` decimal:2) |
| `EventCategoryRaceSetting` | `event_category_race_settings` | `event()`; aturan babak per kategori + mata lomba; konstanta `STAGES` = format tangga |
| `EventRaceHeat` | `event_race_heats` | `event()`, `group()`, `entries()`; konstanta `STAGES` = nama babak (heat/qualification/semi_final/final) |
| `EventRaceGroup` | `event_race_groups` | `event()`, `heats()`; `status()` **method**, bukan accessor |
| `EventRaceEntry` | `event_race_entries` | `heat()`, `registration()` (**tidak ada `event()`**); status pending/finished/no_show/dnf/dsq/postponed |
| `Athlete` | `athletes` | `club()`, `owner()`, scope `search()`; `nik` juga dipakai untuk nomor paspor warga asing |
| `Club` | `clubs` | `owner()`, `athletes()`, `isManagedBy()` |

## 4. Layanan inti (`app/Services`)

| Service | Dipakai untuk |
|---|---|
| `AdminDataManagementService` | backup/restore JSON bertanda tangan, hapus katalog, proteksi admin |
| `AssignRegistrationCategories` | pembagian kategori otomatis (preview + persist, transaksi, `chunkById`) |
| `CategoryFeeCalculator` | hitung fee, gelombang harga, breakdown, sinkron perubahan harga |
| `PricingWaveResolver` | memilih gelombang harga aktif berdasarkan tanggal |
| `ReconcileRegistrationFee` | selisih bayar → refund / pending / paid, migrasi ledger legacy |
| `ResolveEventRegistrationCategory` | kategori layak berdasarkan tanggal lahir, tahun, umur, gender |
| `RegistrationIdentity` | normalisasi & cek duplikat NIK/paspor + HMAC |
| `NikParser` | parsing 16 digit NIK → wilayah, gender, tanggal lahir |
| `RaceHeatBuilder` | susun babak, grup, maju tahap, tunda atlet, hitung finis |
| `RaceStandings` | klasemen & progres tahap |
| `SyncRegistrationToDirectory` | cerminkan pendaftaran ke `athletes`/`clubs` saat model berubah |

## 5. Middleware, command, notifikasi

- `EnsureEventAccess` (`event-access`): 401 bila tamu; non-admin belum verifikasi → redirect `verification.notice`. **Bukan** pengecekan kepemilikan event.
- `EnsureUserHasRole` (`role:...`): admin selalu lolos; user lain harus masuk daftar peran.
- Command: `app:bootstrap-admin` (email tetap dari `config/primary-admin.php`), `directory:backfill` (idempoten), `events:release-participant-lists` (dijadwalkan hourly di `bootstrap/app.php`).
- Notifikasi: `EventSubmittedNotification` → admin; `EventDecisionNotification` → submitter. Keduanya `database` + `mail`.
- Form request: `EventDetailsRequest` (buat/edit event), `UpdateEventRegistrationRequest`; rule `NikMatchesIdentity`.

## 6. Pangkalan data (ringkas)

26 migrasi. Tabel utama: `users`, `events`, `event_registrations`,
`event_registration_financial_entries`, `event_finance_transactions`,
`event_category_race_settings`, `event_race_heats`, `event_race_entries`,
`event_race_groups`, `clubs`, `athletes`, `notifications`, plus tabel bawaan
(`sessions`, `cache`, `jobs`, `password_reset_tokens`).

Status pembayaran: `unpaid` / `pending` / `paid`. Status event: `pending` / `approved` / `completed` / `rejected`.
Seeder: `DatabaseSeeder` hanya membuat 1 user; `DemoEventRegistrationSeeder` bergantung pada **Event ID 8**;
`TulungagungEventRegistrationSeeder` menyimpan password demo tanpa hash. Satu-satunya factory: `UserFactory`.

## 7. Jebakan yang sering bikin salah

1. `event-access` hanya cek login + verifikasi email. Otorisasi pemilik event ada di controller (`$event->isManagedBy()` / `authorizeManager()` yang ditulis ulang di 4 controller).
2. `scopeBindings()` dipakai di rute roster/pendaftaran, tapi **sengaja tidak** di `owner.events.finance.{update,destroy}` (relasinya `financeTransactions()`). Menambahkannya akan merusak rute.
3. Urutan deklarasi rute penting: `.../atlet/simpan`, `/publikasi`, `/bagi-kategori` harus sebelum `.../atlet/{registration}`.
4. `admin.accounts.create` & `.edit` sama-sama `UserController@form`; `event-applications.create` & `.edit` sama-sama `PublicEventApplicationController@form`.
5. `EventRaceGroup::status()` adalah method — jangan tulis `$group->status`.
6. Dua konstanta `STAGES` berbeda arti: `EventCategoryRaceSetting` (format tangga) vs `EventRaceHeat` (nama babak).
7. Nama kategori sudah mengandung suffix `Putra/Putri/Campuran` dan kadang `— di luar kewajiban`; pakai helper `categoryNames()`/`categoryBaseName()`.
8. `Athlete.nik` dipakai juga untuk paspor warga asing; jangan asumsikan selalu 16 digit.
9. `NikMatchesIdentity` membaca `request()` global, bukan data array yang sedang divalidasi.
10. `EventRaceEntry` punya kolom `event_id` tapi **tanpa relasi `event()`** — akses lewat `heat->event` atau `registration->event`.
11. Backup/admin data **tidak** mencakup `clubs` & `athletes`.
12. Email admin hard-coded di 5 tempat (migrasi, `config/primary-admin.php`, `config/mail.php`, `config/contact.php`, `publik/beranda.blade.php`).
13. Di Blade, validasi identitas NIK/paspor diulang di 3 file + `resources/js/app.js` — sinkronkan semuanya.

## 8. Inventaris otomatis

> Dihasilkan oleh `php scripts/generate-peta-proyek.php`.

<!-- AUTO:START -->

### Rute (86)

| Method | URI | Nama | Aksi | Middleware |
|---|---|---|---|---|
| GET\|HEAD | `/` | `home` | `Closure` | web |
| GET\|HEAD | `admin` | `admin.dashboard` | `Closure` | web, auth, role:admin |
| GET\|HEAD | `admin/akun` | `admin.accounts.index` | `App\Http\Controllers\Admin\UserController@index` | web, auth, role:admin |
| POST | `admin/akun` | `admin.accounts.store` | `App\Http\Controllers\Admin\UserController@store` | web, auth, role:admin |
| GET\|HEAD | `admin/akun/buat` | `admin.accounts.create` | `App\Http\Controllers\Admin\UserController@form` | web, auth, role:admin |
| PUT | `admin/akun/{user}` | `admin.accounts.update` | `App\Http\Controllers\Admin\UserController@update` | web, auth, role:admin |
| GET\|HEAD | `admin/akun/{user}/edit` | `admin.accounts.edit` | `App\Http\Controllers\Admin\UserController@form` | web, auth, role:admin |
| POST | `admin/akun/{user}/kirim-verifikasi` | `admin.accounts.verification.resend` | `App\Http\Controllers\Admin\UserController@resendVerification` | web, auth, role:admin, throttle:6,1 |
| POST | `admin/akun/{user}/verifikasi-manual` | `admin.accounts.verification.verify` | `App\Http\Controllers\Admin\UserController@verifyEmail` | web, auth, role:admin |
| GET\|HEAD | `admin/data/backup` | `admin.data.backup` | `App\Http\Controllers\Admin\DataManagementController@backup` | web, auth, role:admin |
| POST | `admin/data/backup` | `admin.data.backup.create` | `App\Http\Controllers\Admin\DataManagementController@createBackup` | web, auth, role:admin |
| POST | `admin/data/backup/hapus` | `admin.data.backup.destroy` | `App\Http\Controllers\Admin\DataManagementController@destroyBackup` | web, auth, role:admin |
| GET\|HEAD | `admin/data/hapus` | `admin.data.delete` | `App\Http\Controllers\Admin\DataManagementController@delete` | web, auth, role:admin |
| POST | `admin/data/hapus` | `admin.data.delete.destroy` | `App\Http\Controllers\Admin\DataManagementController@destroySelected` | web, auth, role:admin |
| POST | `admin/data/hapus/konfirmasi` | `admin.data.delete.review` | `App\Http\Controllers\Admin\DataManagementController@reviewDeletion` | web, auth, role:admin |
| POST | `admin/data/restore` | `admin.data.restore` | `App\Http\Controllers\Admin\DataManagementController@restore` | web, auth, role:admin |
| PATCH | `admin/kejuaraan/{event}/pemilik` | `admin.events.ownership.update` | `App\Http\Controllers\Admin\EventOwnershipController@update` | web, auth, role:admin |
| GET\|HEAD | `admin/pengajuan-event` | `admin.event-submissions.index` | `App\Http\Controllers\Admin\EventSubmissionController@index` | web, auth, role:admin |
| PATCH | `admin/pengajuan-event/{event}/setujui` | `admin.event-submissions.approve` | `App\Http\Controllers\Admin\EventSubmissionController@approve` | web, auth, role:admin |
| PATCH | `admin/pengajuan-event/{event}/tolak` | `admin.event-submissions.reject` | `App\Http\Controllers\Admin\EventSubmissionController@reject` | web, auth, role:admin |
| GET\|HEAD | `admin/pengaturan/tema` | `admin.settings.theme.edit` | `App\Http\Controllers\Admin\ThemeSettingController@edit` | web, auth, role:admin |
| PATCH | `admin/pengaturan/tema` | `admin.settings.theme.update` | `App\Http\Controllers\Admin\ThemeSettingController@update` | web, auth, role:admin |
| GET\|HEAD | `ajukan-event` | `event-applications.create` | `App\Http\Controllers\PublicEventApplicationController@form` | web, auth, event-access |
| POST | `ajukan-event` | `event-applications.store` | `App\Http\Controllers\PublicEventApplicationController@store` | web, auth, event-access, throttle:4,1 |
| GET\|HEAD | `ajukan-event/{event}` | `event-applications.edit` | `App\Http\Controllers\PublicEventApplicationController@form` | web, auth, event-access |
| PUT | `ajukan-event/{event}` | `event-applications.update` | `App\Http\Controllers\PublicEventApplicationController@update` | web, auth, event-access, throttle:4,1 |
| DELETE | `ajukan-event/{event}` | `event-applications.destroy` | `App\Http\Controllers\PublicEventApplicationController@destroy` | web, auth, event-access |
| PATCH | `ajukan-event/{event}/pendaftaran` | `event-applications.toggle-registration` | `App\Http\Controllers\PublicEventApplicationController@toggleRegistration` | web, auth, event-access |
| GET\|HEAD | `daftar` | `register` | `App\Http\Controllers\Auth\RegisteredUserController@create` | web, guest |
| POST | `daftar` | `register.store` | `App\Http\Controllers\Auth\RegisteredUserController@store` | web, guest, throttle:6,1 |
| GET\|HEAD | `dasbor-peserta` | `guest.dashboard` | `App\Http\Controllers\GuestDashboardController` | web, auth, event-access |
| GET\|HEAD | `dasbor-peserta/kejuaraan/{event}/kategori` | `owner.events.categories.edit` | `App\Http\Controllers\EventCategoryController@edit` | web, auth, event-access |
| PUT | `dasbor-peserta/kejuaraan/{event}/kategori` | `owner.events.categories.update` | `App\Http\Controllers\EventCategoryController@update` | web, auth, event-access |
| GET\|HEAD | `dasbor-peserta/kejuaraan/{event}/keuangan` | `owner.events.finance.index` | `App\Http\Controllers\EventFinanceController@index` | web, auth, event-access |
| POST | `dasbor-peserta/kejuaraan/{event}/keuangan` | `owner.events.finance.store` | `App\Http\Controllers\EventFinanceController@store` | web, auth, event-access |
| PATCH | `dasbor-peserta/kejuaraan/{event}/keuangan/{transaction}` | `owner.events.finance.update` | `App\Http\Controllers\EventFinanceController@update` | web, auth, event-access |
| DELETE | `dasbor-peserta/kejuaraan/{event}/keuangan/{transaction}` | `owner.events.finance.destroy` | `App\Http\Controllers\EventFinanceController@destroy` | web, auth, event-access |
| GET\|HEAD | `dasbor-peserta/kejuaraan/{event}/perlombaan` | `owner.events.race.format` | `App\Http\Controllers\EventRaceFormatController@index` | web, auth, event-access |
| PUT | `dasbor-peserta/kejuaraan/{event}/perlombaan` | `owner.events.race.format.update` | `App\Http\Controllers\EventRaceFormatController@update` | web, auth, event-access |
| POST | `dasbor-peserta/kejuaraan/{event}/perlombaan/babak` | `owner.events.race.heats.build` | `App\Http\Controllers\EventRaceDayController@buildHeats` | web, auth, event-access |
| DELETE | `dasbor-peserta/kejuaraan/{event}/perlombaan/babak` | `owner.events.race.heats.reset` | `App\Http\Controllers\EventRaceDayController@resetHeats` | web, auth, event-access |
| POST | `dasbor-peserta/kejuaraan/{event}/perlombaan/grup/{group}/siapkan` | `owner.events.race.group.prepare` | `App\Http\Controllers\EventRaceDayController@prepareGroup` | web, auth, event-access |
| GET\|HEAD | `dasbor-peserta/kejuaraan/{event}/perlombaan/hari` | `owner.events.race.day` | `App\Http\Controllers\EventRaceDayController@show` | web, auth, event-access |
| PATCH | `dasbor-peserta/kejuaraan/{event}/perlombaan/hasil/{heat}` | `owner.events.race.heat.update` | `App\Http\Controllers\EventRaceDayController@updateHeat` | web, auth, event-access |
| POST | `dasbor-peserta/kejuaraan/{event}/perlombaan/rilis` | `owner.events.race.release` | `App\Http\Controllers\EventRaceFormatController@release` | web, auth, event-access |
| DELETE | `dasbor-peserta/kejuaraan/{event}/perlombaan/rilis` | `owner.events.race.unrelease` | `App\Http\Controllers\EventRaceFormatController@unrelease` | web, auth, event-access |
| POST | `dasbor-peserta/kejuaraan/{event}/perlombaan/tahap` | `owner.events.race.advance` | `App\Http\Controllers\EventRaceDayController@advance` | web, auth, event-access |
| POST | `dasbor-peserta/kejuaraan/{event}/perlombaan/tunda/{entry}` | `owner.events.race.entry.postpone` | `App\Http\Controllers\EventRaceDayController@postpone` | web, auth, event-access |
| GET\|HEAD | `direktori` | `directory.index` | `App\Http\Controllers\DirectoryController@index` | web |
| GET\|HEAD | `direktori/daftar-atlet` | `directory.athletes.create` | `App\Http\Controllers\DirectoryController@createAthlete` | web, auth, event-access |
| POST | `direktori/daftar-atlet` | `directory.athletes.store` | `App\Http\Controllers\DirectoryController@storeAthlete` | web, auth, event-access, throttle:6,1 |
| GET\|HEAD | `direktori/daftar-klub` | `directory.clubs.create` | `App\Http\Controllers\DirectoryController@createClub` | web |
| POST | `direktori/daftar-klub` | `directory.clubs.store` | `App\Http\Controllers\DirectoryController@storeClub` | web, throttle:6,1 |
| PATCH | `direktori/klub/{club}` | `directory.clubs.update` | `App\Http\Controllers\DirectoryController@updateClub` | web, auth, event-access |
| GET\|HEAD | `direktori/klub/{club}/ubah` | `directory.clubs.edit` | `App\Http\Controllers\DirectoryController@editClub` | web, auth, event-access |
| POST | `email/verification-notification` | `verification.send` | `App\Http\Controllers\Auth\EmailVerificationController@resend` | web, auth, throttle:6,1 |
| GET\|HEAD | `email/verify` | `verification.notice` | `App\Http\Controllers\Auth\EmailVerificationController@notice` | web, auth |
| GET\|HEAD | `email/verify/{id}/{hash}` | `verification.verify` | `App\Http\Controllers\Auth\EmailVerificationController@verify` | web, auth, signed, throttle:6,1 |
| GET\|HEAD | `kejuaraan/{event}/atlet` | `events.registrations.index` | `App\Http\Controllers\EventRegistrationManagementController@index` | web, auth, event-access |
| POST | `kejuaraan/{event}/atlet/bagi-kategori` | `events.registrations.categories.assign` | `App\Http\Controllers\EventRegistrationManagementController@assignCategories` | web, auth, event-access |
| PATCH | `kejuaraan/{event}/atlet/publikasi` | `events.registrations.publish` | `App\Http\Controllers\EventRegistrationManagementController@publish` | web, auth, event-access |
| GET\|HEAD | `kejuaraan/{event}/atlet/simpan` | `events.registrations.export` | `App\Http\Controllers\EventRegistrationManagementController@export` | web, auth, event-access |
| PUT | `kejuaraan/{event}/atlet/{registration}` | `events.registrations.update` | `App\Http\Controllers\EventRegistrationManagementController@update` | web, auth, event-access |
| GET\|HEAD | `kejuaraan/{event}/atlet/{registration}/bukti` | `events.registrations.proof` | `App\Http\Controllers\EventRegistrationManagementController@proof` | web, auth, event-access |
| POST | `kejuaraan/{event}/atlet/{registration}/konfirmasi-pembayaran` | `events.registrations.payment.confirm` | `App\Http\Controllers\EventRegistrationManagementController@confirmPayment` | web, auth, event-access |
| GET\|HEAD | `kejuaraan/{event}/atlet/{registration}/pembatalan/hubungi` | `events.registrations.cancellations.contact` | `App\Http\Controllers\EventRegistrationManagementController@contactCancellationRequester` | web, auth, event-access |
| POST | `kejuaraan/{event}/atlet/{registration}/pembatalan/keputusan` | `events.registrations.cancellations.review` | `App\Http\Controllers\EventRegistrationManagementController@reviewCancellation` | web, auth, event-access |
| POST | `kejuaraan/{event}/atlet/{registration}/perubahan/keputusan` | `events.registrations.changes.review` | `App\Http\Controllers\EventRegistrationManagementController@reviewParticipantChange` | web, auth, event-access |
| GET\|HEAD | `kejuaraan/{event}/urutan-lomba` | `events.race.order` | `App\Http\Controllers\EventRaceOrderController@show` | web, auth, event-access |
| POST | `keluar` | `logout` | `App\Http\Controllers\Auth\AuthenticatedSessionController@destroy` | web, auth |
| GET\|HEAD | `lisensi` | `license` | `Illuminate\Routing\ViewController` | web |
| GET\|HEAD | `lomba` | `events.index` | `App\Http\Controllers\EventListingController` | web |
| PUT | `lomba/{event}/atlet/{registration}` | `events.registration.update-own` | `App\Http\Controllers\EventRegistrationController@updateOwn` | web, auth, event-access |
| DELETE | `lomba/{event}/atlet/{registration}` | `events.registration.destroy-own` | `App\Http\Controllers\EventRegistrationController@destroyOwn` | web, auth, event-access |
| POST | `lomba/{event}/atlet/{registration}/bukti` | `events.registration.proof.store` | `App\Http\Controllers\EventRegistrationController@uploadProof` | web, auth, event-access, throttle:8,1 |
| POST | `lomba/{event}/atlet/{registration}/konfirmasi-whatsapp` | `events.registration.whatsapp` | `App\Http\Controllers\EventRegistrationController@confirmViaWhatsapp` | web, auth, event-access, throttle:8,1 |
| PATCH | `lomba/{event}/atlet/{registration}/mata-lomba` | `events.registration.races.update` | `App\Http\Controllers\EventRegistrationController@updateRaces` | web, auth, event-access |
| POST | `lomba/{event}/atlet/{registration}/pembatalan-whatsapp` | `events.registration.cancellation.whatsapp` | `App\Http\Controllers\EventRegistrationController@cancelViaWhatsapp` | web, auth, event-access, throttle:8,1 |
| GET\|HEAD | `lomba/{event}/daftar` | `events.registration.create` | `App\Http\Controllers\EventRegistrationController@create` | web, auth, event-access |
| POST | `lomba/{event}/daftar` | `events.registration.store` | `App\Http\Controllers\EventRegistrationController@store` | web, auth, event-access, throttle:8,1 |
| POST | `lomba/{event}/resolve-category` | `events.registration.resolve-category` | `App\Http\Controllers\EventRegistrationController@resolveCategory` | web, auth, event-access |
| GET\|HEAD | `masuk` | `login` | `App\Http\Controllers\Auth\AuthenticatedSessionController@create` | web, guest |
| POST | `masuk` | `login.store` | `App\Http\Controllers\Auth\AuthenticatedSessionController@store` | web, guest, throttle:6,1 |
| POST | `nik/baca` | `nik.lookup` | `App\Http\Controllers\NikLookupController` | web, auth, throttle:30,1 |
| GET\|HEAD | `profil` | `profile.show` | `App\Http\Controllers\ProfileController@show` | web, auth |
| PUT | `profil` | `profile.update` | `App\Http\Controllers\ProfileController@update` | web, auth |

### Lapisan aplikasi (app/) (56 file)

- `app/Console/Commands/BackfillDirectory.php` — 50 baris — `BackfillDirectory` — method: `handle`
- `app/Console/Commands/BootstrapPrimaryAdmin.php` — 73 baris — `BootstrapPrimaryAdmin` — method: `handle`
- `app/Console/Commands/ReleaseClosedParticipantLists.php` — 48 baris — `ReleaseClosedParticipantLists` — method: `handle`
- `app/Http/Controllers/Admin/DataManagementController.php` — 138 baris — `DataManagementController` — method: `backup`, `createBackup`, `restore`, `destroyBackup`, `delete`, `reviewDeletion`, `destroySelected`
- `app/Http/Controllers/Admin/EventOwnershipController.php` — 46 baris — `EventOwnershipController` — method: `update`
- `app/Http/Controllers/Admin/EventSubmissionController.php` — 107 baris — `EventSubmissionController` — method: `index`, `approve`, `reject`
- `app/Http/Controllers/Admin/ThemeSettingController.php` — 35 baris — `ThemeSettingController` — method: `edit`, `update`
- `app/Http/Controllers/Admin/UserController.php` — 181 baris — `UserController` — method: `index`, `form`, `store`, `update`, `resendVerification`, `verifyEmail`
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php` — 47 baris — `AuthenticatedSessionController` — method: `create`, `store`, `destroy`
- `app/Http/Controllers/Auth/EmailVerificationController.php` — 57 baris — `EmailVerificationController` — method: `notice`, `verify`, `resend`
- `app/Http/Controllers/Auth/RegisteredUserController.php` — 47 baris — `RegisteredUserController` — method: `create`, `store`
- `app/Http/Controllers/Controller.php` — 8 baris — `Controller`
- `app/Http/Controllers/DirectoryController.php` — 245 baris — `DirectoryController` — method: `index`, `createClub`, `storeClub`, `editClub`, `updateClub`, `createAthlete`, `storeAthlete`
- `app/Http/Controllers/EventCategoryController.php` — 689 baris — `EventCategoryController` — method: `edit`, `update`
- `app/Http/Controllers/EventFinanceController.php` — 262 baris — `EventFinanceController` — method: `index`, `store`, `update`, `destroy`
- `app/Http/Controllers/EventListingController.php` — 23 baris — `EventListingController` — method: `__invoke`
- `app/Http/Controllers/EventRaceDayController.php` — 377 baris — `EventRaceDayController` — method: `show`, `buildHeats`, `resetHeats`, `prepareGroup`, `postpone`, `updateHeat`, `advance`
- `app/Http/Controllers/EventRaceFormatController.php` — 310 baris — `EventRaceFormatController` — method: `index`, `update`, `release`, `unrelease`
- `app/Http/Controllers/EventRaceOrderController.php` — 80 baris — `EventRaceOrderController` — method: `show`
- `app/Http/Controllers/EventRegistrationController.php` — 501 baris — `EventRegistrationController` — method: `create`, `store`, `updateOwn`, `updateRaces`, `destroyOwn`, `uploadProof`, `confirmViaWhatsapp`, `cancelViaWhatsapp`, …
- `app/Http/Controllers/EventRegistrationManagementController.php` — 799 baris — `EventRegistrationManagementController` — method: `index`, `export`, `assignCategories`, `publish`, `update`, `confirmPayment`, `reviewParticipantChange`, `reviewCancellation`, …
- `app/Http/Controllers/GuestDashboardController.php` — 148 baris — `GuestDashboardController` — method: `__invoke`
- `app/Http/Controllers/NikLookupController.php` — 44 baris — `NikLookupController` — method: `__invoke`
- `app/Http/Controllers/ProfileController.php` — 43 baris — `ProfileController` — method: `show`, `update`
- `app/Http/Controllers/PublicEventApplicationController.php` — 146 baris — `PublicEventApplicationController` — method: `form`, `store`, `update`, `destroy`, `toggleRegistration`
- `app/Http/Middleware/EnsureEventAccess.php` — 25 baris — `EnsureEventAccess` — method: `handle`
- `app/Http/Middleware/EnsureUserHasRole.php` — 22 baris — `EnsureUserHasRole` — method: `handle`
- `app/Http/Requests/EventDetailsRequest.php` — 137 baris — `EventDetailsRequest` — method: `authorize`, `isCreating`, `rules`, `eventAttributes`
- `app/Http/Requests/UpdateEventRegistrationRequest.php` — 62 baris — `UpdateEventRegistrationRequest` — method: `authorize`, `rules`, `withValidator`, `attributes`
- `app/Models/Athlete.php` — 97 baris — `Athlete` — method: `club`, `owner`, `canViewNik`, `maskedNik`, `scopeSearch`
- `app/Models/Club.php` — 52 baris — `Club` — method: `owner`, `athletes`, `isManagedBy`
- `app/Models/Event.php` — 444 baris — `Event` — method: `submitter`, `reviewer`, `registrations`, `registrationFinancialEntries`, `financeTransactions`, `raceSettings`, `raceHeats`, `raceEntries`, …
- `app/Models/EventCategoryRaceSetting.php` — 265 baris — `EventCategoryRaceSetting` — method: `event`, `usesPoints`, `stageLadder`, `stageBefore`, `stageAfter`, `hasQualificationRound`, `hasSemiFinal`, `hasFinal`, …
- `app/Models/EventFinanceTransaction.php` — 83 baris — `EventFinanceTransaction` — method: `event`, `author`, `isIncome`, `categoryLabel`, `categoriesFor`
- `app/Models/EventRaceEntry.php` — 128 baris — `EventRaceEntry` — method: `heat`, `registration`, `statusLabel`, `isAwaitingRace`, `parseTime`, `formatTime`
- `app/Models/EventRaceGroup.php` — 102 baris — `EventRaceGroup` — method: `event`, `heats`, `isPrepared`, `status`, `statusLabel`, `label`, `stageLabel`
- `app/Models/EventRaceHeat.php` — 85 baris — `EventRaceHeat` — method: `event`, `entries`, `group`, `stageLabel`, `statusLabel`, `hasResults`
- `app/Models/EventRegistration.php` — 160 baris — `EventRegistration` — method: `event`, `user`, `financialEntries`, `isForeignNational`, `maskedIdentity`, `identityHash`, `amountPaid`, `amountDue`
- `app/Models/EventRegistrationFinancialEntry.php` — 42 baris — `EventRegistrationFinancialEntry` — method: `event`, `registration`, `recorder`
- `app/Models/User.php` — 59 baris — `User` — method: `roleLabel`, `isAdmin`
- `app/Notifications/CancellationRequestedNotification.php` — 50 baris — `CancellationRequestedNotification` — method: `via`, `toMail`, `toArray`
- `app/Notifications/EventDecisionNotification.php` — 59 baris — `EventDecisionNotification` — method: `via`, `toMail`, `toArray`
- `app/Notifications/EventSubmittedNotification.php` — 43 baris — `EventSubmittedNotification` — method: `via`, `toMail`, `toArray`
- `app/Providers/AppServiceProvider.php` — 45 baris — `AppServiceProvider` — method: `register`, `boot`
- `app/Rules/NikMatchesIdentity.php` — 72 baris — `NikMatchesIdentity` — method: `validate`
- `app/Services/AdminDataManagementService.php` — 714 baris — `AdminDataManagementService` — method: `catalogs`, `listBackups`, `createBackup`, `deleteBackup`, `deletionSnapshot`, `deleteSelected`, `restoreBackup`
- `app/Services/AssignRegistrationCategories.php` — 190 baris — `AssignRegistrationCategories` — method: `assign`, `preview`
- `app/Services/CategoryFeeCalculator.php` — 516 baris — `CategoryFeeCalculator` — method: `pricing`, `requiredRaces`, `breakdown`, `breakdownForPayment`, `ruleByName`, `sync`, `hasPricing`, `hasAnyPricing`, …
- `app/Services/NikParser.php` — 249 baris — `NikParser` — method: `parse`, `suggestAddress`
- `app/Services/PricingWaveResolver.php` — 174 baris — `PricingWaveResolver` — method: `activeFor`, `basePricingAppliesOn`, `baseRange`, `waves`, `hasWaves`, `apply`
- `app/Services/RaceHeatBuilder.php` — 587 baris — `RaceHeatBuilder` — method: `entrantsFor`, `buildHeats`, `resetArrangement`, `hasResults`, `advance`, `postpone`, `syncHeatStatus`, `finishers`
- `app/Services/RaceStandings.php` — 168 baris — `RaceStandings` — method: `forCategory`, `stageProgress`
- `app/Services/ReconcileRegistrationFee.php` — 80 baris — `ReconcileRegistrationFee` — method: `preserveHistoricalPayment`, `reconcile`
- `app/Services/RegistrationIdentity.php` — 148 baris — `RegistrationIdentity` — method: `normalize`, `ensureNotDuplicate`, `fieldRules`, `assertComplete`
- `app/Services/ResolveEventRegistrationCategory.php` — 78 baris — `ResolveEventRegistrationCategory` — method: `eligibleCategories`, `matchingCategory`, `bestMatch`
- `app/Services/SyncRegistrationToDirectory.php` — 166 baris — `SyncRegistrationToDirectory` — method: `registrationChanged`, `syncRegistration`, `resolveClub`

### View Blade (resources/views/) (43 file)

- `resources/views/admin/accounts/form.blade.php` — 86 baris
- `resources/views/admin/accounts/index.blade.php` — 267 baris
- `resources/views/admin/dashboard.blade.php` — 132 baris
- `resources/views/admin/data/backup.blade.php` — 95 baris
- `resources/views/admin/data/delete-confirmation.blade.php` — 50 baris
- `resources/views/admin/data/delete.blade.php` — 57 baris
- `resources/views/admin/event-submissions/index.blade.php` — 48 baris
- `resources/views/admin/settings/theme.blade.php` — 144 baris
- `resources/views/auth/login.blade.php` — 42 baris
- `resources/views/auth/profile.blade.php` — 124 baris
- `resources/views/auth/register.blade.php` — 73 baris
- `resources/views/auth/verify-email.blade.php` — 21 baris
- `resources/views/components/blade-location.blade.php` — 10 baris
- `resources/views/components/contact-card.blade.php` — 98 baris
- `resources/views/components/footer.blade.php` — 10 baris
- `resources/views/components/logo.blade.php` — 13 baris
- `resources/views/components/navbar.blade.php` — 33 baris
- `resources/views/components/page-context.blade.php` — 28 baris
- `resources/views/components/preset-picker.blade.php` — 62 baris
- `resources/views/components/sidebar.blade.php` — 59 baris
- `resources/views/components/topbar.blade.php` — 9 baris
- `resources/views/components/user-badge.blade.php` — 18 baris
- `resources/views/layouts/admin.blade.php` — 50 baris
- `resources/views/layouts/public.blade.php` — 66 baris
- `resources/views/publik/beranda.blade.php` — 279 baris
- `resources/views/publik/direktori/atlet.blade.php` — 90 baris
- `resources/views/publik/direktori/index.blade.php` — 155 baris
- `resources/views/publik/direktori/klub-edit.blade.php` — 53 baris
- `resources/views/publik/direktori/klub.blade.php` — 52 baris
- `resources/views/publik/lisensi.blade.php` — 84 baris
- `resources/views/publik/lomba/index.blade.php` — 110 baris
- `resources/views/verified/dashboard.blade.php` — 426 baris
- `resources/views/verified/events/categories.blade.php` — 585 baris
- `resources/views/verified/events/finance/index.blade.php` — 301 baris
- `resources/views/verified/events/participants/export.blade.php` — 173 baris
- `resources/views/verified/events/participants/index.blade.php` — 609 baris
- `resources/views/verified/events/participants/ubah.blade.php` — 137 baris
- `resources/views/verified/events/pendaftaran/daftar.blade.php` — 99 baris
- `resources/views/verified/events/pengajuan/form.blade.php` — 217 baris
- `resources/views/verified/events/race/day.blade.php` — 252 baris
- `resources/views/verified/events/race/format.blade.php` — 370 baris
- `resources/views/verified/events/race/order.blade.php` — 93 baris
- `resources/views/verified/events/race/partials/heat.blade.php` — 174 baris

### Pengujian (tests/) (29 file)

- `tests/Feature/AccountPortalTest.php` — 498 baris — `AccountPortalTest` — method: `test_guests_can_open_registration_and_login_pages`, `test_public_registration_creates_and_signs_in_a_non_admin_account`, `test_registration_sends_a_verification_email_and_the_signed_link_verifies_the_account`, `test_public_registration_cannot_select_admin_role`, `test_profile_form_uses_control_two_inputs_and_validation_states`, `test_users_can_login_update_their_profile_and_logout`, `test_primary_admin_email_is_fixed_in_profiles_and_when_saving_the_account`, `test_admin_can_edit_an_account_and_email_changes_require_reverification`, …
- `tests/Feature/AdminDashboardThemeTest.php` — 23 baris — `AdminDashboardThemeTest` — method: `test_admin_dashboard_hero_text_is_readable_in_ceria_theme`
- `tests/Feature/AdminDataManagementTest.php` — 286 baris — `AdminDataManagementTest` — method: `test_guest_is_redirected_from_data_management_pages`, `test_non_admin_cannot_access_data_management_pages`, `test_admin_can_review_and_delete_a_selected_catalog`, `test_deletion_requires_a_fresh_review_when_catalog_contents_change`, `test_user_catalog_deletion_preserves_admin_accounts`, `test_selected_catalog_is_not_deleted_without_confirmation`, `test_admin_can_create_list_and_restore_a_server_backup`, `test_restore_rejects_a_tampered_server_backup_without_changing_data`, …
- `tests/Feature/AdminEventFormTest.php` — 252 baris — `AdminEventFormTest` — method: `test_admin_submits_event_from_the_shared_dashboard_flow`, `test_admin_can_mark_an_event_as_free`, `test_admin_configures_categories_from_the_shared_dashboard`, `test_event_forms_ask_only_for_categories_and_races`, `test_age_groups_can_no_longer_be_submitted_from_the_event_form`, `test_legacy_admin_create_mode_does_not_open_an_admin_event_form`, `test_event_form_exposes_cancellation_policy_fields`, `test_event_end_date_cannot_precede_its_start_date`, …
- `tests/Feature/AdminEventManagementTest.php` — 135 baris — `AdminEventManagementTest` — method: `test_admin_reviews_submissions_in_notifications_and_manages_events_in_the_shared_dashboard`, `test_admin_can_approve_event_submission_without_500`, `test_admin_can_reject_event_submission`, `test_public_listing_hides_pending_events_and_management_actions_for_every_role`, `test_the_legacy_admin_event_management_endpoint_is_removed`
- `tests/Feature/AdminSidebarTest.php` — 53 baris — `AdminSidebarTest` — method: `test_admin_sidebar_has_a_toggle_and_no_help_card`, `test_admin_sidebar_only_links_to_modules_that_exist`, `test_placeholder_admin_module_pages_have_been_removed`
- `tests/Feature/CategoryPricingTest.php` — 1105 baris — `CategoryPricingTest` — method: `test_fee_calculator_applies_package_extra_races_outside_races_and_discount`, `test_wave_tariffs_charge_package_once_and_extras_per_race`, `test_fee_calculator_never_returns_a_negative_total`, `test_outside_obligation_category_charges_the_outside_fee_for_each_selected_race`, `test_organizer_can_store_required_races_and_pricing_per_category`, `test_organizer_can_save_separate_outside_obligation_category_and_fee`, `test_overlapping_categories_report_the_shared_birth_gender_and_race_details`, `test_category_form_builds_the_final_name_from_category_and_gender_dropdowns`, …
- `tests/Feature/CategoryQuotaTest.php` — 416 baris — `CategoryQuotaTest` — method: `test_a_category_without_a_quota_is_never_full`, `test_only_paid_and_accepted_athletes_consume_a_quota_slot`, `test_quota_scope_can_count_only_female_only_male_or_both`, `test_single_gender_category_defaults_to_its_own_quota_scope`, `test_a_full_category_closes_itself_once_the_quota_is_reached`, `test_an_approved_cancellation_frees_the_slot_again`, `test_the_organizer_can_force_a_category_closed_without_setting_a_quota`, `test_the_organizer_can_reopen_a_full_category`, …
- `tests/Feature/DirectoryTest.php` — 557 baris — `DirectoryTest` — method: `test_the_public_dashboard_links_to_the_directory`, `test_a_guest_can_browse_the_club_list_and_search_by_club_name`, `test_the_participant_list_is_limited_to_the_club_owner_the_account_owner_and_admin`, `test_the_nik_is_hidden_from_everyone_except_the_owner_and_the_club_owner`, `test_an_event_registration_is_mirrored_into_the_directory`, `test_a_foreign_national_registration_is_mirrored_with_its_passport`, `test_a_club_can_be_registered_without_any_event_and_shows_its_contact`, `test_the_same_club_cannot_be_registered_twice`, …
- `tests/Feature/EventApplicationTest.php` — 849 baris — `EventApplicationTest` — method: `test_event_applications_require_an_account_and_verified_email`, `test_complete_event_application_is_saved_as_pending_and_not_public`, `test_event_application_requires_a_valid_organizer_whatsapp_number`, `test_admin_approval_notifies_submitter_and_publishes_event`, `test_each_event_submission_is_shown_in_its_own_compact_card`, `test_admin_rejection_keeps_event_private_and_sends_feedback_to_applicant`, `test_participant_registration_requires_account_verification_and_saves_selected_races`, `test_event_manager_can_update_age_gender_and_race_rules_and_only_owner_can_change_them`, …
- `tests/Feature/EventFinanceTest.php` — 388 baris — `EventFinanceTest` — method: `test_owner_of_an_approved_event_can_open_the_finance_page`, `test_admin_can_open_the_finance_page_for_any_event`, `test_user_without_the_event_is_forbidden`, `test_owner_cannot_finance_an_event_that_is_not_approved_yet`, `test_guest_is_redirected_to_login`, `test_registration_income_counts_only_confirmed_payments`, `test_registration_income_ignores_manual_notes`, `test_owner_can_store_an_expense`, …
- `tests/Feature/EventListingTest.php` — 316 baris — `EventListingTest` — method: `test_public_listing_contains_only_approved_events_and_no_management_controls`, `test_verified_owner_manages_events_from_the_shared_dashboard_not_the_public_listing`, `test_unverified_user_is_prompted_to_verify_before_registering_or_applying`, `test_admin_uses_shared_dashboard_for_management_and_notifications_for_review_only`, `test_admin_can_transfer_an_admin_owned_event_to_a_verified_account`, `test_admin_can_assign_an_unowned_legacy_event_to_a_verified_account`, `test_admin_cannot_transfer_an_event_owned_by_a_non_admin_account`, `test_only_admin_can_transfer_event_ownership`, …
- `tests/Feature/EventPaymentAccountTest.php` — 258 baris — `EventPaymentAccountTest` — method: `test_organizer_can_submit_an_event_with_a_payment_account`, `test_payment_account_is_optional`, `test_account_number_without_a_bank_name_is_rejected`, `test_account_number_with_letters_is_rejected`, `test_owner_can_change_the_payment_account_from_the_edit_form`, `test_clearing_the_payment_account_removes_it`, `test_registration_page_shows_the_payment_account_to_the_athlete`, `test_registration_page_hides_the_payment_account_for_free_events`, …
- `tests/Feature/EventRaceFormatTest.php` — 1853 baris — `EventRaceFormatTest` — method: `test_owner_of_an_approved_event_can_open_the_format_page`, `test_admin_can_open_the_format_page_for_any_event`, `test_user_without_the_event_is_forbidden`, `test_owner_cannot_configure_an_event_that_is_not_approved_yet`, `test_guest_is_redirected_to_login`, `test_format_page_shows_usable_defaults_before_anything_is_saved`, `test_format_is_saved_per_category_and_race`, `test_saving_saves_every_category_in_one_submit`, …
- `tests/Feature/EventRegistrationManagementTest.php` — 1571 baris — `EventRegistrationManagementTest` — method: `test_event_owner_can_update_athlete_data_and_confirm_payment_from_the_roster`, `test_admin_can_edit_a_released_roster_but_not_publish_it`, `test_the_managed_roster_shows_full_biodata_and_a_whatsapp_link`, `test_the_public_roster_stays_free_of_biodata_and_contact_details`, `test_the_public_roster_can_be_searched_by_name_or_club`, `test_the_released_roster_can_be_filtered_by_category_race_and_gender`, `test_the_fee_is_shown_only_to_the_event_manager`, `test_only_a_manager_gets_the_printable_category_toggles`, …
- `tests/Feature/EventSimulationSeederTest.php` — 79 baris — `EventSimulationSeederTest` — method: `test_seeder_creates_pending_event_with_login_ready_accounts`, `test_seeder_can_be_run_again_without_duplicating_data`, `test_purge_removes_simulation_but_keeps_real_accounts`
- `tests/Feature/ExampleTest.php` — 101 baris — `ExampleTest` — method: `test_the_public_homepage_highlights_available_event_platform_features`, `test_the_public_homepage_shows_the_event_empty_state_when_no_events_are_upcoming`, `test_the_public_event_listing_only_displays_approved_events`, `test_the_panitia_dashboard_remains_available_separately`
- `tests/Feature/GuestDashboardTest.php` — 212 baris — `GuestDashboardTest` — method: `test_verified_user_can_open_their_dashboard_and_see_only_their_registrations`, `test_participant_can_update_races_directly_from_dashboard_table`, `test_paid_participant_cannot_update_races_from_table`, `test_registration_form_does_not_show_already_registered_athletes`, `test_unverified_user_is_prompted_to_verify_and_does_not_see_dashboard_button`, `test_individual_and_club_accounts_can_register_when_registration_is_open`, `test_dashboard_hides_registration_link_until_manager_opens_registration`
- `tests/Feature/HomeAuthFormTest.php` — 54 baris — `HomeAuthFormTest` — method: `test_guest_sees_login_and_registration_forms_on_homepage`, `test_authenticated_user_does_not_see_homepage_auth_forms`, `test_failed_login_shows_the_error_in_the_homepage_form`
- `tests/Feature/LicensePageTest.php` — 32 baris — `LicensePageTest` — method: `test_public_footer_menampilkan_copyright_damai_dan_tautan_lisensi`, `test_halaman_lisensi_dapat_dibuka_tanpa_login`
- `tests/Feature/MergedFormPagesTest.php` — 158 baris — `MergedFormPagesTest` — method: `test_event_create_and_edit_render_the_same_view`, `test_admin_sees_the_same_shell_for_event_create_and_edit`, `test_event_edit_uses_the_unified_url_without_an_edit_suffix`, `test_account_create_and_edit_render_the_same_view`, `test_account_form_switches_only_by_mode`, `test_new_event_still_requires_a_future_start_date_and_deadline`
- `tests/Feature/NikAutofillTest.php` — 191 baris — `NikAutofillTest` — method: `test_authenticated_user_can_read_a_nik_into_region_gender_and_birth_date`, `test_nik_lookup_reads_female_nik_from_a_day_above_forty`, `test_nik_lookup_rejects_unknown_region_code`, `test_nik_lookup_requires_sixteen_digits`, `test_nik_lookup_requires_an_account`, `test_registration_page_exposes_the_nik_feedback_container_for_the_region_notice`, `test_registration_rejects_nik_that_does_not_match_the_given_gender`, `test_registration_rejects_nik_that_does_not_match_the_given_birth_date`, …
- `tests/Feature/PageContextWidgetTest.php` — 119 baris — `PageContextWidgetTest` — method: `test_user_badge_menampilkan_nama_dan_role_pada_setiap_halaman`, `test_lokasi_blade_hanya_muncul_untuk_admin`, `test_halaman_pendaftaran_menampilkan_navbar_di_atas_dan_konten_di_dalam_layout`
- `tests/Feature/ProjectMapTest.php` — 36 baris — `ProjectMapTest` — method: `test_project_map_contains_every_named_route`, `test_project_map_keeps_the_auto_generated_markers`
- `tests/Feature/ThemeSettingTest.php` — 74 baris — `ThemeSettingTest` — method: `test_theme_settings_page_offers_the_ceria_theme`, `test_admin_can_save_the_ceria_theme`, `test_saved_theme_is_written_before_client_scripts_run`, `test_theme_settings_rejects_an_unknown_theme`, `test_ceria_stickers_are_marked_only_on_public_pages`
- `tests/TestCase.php` — 46 baris — `TestCase`
- `tests/Unit/ExampleTest.php` — 16 baris — `ExampleTest` — method: `test_that_true_is_true`
- `tests/Unit/NikParserTest.php` — 90 baris — `NikParserTest` — method: `it_parses_male_nik_with_region_gender_and_birth_date`, `it_detects_female_nik_from_day_above_forty`, `it_reads_two_digit_year_as_1900s_for_values_above_forty_nine`, `it_rejects_nik_that_is_not_sixteen_digits`, `it_rejects_nik_with_unknown_region_code`, `it_rejects_nik_with_invalid_birth_month`, `it_builds_suggested_address_from_region_names`
- `tests/Unit/PricingWaveTest.php` — 139 baris — `PricingWaveTest` — method: `it_selects_the_wave_matching_the_given_date`, `it_treats_wave_boundaries_as_inclusive`, `active_wave_overrides_only_the_fields_it_fills`, `pricing_falls_back_to_base_fee_outside_any_wave`, `breakdown_uses_the_wave_active_on_the_registration_date`, `a_rule_with_no_base_fee_but_a_wave_still_counts_as_priced`, `wave_summaries_flag_the_wave_active_on_the_given_date`

### Database: migrasi, seeder, factory (36 file)

- `database/factories/UserFactory.php` — 45 baris — `UserFactory` — method: `definition`, `unverified`
- `database/migrations/0001_01_01_000000_create_users_table.php` — 49 baris — method: `up`, `down`
- `database/migrations/0001_01_01_000001_create_cache_table.php` — 35 baris — method: `up`, `down`
- `database/migrations/0001_01_01_000002_create_jobs_table.php` — 59 baris — method: `up`, `down`
- `database/migrations/2026_09_26_223725_create_events_table.php` — 46 baris — method: `up`, `down`
- `database/migrations/2026_09_26_230000_add_account_fields_to_users_table.php` — 26 baris — method: `up`, `down`
- `database/migrations/2026_09_26_231000_add_event_review_fields.php` — 55 baris — method: `up`, `down`
- `database/migrations/2026_09_27_001000_set_primary_admin_email.php` — 42 baris — method: `up`, `down`
- `database/migrations/2026_09_27_020000_add_event_category_rules_and_athlete_biodata.php` — 48 baris — method: `up`, `down`
- `database/migrations/2026_09_27_022634_add_registration_open_to_events_table.php` — 45 baris — method: `up`, `down`
- `database/migrations/2026_09_28_141040_add_payment_status_to_event_registrations_table.php` — 28 baris — method: `up`, `down`
- `database/migrations/2026_10_01_082854_add_participant_release_flag_to_events_table.php` — 28 baris — method: `up`, `down`
- `database/migrations/2026_10_01_124522_add_payment_workflow_to_events_and_registrations.php` — 44 baris — method: `up`, `down`
- `database/migrations/2026_10_01_134657_add_organizer_whatsapp_to_events_table.php` — 37 baris — method: `up`, `down`
- `database/migrations/2026_10_02_010000_add_category_pricing_to_event_registrations.php` — 28 baris — method: `up`, `down`
- `database/migrations/2026_10_02_020000_add_payment_account_to_events_table.php` — 33 baris — method: `up`, `down`
- `database/migrations/2026_10_02_030000_make_age_groups_nullable_on_events_table.php` — 35 baris — method: `up`, `down`
- `database/migrations/2026_10_02_040000_create_event_finance_transactions_table.php` — 43 baris — method: `up`, `down`
- `database/migrations/2026_10_02_041000_add_confirmed_paid_at_to_event_registrations_table.php` — 31 baris — method: `up`, `down`
- `database/migrations/2026_10_02_050000_create_event_category_race_settings_table.php` — 48 baris — method: `up`, `down`
- `database/migrations/2026_10_02_051000_create_event_race_heats_table.php` — 39 baris — method: `up`, `down`
- `database/migrations/2026_10_02_052000_create_event_race_entries_table.php` — 42 baris — method: `up`, `down`
- `database/migrations/2026_10_04_224049_add_participant_change_and_cancellation_fields_to_event_registrations_table.php` — 62 baris — method: `up`, `down`
- `database/migrations/2026_10_11_031056_add_foreign_identity_to_event_registrations_table.php` — 43 baris — method: `up`, `down`
- `database/migrations/2026_10_11_033232_add_participant_release_timestamp_to_events_table.php` — 31 baris — method: `up`, `down`
- `database/migrations/2026_10_11_051047_create_clubs_and_athletes_tables.php` — 66 baris — method: `up`, `down`
- `database/migrations/2026_10_11_130609_add_race_scope_to_race_tables.php` — 122 baris — method: `up`, `down`
- `database/migrations/2026_10_11_160000_create_event_race_groups_table.php` — 108 baris — method: `up`, `down`
- `database/migrations/2026_11_26_075706_add_cancellation_reason_to_event_registrations_table.php` — 22 baris — method: `up`, `down`
- `database/migrations/2026_11_26_101921_add_cancellation_policy_to_events.php` — 24 baris — method: `up`, `down`
- `database/migrations/2026_11_27_115333_add_whatsapp_group_link_to_events_table.php` — 22 baris — method: `up`, `down`
- `database/seeders/DatabaseSeeder.php` — 25 baris — `DatabaseSeeder` — method: `run`
- `database/seeders/DemoEventRegistrationSeeder.php` — 540 baris — `DemoEventRegistrationSeeder` — method: `run`
- `database/seeders/EventFinanceSeeder.php` — 68 baris — `EventFinanceSeeder` — method: `run`
- `database/seeders/EventSimulationSeeder.php` — 457 baris — `EventSimulationSeeder` — method: `run`, `purge`
- `database/seeders/TulungagungEventRegistrationSeeder.php` — 144 baris — `TulungagungEventRegistrationSeeder` — method: `run`

### Konfigurasi, rute, dan skrip (23 file)

- `config/app.php` — 126 baris
- `config/auth.php` — 117 baris
- `config/cache.php` — 136 baris
- `config/contact.php` — 29 baris
- `config/database.php` — 184 baris
- `config/event-example.php` — 44 baris
- `config/event-field-presets.php` — 142 baris
- `config/filesystems.php` — 80 baris
- `config/logging.php` — 140 baris
- `config/mail.php` — 118 baris
- `config/preview-events.php` — 40 baris
- `config/primary-admin.php` — 5 baris
- `config/queue.php` — 129 baris
- `config/services.php` — 38 baris
- `config/session.php` — 233 baris
- `config/user-roles.php` — 8 baris
- `routes/console.php` — 8 baris
- `routes/web.php` — 232 baris
- `scripts/fetch-wilayah-data.php` — 99 baris
- `scripts/generate-peta-proyek.php` — 220 baris
- `scripts/inspect-dev-data.php` — 30 baris
- `scripts/qa-make-user.php` — 30 baris
- `scripts/seed-waves-demo.php` — 56 baris

<!-- AUTO:END -->
