# ADR 0010: Gradasi Background Kuat dan Mengikuti Pointer

## Status
Diterima.

## Konteks
Gradasi latar tema Ceria masih sangat tipis (opacity 0.12–0.22) dan statis,
sehingga kesan "cerah dan penuh warna" belum kuat. Pemilik proyek meminta
warnanya diperkuat dan sorotan gradasi bergerak mengikuti pointer.

## Keputusan
- Menaikkan opacity gradasi pada body (0.4–0.9) dan hero (0.62–0.9) pada
  `resources/css/ceria-theme.css`, serta menambah lapisan `linear-gradient`
  hangat sebagai dasar.
- Menambahkan variabel `--pointer-x` / `--pointer-y` (default 50%/50%) yang
  dipakai `radial-gradient(circle 460px at var(--pointer-x) var(--pointer-y), ...)`
  pada body dan hero.
- Membuat `resources/js/pointer-gradient.js`: mencatat posisi pointer, lalu
  mengubah variabel CSS tersebut lewat `requestAnimationFrame` dengan faktor
  easing 0.14 agar geraknya halus, bukan melompat. Skrip hanya aktif saat tema
  `ceria` dan menaati `prefers-reduced-motion`.
- Mendaftarkan skrip ke `vite.config.js` dan kedua layout.

## Konsekuensi
- Latar dan hero tampil jauh lebih cerah; sorotan kuning–coral mengikuti gerak
  mouse dengan halus di desktop.
- Tidak ada perubahan rute, logika bisnis, atau database.
- Di perangkat yang meminta pengurangan gerak, efek tidak berjalan (gradasi
  tetap kuat tapi statis).
