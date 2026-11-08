<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Waktu pembayaran peserta dikonfirmasi.
     *
     * Tanpa kolom ini, halaman keuangan tidak bisa mengelompokkan pemasukan
     * pendaftaran per bulan: satu-satunya cap waktu yang ada adalah updated_at,
     * yang ikut berubah setiap kali data peserta diedit. payment_status sendiri
     * hanya pernah bernilai "pending" atau "paid", jadi kolom ini diisi pada
     * saat konfirmasi dan tidak pernah diubah lagi.
     */
    public function up(): void
    {
        Schema::table('event_registrations', function (Blueprint $table): void {
            $table->timestamp('confirmed_paid_at')->nullable()->after('payment_proof_path');
        });
    }

    public function down(): void
    {
        Schema::table('event_registrations', function (Blueprint $table): void {
            $table->dropColumn('confirmed_paid_at');
        });
    }
};
