# ADR 0046: Footer copyright Damai digital creative dan halaman lisensi

## Status
Diterima.

## Konteks
Pemilik ingin footer Inlinesia memakai copyright yang sama dengan proyek `damai/coding/kilat`, yaitu `© 2026 - Damai digital creative`, dan menyediakan halaman lisensi dengan ketentuan yang mengikuti proyek tersebut tetapi disesuaikan dengan konteks Inlinesia sebagai platform event sepatu roda.

## Keputusan
- `resources/views/components/footer.blade.php` dan footer dasbor admin memakai `© 2026 - Damai digital creative` serta tautan **Lisensi** ke rute `license`.
- Rute publik baru `Route::view('/lisensi', 'publik.lisensi')->name('license')` ditambahkan tanpa controller.
- Halaman `resources/views/publik/lisensi.blade.php` memakai layout publik Inlinesia (`layouts.public`) dan kartu yang sudah ada (`account-table-card`), dengan konten:
  - pilihan harga 1x beli dan langganan sesuai proyek Kilat;
  - ketentuan lisensi (1 website per organisasi, larangan jual kembali/sebar, larangan menghapus copyright, lisensi tambahan untuk website ke-2, sanksi UU Hak Cipta No. 28 Tahun 2014);
  - kontak WhatsApp Damai digital creative (085800006248) dengan pesan yang menyebut Inlinesia.
- Gaya tambahan diletakkan di `resources/css/landing.css` dengan prefix `license-` dan `footer-license-link` agar mengikuti tema yang aktif, termasuk Ceria.
- Tes feature baru memastikan footer dan halaman lisensi dapat diakses publik serta memuat ketentuan utama.

## Konsekuensi
- Teks copyright lama `© <tahun> Inlinesia` diganti di footer publik dan dasbor admin; identitas Inlinesia tetap ada pada logo, judul, dan deskripsi halaman.
- Daftar harga disalin dari proyek Kilat dan dapat disesuaikan lagi bila pemilik menentukan harga Inlinesia tersendiri.
- Halaman lisensi terbuka untuk tamu (tidak memakai middleware `auth`/`event-access`).
- Peta proyek otomatis di-regenerate agar inventaris rute tetap valid.
