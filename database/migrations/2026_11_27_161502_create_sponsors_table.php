<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsors', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 20);
            $table->string('package', 30);
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->string('brand_name', 120);
            $table->string('contact_name', 120);
            $table->string('phone', 40);
            $table->string('email', 180)->nullable();
            $table->string('website_url', 500);
            $table->string('logo_path', 500)->nullable();
            $table->text('message')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedInteger('amount')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsors');
    }
};
