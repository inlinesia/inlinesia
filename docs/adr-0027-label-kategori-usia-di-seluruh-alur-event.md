# ADR 0027: Label Kategori diganti KU (Kelompok Usia)

## Status
Diterima.

## Konteks
Istilah "Kategori" di halaman pendaftaran atlet dan halaman pengelolaan event
dianggap kurang jelas karena bercampur dengan "kategori" dalam arti kelompok
usia maupun kategori biaya. Pengelola meminta seluruh teks UI yang terlihat
pengguna memakai istilah **KU (Kelompok Usia)**, termasuk halaman terkait:
atur kejuaraan, atur perlombaan, hari lomba, urutan lomba, daftar peserta,
cetak/ekspor peserta, keuangan, beranda, dan dasbor.

## Keputusan
- Mengganti teks UI "Kategori/kategori" menjadi "KU (Kelompok Usia)" atau
  "KU" pada 15 file Blade di `resources/views` (publik, verified, admin).
- Teks yang diubah mencakup judul halaman, tombol, label form, legend,
  kalimat penjelas, header/filter tabel, aria-label, dan placeholder data.
- **Tidak mengubah** nama field, nama parameter (`kategori`), rute, kelas CSS,
  nilai database, atau logika controller — perubahan murni kosakata tampilan.
- Tes yang mengekspektasi teks lama disesuaikan di seluruh file
  `tests/Feature` yang terdampak.
- Satu tes (`test_dasbor_peserta_tidak_memuat_tautan_daftar_peserta_lagi`)
  disesuaikan dengan keputusan sebelumnya yang menghapus tautan tersebut dari
  dasbor.

## Konsekuensi
- Istilah konsisten di seluruh alur: pendaftar melihat KU, pengelola mengatur
  KU, dan hasil perlombaan dikelompokkan per KU.
- Karena ini perubahan teks, seluruh tes tampilan (331 test) dijalankan ulang
  dan lulus; tidak ada perubahan pada data simulasi maupun skema.
- Build aset tidak berubah karena tidak ada CSS/JS yang disentuh — hanya view
  cache yang perlu diperbarui di server.
