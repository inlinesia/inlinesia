<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Direktori klub dan atlet.
 *
 * Tabel ini menyimpan orang dan klub yang dikenal Inlinesia, termasuk yang
 * belum ikut lomba apa pun. Mereka tetap bisa didaftarkan supaya biodata
 * dan NIK-nya tersimpan sekali saja, lalu dipakai lagi saat mendaftar
 * lomba lain.
 *
 * Kolom owner_user_id menandai siapa yang berhak melihat NIK. NULL berarti
 * data tersebut tidak dikuasai akun mana pun, misalnya klub yang
 * mendaftarkan dirinya sendiri lewat formulir publik.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clubs', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 180);
            $table->string('city', 120)->nullable();
            $table->string('contact_name', 120)->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->string('contact_email', 180)->nullable();
            $table->text('description')->nullable();
            // Pemilik klub adalah akun yang boleh mengubah data klub ini.
            // Klubnya sendiri tidak ikut terhapus bila akun pemilik hilang,
            // karena klub adalah entitas nyata yang bisa diteruskan pengelola
            // lain. Yang hilang hanya penanda siapa yang berhak mengubahnya.
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('name');
        });

        Schema::create('athletes', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('gender', 10)->nullable();
            $table->date('date_of_birth')->nullable();
            // NIK disimpan terenkripsi, sama seperti pada pendaftaran lomba.
            $table->text('nik')->nullable();
            $table->char('nik_hash', 64)->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('guardian_name', 120)->nullable();
            $table->foreignId('club_id')->nullable()->constrained('clubs')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('name');
            $table->index('nik_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('athletes');
        Schema::dropIfExists('clubs');
    }
};
