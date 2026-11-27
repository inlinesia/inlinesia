# ADR 0061: Nama Damai digital creative menuju portofolio

## Status
Diterima.

## Konteks
Pada kartu "Kontak lisensi", nama "Damai digital creative" masih menjadi tautan WhatsApp. Pemilik ingin nama tersebut menjadi trigger ke halaman portofolio pribadinya di `https://damai.kilatschool.my.id/`.

## Keputusan
- Tautan pada teks **Damai digital creative** diubah ke `https://damai.kilatschool.my.id/` dengan `target="_blank"` dan `rel="noopener noreferrer"`.
- Nomor WhatsApp 085800006248 tetap dicantumkan sebagai teks kontak alternatif, bukan lagi sebagai href pada nama tersebut.
- Teks kartu disesuaikan agar menyebut portofolio sebagai sumber informasi lisensi, harga, dan kerja sama.
- Tes lisensi diperbarui untuk memeriksa URL portofolio dan nomor WhatsApp.

## Konsekuensi
- Pengunjung yang mengklik nama pembuat langsung menuju portofolio, bukan WhatsApp.
- Channel WhatsApp tetap tersedia lewat nomor yang tertulis.
- Tidak ada perubahan rute, data, atau gaya halaman lisensi.
