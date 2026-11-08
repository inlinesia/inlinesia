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
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('registration_open')->default(false);
        });

        DB::table('events')
            ->where('status', 'approved')
            ->whereNotNull('category_rules')
            ->where(function ($query): void {
                $query->whereNull('registration_deadline')
                    ->orWhereDate('registration_deadline', '>=', today());
            })
            ->get(['id', 'category_rules'])
            ->each(function (object $event): void {
                $rules = json_decode($event->category_rules, true);

                if (is_array($rules) && $rules !== []) {
                    DB::table('events')->where('id', $event->id)->update(['registration_open' => true]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('registration_open');
        });
    }
};
