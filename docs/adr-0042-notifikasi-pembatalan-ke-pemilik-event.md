# ADR 0042: Notifikasi pembatalan dengan keputusan di dasbor pemilik event

## Status
Diterima.

## Konteks
Notifikasi `CancellationRequestedNotification` sudah dikirim ke pemilik event dan admin, tetapi tidak pernah ditampilkan di dasbor. Pemilik tidak tahu atlet mana yang mengajukan pembatalan dan tidak ada jalan pintas untuk menyetujui, menolak, atau menghubungi pengaju. Halaman kelola peserta sudah punya panel keputusan, tetapi pemilik harus mencari sendiri ke event yang bersangkutan.

## Keputusan
- Dasbor pemilik/admin kini menampilkan panel "Permintaan pembatalan peserta" berisi pendaftaran dengan `cancellation_status = pending` pada event yang dikelolanya (admin melihat semua).
- Tiap kartu menampilkan nama atlet, event, alasan, serta tiga opsi: **Setujui**, **Tolak**, dan **Hubungi official**.
- Tombol **Setujui/Tolak** memakai endpoint `events.registrations.cancellations.review` yang sudah ada, sehingga setuju hanya menandai `cancellation_status = approved` (data pendaftaran tetap tersimpan di server, atlet hilang dari kepesertaan event) dan tolak mengembalikan ke status `rejected`.
- Rute baru `events.registrations.cancellations.contact` membuka `https://wa.me/<nomor>?text=...` ke nomor WhatsApp akun yang mengajukan pembatalan (fallback ke biodata atlet) dengan pesan yang menyebut nama atlet dan event.
- Setelah keputusan diambil, notifikasi pembatalan terkait ditandai terbaca untuk user yang memutuskan.
- Peta proyek otomatis di-regenerate agar inventaris rute tetap valid.

## Konsekuensi
- Pemilik event dapat langsung bertindak dari dasbor tanpa membuka halaman kelola peserta.
- Data pendaftaran tidak pernah dihapus fisik; pembatalan tetap bersifat status (audit dan riwayat pembayaran terjaga).
- Tombol "Hubungi official" hanya aktif untuk permintaan berstatus `pending` dan nomor harus valid (8–15 digit setelah normalisasi `62`).
- Refund dari tombol cepat dasbor tetap `0` (sesuai panel keputusan yang sudah ada); nominal refund dapat diubah pemilik dari halaman kelola peserta.
- Tidak ada perubahan pada alur pengajuan pembatalan peserta maupun kebijakan refund yang sudah ada.
