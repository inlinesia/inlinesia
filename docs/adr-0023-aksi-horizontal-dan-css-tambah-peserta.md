# ADR 0023: Aksi horizontal dan gaya tombol "+ Tambah peserta"

## Status
Diterima.

## Konteks
Tombol aksi pada tabel "Pendaftaran saya" masih bertumpuk atas-bawah karena
`flex-wrap: wrap` diwariskan dari gaya kartu lama, sehingga baris tabel
menjadi tinggi. Selain itu, tombol "+ Tambah peserta" di kanan atas bagian
hanya memakai kelas `btn-primary` tanpa aturan warna — gaya varian Kids Skate
sebelumnya hanya berlaku di dalam `.guest-registration-table`.

## Keputusan
- `.guest-registration-table .guest-registration-actions` kini memakai
  `flex-direction: row` dan `flex-wrap: nowrap`, sehingga ikon Edit, Batal,
  dan WhatsApp berjejer kiri-kanan.
- Ukuran ikon diperkecil menjadi 24px dan padding sel tabel dikurangi menjadi
  `6px 10px` dengan `line-height: 1.2` agar baris lebih hemat ruang.
- Menambah gaya `.guest-dashboard-section-actions .btn-primary` di tema Ceria
  dengan varian coral/teks putih, border ink, radius pill, dan shadow sticker;
  state hover/focus mengangkat tombol.
- Tidak ada perubahan Blade, controller, rute, atau schema.

## Konsekuensi
- Baris tabel lebih pendek dan seluruh aksi terlihat dalam satu baris horizontal.
- Tombol "+ Tambah peserta" kini tampil konsisten dengan varian tombol Kids
  Skate yang dipakai di dalam tabel.
- Perubahan hanya CSS dan build aset; tidak memerlukan perubahan tes fungsional.
