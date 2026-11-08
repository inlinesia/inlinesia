<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catatan keuangan manual per event.
     *
     * Pemasukan pendaftaran SENGAJA TIDAK disimpan di tabel ini. Nilainya
     * dihitung langsung dari event_registrations (jumlah fee_total pada
     * pendaftaran yang pembayarannya sudah dikonfirmasi), supaya tidak bisa
     * dobel atau tidak sinkron dengan data peserta yang sebenarnya.
     *
     * Tabel ini hanya menampung sumber pemasukan lain (sponsor, penjualan
     * merchandise, donasi, dan sejenisnya) serta seluruh pengeluaran.
     */
    public function up(): void
    {
        Schema::create('event_finance_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10); // income | expense
            $table->string('category', 40);
            $table->string('description', 180);
            $table->decimal('amount', 14, 2);
            $table->date('occurred_on');
            $table->string('notes', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['event_id', 'occurred_on']);
            $table->index(['event_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_finance_transactions');
    }
};
