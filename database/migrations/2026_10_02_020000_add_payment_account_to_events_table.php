<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rekening tujuan pembayaran peserta. Semua kolom opsional supaya event
     * gratis, maupun event yang konfirmasinya lewat WhatsApp saja, tetap bisa
     * disimpan tanpa mengisi rekening.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->string('payment_bank_name', 80)->nullable();
            $table->string('payment_account_number', 60)->nullable();
            $table->string('payment_account_holder', 120)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn([
                'payment_bank_name',
                'payment_account_number',
                'payment_account_holder',
            ]);
        });
    }
};
