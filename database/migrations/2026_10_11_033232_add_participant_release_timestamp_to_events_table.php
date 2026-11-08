<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mencatat kapan daftar peserta pertama kali dirilis.
 *
 * Kolom ini membedakan dua keadaan yang sama-sama berarti "tidak tampil":
 * daftar yang belum pernah dirilis, dan daftar yang sudah pernah dirilis lalu
 * ditarik kembali oleh pengelola. Tanpa penanda ini, rilis otomatis saat masa
 * pendaftaran habis akan langsung membatalkan pilihan organizers untuk
 * menyembunyikan kembali daftar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->timestamp('participants_published_at')->nullable()->after('participants_published');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('participants_published_at');
        });
    }
};
