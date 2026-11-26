<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->boolean('cancellation_allowed')->default(true)->after('organizer_whatsapp');
            $table->unsignedSmallInteger('cancellation_min_days')->nullable()->after('cancellation_allowed');
            $table->date('cancellation_deadline')->nullable()->after('cancellation_min_days');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn(['cancellation_allowed', 'cancellation_min_days', 'cancellation_deadline']);
        });
    }
};
