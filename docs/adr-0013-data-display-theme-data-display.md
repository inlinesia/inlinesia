# ADR 0013: Data Display & Card Theme Diluaskan ke Setiap Halaman

## Status
Diterima.

## Konteks
Tema Ceria sudah diterapkan di 5 aspek sistem desain: foundation, control,
navigations, data displays, feedbacks. Namun beberapa komponen data
display/card belum sepenuhnya memakai gaya baru — e.g. `community-values`,
kartu `guest-event-card`, kartu lomba, dan lainnya masih memakai gaya asal
dari tema lama. Sementara bagian komponen lain (tab, alert, form, foundation)
masih memakai gaya lamanya. Pemilik proyek meminta:

1. Tema data display & card library diterapkan ke semua halaman yang bagiannya
   adalah data display & card library (kartu, tabel, stats, badge).
2. Untuk komponen lain (tab, alert, form, foundation) terapikan contoh tema
   yang sesuai dengan class masing-masing di aplikasi.

## Keputusan
- Menambah bagian «Data displays & cards — ekstensi untuk class asli
  aplikasi» pada `resources/css/ceria-theme.css`: stylingul komponen
  `guest-event-card`, `guest-registration-card`, `guest-dashboard-section`,
  angka kartu lomba (`listing-card-number`), `sponsor-card`,
  `sponsor-option-number`, `community-values`, `value-number`, `value-mark`,
  `payment-account`, `stat-spark`.
- Menambah variabel `--ceria-panel` (alias `--bg-surface`) dan
  `--ceria-mint` (alias `--color-sky`) pada tokens Foundations.
- Diperiksa markup aplikasi (`data-management`, `account-portal`, `landing`),
  map class app → gaya library, masukkan ke `ceria-theme.css`:
  - Badge & tag (`profile-role`, status event, medali, `listing-status`).
  - Tab & badge (sort label, pagination).
  - Alert & banner (flash publik/admin, error account, demo notice).
  - Form & input (field lebar bertambah, radio & checkbox).
  - Foundation (`fontFredoka` di heading, radius membulat).
- Semua perubahan tercatat dalam ADR ini.

## Konsekuensi
- Semua komponen data display/card di publik dan panel admin makin konsisten
  memakai tema Ceria.
- Komponen lain dipelihara sesuai contoh library, satu tema tunggal.
- Tidak ada perubahan rute, logika bisnis, atau skema database.
