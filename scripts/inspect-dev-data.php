<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\Event;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

echo 'Users: '.User::query()->count().PHP_EOL;
echo 'Events: '.Event::query()->count().PHP_EOL;
echo 'Approved: '.Event::query()->where('status', 'approved')->count().PHP_EOL;

foreach (Event::query()->limit(5)->get() as $event) {
    echo sprintf(
        '#%d %s | status=%s | open=%s | rules=%d | owner=%s%s',
        $event->id,
        $event->name,
        $event->status,
        $event->registration_open ? 'yes' : 'no',
        count($event->category_rules ?? []),
        $event->submitted_by,
        PHP_EOL,
    );
}

$admin = User::query()->where('role', 'admin')->first();
echo 'Admin: '.($admin?->email ?? 'none').PHP_EOL;
