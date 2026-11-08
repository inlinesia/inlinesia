<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kelompok usia tidak lagi diisi pengelola saat membuat atau mengubah
     * event. Rentang usia tiap kategori dijelaskan lewat aturan kategori di
     * halaman pengaturan kategori, sehingga kolom ini hanya menyisakan data
     * lama.
     *
     * Kolom sengaja tidak dihapus supaya isi event yang sudah ada tidak ikut
     * hilang; nilainya sudah tidak dibaca maupun ditulis di mana pun.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->json('age_groups')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Baris kosong diisi dulu supaya constraint NOT NULL bisa dipasang lagi.
        DB::table('events')->whereNull('age_groups')->update(['age_groups' => '[]']);

        Schema::table('events', function (Blueprint $table): void {
            $table->json('age_groups')->nullable(false)->change();
        });
    }
};
