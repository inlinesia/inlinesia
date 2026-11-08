<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\Event;
use App\Services\CategoryFeeCalculator;
use Illuminate\Contracts\Console\Kernel;

$event = Event::query()->where('status', 'approved')->firstOrFail();
$rules = $event->category_rules;

// Sisipkan dua gelombang tarif pada kategori pertama untuk uji coba.
$rules[0]['pricing_waves'] = [
    [
        'label' => 'Early bird',
        'starts_on' => now()->subMonth()->format('Y-m-d'),
        'ends_on' => now()->addWeek()->format('Y-m-d'),
        'package_fee' => 250000,
        'extra_race_fee' => null,
        'outside_race_fee' => null,
        'discount_percent' => null,
        'discount_amount' => null,
    ],
    [
        'label' => 'Gelombang 2',
        'starts_on' => now()->addWeek()->addDay()->format('Y-m-d'),
        'ends_on' => now()->addMonth()->format('Y-m-d'),
        'package_fee' => 350000,
        'extra_race_fee' => null,
        'outside_race_fee' => null,
        'discount_percent' => null,
        'discount_amount' => null,
    ],
];

$event->update(['category_rules' => $rules]);

$calculator = app(CategoryFeeCalculator::class);
$rule = $event->fresh()->category_rules[0];

echo 'Kategori: '.$rule['name'].PHP_EOL;
foreach ($calculator->waveSummaries($rule) as $wave) {
    echo sprintf(
        ' - %s | %s | paket %s | aktif=%s%s',
        $wave['label'],
        $calculator->waveRangeLabel($wave),
        CategoryFeeCalculator::rupiah($wave['package_fee']),
        $wave['is_active'] ? 'ya' : 'tidak',
        PHP_EOL,
    );
}

echo 'Summary: '.$calculator->summary($rule).PHP_EOL;
