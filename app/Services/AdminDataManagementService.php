<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AdminDataManagementService
{
    private const BACKUP_DIRECTORY = 'backups';

    private const BACKUP_NAME_PATTERN = '/^inlinesia-\d{8}-\d{6}-[a-z0-9]{4}$/';

    /**
     * Tabel yang tidak dimasukkan dump karena bersifat sesi/antrian.
     *
     * @var list<string>
     */
    private const EXCLUDED_TABLES = [
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
    ];

    /**
     * Urutan pemulihan: induk sebelum anak agar foreign key terpenuhi.
     *
     * @var list<string>
     */
    private const SQL_RESTORE_ORDER = [
        'users',
        'clubs',
        'events',
        'athletes',
        'event_registrations',
        'event_category_race_settings',
        'event_race_groups',
        'event_race_heats',
        'event_race_entries',
        'event_finance_transactions',
        'event_registration_financial_entries',
        'notifications',
        'password_reset_tokens',
        'migrations',
    ];

    /**
     * @var list<string>
     */
    private const BACKUP_TABLES = [
        'users',
        'events',
        'event_registrations',
        'event_category_race_settings',
        'event_race_heats',
        'event_race_entries',
        'event_finance_transactions',
        'event_registration_financial_entries',
        'notifications',
    ];

    /**
     * @var array<string, array{label: string, description: string, table?: string}>
     */
    private const CATALOGS = [
        'users' => [
            'label' => 'Akun non-admin',
            'description' => 'Menghapus akun peserta dan pengelola. Pendaftaran milik akun tersebut ikut terhapus; akun admin tetap dilindungi.',
            'table' => 'users',
        ],
        'events' => [
            'label' => 'Event dan kejuaraan',
            'description' => 'Menghapus seluruh event beserta pendaftar, hasil perlombaan, pengaturan, dan keuangan terkait.',
            'table' => 'events',
        ],
        'event_registrations' => [
            'label' => 'Pendaftaran peserta',
            'description' => 'Menghapus data pendaftar; hasil peserta dan catatan pembayaran/refund terkait ikut terhapus.',
            'table' => 'event_registrations',
        ],
        'event_category_race_settings' => [
            'label' => 'Pengaturan nomor lomba',
            'description' => 'Menghapus konfigurasi perlombaan per kategori.',
            'table' => 'event_category_race_settings',
        ],
        'event_race_heats' => [
            'label' => 'Babak dan hasil perlombaan',
            'description' => 'Menghapus babak perlombaan dan hasil/lajur peserta pada babak tersebut.',
            'table' => 'event_race_heats',
        ],
        'event_race_entries' => [
            'label' => 'Entri dan hasil atlet',
            'description' => 'Menghapus lajur, status, waktu, dan hasil atlet tanpa menghapus data pendaftaran.',
            'table' => 'event_race_entries',
        ],
        'event_finance_transactions' => [
            'label' => 'Catatan keuangan event',
            'description' => 'Menghapus catatan pemasukan lain dan pengeluaran manual event.',
            'table' => 'event_finance_transactions',
        ],
        'event_registration_financial_entries' => [
            'label' => 'Riwayat pembayaran dan refund peserta',
            'description' => 'Menghapus rincian transaksi pembayaran/refund; status dan jumlah pendaftaran tetap tersimpan.',
            'table' => 'event_registration_financial_entries',
        ],
        'notifications' => [
            'label' => 'Notifikasi',
            'description' => 'Menghapus seluruh notifikasi di dalam aplikasi.',
            'table' => 'notifications',
        ],
        'password_reset_tokens' => [
            'label' => 'Token reset kata sandi',
            'description' => 'Menghapus semua tautan reset kata sandi yang masih tersimpan.',
            'table' => 'password_reset_tokens',
        ],
        'sessions' => [
            'label' => 'Sesi login lain',
            'description' => 'Menghapus sesi login tersimpan selain sesi admin yang sedang digunakan.',
            'table' => 'sessions',
        ],
        'payment_proofs' => [
            'label' => 'File bukti pembayaran',
            'description' => 'Menghapus semua file bukti pembayaran dan melepas rujukannya dari pendaftaran.',
        ],
    ];

    /**
     * @var list<string>
     */
    private const DELETE_ORDER = [
        'notifications',
        'password_reset_tokens',
        'sessions',
        'event_registration_financial_entries',
        'event_race_entries',
        'event_race_heats',
        'event_category_race_settings',
        'event_finance_transactions',
        'event_registrations',
        'events',
        'users',
    ];

    /**
     * @var list<string>
     */
    private const RESTORE_ORDER = [
        'users',
        'events',
        'event_registrations',
        'event_category_race_settings',
        'event_race_heats',
        'event_race_entries',
        'event_finance_transactions',
        'event_registration_financial_entries',
        'notifications',
    ];

    /**
     * @return array<string, array{label: string, description: string, count: int}>
     */
    public function catalogs(string $currentSessionId): array
    {
        $catalogs = [];

        foreach (self::CATALOGS as $key => $catalog) {
            $count = match ($key) {
                'users' => DB::table('users')->where('role', '!=', 'admin')->count(),
                'sessions' => DB::table('sessions')->where('id', '!=', $currentSessionId)->count(),
                'payment_proofs' => count(Storage::disk('local')->allFiles('payment-proofs')),
                default => DB::table($catalog['table'])->count(),
            };

            $catalogs[$key] = [
                ...$catalog,
                'count' => (int) $count,
            ];
        }

        return $catalogs;
    }

    /**
     * @return list<array{name: string, created_at: ?string, tables: array<string, int>, files: int, size: int}>
     */
    public function listBackups(): array
    {
        $disk = Storage::disk('local');
        $backups = [];

        foreach ($disk->directories(self::BACKUP_DIRECTORY) as $directory) {
            $name = basename($directory);

            if (! preg_match(self::BACKUP_NAME_PATTERN, $name)
                || ! $disk->exists($directory.'/database.sql')
                || ! $disk->exists($directory.'/meta.json')) {
                continue;
            }

            $meta = json_decode((string) $disk->get($directory.'/meta.json'), true);
            $backups[] = [
                'name' => $name,
                'created_at' => is_array($meta) && is_string($meta['created_at'] ?? null)
                    ? $meta['created_at']
                    : null,
                'tables' => is_array($meta) && is_array($meta['tables'] ?? null) ? $meta['tables'] : [],
                'files' => is_array($meta) && is_array($meta['files'] ?? null) ? count($meta['files']) : 0,
                'size' => (int) $disk->size($directory.'/database.sql'),
            ];
        }

        usort($backups, fn (array $left, array $right): int => strcmp($right['name'], $left['name']));

        return $backups;
    }

    /**
     * Buat dump SQL lengkap dari database server beserta file bukti pembayaran.
     */
    public function createBackup(): string
    {
        $disk = Storage::disk('local');
        $name = 'inlinesia-'.now()->format('Ymd-His').'-'.strtolower(Str::random(4));

        while ($disk->exists(self::BACKUP_DIRECTORY.'/'.$name)) {
            $name = 'inlinesia-'.now()->format('Ymd-His').'-'.strtolower(Str::random(4));
        }

        $sql = $this->buildSqlDump();
        $proofs = [];

        foreach ($disk->allFiles('payment-proofs') as $path) {
            $this->assertSafeProofPath($path);
            $contents = $disk->get($path);

            if (! is_string($contents)) {
                throw new RuntimeException("File bukti pembayaran {$path} tidak dapat dibaca untuk backup.");
            }

            $proofs[$path] = sha1($contents);
        }

        $meta = [
            'format' => 'inlinesia-sql-backup',
            'version' => 1,
            'created_at' => now()->toIso8601String(),
            'tables' => $this->tableCounts(),
            'files' => $proofs,
            'signature' => $this->sign($sql, $proofs),
        ];

        $directory = self::BACKUP_DIRECTORY.'/'.$name;
        $disk->put($directory.'/database.sql', $sql);
        $disk->put($directory.'/meta.json', $this->encode($meta));

        foreach ($proofs as $path => $hash) {
            $disk->put($directory.'/'.$path, (string) $disk->get($path));
        }

        return $name;
    }

    /**
     * Hapus salah satu backup yang tersimpan di server.
     */
    public function deleteBackup(string $name): void
    {
        $this->assertValidBackupName($name);

        Storage::disk('local')->deleteDirectory(self::BACKUP_DIRECTORY.'/'.$name);
    }

    /**
     * @param  list<string>  $selected
     * @return array<string, array{label: string, description: string, count: int}>
     */
    public function deletionSnapshot(array $selected, string $currentSessionId): array
    {
        $catalogs = $this->catalogs($currentSessionId);
        $snapshot = [];

        foreach ($selected as $key) {
            $snapshot[$key] = [
                'label' => $catalogs[$key]['label'],
                'description' => $catalogs[$key]['description'],
                'count' => $catalogs[$key]['count'],
            ];
        }

        return $snapshot;
    }

    /**
     * @param  list<string>  $selected
     * @return array<string, int>
     */
    public function deleteSelected(array $selected, string $currentSessionId): array
    {
        $before = $this->deletionSnapshot($selected, $currentSessionId);
        $userIds = in_array('users', $selected, true)
            ? DB::table('users')->where('role', '!=', 'admin')->pluck('id')->all()
            : [];
        $proofPaths = $this->proofPathsToDelete($selected, $userIds);

        DB::transaction(function () use ($selected, $currentSessionId, $userIds): void {
            foreach (self::DELETE_ORDER as $table) {
                if (! in_array($table, $selected, true)) {
                    continue;
                }

                if ($table === 'users') {
                    if ($userIds !== []) {
                        DB::table('sessions')->whereIn('user_id', $userIds)->delete();
                        DB::table('notifications')
                            ->where('notifiable_type', User::class)
                            ->whereIn('notifiable_id', $userIds)
                            ->delete();
                        DB::table('users')->whereIn('id', $userIds)->delete();
                    }

                    continue;
                }

                if ($table === 'sessions') {
                    DB::table('sessions')->where('id', '!=', $currentSessionId)->delete();

                    continue;
                }

                DB::table($table)->delete();
            }

            if (in_array('payment_proofs', $selected, true)) {
                DB::table('event_registrations')
                    ->where('payment_status', 'pending')
                    ->where('payment_channel', 'upload')
                    ->update([
                        'payment_status' => 'unpaid',
                        'payment_channel' => null,
                        'payment_proof_path' => null,
                    ]);
                DB::table('event_registrations')
                    ->whereNotNull('payment_proof_path')
                    ->update(['payment_proof_path' => null]);
            }
        });

        $disk = Storage::disk('local');
        foreach ($proofPaths as $path) {
            if ($disk->exists($path) && ! $disk->delete($path)) {
                throw new RuntimeException("File bukti pembayaran {$path} gagal dihapus.");
            }
        }

        if (in_array('payment_proofs', $selected, true)) {
            foreach ($disk->allFiles('payment-proofs') as $path) {
                if (! $disk->delete($path)) {
                    throw new RuntimeException("File bukti pembayaran {$path} gagal dihapus.");
                }
            }
        }

        return array_map(fn (array $row): int => $row['count'], $before);
    }

    /**
     * Pulihkan database dan file bukti dari backup yang tersimpan di server.
     */
    public function restoreBackup(string $name, int $currentAdminId, string $currentSessionId): void
    {
        $this->assertValidBackupName($name);

        $disk = Storage::disk('local');
        $directory = self::BACKUP_DIRECTORY.'/'.$name;
        $sql = $disk->get($directory.'/database.sql');
        $rawMeta = $disk->get($directory.'/meta.json');

        if (! is_string($sql) || ! is_string($rawMeta)) {
            throw $this->invalidBackup('Backup tidak ditemukan atau tidak lengkap di server.');
        }

        try {
            $meta = json_decode($rawMeta, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw $this->invalidBackup('Metadata backup tidak valid.');
        }

        if (! is_array($meta)
            || ($meta['format'] ?? null) !== 'inlinesia-sql-backup'
            || ($meta['version'] ?? null) !== 1
            || ! is_array($meta['tables'] ?? null)
            || ! is_array($meta['files'] ?? null)
            || ! is_string($meta['signature'] ?? null)) {
            throw $this->invalidBackup('Struktur backup tidak dikenali.');
        }

        $signature = $meta['signature'];
        unset($meta['signature']);

        if (! hash_equals($this->sign($sql, $meta['files']), $signature)) {
            throw $this->invalidBackup('Backup telah berubah atau tanda tangannya tidak valid.');
        }

        $statements = $this->parseStatements($sql);

        DB::transaction(function () use ($statements, $currentAdminId, $currentSessionId): void {
            foreach ($statements as $statement) {
                DB::statement($statement);
            }

            DB::table('sessions')->where('id', '!=', $currentSessionId)->delete();
            DB::table('password_reset_tokens')->delete();

            $currentAdminStillExists = DB::table('users')
                ->where('id', $currentAdminId)
                ->where('role', 'admin')
                ->exists();
            $primaryAdminStillExists = DB::table('users')
                ->where('is_primary_admin', true)
                ->where('email', config('primary-admin.email'))
                ->exists();

            if (! $currentAdminStillExists || ! $primaryAdminStillExists) {
                throw $this->invalidBackup(
                    'Backup harus berasal dari instalasi ini dan memuat akun Admin aktif serta Admin utama.',
                );
            }
        });

        $this->restoreProofFiles($name, $meta['files']);
    }

    /**
     * @param  list<string>  $selected
     * @param  list<int|string>  $userIds
     * @return list<string>
     */
    private function proofPathsToDelete(array $selected, array $userIds): array
    {
        if (in_array('payment_proofs', $selected, true)) {
            return Storage::disk('local')->allFiles('payment-proofs');
        }

        if (! array_intersect($selected, ['events', 'event_registrations', 'users'])) {
            return [];
        }

        $registrations = DB::table('event_registrations')
            ->whereNotNull('payment_proof_path')
            ->where(function ($query) use ($selected, $userIds): void {
                if (in_array('events', $selected, true)) {
                    $query->orWhereIn('event_id', DB::table('events')->select('id'));
                }
                if (in_array('event_registrations', $selected, true)) {
                    $query->orWhereIn('id', DB::table('event_registrations')->select('id'));
                }
                if (in_array('users', $selected, true) && $userIds !== []) {
                    $query->orWhereIn('user_id', $userIds);
                }
            })
            ->pluck('payment_proof_path');

        return $registrations->all();
    }

    private function assertValidBackupName(string $name): void
    {
        if (! preg_match(self::BACKUP_NAME_PATTERN, $name)) {
            throw $this->invalidBackup('Nama backup tidak valid.');
        }
    }

    private function invalidBackup(string $message): ValidationException
    {
        return ValidationException::withMessages(['backup' => $message]);
    }

    /**
     * @return array<string, int>
     */
    private function tableCounts(): array
    {
        $counts = [];

        foreach ($this->orderedTables() as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        return $counts;
    }

    /**
     * @return list<string>
     */
    private function orderedTables(): array
    {
        $all = collect(Schema::getTables())
            ->pluck('name')
            ->filter(fn ($table): bool => is_string($table) && ! in_array($table, self::EXCLUDED_TABLES, true))
            ->map(fn (string $table): string => $table)
            ->all();

        $ordered = array_values(array_intersect(self::SQL_RESTORE_ORDER, $all));
        $rest = array_values(array_diff($all, $ordered));
        sort($rest);

        return [...$ordered, ...$rest];
    }

    private function buildSqlDump(): string
    {
        $tables = $this->orderedTables();
        $lines = [
            '-- Inlinesia SQL backup',
            '-- Dibuat: '.now()->toIso8601String(),
            '-- Database: '.(string) config('database.default'),
            '-- Sesi login dan tabel antrian sengaja tidak disertakan.',
        ];

        foreach (array_reverse($tables) as $table) {
            $lines[] = 'DELETE FROM '.$this->quoteIdentifier($table).';';
        }

        foreach ($tables as $table) {
            $rows = DB::table($table)->get()->map(fn (object $row): array => (array) $row)->all();

            if ($rows === []) {
                continue;
            }

            $columns = Schema::getColumnListing($table);
            $columnsSql = implode(', ', array_map(fn (string $column): string => $this->quoteIdentifier($column), $columns));
            $lines[] = '';
            $lines[] = '-- TABLE: '.$table.' ('.count($rows).' baris)';

            foreach (array_chunk($rows, 100) as $chunk) {
                $values = [];

                foreach ($chunk as $row) {
                    $cells = array_map(
                        fn (string $column): string => $this->quoteValue($row[$column] ?? null),
                        $columns,
                    );
                    $values[] = '('.implode(', ', $cells).')';
                }

                $lines[] = 'INSERT INTO '.$this->quoteIdentifier($table).' ('.$columnsSql.') VALUES '.implode(', ', $values).';';
            }
        }

        return implode("\n", $lines)."\n";
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            return '`'.str_replace('`', '``', $identifier).'`';
        }

        return '"'.str_replace('"', '""', $identifier).'"';
    }

    private function quoteValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return DB::connection()->getPdo()->quote((string) $value);
    }

    /**
     * @param  array<string, string>  $proofs
     */
    private function sign(string $sql, array $proofs): string
    {
        $payload = hash('sha256', $sql).'|'.$this->encode($proofs);

        return hash_hmac('sha256', $payload, (string) config('app.key'));
    }

    /**
     * @return list<string>
     */
    private function parseStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $insideQuote = false;
        $length = strlen($sql);

        for ($index = 0; $index < $length; $index++) {
            $character = $sql[$index];

            if ($character === '\\' && $insideQuote) {
                $buffer .= $character;

                if ($index + 1 < $length) {
                    $index++;
                    $buffer .= $sql[$index];
                }

                continue;
            }

            if ($character === "'") {
                if ($insideQuote && $index + 1 < $length && $sql[$index + 1] === "'") {
                    $buffer .= "''";
                    $index++;

                    continue;
                }

                $insideQuote = ! $insideQuote;
                $buffer .= $character;

                continue;
            }

            if ($character === ';' && ! $insideQuote) {
                $statement = trim(preg_replace('/^--[^\n]*\n/m', '', $buffer) ?? '');

                if ($statement !== '') {
                    $statements[] = $statement.';';
                }

                $buffer = '';

                continue;
            }

            $buffer .= $character;
        }

        foreach ($statements as $statement) {
            if (! preg_match('/^(DELETE FROM|INSERT INTO)\s+/i', $statement)) {
                throw $this->invalidBackup('Backup mengandung pernyataan SQL yang tidak dikenali.');
            }
        }

        if ($statements === []) {
            throw $this->invalidBackup('Backup tidak berisi data sama sekali.');
        }

        return $statements;
    }

    private function assertSafeProofPath(string $path): void
    {
        $segments = explode('/', str_replace('\\', '/', $path));

        if (! preg_match('#^payment-proofs/[A-Za-z0-9._/-]+$#', $path)
            || in_array('..', $segments, true)) {
            throw new RuntimeException("Lokasi file bukti pembayaran {$path} tidak valid.");
        }
    }

    /**
     * @param  array<string, string>  $proofs
     */
    private function restoreProofFiles(string $name, array $proofs): void
    {
        $disk = Storage::disk('local');
        $directory = self::BACKUP_DIRECTORY.'/'.$name;
        $restored = [];

        foreach ($proofs as $path => $hash) {
            $this->assertSafeProofPath((string) $path);
            $source = $directory.'/'.$path;
            $contents = $disk->get($source);

            if (! is_string($contents) || sha1($contents) !== $hash) {
                throw new RuntimeException("File bukti pembayaran {$path} di backup tidak valid.");
            }

            $restored[$path] = $contents;
        }

        foreach ($disk->allFiles('payment-proofs') as $path) {
            if (! $disk->delete($path)) {
                throw new RuntimeException("File bukti pembayaran lama {$path} gagal dihapus saat pemulihan.");
            }
        }

        foreach ($restored as $path => $contents) {
            if (! $disk->put($path, $contents)) {
                throw new RuntimeException("File bukti pembayaran {$path} gagal dipulihkan.");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function encode(array $payload): string
    {
        return json_encode(
            $payload,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
        );
    }
}
