<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('submitted_by')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->text('decision_note')->nullable()->after('reviewed_by');
            $table->timestamp('reviewed_at')->nullable()->after('decision_note');
            $table->index(['status', 'start_date']);
        });

        Schema::create('event_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('participant_name', 120);
            $table->unsignedSmallInteger('birth_year');
            $table->string('club_name')->nullable();
            $table->string('category', 120);
            $table->json('races');
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->index(['event_id', 'status']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('event_registrations');

        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['status', 'start_date']);
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['decision_note', 'reviewed_at']);
        });
    }
};
