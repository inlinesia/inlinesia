<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('individual')->after('email');
            $table->string('phone', 40)->nullable()->after('role');
            $table->string('organization')->nullable()->after('phone');
            $table->text('address')->nullable()->after('organization');
            $table->boolean('is_primary_admin')->default(false)->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'phone', 'organization', 'address', 'is_primary_admin']);
        });
    }
};
