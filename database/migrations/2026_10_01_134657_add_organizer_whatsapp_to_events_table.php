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
            $table->string('organizer_whatsapp', 40)->nullable();
        });

        DB::table('events')
            ->whereNotNull('submitted_by')
            ->update([
                'organizer_whatsapp' => DB::table('users')
                    ->select('phone')
                    ->whereColumn('users.id', 'events.submitted_by'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('organizer_whatsapp');
        });
    }
};
