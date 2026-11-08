<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Satu atlet pada satu babak: start number/lane, hasil, dan apakah dia
     * lolos ke tahap berikutnya. Kolom ini yang diisi panitia pada hari lomba.
     */
    public function up(): void
    {
        Schema::create('event_race_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_race_heat_id')->constrained('event_race_heats')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_registration_id')->constrained()->cascadeOnDelete();
            $table->string('category', 120);
            $table->unsignedSmallInteger('lane');

            // pending = belum ditentukan, finished = finis, no_show = tidak muncul,
            // dnf = finisWalking gagal mencapai garis, dsq = diskualifikasi.
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('place')->nullable();
            $table->unsignedInteger('time_ms')->nullable();
            $table->integer('points')->default(0);
            $table->boolean('is_qualified')->default(false);
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(['event_race_heat_id', 'lane'], 'event_race_entry_lane');
            $table->index(['event_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_race_entries');
    }
};
