# ADR 0024: Tema form "Data profil" dari Controls-2

## Status
Diterima.

## Konteks
Form "Data profil" di halaman profil masih memakai gaya akun lama: input
40px, border 1px, dan label polos. Pengelola ingin tema ini mengikuti
`kids_skate_controls_form_inputs_library` bagian **2. Form Inputs & Validation
States** (`controls-2`) yang ada di
`resources/css/stitch_kids_roller_skate_ui_design_system/`.

## Keputusan
- Struktur form di `auth/profile.blade.php` memakai `.form-grid` (auto-fit,
  min 240px, gap 16px) dan tiap field `.form-group` berisi `.form-label`
  plus `.input-field` / `.textarea-field`.
- Validation state diterapkan lewat `@error`: input yang gagal mendapat
  `.is-error`, disertai `.helper-text.is-error`; field read-only memakai
  helper text netral. CSS untuk `.is-valid` dan `.helper-text.is-valid`
  juga disiapkan sesuai referensi.
- `ceria-theme.css` memakai token yang sudah ada: font display untuk label,
  input min-height 48px, border 2.5px `--border-soft`, radius
  `--radius-md`, background `--bg-canvas`, focus `--color-sky-blue` +
  `--shadow-focus`, state valid `--status-success`, state error
  `--status-error`.
- `account-portal.css` menyesuaikan grid profil ke pola auto-fit dan
  menyediakan aturan dasar `.form-group`, `.form-label`, `.helper-text`.
- Tes `AccountPortalTest` memastikan kelas Controls-2 hadir dan state error
  muncul saat validasi gagal.

## Konsekuensi
- Form profil lebih lega dan konsisten dengan pustaka Controls-2; perilaku
  simpan, validasi, dan field read-only tidak berubah.
- Selector input diberi `.public-site .profile-edit-card .account-form`
  supaya mengalahkan aturan lama
  `.account-form input:not([type=checkbox]):not([type=radio])` serta aturan
  `.public-site :is(.account-form, .event-form) ...` di `glass-theme.css` yang
  tidak di-scope tema dan sebelumnya membuat teks input pucat.
- Perubahan hanya Blade, CSS, tes, dan build aset — tidak ada perubahan
  controller, rute, atau skema.
