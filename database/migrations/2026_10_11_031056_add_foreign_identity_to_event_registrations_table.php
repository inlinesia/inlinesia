<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambah identitas untuk peserta yang bukan warga negara Indonesia.
 *
 * Warga negara Indonesia memakai NIK 16 digit. Peserta yang tidak punya NIK
 * memakai nomor paspor dan negara asal. Nomor pasport diperlakukan sama seperti
 * NIK: disimpan terenkripsi di kolom aslinya dan dicuric menjadi hash untuk
 * mendeteksi pendaftaran ganda tanpa membuka nomor aslinya.
 *
 * Batasan unik mengikuti pola NIK yang sudah ada, yaitu satu nomor paspor hanya
 * boleh dipakai satu kali pada satu akun dalam satu event.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_registrations', function (Blueprint $table): void {
            $table->text('passport_number')->nullable()->after('nik_hash');
            $table->char('passport_hash', 64)->nullable()->after('passport_number');
            $table->string('nationality', 80)->nullable()->after('passport_hash');
        });

        Schema::table('event_registrations', function (Blueprint $table): void {
            $table->unique(['event_id', 'user_id', 'passport_hash'], 'event_registrations_passport_unique');
        });
    }

    public function down(): void
    {
        Schema::table('event_registrations', function (Blueprint $table): void {
            $table->dropIndex('event_registrations_passport_unique');
        });

        Schema::table('event_registrations', function (Blueprint $table): void {
            $table->dropColumn(['passport_number', 'passport_hash', 'nationality']);
        });
    }
};
