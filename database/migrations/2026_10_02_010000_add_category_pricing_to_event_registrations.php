<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menyimpan harga tiket yang sudah dihitung saat kategori ditetapkan,
     * supaya nominal peserta tidak berubah ketika pengelola mengubah
     * aturan harga kategori di kemudian hari.
     */
    public function up(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->integer('fee_total')->nullable()->after('category');
            $table->json('fee_breakdown')->nullable()->after('fee_total');
        });
    }

    public function down(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropColumn(['fee_total', 'fee_breakdown']);
        });
    }
};
