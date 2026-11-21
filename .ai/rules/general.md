---
paths:
  - '**'
---

# General

## Wajib commit dan push setelah selesai mengerjakan
Setiap kali selesai mengerjakan tugas, wajib git pull, git add, git commit, dan git push ke server sebelum menyatakan selesai. Ini owner decision K9 (pemutus konflik): menggantikan aturan lama 'usahakan pull/push' dan menang atas aturan lain yang bertentangan. Urutan: pull -> verifikasi (Pint + tes terdampak) -> add -> commit -> push. Pengecualian: tidak ada perubahan, atau push gagal karena autentikasi/jaringan (lapor ke pemilik). Konflik merge Git tetap ditanyakan terlebih dahulu.
