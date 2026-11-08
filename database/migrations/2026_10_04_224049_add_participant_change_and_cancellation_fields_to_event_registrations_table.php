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
        Schema::table('event_registrations', function (Blueprint $table): void {
            $table->json('participant_change_request')->nullable();
            $table->string('participant_change_status', 20)->nullable();
            $table->timestamp('participant_change_requested_at')->nullable();
            $table->string('cancellation_status', 20)->nullable();
            $table->timestamp('cancellation_requested_at')->nullable();
            $table->timestamp('cancellation_decided_at')->nullable();
            $table->unsignedBigInteger('refund_amount')->default(0);
            $table->timestamp('refunded_at')->nullable();
        });

        Schema::create('event_registration_financial_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_registration_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->unsignedBigInteger('amount');
            $table->string('description', 180);
            $table->timestamp('occurred_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['event_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_registration_financial_entries');

        Schema::table('event_registrations', function (Blueprint $table): void {
            $table->dropColumn([
                'participant_change_request',
                'participant_change_status',
                'participant_change_requested_at',
                'cancellation_status',
                'cancellation_requested_at',
                'cancellation_decided_at',
                'refund_amount',
                'refunded_at',
            ]);
        });
    }
};
