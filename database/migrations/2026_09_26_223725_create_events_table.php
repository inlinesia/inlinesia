<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('venue');
            $table->text('venue_address')->nullable();
            $table->string('scale');
            $table->text('regulations')->nullable();
            $table->text('officials')->nullable();
            $table->text('venue_conditions')->nullable();
            $table->text('awards')->nullable();
            $table->json('age_groups');
            $table->json('categories');
            $table->json('races');
            $table->date('registration_deadline')->nullable();
            $table->text('registration_info')->nullable();
            $table->text('fees')->nullable();
            $table->text('force_majeure')->nullable();
            $table->json('schedule');
            $table->string('status')->default('draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
