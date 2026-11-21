<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;

require __DIR__.'/../vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$projectRoot = dirname(__DIR__);
$mapPath = $projectRoot.'/docs/peta-proyek.md';
$startMarker = '<!-- AUTO:START -->';
$endMarker = '<!-- AUTO:END -->';

if (! is_file($mapPath)) {
    fwrite(STDERR, "File docs/peta-proyek.md tidak ditemukan.\n");
    exit(1);
}

$mapContents = file_get_contents($mapPath);
$startPosition = strpos($mapContents, $startMarker);
$endPosition = strpos($mapContents, $endMarker);

if ($mapContents === false || $startPosition === false || $endPosition === false || $endPosition < $startPosition) {
    fwrite(STDERR, "Penanda AUTO:START / AUTO:END tidak lengkap di docs/peta-proyek.md.\n");
    exit(1);
}

$autoSection = implode("\n\n", [
    buildRouteIndex(),
    buildFileInventory('app', ['php'], 'Lapisan aplikasi (app/)'),
    buildFileInventory('resources/views', ['php'], 'View Blade (resources/views/)'),
    buildFileInventory('tests', ['php'], 'Pengujian (tests/)'),
    buildFileInventory('database', ['php'], 'Database: migrasi, seeder, factory'),
    buildFileInventory(['config', 'routes', 'scripts'], ['php', 'json'], 'Konfigurasi, rute, dan skrip'),
]);

$updatedContents = substr($mapContents, 0, $startPosition + strlen($startMarker))
    ."\n\n".$autoSection."\n\n"
    .substr($mapContents, $endPosition);

file_put_contents($mapPath, $updatedContents);

echo "Peta proyek diperbarui: docs/peta-proyek.md\n";

/**
 * Build the complete HTTP route index from Artisan, excluding vendor routes.
 */
function buildRouteIndex(): string
{
    Artisan::call('route:list', ['--json' => true, '--except-vendor' => true]);

    /** @var array<int, array<string, mixed>>|null $routes */
    $routes = json_decode(Artisan::output(), true);

    if (! is_array($routes)) {
        return "### Rute\n\nGagal membaca daftar rute dari `php artisan route:list`.";
    }

    $lines = [
        '### Rute ('.count($routes).')',
        '',
        '| Method | URI | Nama | Aksi | Middleware |',
        '|---|---|---|---|---|',
    ];

    foreach ($routes as $route) {
        $middleware = is_array($route['middleware'] ?? null)
            ? implode(', ', array_map('strval', $route['middleware']))
            : '';

        $lines[] = sprintf(
            '| %s | `%s` | `%s` | `%s` | %s |',
            escapeTableCell((string) ($route['method'] ?? '')),
            escapeTableCell((string) ($route['uri'] ?? '')),
            escapeTableCell((string) ($route['name'] ?? '')),
            escapeTableCell((string) ($route['action'] ?? '')),
            escapeTableCell($middleware)
        );
    }

    return implode("\n", $lines);
}

/**
 * Build a file inventory with class names and public methods for PHP files.
 *
 * @param  array<int, string>  $directories
 * @param  array<int, string>  $extensions
 */
function buildFileInventory(array|string $directories, array $extensions, string $title): string
{
    $projectRoot = dirname(__DIR__);
    $entries = [];

    foreach ((array) $directories as $directory) {
        $absolutePath = $projectRoot.'/'.$directory;

        if (! is_dir($absolutePath)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($absolutePath, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                continue;
            }

            if (! in_array(strtolower($file->getExtension()), $extensions, true)) {
                continue;
            }

            $relativePath = str_replace('\\', '/', substr($file->getPathname(), strlen($projectRoot) + 1));
            $contents = file($file->getPathname());

            $entries[] = [
                'path' => $relativePath,
                'lines' => $contents === false ? 0 : count($contents),
                'details' => $file->getExtension() === 'php' ? describePhpFile($file->getPathname()) : '',
            ];
        }
    }

    usort($entries, fn (array $left, array $right): int => strcmp($left['path'], $right['path']));

    $lines = ['### '.$title.' ('.count($entries).' file)', ''];

    foreach ($entries as $entry) {
        $line = '- `'.$entry['path'].'` — '.$entry['lines'].' baris';

        if ($entry['details'] !== '') {
            $line .= ' — '.$entry['details'];
        }

        $lines[] = $line;
    }

    return implode("\n", $lines);
}

/**
 * Extract the declared symbol and public method names from a PHP source file.
 */
function describePhpFile(string $path): string
{
    $source = file_get_contents($path);

    if ($source === false) {
        return '';
    }

    $codeWithoutComments = stripPhpComments($source);
    $symbol = '';

    if (preg_match('/\b(?:final\s+|abstract\s+)?(?:class|interface|trait|enum)\s+([A-Za-z_]\w*)/', $codeWithoutComments, $matches) === 1) {
        if (! in_array(strtolower($matches[1]), ['extends', 'implements'], true)) {
            $symbol = $matches[1];
        }
    }

    preg_match_all('/^\s*public\s+(?:static\s+)?function\s+([A-Za-z_]\w*)\s*\(/m', $codeWithoutComments, $matches);

    $methods = array_values(array_diff($matches[1], ['__construct']));
    $parts = [];

    if ($symbol !== '') {
        $parts[] = '`'.$symbol.'`';
    }

    if ($methods !== []) {
        $shownMethods = array_slice($methods, 0, 8);
        $methodList = implode(', ', array_map(fn (string $method): string => '`'.$method.'`', $shownMethods));

        if (count($methods) > count($shownMethods)) {
            $methodList .= ', …';
        }

        $parts[] = 'method: '.$methodList;
    }

    return implode(' — ', $parts);
}

/**
 * Remove PHP comments so declarations are detected only in executable code.
 */
function stripPhpComments(string $source): string
{
    $code = '';

    foreach (token_get_all($source) as $token) {
        if (is_array($token)) {
            if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $code .= $token[1];

            continue;
        }

        $code .= $token;
    }

    return $code;
}

/**
 * Escape pipe characters so values stay inside one Markdown table cell.
 */
function escapeTableCell(string $value): string
{
    return str_replace(['|', "\n"], ['\\|', ' '], $value);
}
