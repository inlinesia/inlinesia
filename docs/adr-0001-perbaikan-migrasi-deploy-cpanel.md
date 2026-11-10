# ADR 0001: Perbaikan Migrasi untuk Deploy cPanel

## Status
Diterima.

## Konteks
Saat `php artisan migrate:fresh --force` di server cPanel (MariaDB), dua migrasi gagal:
1. `2026_10_04_224049_add_participant_change_and_cancellation_fields_to_event_registrations_table` gagal karena nama foreign key otomatis `event_registration_financial_entries_event_registration_id_foreign` terlalu panjang (melebihi batas 64 karakter).
2. `2026_10_11_130609_add_race_scope_to_race_tables` gagal karena mencoba menghapus unique index `event_category_race_settings_event_id_category_unique` yang masih dipakai oleh foreign key `event_id`.

## Keputusan
- Pada migrasi `2026_10_04_224049`: mengganti `$table->foreignId(...)->constrained()->cascadeOnDelete()` dengan kolom `unsignedBigInteger` + foreign key bernama singkat `fin_entry_reg_fk`.
- Pada migrasi `2026_10_11_130609`: saat mengubah unique index di `event_category_race_settings` (baik `up` maupun `down`), foreign key `event_id` dilepas dulu lalu dipasang kembali setelah unique index diganti. `dropColumn` pada `down` juga dibungkus array agar aman.

## Konsekuensi
- Migrasi dapat berjalan sampai selesai di MySQL/MariaDB dengan batas panjang identifier 64 karakter.
- Nama constraint berubah (lebih singkat) tetapi relasi dan perilaku cascade tetap sama.
- Tidak ada perubahan perilaku aplikasi selain kemampuan menjalankan migrasi; file migrasi lain tidak diubah.
