# ADR 0010: Gradasi Background Kuat dan Mengikuti Pointer

## Status
Diterima.

## Konteks
Gradasi latar tema Ceria masih sangat tipis (opacity 0.12–0.22) dan statis,
sehingga kesan "cerah dan penuh warna" belum kuat. Pemilik proyek meminta
warnanya diperkuat dan sorotan gradasi bergerak mengikuti pointer.

## Keputusan
- Menaikkan opacity gradasi pada **body (seluruh halaman)** menjadi 0.4–0.9
  pada `resources/css/ceria-theme.css`, serta menambah lapisan `linear-gradient`
  hangat sebagai dasar.
- Menambahkan variabel `--pointer-x` / `--pointer-y` (default 50%/50%) yang
  dipakai `radial-gradient(circle 460px at var(--pointer-x) var(--pointer-y), ...)`
  **khusus pada body**. Latar hero/judul sengaja dikembalikan ke gradasi
  `linear-gradient(120deg, ...)` seperti sebelumnya dan tidak mengikuti pointer.
- Membuat `resources/js/pointer-gradient.js`: mencatat posisi pointer, lalu
  mengubah variabel CSS tersebut lewat `requestAnimationFrame` dengan faktor
  easing 0.14 agar geraknya halus, bukan melompat. Skrip hanya aktif saat tema
  `ceria` dan menaati `prefers-reduced-motion`.
- Mendaftarkan skrip ke `vite.config.js` dan kedua layout.

## Konsekuensi
- Latar seluruh halaman tampil jauh lebih cerah; sorotan kuning–coral
  mengikuti gerak mouse dengan halus di desktop. Tampilan hero/judul tidak
  berubah dari implementasi sebelumnya.
- Tidak ada perubahan rute, logika bisnis, atau database.
- Di perangkat yang meminta pengurangan gerak, efek tidak berjalan (gradasi
  tetap kuat tapi statis).
