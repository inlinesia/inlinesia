<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aturan perlombaan per kategori: berapa orang per babak, sistem
     * penilaian, berapa yang lolos, apakah ada semifinal, dan siapa lawannya.
     *
     * Dipisah dari events.category_rules karena yang di sini bukan syarat
     * pendaftaran, melainkan cara pertandingan dijalankan pada hari lomba.
     * Satu baris = satu kategori; kategori tanpa baris memakai bawaan.
     */
    public function up(): void
    {
        Schema::create('event_category_race_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('category', 120);

            // Berapa orang sekaligus start dalam satu babak.
            $table->unsignedSmallInteger('runners_per_heat')->default(6);
            // qualification = urutan finis menentukan, points = akumulasi poin.
            $table->string('scoring_system', 20)->default('qualification');
            // Berapa orang yang lolos dari tiap babak.
            $table->unsignedSmallInteger('qualify_count')->default(3);
            // direct_final = pemenang babak langsung masuk final, semi_final = ada semifinal.
            $table->string('stages', 20)->default('direct_final');
            $table->unsignedSmallInteger('semi_finalists')->default(0);
            $table->unsignedSmallInteger('finalists')->default(8);
            $table->unsignedSmallInteger('champion_count')->default(3);
            // mixed = bebas, cross_club = satu babak wajib lintas klub, same_club = satu klub per babak.
            $table->string('opponent_scope', 20)->default('mixed');

            $table->timestamps();

            $table->unique(['event_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_category_race_settings');
    }
};
