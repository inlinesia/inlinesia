<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AdminDataManagementService
{
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

    public function makeBackup(): string
    {
        $tables = [];
        foreach (self::BACKUP_TABLES as $table) {
            $tables[$table] = DB::table($table)->get()->map(
                fn (object $row): array => (array) $row,
            )->all();
        }

        $disk = Storage::disk('local');
        $files = [];
        foreach ($disk->allFiles('payment-proofs') as $path) {
            $contents = $disk->get($path);
            if (! is_string($contents)) {
                throw new RuntimeException("File bukti pembayaran {$path} tidak dapat dibaca untuk backup.");
            }
            $files[$path] = base64_encode($contents);
        }

        foreach ($tables['event_registrations'] as $registration) {
            $proofPath = $registration['payment_proof_path'] ?? null;
            if ($proofPath && ! array_key_exists($proofPath, $files)) {
                throw new RuntimeException("File bukti pembayaran {$proofPath} dirujuk oleh pendaftaran tetapi tidak ditemukan.");
            }
        }

        $payload = [
            'format' => 'inlinesia-data-backup',
            'version' => 1,
            'created_at' => now()->toIso8601String(),
            'tables' => $tables,
            'files' => $files,
        ];
        $payload['signature'] = hash_hmac('sha256', $this->encode($payload), (string) config('app.key'));

        return $this->encode($payload);
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

    public function restore(string $contents, int $currentAdminId, string $currentSessionId): void
    {
        try {
            $backup = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ValidationException::withMessages([
                'backup' => 'File backup bukan JSON Inlinesia yang valid.',
            ]);
        }

        if (! is_array($backup)
            || ($backup['format'] ?? null) !== 'inlinesia-data-backup'
            || ($backup['version'] ?? null) !== 1
            || ! is_array($backup['tables'] ?? null)
            || ! is_array($backup['files'] ?? null)
            || ! is_string($backup['signature'] ?? null)) {
            throw ValidationException::withMessages([
                'backup' => 'Struktur file tidak dikenali sebagai backup Inlinesia.',
            ]);
        }

        $signature = $backup['signature'];
        unset($backup['signature']);
        $expectedSignature = hash_hmac('sha256', $this->encode($backup), (string) config('app.key'));
        if (! hash_equals($expectedSignature, $signature)) {
            throw ValidationException::withMessages([
                'backup' => 'Tanda tangan backup tidak valid atau file telah berubah.',
            ]);
        }

        $tables = $backup['tables'];
        $tableNames = array_keys($tables);
        sort($tableNames);
        $expectedTables = self::BACKUP_TABLES;
        sort($expectedTables);
        if ($tableNames !== $expectedTables) {
            throw ValidationException::withMessages([
                'backup' => 'Backup harus berisi seluruh katalog data Inlinesia.',
            ]);
        }

        $this->validateRows($tables);
        $files = $this->decodeFiles($backup['files']);
        $this->validateCurrentAdmin($tables['users'], $currentAdminId);
        $this->validateReferencedProofs($tables['event_registrations'], $files);

        DB::transaction(function () use ($tables, $currentSessionId): void {
            foreach (self::DELETE_ORDER as $table) {
                DB::table($table)->delete();
            }

            foreach (self::RESTORE_ORDER as $table) {
                foreach (array_chunk($tables[$table], 500) as $rows) {
                    DB::table($table)->insert($rows);
                }
            }

            DB::table('sessions')->where('id', '!=', $currentSessionId)->delete();
            DB::table('password_reset_tokens')->delete();
        });

        $disk = Storage::disk('local');
        foreach ($disk->allFiles('payment-proofs') as $path) {
            if (! $disk->delete($path)) {
                throw new RuntimeException("File bukti pembayaran lama {$path} gagal dihapus saat pemulihan.");
            }
        }

        foreach ($files as $path => $fileContents) {
            if (! $disk->put($path, $fileContents)) {
                throw new RuntimeException("File bukti pembayaran {$path} gagal dipulihkan.");
            }
        }
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

    /**
     * @param  array<string, mixed>  $tables
     */
    private function validateRows(array $tables): void
    {
        foreach (self::BACKUP_TABLES as $table) {
            if (! is_array($tables[$table])) {
                throw ValidationException::withMessages([
                    'backup' => "Data katalog {$table} tidak valid.",
                ]);
            }

            $expectedColumns = Schema::getColumnListing($table);
            sort($expectedColumns);

            foreach ($tables[$table] as $row) {
                if (! is_array($row)) {
                    throw ValidationException::withMessages([
                        'backup' => "Baris data katalog {$table} tidak valid.",
                    ]);
                }

                $columns = array_keys($row);
                sort($columns);
                if ($columns !== $expectedColumns) {
                    throw ValidationException::withMessages([
                        'backup' => "Struktur kolom katalog {$table} tidak sesuai dengan aplikasi ini.",
                    ]);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $encodedFiles
     * @return array<string, string>
     */
    private function decodeFiles(array $encodedFiles): array
    {
        $files = [];
        foreach ($encodedFiles as $path => $encodedContents) {
            $segments = explode('/', str_replace('\\', '/', (string) $path));
            if (! preg_match('#^payment-proofs/[A-Za-z0-9._/-]+$#', (string) $path)
                || in_array('..', $segments, true)
                || ! is_string($encodedContents)) {
                throw ValidationException::withMessages([
                    'backup' => 'Backup memuat lokasi atau isi file bukti pembayaran yang tidak valid.',
                ]);
            }

            $contents = base64_decode($encodedContents, true);
            if (! is_string($contents)) {
                throw ValidationException::withMessages([
                    'backup' => "File bukti pembayaran {$path} tidak valid.",
                ]);
            }

            $files[$path] = $contents;
        }

        return $files;
    }

    /**
     * @param  list<array<string, mixed>>  $users
     */
    private function validateCurrentAdmin(array $users, int $currentAdminId): void
    {
        $includesCurrentAdmin = collect($users)->contains(
            fn (array $user): bool => (int) $user['id'] === $currentAdminId
                && $user['role'] === 'admin',
        );
        $includesPrimaryAdmin = collect($users)->contains(
            fn (array $user): bool => (bool) $user['is_primary_admin']
                && $user['email'] === config('primary-admin.email'),
        );

        if (! $includesCurrentAdmin || ! $includesPrimaryAdmin) {
            throw ValidationException::withMessages([
                'backup' => 'Backup harus berasal dari instalasi ini dan memuat akun Admin yang sedang digunakan serta Admin utama.',
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $registrations
     * @param  array<string, string>  $files
     */
    private function validateReferencedProofs(array $registrations, array $files): void
    {
        foreach ($registrations as $registration) {
            $path = $registration['payment_proof_path'] ?? null;
            if ($path !== null && ! array_key_exists($path, $files)) {
                throw ValidationException::withMessages([
                    'backup' => "Backup tidak menyertakan file bukti pembayaran {$path}.",
                ]);
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
