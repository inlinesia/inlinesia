# ADR 0021: Perapian aksi dan data tabel "Pendaftaran saya"

## Status
Diterima.

## Konteks
Tabel pendaftaran sudah memakai header dua baris dan varian tombol Kids Skate,
tetapi masih memiliki tombol "Tambah atlet" di setiap baris, tautan "Urutan
lomba", dan "Lihat daftar peserta" yang dianggap tidak perlu. Beberapa sel
juga membungkus teks ke baris berikutnya, sehingga lebar kolom tidak bisa
mengikuti isi terpanjang.

## Keputusan
- Tombol "Tambah atlet" dipindah ke kanan atas bagian "Pendaftaran saya",
  disandingkan dengan total peserta, dan hanya ada satu: **"+ Tambah peserta"**.
  Tombol ini memakai event pertama dari daftar pendaftaran (atau event
  tersedia pertama) dan hanya muncul bila pendaftaran event masih dibuka.
- Tautan "Urutan lomba" dan "Lihat daftar peserta" dihapus dari tabel.
- Aksi per baris menjadi ringkas berbentuk ikon: Edit (coral), Ajukan/Batalkan
  pembatalan (berry), dan — hanya untuk yang belum bayar — Kirim bukti via
  WhatsApp (grape). Unggah berkas bukti dihapus supaya ringan di server.
- Nama lengkap hanya menampilkan nama peserta, tanpa nama event.
- Semua header dan sel memakai `white-space: nowrap` dan rata kiri; lebar
  kolom mengikuti isi terpanjang. Catatan tambahan (gelombang harga, sisa
  tagihan, rekening, status permintaan) tidak dirender lagi.
- Status bayar hanya dua nilai: **Belum bayar** dan **Lunas**. Badge status
  pendaftaran (Diterima/Menunggu panitia) dihapus karena sudah terwakili.
- Tes `GuestDashboardTest` diperbarui untuk memastikan tombol header, label
  status baru, dan tidak munculnya tombol/teks yang dihapus.

## Konsekuensi
- Aksi lebih padat; pengguna tetap bisa mengirim bukti pembayaran, tetapi
  hanya melalui WhatsApp.
- Tabel lebih lebar secara alami dan dapat digeser horizontal lewat wrapper
  yang sudah ada.
- Tidak ada perubahan controller, rute, atau skema; hanya Blade, CSS, tes,
  dan build aset.
