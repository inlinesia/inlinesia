# ADR 0063: Tarif sponsor diterapkan di halaman lisensi

## Status
Diterima.

## Konteks
Tarif sponsor sudah dipakai pada form pengajuan, tetapi kartu "Sponsor website & event" di halaman lisensi masih bertuliskan "Konsep — harga belum ditetapkan". Pemilik meminta tarif sponsor ikut diterapkan seperti tarif kelola event.

## Keputusan
- Kartu "Sponsor website & event" menampilkan rentang tarif: website Rp 250.000–1.000.000/bulan dan event Rp 150.000–300.000/event.
- Rincian paket dicantumkan: banner website Rp 250.000/bulan, sponsor utama website Rp 1.000.000/bulan, logo event Rp 150.000/event, sponsor utama event Rp 300.000/event.
- Bagian "Iklan & sponsor" juga memakai rentang tarif yang sama, bukan lagi "Mulai Rp …".
- Tes lisensi diperbarui untuk memastikan nominal sponsor tampil dan teks "harga belum ditetapkan" hilang.

## Konsekuensi
- Harga di form pengajuan, kalkulasi `Sponsor::PRICES`, dan halaman lisensi sekarang konsisten.
- Pembayaran tetap manual lewat WhatsApp; tidak ada payment gateway baru.
- Tidak ada perubahan logika validasi atau penempatan iklan.
