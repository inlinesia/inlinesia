<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class BootstrapPrimaryAdmin extends Command
{
    protected $signature = 'app:bootstrap-admin
        {--email= : Email admin utama (tetap)}
        {--name=Inlinesia : Nama admin utama}';

    protected $description = 'Buat akun admin utama pertama dengan kata sandi yang dimasukkan secara tersembunyi';

    public function handle(): int
    {
        if (User::query()->where('is_primary_admin', true)->exists()) {
            $this->components->error('Admin utama sudah terdaftar. Perintah ini hanya dapat digunakan satu kali.');

            return self::FAILURE;
        }

        $email = strtolower(trim((string) ($this->option('email') ?: config('primary-admin.email'))));
        $name = trim((string) $this->option('name'));

        if ($email !== config('primary-admin.email')) {
            $this->components->error('Email admin utama bersifat tetap dan tidak dapat diganti.');

            return self::FAILURE;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '') {
            $this->components->error('Nama wajib diisi dan alamat email admin utama harus valid.');

            return self::FAILURE;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->components->error('Email tersebut sudah digunakan. Tidak ada akun yang diubah.');

            return self::FAILURE;
        }

        $password = getenv('INLINESIA_PRIMARY_ADMIN_PASSWORD');
        if ($password === false || $password === '') {
            $password = $this->secret('Kata sandi admin utama');
            $confirmation = $this->secret('Ulangi kata sandi');
        } else {
            $confirmation = $password;
        }

        if ($password === null || $password !== $confirmation || strlen($password) < 8) {
            $this->components->error('Kata sandi harus sama pada kedua isian dan minimal 8 karakter.');

            return self::FAILURE;
        }

        $user = new User;
        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'role' => 'admin',
            'password' => Hash::make($password),
            'is_primary_admin' => true,
        ])->save();

        $this->components->info("Admin utama {$email} berhasil dibuat.");

        return self::SUCCESS;
    }
}
