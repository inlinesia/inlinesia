<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->boolean('is_free')->default(false);
        });

        Schema::table('event_registrations', function (Blueprint $table): void {
            $table->string('payment_channel')->nullable();
            $table->string('payment_proof_path')->nullable();
            $table->string('category', 120)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('event_registrations')
            ->whereNull('category')
            ->update(['category' => 'Belum dikategorikan']);

        Schema::table('event_registrations', function (Blueprint $table): void {
            $table->string('category', 120)->nullable(false)->change();
            $table->dropColumn(['payment_channel', 'payment_proof_path']);
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('is_free');
        });
    }
};
