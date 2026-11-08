<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\Event;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

// Akun QA sementara: admin, sudah terverifikasi, supaya bisa membuka semua
// halaman termasuk pengaturan kategori dan halaman pendaftaran.
$user = User::query()->updateOrCreate(
    ['email' => 'qa-visual@example.test'],
    [
        'name' => 'QA Visual',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'email_verified_at' => now(),
        'is_primary_admin' => false,
    ],
);

$event = Event::query()->where('status', 'approved')->first();

echo 'QA user: '.$user->email.' / password'.PHP_EOL;
echo 'Event ID: '.($event?->id ?? 'none').PHP_EOL;
echo 'Event name: '.($event?->name ?? '-').PHP_EOL;
