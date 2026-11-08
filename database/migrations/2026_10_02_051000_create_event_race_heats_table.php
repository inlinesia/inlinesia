<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Satu babak heats (babak heats, semifinal, atau final) untuk satu kategori.
     * Baris pesertanya disimpan di event_race_entries.
     */
    public function up(): void
    {
        Schema::create('event_race_heats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('category', 120);
            // heat = babak penyisihan, semi_final = semifinal, final = final.
            $table->string('stage', 20)->default('heat');
            $table->unsignedSmallInteger('number');
            $table->string('label', 120)->nullable();
            // draft = belum diisi, running = sedang dilombakan, done = selesai.
            $table->string('status', 20)->default('draft');
            $table->unsignedSmallInteger('lanes_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'category', 'stage', 'number'], 'event_race_heat_identity');
            $table->index(['event_id', 'category', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_race_heats');
    }
};
