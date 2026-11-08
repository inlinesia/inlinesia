<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'is_primary_admin')) {
            return;
        }

        $email = 'inlinesia@gmail.com';
        $primaryAdmin = DB::table('users')
            ->where('is_primary_admin', true)
            ->orderBy('id')
            ->first();

        $emailOwner = DB::table('users')->where('email', $email)->first();
        if ($emailOwner && (! $primaryAdmin || $emailOwner->id !== $primaryAdmin->id)) {
            throw new RuntimeException("Alamat {$email} sudah digunakan akun lain; email admin utama tidak diubah.");
        }

        if ($primaryAdmin) {
            DB::table('users')
                ->where('id', $primaryAdmin->id)
                ->update([
                    'email' => $email,
                    'role' => 'admin',
                    'email_verified_at' => null,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Keep the designated primary admin email fixed when rolling back newer migrations.
    }
};
