<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Faker\Generator;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TulungagungEventRegistrationSeeder extends Seeder
{
    use WithoutModelEvents;

    private const EVENT_NAME = 'Kejurprov XI Piala Bupati Tulungagung';

    private const TARGET_REGISTRATIONS = 300;

    /** Password yang sama untuk seluruh akun demo, agar mudah dipakai login. */
    public const DEMO_PASSWORD = 'Sukses123';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $event = Event::query()->where('name', self::EVENT_NAME)->first();

        if (! $event) {
            throw new RuntimeException('Kejurprov XI Piala Bupati Tulungagung tidak ditemukan.');
        }

        if ($event->status !== 'approved' || ! $event->registration_open || empty($event->category_rules)) {
            throw new RuntimeException('Kejuaraan harus disetujui, membuka pendaftaran, dan memiliki aturan kategori.');
        }

        $existingRegistrationCount = $event->registrations()->count();

        if ($existingRegistrationCount > self::TARGET_REGISTRATIONS) {
            throw new RuntimeException('Jumlah pendaftaran yang sudah ada melebihi target simulasi 300.');
        }

        DB::transaction(function () use ($event, $existingRegistrationCount): void {
            $this->createDemoAccounts();

            $accounts = User::query()
                ->whereIn('role', ['club_manager', 'individual'])
                ->orderBy('id')
                ->limit(20)
                ->get();

            if ($accounts->count() !== 20) {
                throw new RuntimeException('Simulasi memerlukan tepat 20 akun klub dan individu.');
            }

            $faker = fake();

            for ($sequence = $existingRegistrationCount + 1; $sequence <= self::TARGET_REGISTRATIONS; $sequence++) {
                $user = $accounts->random();
                $categoryRule = $faker->randomElement($event->category_rules);
                $birthYear = (int) $faker->randomElement($categoryRule['birth_years']);
                $dateOfBirth = $faker
                    ->dateTimeBetween("{$birthYear}-01-01", "{$birthYear}-12-31")
                    ->format('Y-m-d');
                $gender = $faker->randomElement($categoryRule['genders']);
                $races = $faker->randomElements(
                    $categoryRule['races'],
                    $faker->numberBetween(1, count($categoryRule['races'])),
                );
                $status = $faker->randomElement(['pending', 'pending', 'approved', 'approved', 'rejected']);
                $nik = '9999'.sprintf('%012d', $sequence);

                EventRegistration::query()->create([
                    'event_id' => $event->id,
                    'user_id' => $user->id,
                    'nik' => $nik,
                    'nik_hash' => hash_hmac('sha256', $nik, (string) config('app.key')),
                    'participant_name' => $faker->unique()->name(),
                    'nickname' => $faker->firstName(),
                    'gender' => $gender,
                    'date_of_birth' => $dateOfBirth,
                    'address' => $faker->address(),
                    'guardian_name' => $faker->name(),
                    'whatsapp' => '+62812'.sprintf('%08d', $sequence),
                    'birth_year' => $birthYear,
                    'club_name' => $user->role === 'club_manager' ? $user->organization : null,
                    'category' => $categoryRule['name'],
                    'races' => array_values($races),
                    'status' => $status,
                    'payment_status' => $this->paymentStatus($faker, $status),
                ]);
            }
        });
    }

    private function createDemoAccounts(): void
    {
        // Akun demo harus punya password yang diketahui agar bisa dipakai login.
        // Password acak membuat akun tidak bisa diakses sama sekali.
        $password = self::DEMO_PASSWORD;

        foreach (range(1, 8) as $number) {
            $organization = sprintf('Klub Inline Demo %02d', $number);

            User::query()->firstOrCreate(
                ['email' => sprintf('demo.club.%02d@inlinesia.test', $number)],
                [
                    'name' => sprintf('Klub Demo %02d', $number),
                    'password' => $password,
                    'role' => 'club_manager',
                    'phone' => fake()->phoneNumber(),
                    'organization' => $organization,
                    'address' => fake()->address(),
                    'email_verified_at' => now(),
                ],
            );
        }

        foreach (range(1, 10) as $number) {
            User::query()->firstOrCreate(
                ['email' => sprintf('demo.individual.%02d@inlinesia.test', $number)],
                [
                    'name' => fake()->name(),
                    'password' => $password,
                    'role' => 'individual',
                    'phone' => fake()->phoneNumber(),
                    'email_verified_at' => now(),
                ],
            );
        }
    }

    private function paymentStatus(Generator $faker, string $registrationStatus): string
    {
        return match ($registrationStatus) {
            'approved' => $faker->randomElement(['paid', 'paid', 'pending']),
            'pending' => $faker->randomElement(['unpaid', 'pending']),
            default => 'unpaid',
        };
    }
}
