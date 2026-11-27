# ADR 0059: Hapus bagian "Alur peserta dan pengelola" di beranda

## Status
Diterima.

## Konteks
Bagian "ALUR PESERTA DAN PENGELOLA — Dari agenda hingga pendaftaran" di beranda mengulang informasi yang sudah diwakili oleh kartu fitur "Komponen event dalam satu alur" di atasnya. Pemilik meminta bagian tersebut dihapus.

## Keputusan
- Section `.public-community#cara-kerja` beserta tiga kartu langkah (Jelajahi agenda, Daftarkan atlet, Kelola kejuaraan) dihapus dari `publik/beranda.blade.php`.
- Tautan navbar "Alur penggunaan" yang sebelumnya mengarah ke `#cara-kerja` dialihkan ke `#layanan` agar tidak menjadi anchor mati.
- Tes homepage diperbarui untuk memastikan judul dan deskripsi bagian lama tidak lagi muncul.
- CSS tema untuk kelas-kelas lama sengaja tidak dihapus karena dipakai bersama oleh komponen lain; tidak ada dampak visual saat elemen tidak dirender.

## Konsekuensi
- Beranda lebih ringkas dan tidak mengulang penjelasan alur yang sama.
- Card kemitraan, agenda kejuaraan, dan fitur platform tidak berubah.
- Tidak ada perubahan rute, data, atau perilaku.
