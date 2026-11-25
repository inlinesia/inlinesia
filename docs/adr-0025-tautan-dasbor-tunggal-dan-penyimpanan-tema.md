# ADR 0025: Tautan dasbor tunggal dan tema tersimpan saat radio dipilih

## Status
Diterima.

## Konteks
Tiga tautan — "Pendaftaran saya" dan "Buka dasbor" di navbar serta
"Buka dasbor peserta" di halaman profil — semuanya mengarah ke
`guest.dashboard` (satu dengan anchor `#pendaftaran-saya`), sehingga
membingungkan. Di sisi lain, pemilih tema hanya menulis `localStorage` ketika
form "Simpan tema" disubmit; memilih radio Ceria tanpa menekan tombol itu
membuat tema lama muncul kembali setelah pindah halaman.

## Keputusan
- Navbar cukup memuat satu tombol **"Buka dasbor"**; tautan "Pendaftaran saya"
  dihapus.
- Tautan "Buka dasbor peserta" di halaman profil dihapus. Tautan "Buka panel
  admin" dan "Kelola event" untuk admin tetap ada.
- `theme-switcher.js` kini memanggil `writeStored(input.value)` pada event
  `change` radio tema, sebelum `apply()`, sehingga memilih tema langsung
  tersimpan di `localStorage` tanpa harus menekan "Simpan tema".
- Tes `AccountPortalTest` dan `GuestDashboardTest` disesuaikan untuk
  memastikan tautan ganda tidak muncul dan tombol "Buka dasbor" tetap ada.

## Konsekuensi
- Navigasi lebih sederhana: semua pengguna masuk ke dasbor lewat satu tombol.
- Memilih Ceria (atau tema lain) langsung bertahan lintas halaman; tombol
  "Simpan tema" tetap berfungsi seperti sebelumnya.
- Tidak ada perubahan controller, rute, atau skema; hanya Blade, JS, tes,
  dan build aset.
