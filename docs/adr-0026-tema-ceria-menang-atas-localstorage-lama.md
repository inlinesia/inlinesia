# ADR 0026: Tema dari pengaturan menang atas localStorage lama

## Status
Diterima.

## Konteks
Pengguna menerapkan Ceria lewat halaman pengaturan tema, tetapi yang tampil
masih Topic Listing. Tema disimpan di `localStorage`; bila nilai lama belum
tertulis (misalnya JS tersimpan cache atau radio belum sempat tersimpan),
script inline di layout memakai nilai lama itu dan Ceria kalah.

## Keputusan
- Script inline di `layouts/public.blade.php` dan `layouts/admin.blade.php`
  kini membaca flash `session('theme')` yang dikirim controller pengaturan tema.
  Bila nilainya valid, script itu langsung memakainya **dan menulisnya ke
  `localStorage`** sebelum aset JS lain dimuat.
- Perbaikan sebelumnya di `theme-switcher.js` tetap dipertahankan: memilih
  radio tema langsung menulis `localStorage` tanpa perlu menekan "Simpan tema".
- Tes `ThemeSettingTest` memastikan flash tema muncul di HTML dan ditulis ke
  `localStorage` pada kunjungan setelah penyimpanan.

## Konsekuensi
- Menerapkan Ceria (atau tema lain) lewat pengaturan selalu menang atas nilai
  lama di browser, termasuk setelah refresh.
- Bila tidak ada flash tema, perilaku tetap seperti sebelumnya: `localStorage`
  dipakai, dengan Ceria sebagai default.
- Hanya layout, tes, dan view cache yang berubah; tidak ada perubahan CSS,
  controller, rute, atau skema.
