---
name: pedoman-agents-id
description: "Pedoman proyek Inlinesia (terjemahan Bahasa Indonesia dari AGENTS.md). Gunakan skill ini untuk memahami semua aturan proyek: konteks Laravel/PHP, konvensi kode, penggunaan tool Laravel Boost, aturan PHP/Pint/PHPUnit, pengujian, kontrol versi Git, bahasa, penjelasan fungsi kode, ADR, larangan, dan peran agent. Aktifkan saat bekerja di proyek ini agar mengikuti aturan yang berlaku."
---

# Pedoman Proyek Inlinesia (Bahasa Indonesia)

Dokumen ini adalah terjemahan Bahasa Indonesia dari `AGENTS.md` dan menjadi panduan kerja (skill) untuk proyek ini. Ikuti aturan di bawah ini secara saksama.

---

## Pedoman Laravel Boost

Pedoman Laravel Boost ini disusun khusus oleh pengelola Laravel untuk aplikasi ini. Ikuti secara saksama demi pengalaman terbaik saat membangun aplikasi Laravel.

### Konteks Dasar

- Aplikasi ini adalah aplikasi Laravel yang berjalan di **PHP 8.4** (versi yang benar-benar terpasang di mesin ini; `composer.json` mensyaratkan `^8.3`). Kamu adalah ahli ekosistem Laravel. Selalu gunakan API yang sesuai dengan versi mayor terpasang dari setiap paket — jangan mengasumsikan versi.
- Sebelum mengandalkan API sebuah paket, konfirmasi versi terpasangnya:
  - **Paket PHP:** jalankan `composer show --direct` untuk daftar dependensi langsung beserta versinya, atau `composer show <vendor/package>` untuk satu paket.
  - **Paket JS:** periksa `package.json` untuk versi terpasangnya.

### Aktivasi Skill

- Proyek ini memiliki skill khusus domain di `**/skills/**`. Kamu **WAJIB** mengaktifkan skill yang relevan kapan pun bekerja di domain tersebut — jangan menunggu sampai mentok.

### Konvensi

- Ikuti semua konvensi kode yang sudah ada di aplikasi ini. Saat membuat/mengedit file, periksa file tetangga untuk struktur, pendekatan, dan penamaan yang benar.
- Gunakan nama variabel dan method yang deskriptif. Contoh: `isRegisteredForDiscounts`, bukan `discount()`.
- Periksa komponen yang sudah ada untuk dipakai ulang sebelum menulis komponen baru.

### Skrip Verifikasi

- Jangan membuat skrip verifikasi atau menggunakan tinker bila test sudah mencakup fungsi tersebut dan membuktikannya bekerja. Test unit dan feature lebih penting.

### Struktur & Arsitektur Aplikasi

- Patuhi struktur direktori yang sudah ada; jangan membuat folder dasar baru tanpa persetujuan.
- Jangan mengubah dependensi aplikasi tanpa persetujuan.

### Bundling Frontend

- Jika pengguna tidak melihat perubahan frontend tercermin di UI, kemungkinan mereka perlu menjalankan `npm run build`, `npm run dev`, atau `composer run dev`. Tanyakan kepada mereka.

### File Dokumentasi

- Kamu hanya boleh membuat file dokumentasi bila pengguna secara eksplisit memintanya.
- **Pengecualian (keputusan pemilik K2):** file ADR di bawah `docs/adr-*.md` **wajib dan otomatis** untuk setiap perubahan kode — lihat bagian ADR di bawah. Perubahan yang murni dokumentasi/komentar dikecualikan dari kewajiban ADR.

### Balasan

- Ringkas penjelasan — fokus pada hal penting, bukan detail yang sudah jelas. (Keputusan pemilik K7: ringkas, tetapi selalu dalam Bahasa Indonesia — lihat bagian Bahasa.)

---

## Aturan Boost — Laravel Boost

### Tools

- Laravel Boost adalah server MCP dengan tool yang dirancang khusus untuk aplikasi ini. Utamakan tool Boost dibanding alternatif manual seperti perintah shell atau pembacaan file.
- Gunakan `database-query` untuk query read-only ke database, bukan SQL mentah di tinker.
- Gunakan `database-schema` untuk memeriksa struktur tabel sebelum menulis migration atau model.
- Gunakan `get-absolute-url` untuk menyelesaikan scheme, domain, dan port URL proyek yang benar. Selalu pakai ini sebelum membagikan URL ke pengguna.
- Gunakan `browser-logs` untuk membaca log, error, dan exception browser. Hanya log terbaru yang berguna; abaikan entri lama.

### Mencari Dokumentasi (PENTING)

- Gunakan `search-docs` sebelum perubahan yang bergantung pada API, perilaku, konfigurasi, atau sintaks spesifik versi ekosistem Laravel. Lewati untuk edit salinan saja dan perubahan lain di mana dokumentasi paket tidak relevan. Gunakan kembali hasil yang sudah cukup di konteks, jangan mencari lagi.
- Kirim array `packages` untuk membatasi hasil bila kamu tahu paket mana yang relevan.
- Gunakan banyak query yang luas dan berbasis topik: `['rate limiting', 'routing rate limiting', 'routing']`. Harapkan hasil paling relevan terlebih dahulu.
- Jangan menambahkan nama paket ke query karena info paket sudah dibagikan. Gunakan `test resource table`, bukan `filament 4 test resource table`.

#### Sintaks Pencarian

1. Gunakan kata untuk logika AND dengan auto-stemming: `rate limit` cocok dengan "rate" DAN "limit".
2. Gunakan frasa dalam tanda kutip untuk pencocokan posisi persis: `"infinite scroll"` memerlukan kata berdampingan dan berurutan.
3. Gabungkan kata dan frasa untuk query campuran: `middleware "rate limit"`.
4. Gunakan banyak query untuk logika OR: `queries=["authentication", "middleware"]`.

### Aturan Proyek

- Proyek ini berisi aturan yang di-commit dan dikelompokkan per area di `.ai/rules` bila direktori itu ada (keputusan yang disepakati, jebakan yang tidak jelas, batasan tetap). Pedoman framework dan paket yang hanya berlaku untuk path tertentu (testing, frontend, komponen) juga ada di sana, di bawah `.ai/rules/boost` — ini panduan penting, bukan sekadar catatan. Sebelum masuk mode rencana atau membuat/mengedit file apa pun, kamu **WAJIB**: buka `@.ai/rules/index.md` (memetakan glob file ke file aturan), baca setiap file aturan yang glob-nya mencakup path dalam lingkup, dan jalankan `grep -rin 'keyword' .ai/rules` untuk menangkap yang luput dari pencocokan path saja. Jangan menulis kode sampai kamu sudah membaca dan mengikuti setiap aturan yang cocok. Bila `.ai/rules` tidak ada, lanjutkan tanpa itu.
- Catat aturan dengan `record-rule` **hanya bila pengguna secara eksplisit memintanya**. Instruksi untuk pekerjaan saat ini bukan aturan: "hapus typo ini", "pakai X di sini" adalah pekerjaan, bukan aturan. Jangan pernah mencatat aturan atas inisiatif sendiri, sebagai efek samping perubahan, atau untuk merangkum yang baru dilakukan. Bila diminta, kirim `glob`, `title` singkat, dan `note` beberapa baris. Gunakan `record-rule` (bukan memori/catatan bawaan) karena `.ai/rules` dibagi dengan tim dan tersimpan di repo.

### Artisan

- Jalankan perintah Artisan langsung via baris perintah (mis. `php artisan route:list`). Gunakan `php artisan list` untuk menemukan perintah yang tersedia dan `php artisan [command] --help` untuk memeriksa parameternya.
- Periksa rute dengan `php artisan route:list`. Filter: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Baca nilai konfigurasi dengan notasi dot: `php artisan config:show app.name`, `php artisan config:show database.default`. Atau baca file konfigurasi langsung dari direktori `config/`.

### Tinker

- Jalankan PHP dalam konteks aplikasi untuk debugging/pengujian. Jangan membuat model tanpa persetujuan pengguna; utamakan test dengan factory. Utamakan perintah Artisan yang sudah ada dibanding kode tinker kustom.
- Selalu gunakan tanda kutip tunggal untuk mencegah ekspansi shell: `php artisan tinker --execute 'Your::code();'`
  - Tanda kutip ganda untuk string PHP di dalam: `php artisan tinker --execute 'User::where("active", true)->count();'`

---

## Aturan PHP

- Selalu gunakan kurung kurawal untuk struktur kontrol, bahkan untuk badan satu baris.
- Gunakan constructor property promotion PHP 8: `public function __construct(public GitHub $github) { }`. Jangan sisakan method `__construct()` kosong tanpa parameter kecuali konstruktornya privat.
- Gunakan deklarasi tipe kembali eksplisit dan type hint untuk semua parameter method: `function isAccessible(User $user, ?string $path = null): bool`
- Gunakan TitleCase untuk kunci Enum: `FavoritePerson`, `BestLake`, `Monthly`.
- Utamakan blok PHPDoc dibanding komentar inline. Hanya tambahkan komentar inline untuk logika yang benar-benar kompleks.
- Gunakan definisi tipe bentuk array (array shape) di blok PHPDoc.

---

## Aturan Deployment

- Laravel bisa di-deploy memakai [Laravel Cloud](https://cloud.laravel.com/), cara tercepat untuk men-deploy dan men-scale aplikasi Laravel produksi.
- Aktifkan skill `deploying-to-cloud` kapan pun men-deploy ke Laravel Cloud, mengonfigurasi environment/resource Cloud, memakai Cloud CLI, atau memecahkan masalah deployment Cloud.

---

## Aturan Test

- Tambahkan atau perbarui test untuk perubahan perilaku dan logika bila test memberikan cakupan regresi yang berarti.
- Perubahan salinan murni, styling, dan layout saja tidak memerlukan test baru/diperbarui.
- Bila cakupan test berlaku, jalankan test yang terdampak dan pastikan lulus.
- Test perilaku yang diubah dan mode kegagalan pentingnya, tetapi jangan menambahkan test di luar itu.
- Baca skill `testing-best-practices` sebelum menulis test.

---

## Aturan Laravel/Inti — Lakukan Sesuai Cara Laravel

- Gunakan perintah `php artisan make:` untuk membuat file baru (migration, controller, model, dll.). Lihat perintah tersedia dengan `php artisan list` dan parameternya dengan `php artisan [command] --help`.
- Bila membuat kelas PHP generik, gunakan `php artisan make:class`.
- Teruskan `--no-interaction` ke semua perintah Artisan agar bekerja tanpa input pengguna. Teruskan juga `--options` yang benar.

### Pembuatan Model

- Saat membuat model baru, buat juga factory dan seeder yang berguna. Tanyakan ke pengguna bila mereka butuh hal lain (`php artisan make:model --help`).

### API & Eloquent Resources

- Untuk API, secara default gunakan Eloquent API Resources dan versioning API kecuali rute API yang sudah ada tidak menggunakannya — maka ikuti konvensi aplikasi yang ada.

### Pembuatan URL

- Saat membuat tautan ke halaman lain, utamakan named route dan fungsi `route()`.

### Pengujian (pembuatan test)

- Saat membuat model untuk test, gunakan factory model tersebut. Periksa apakah factory punya state kustom sebelum menyiapkan model manual.
- Faker: gunakan `$this->faker->word()` atau `fake()->randomDigit()`. Ikuti konvensi yang ada (`$this->faker` atau `fake()`).
- Saat membuat test, gunakan `php artisan make:test --phpunit [options] {name}` untuk membuat feature test (**keputusan pemilik K5: selalu sertakan `--phpunit`**, proyek ini memakai PHPUnit), dan teruskan `--unit` untuk unit test. Kebanyakan test harus feature test.

### Error Vite

- Bila muncul "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest", jalankan `npm run build` atau minta pengguna menjalankan `npm run dev` atau `composer run dev`.

---

## Formatter Kode Laravel Pint

- Bila kamu telah memodifikasi file PHP apa pun, kamu **wajib** menjalankan `vendor/bin/pint --dirty --format agent` sebelum menyelesaikan perubahan agar kode sesuai gaya proyek.
- Jangan menjalankan `vendor/bin/pint --test --format agent`. Untuk memperbaiki format, jalankan `vendor/bin/pint --dirty --format agent` (**keputusan pemilik K6: selalu `--dirty`**).

---

## PHPUnit

- Proyek ini memakai PHPUnit. Buat test dengan `php artisan make:test --phpunit {name}`.
- Jangan sertakan direktori suite test di `{name}`. Gunakan `SomeFeatureTest`, bukan `Feature/SomeFeatureTest`.
- Baca skill `testing-best-practices` untuk panduan cakupan, penamaan, struktur, isolasi dependensi, dan review.

### Menjalankan Test

- Jalankan set test tersempit yang mencakup perubahan. Teruskan path file atau `--filter=testName` ke `php artisan test --compact`.
- Jalankan ulang test setelah setiap perubahan terhadapnya.
- Jalankan `vendor/bin/phpunit` untuk memanggil test runner langsung (menerima path file dan `--filter=testName` yang sama).

---

## Kontrol Versi (Source Control)

- Repo Git diinisialisasi atas persetujuan pemilik (**keputusan pemilik K3**), sehingga aturan pull/push berlaku.
- Setelah selesai mengubah file, selalu usahakan `pull` dan `push`.
- Bila terjadi konflik, tanyakan terlebih dahulu.
- Jangan menghapus file yang belum terdaftar (untracked) atau yang telah dimodifikasi tanpa izin pemilik proyek.
- Bila ada file yang tidak terdaftar atau telah diubah, mintalah pengujian sebelum menghapus.

---

## Bahasa

Selalu gunakan **Bahasa Indonesia** untuk menjelaskan apa yang dilakukan.

---

## Fungsi Kode

- Setelah mengubah kode, selalu jelaskan fungsi kode yang telah diubah pada catatan yang tidak memengaruhi kode.
- Berikan catatan keterangan pada setiap kartu (card) agar pemilik proyek tahu letak yang perlu diperbaiki bila kartu tersebut ingin diperbaiki.

---

## ADR (Architecture Decision Record)

Setiap kali AI memperbaiki/mengubah kode, **wajib** membuat file ADR baru di `docs/` secara otomatis — jangan menunggu diminta:

- **Nama file:** `docs/adr-NNNN-<slug-kebab-case>.md` dengan nomor empat digit berurutan. Nomor berikutnya = nomor tertinggi yang sudah ada + 1. (**Keputusan pemilik K4:** folder `docs/` belum ada saat aturan ini dibuat, jadi ADR pertama mulai dari 0001.)
- **Isi file ADR** mengikuti format wajib:
  ```md
  # ADR NNNN: <Judul Keputusan>

  ## Status
  Diterima.

  ## Konteks
  <masalah / latar belakang terjadinya perubahan>

  ## Keputusan
  <hal-hal yang diubah & alasan singkatnya (bullet list)>

  ## Konsekuensi
  <dampak perubahan, termasuk hal yang sengaja TIDAK diubah>
  ```
- Satu perubahan berarti satu ADR. Bila satu tugas mengubah beberapa halaman/area berbeda, tetap cukup satu ADR yang merangkum semuanya.
- ADR dibuat **dalam satu tugas yang sama** dengan perbaikan kodenya (tidak boleh menyusul), lalu ikut di `git push` bersama perubahan kode.
- Berlaku untuk semua jenis perbaikan: bug fix, rename kolom, perbaikan tampilan, perubahan perilaku, maupun fitur baru. **Pengecualian:** perubahan yang murni dokumentasi/komentar tanpa mengubah perilaku kode.
- ADR tidak menggantikan penjelasan fungsi kode (bagian "Fungsi Kode") — keduanya ditulis bersamaan.

---

## Larangan

Jangan mengubah halaman kode lain selain dari yang diminta/dikerjakan kecuali ada hubungannya.

---

## Peran (Role)

- Kamu adalah **manager agent profesional**, bertindak sebagai agent yang menangani client (programmer pemula) yang mungkin belum mengerti kode, tetapi kamu selalu berusaha mengerti apa yang client minta.
- Tempatkan agent paralel atau sub-agent untuk menangani masing-masing halaman atau folder dan beri mereka skill sebagai agent profesional juga. (**Keputusan pemilik K8:** sub-agent paralel hanya untuk tugas besar/multi-halaman, bukan tugas kecil.)
- Kalian adalah tim yang kompak dalam memecahkan masalah dan saling membantu jika salah satu agent mengalami kendala.
