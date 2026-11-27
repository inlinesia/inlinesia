# ADR 0047: Tema Ceria pada notifikasi pembatalan peserta

## Status
Diterima.

## Konteks
Panel "Permintaan pembatalan peserta" di dasbor pemilik/admin masih memakai gaya polos bawaan, termasuk tombol Setujui/Tolak/Hubungi official yang tampak seperti tautan biasa. Pemilik meminta tampilan notifikasi dan seluruh tombolnya mengikuti tema Ceria.

## Keputusan
- Judul panel memakai font display `--font-display` dan ukuran `--text-sm`.
- Pemisah tiap permintaan memakai garis putus-putus `--border-soft`; cap waktu menjadi pill kecil dengan latar `--bg-soft-sky`.
- Ketiga tombol aksi memakai pola tombol Kids Skate yang sudah dipakai di dasbor: border ink 2.5px, radius pill, shadow stiker, font display, dan tinggi mengikuti teks.
- Warna tombol: **Setujui** `--grad-sunny`, **Tolak** `--grad-berry-pop`, **Hubungi official** `--grad-sky-roll`.
- Hover/focus menaikkan tombol 2px dan memperbesar shadow, konsisten dengan tombol aksi event.

## Konsekuensi
- Hanya gaya yang diubah; alur review, nama rute, dan hidden field `refund_amount` tetap sama.
- Tema selain Ceria tidak terpengaruh karena seluruh aturan baru berscoped `[data-theme='ceria']`.
- Tombol tetap berukuran sesuai teks dan tidak memaksa lebar penuh seperti `.btn-primary` bawaan.
