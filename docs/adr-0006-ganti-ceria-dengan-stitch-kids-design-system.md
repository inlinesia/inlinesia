# ADR 0006: Ceria Diganti dengan Stitch Kids Roller Skate Design System

## Status
Diterima.

## Konteks
Pemilik proyek menambahkan berkas
`resources/css/stitch_kids_roller_skate_ui_design_system.zip` yang berisi contoh
sistem desain ramah anak dengan 5 aspek: foundations, controls, navigations,
data displays, dan feedbacks (plus pustaka komponen tabs, alerts, form inputs,
cards, dan tokens). Implementasi Ceria sebelumnya memakai palet pastel buatan
sendiri, sehingga perlu diganti agar sesuai contoh tersebut.

Berkas ZIP tetap dipertahankan sebagai referensi (tidak dihapus, tidak
di-commit karena pola `*.zip` diabaikan Git).

## Keputusan
- Menimpa `resources/css/ceria-theme.css` dengan adaptasi sistem desain Stitch:
  - **Foundations**: token `--color-lemon`, `--color-sky`, `--color-coral`,
    `--color-grape`, `--color-berry`, `--border-ink`, `--shadow-sticker`,
    radius pil/roda, tipografi Fredoka + Nunito.
  - **Controls**: tombol pil dengan border 2.5px + bayangan stiker 3D, input
    48px dengan focus ring, pilihan berbentuk pill.
  - **Navigations**: header bergaya stiker, tautan nav pill aktif ungu, tab,
    dan paginasi melingkar.
  - **Data Displays**: kartu tiga warna pastel (mint/peach/lavender) dengan
    sudut asimetris, badge, statistik, dan tabel.
  - **Feedbacks**: alert/flash/validasi dengan warna semantik, modal, dan
    dukungan `prefers-reduced-motion`.
- Menambahkan font **Fredoka** dan **Nunito** ke impor Google Fonts di
  `resources/css/app.css`.
- Memperbarui pratinjau kartu "Ceria" di halaman setting tema dan deskripsinya.
- **Tetap dipertahankan**: kunci tema `ceria` (kompatibel dengan preferensi
  tersimpan), pembatas stiker hanya di halaman publik
  (`body.ceria-sticker-page`), stiker statis tanpa animasi berulang, dan
  override terhadap aturan global `glass-theme.css`.

## Konsekuensi
- Tampilan tema Ceria berubah total mengikuti sistem desain Stitch; warna kunci
  adalah lemon/coral/grape dengan aksen mint, peach, dan lavender.
- Tidak ada perubahan rute, logika bisnis, atau skema database.
- Perlu `npm run build` dan unggah ulang `public/build` ke server produksi.
