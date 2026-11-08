<?php

require __DIR__.'/../vendor/autoload.php';

use GuzzleHttp\Client;

$client = new Client([
    'verify' => false,
    'http_errors' => false,
    'timeout' => 30,
]);

/**
 * Mengambil data dari API kodewilayah dengan percobaan ulang.
 */
$getJson = function (string $url) use ($client): ?array {
    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $response = $client->get($url);

        if ($response->getStatusCode() === 200) {
            $decoded = json_decode((string) $response->getBody(), true);

            if (is_array($decoded) && ($decoded['success'] ?? false) === true) {
                return $decoded['data'];
            }
        }

        usleep(200_000 * $attempt);
    }

    return null;
};

$provinces = $getJson('https://api.kodewilayah.web.id/provinces');

if ($provinces === null) {
    fwrite(STDERR, 'Gagal mengambil daftar provinsi.'.PHP_EOL);
    exit(1);
}

$provinceMap = [];
$regencyMap = [];
$districtMap = [];
$missing = [];

foreach ($provinces as $province) {
    $provinceMap[(string) $province['code']] = $province['name'];

    $regencies = $getJson("https://api.kodewilayah.web.id/regencies/{$province['code']}");

    if ($regencies === null) {
        $missing[] = "kab/kota provinsi {$province['code']}";
        fwrite(STDERR, "Gagal: kab/kota provinsi {$province['code']}".PHP_EOL);

        continue;
    }

    foreach ($regencies as $regency) {
        $regencyMap[(string) $regency['code']] = $regency['name'];

        $districts = $getJson("https://api.kodewilayah.web.id/districts/{$regency['code']}");

        if ($districts === null) {
            $missing[] = 'kecamatan kab/kota '.$regency['code'];
            fwrite(STDERR, "Gagal: kecamatan kab/kota {$regency['code']}".PHP_EOL);

            continue;
        }

        foreach ($districts as $district) {
            $districtMap[(string) $district['code']] = $district['name'];
        }
    }
}

$result = [
    'provinces' => $provinceMap,
    'regencies' => $regencyMap,
    'districts' => $districtMap,
];

$dir = __DIR__.'/../database/seeders/data';

if (! is_dir($dir)) {
    mkdir($dir, 0755, true);
}

file_put_contents(
    $dir.'/wilayah.json',
    json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
);

echo PHP_EOL.'Provinces: '.count($result['provinces']).PHP_EOL;
echo 'Regencies: '.count($result['regencies']).PHP_EOL;
echo 'Districts: '.count($result['districts']).PHP_EOL;

if ($missing !== []) {
    echo 'Gagal: '.implode(', ', $missing).PHP_EOL;
}
