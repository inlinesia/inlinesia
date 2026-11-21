# ADR 0003: Wajib Commit dan Push Setelah Selesai Bekerja

## Status
Diterima.

## Konteks
Aturan lama di `AGENTS.md` hanya menyatakan "selalu **usahakan** `pull` dan
`push`" setelah selesai mengubah file. Kata "usahakan" membuat perilaku tidak
pasti: perubahan bisa tertinggal di lokal tanpa pernah sampai ke server.
Pemilik proyek meminta agar commit + push menjadi kewajiban dan menjadi
keputusan terbaru yang menang bila bertentangan dengan aturan lain.

## Keputusan
- Menetapkan **owner decision K9** sebagai keputusan terbaru dan pemutus konflik:
  setiap selesai mengerjakan tugas, **wajib** `git pull` → `git add` →
  `git commit` → `git push` ke server.
- Mengubah `AGENTS.md` bagian "Kontrol Versi": mengganti "usahakan" dengan
  kewajiban, menambahkan urutan kerja, dan mencatat K9 sebagai pengganti aturan
  lama yang bertentangan.
- Mencatat K9 di daftar "Keputusan pemilik proyek" pada
  `docs/peta-github-mcp.md`.
- Merekam aturan ini sebagai aturan proyek durable via Laravel Boost
  `record-rule` agar terbaca otomatis oleh agent pada tugas berikutnya.
- Pengecualian: tidak ada perubahan sama sekali, atau push gagal karena
  autentikasi/jaringan (lapor ke pemilik). Konflik **merge Git** tetap
  ditanyakan terlebih dahulu — K9 tidak mengubah ini.

## Konsekuensi
- Setiap tugas ditutup dengan perubahan yang sudah masuk ke remote; tidak ada
  lagi kerjaan yang "setengah jadi" di lokal.
- K9 menggantikan narasi "usahakan pull/push" di `AGENTS.md`; bila ada aturan
  lain yang bertentangan, K9 yang menang.
- Tetap ada pengaman: konflik merge tidak diselesaikan otomatis, dan file
  untracked/modifikasi pengguna tidak dihapus tanpa izin.
- Tidak ada perubahan kode aplikasi, rute, atau skema database.
