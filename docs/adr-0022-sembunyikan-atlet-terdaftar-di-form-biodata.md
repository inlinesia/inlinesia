# ADR 0022: Sembunyikan atlet terdaftar pada form biodata

## Status
Diterima.

## Konteks
Halaman formulir pendaftaran (`verified.events.pendaftaran.daftar`) menampilkan
blok "Atlet yang sudah didaftarkan" beserta daftar nama, kategori, status,
dan tautan ke dasbor/daftar peserta. Untuk pengguna yang mendaftarkan banyak
atlet, blok ini mengganggu dan informasi yang sama sudah tersedia di dasbor
"Pendaftaran saya".

## Keputusan
- Menghapus blok daftar atlet dan tautan terkait dari formulir biodata atlet.
- Menghapus query `$registrations` dari `EventRegistrationController::create()`
  karena tidak lagi dipakai tampilan.
- Menambah tes regresi di `GuestDashboardTest` untuk memastikan nama atlet
  yang sudah terdaftar dan label lama tidak muncul pada halaman formulir,
  sementara judul "Biodata atlet" tetap ada.

## Konsekuensi
- Formulir lebih bersih dan fokus pada input biodata baru.
- Daftar peserta tetap bisa diakses dari dasbor peserta, bukan dari formulir.
- Tidak ada perubahan pada logika penyimpanan pendaftaran, rute, atau skema.
