# ADR 0049: Navbar publik hanya di beranda, rata kiri/kanan, dan melekat saat scroll

## Status
Diterima.

## Konteks
Navbar publik menampilkan enam tautan (Beranda utama, Daftar kejuaraan, Fitur platform, Alur penggunaan, Kontak, Tentang kami) di semua halaman dan posisinya cenderung ke tengah/kanan. Pemilik meminta tautan publik lengkap hanya muncul di halaman beranda, di halaman lain cukup "Beranda utama"; grup aksi akun (Masuk, Profil, Buka dasbor, Daftarkan event, Keluar, Buat akun) tetap rata kanan. Selain itu navbar harus tetap berada di atas saat halaman digeser ke bawah.

## Keputusan
- `resources/views/components/navbar.blade.php` merender tautan publik tambahan hanya bila `request()->routeIs('home')`; pada halaman lain nav diberi kelas `is-minimal` dan hanya berisi "Beranda utama".
- `resources/css/landing.css`: `.public-nav` tidak lagi memakai `margin-left: auto`, sehingga rata kiri setelah logo; `.public-header-actions` memakai `margin-left: auto` sehingga rata kanan.
- Tampilan mobile tetap memindahkan nav ke baris kedua; aturan auth-page diperbarui agar nav minimal tetap terlihat pada layar kecil.
- `.public-header` memakai `position: sticky; top: 0; z-index: 40` di aturan dasar; override Ceria yang sebelumnya `position: relative` diubah menjadi sticky agar navbar tidak ikut bergulir.
- Tes profil diperbarui: tautan publik lengkap tidak lagi diharapkan muncul di halaman non-beranda.

## Konsekuensi
- Halaman beranda tetap menampilkan navigasi lengkap; halaman lain lebih ringkas dan fokus ke konten.
- Navbar tetap terlihat saat scroll pada semua tema, termasuk Ceria.
- Tidak ada perubahan rute, data, atau perilaku tombol aksi.
