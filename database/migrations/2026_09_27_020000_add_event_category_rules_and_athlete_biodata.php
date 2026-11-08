<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->json('category_rules')->nullable()->after('categories');
        });

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->text('nik')->nullable()->after('user_id');
            $table->char('nik_hash', 64)->nullable()->after('nik');
            $table->string('nickname', 80)->nullable()->after('participant_name');
            $table->string('gender', 20)->nullable()->after('nickname');
            $table->date('date_of_birth')->nullable()->after('gender');
            $table->text('address')->nullable()->after('date_of_birth');
            $table->string('guardian_name', 120)->nullable()->after('address');
            $table->string('whatsapp', 40)->nullable()->after('guardian_name');
            $table->unique(['event_id', 'user_id', 'nik_hash']);
        });
    }

    public function down(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropUnique(['event_id', 'user_id', 'nik_hash']);
            $table->dropColumn([
                'nik',
                'nik_hash',
                'nickname',
                'gender',
                'date_of_birth',
                'address',
                'guardian_name',
                'whatsapp',
            ]);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('category_rules');
        });
    }
};
