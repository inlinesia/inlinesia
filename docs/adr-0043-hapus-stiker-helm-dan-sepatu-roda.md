# ADR 0043: Hapus stiker helm dan sepatu roda dari kartu

## Status
Diterima.

## Konteks
Tema Ceria menambahkan emoji helm (`⛑️`) dan sepatu roda (`🛼`) sebagai pseudo-elemen dekoratif pada sejumlah kartu publik, header, hero, footer, dan kartu pratinjau pengaturan tema. Pemilik menilai stiker tersebut mengganggu dan memintanya dihapus.

## Keputusan
- Menghapus semua aturan `content` bertema helm dan sepatu roda dari `resources/css/ceria-theme.css` (header, brand, hero, kartu lomba, kejuaraan, layanan, akun, daftar kosong, dan footer).
- Menghapus stiker sepatu roda pada kartu pratinjau di `resources/css/theme-settings.css`.
- Menyesuaikan deskripsi tema di `resources/views/admin/settings/theme.blade.php` agar tidak lagi menyebut stiker sepatu roda.
- Dekorasi lain (sparkle `✨` dan medali `🏅`) sengaja dipertahankan karena tidak termasuk dalam permintaan.

## Konsekuensi
- Tidak ada emoji helm/sepatu roda lagi di halaman publik maupun kartu pratinjau tema.
- Kelas `ceria-sticker-page` tetap dipakai untuk stiker sparkle/medali, sehingga pengujian terkait tidak berubah.
- Perubahan bersifat presentasional; tidak ada perubahan data, rute, atau perilaku formulir.
- File referensi desain di `resources/css/Ceria/*.html` tidak diubah karena bukan aset yang dirender aplikasi.
