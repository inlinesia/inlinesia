# ADR 0028: Deploy otomatis ke server saat git push

## Status
Diterima.

## Konteks
Sebelumnya each deploy ke produksi dilakukan manual: `git pull` di server,
`view:cache`, `config:cache`, dan salin `public/build` ke docroot. Ada kalanya
push ke GitHub berhasil tetapi server belum ditarik, sehingga perubahan tidak
terlihat. Pengelola meminta push ke git langsung mengikuti deploy ke server.

## Keputusan
- Remote `origin` kini memiliki dua push URL: GitHub (tetap sebagai fetch dan
  backup source) dan repo bare `kilat:/home/kilatsch/deploy-inlinesia.git`.
- Repo bare di server punya hook `post-receive`
  (`deploy/post-receive`) yang: menarik `master` dari GitHub ke
  `/home/kilatsch/inlinesia-app` (dengan retry), menjalankan
  `view:clear`/`view:cache` dan `config:clear`/`config:cache`, lalu menyalin
  `public/build` ke `inlinesia.kilatschool.my.id/build` bila ada.
- Script `scripts/install-server-auto-deploy.sh` disediakan untuk memasang
  konfigurasi ini di mesin/server lain.
- build CSS/JS masih dilakukan lokal sebelum push bila ada perubahan aset;
  hook hanya menyalin build yang sudah ada.

## Konsekuensi
- `git push origin master` sekarang otomatis: GitHub terbaru, server terbaru,
  cache segar — tanpa langkah manual.
- Bila hook gagal (misalnya origin belum menerima commit saat hook jalan),
  retry 5x; jika tetap gagal, push ke server gagal danGitHub tetap tersimpan.
- Tidak ada perubahan aplikasi; hanya konfigurasi git, hook server, dan skrip
  instalasi.
