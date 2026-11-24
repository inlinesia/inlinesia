<?php

namespace Database\Seeders;

use App\Models\Athlete;
use App\Models\Club;
use App\Models\Event;
use App\Models\EventCategoryRaceSetting;
use App\Models\EventFinanceTransaction;
use App\Models\EventRaceEntry;
use App\Models\EventRaceGroup;
use App\Models\EventRaceHeat;
use App\Models\EventRegistration;
use App\Models\EventRegistrationFinancialEntry;
use App\Models\User;
use App\Services\CategoryFeeCalculator;
use App\Services\RaceHeatBuilder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Data simulasi lengkap untuk mencoba alur pengelolaan event.
 *
 * Semua akun dan event yang dibuat memakai penanda "simulasi", sehingga bisa
 * dibersihkan lewat purge() tanpa menyentuh data asli. Kata sandi seluruh akun
 * simulasi adalah 12345678 dan email-nya sudah terverifikasi supaya langsung
 * bisa dipakai login.
 *
 * Aman dijalankan ulang: run() membersihkan simulasi lama lebih dulu.
 */
class EventSimulationSeeder extends Seeder
{
    private const EMAIL_PREFIX = 'simulasi.';

    private const EMAIL_DOMAIN = 'kilat.com';

    private const PASSWORD = '12345678';

    private const EVENT_NAME = 'SIMULASI Kediri Skatefest 2027';

    private const OWNER_EMAIL = 'simulasi.owner@kilat.com';

    /** Total pendaftaran, termasuk 8 contoh awal. */
    private const TOTAL_REGISTRATIONS = 100;

    /**
     * @var array<int, array{name: string, gender: string, birth: string, category: string, races: array<int, string>, club: ?string, paid: bool, status: string, cancellation: ?string, change: bool}>
     */
    private const ATHLETES = [
        ['name' => 'Ayu Simulasi', 'gender' => 'female', 'birth' => '2019-05-12', 'category' => 'Pemula U9', 'races' => ['Skate Cross', 'Speed Slalom'], 'club' => 'SIMULASI Skate Kediri', 'paid' => true, 'status' => 'approved', 'cancellation' => null, 'change' => false],
        ['name' => 'Bagas Simulasi', 'gender' => 'male', 'birth' => '2018-08-03', 'category' => 'Pemula U9', 'races' => ['Skate Cross'], 'club' => 'SIMULASI Skate Kediri', 'paid' => true, 'status' => 'approved', 'cancellation' => null, 'change' => false],
        ['name' => 'Cantika Simulasi', 'gender' => 'female', 'birth' => '2020-02-18', 'category' => 'Pemula U9', 'races' => ['Skate Cross', 'Speed Slalom'], 'club' => 'SIMULASI Roda Jaya', 'paid' => true, 'status' => 'approved', 'cancellation' => null, 'change' => false],
        ['name' => 'Dimas Simulasi', 'gender' => 'male', 'birth' => '2016-04-21', 'category' => 'Junior U12', 'races' => ['Skate Cross', 'Speed Slalom'], 'club' => 'SIMULASI Roda Jaya', 'paid' => true, 'status' => 'approved', 'cancellation' => null, 'change' => false],
        ['name' => 'Elsa Simulasi', 'gender' => 'female', 'birth' => '2017-11-09', 'category' => 'Junior U12', 'races' => ['Skate Cross'], 'club' => 'SIMULASI Skate Kediri', 'paid' => true, 'status' => 'approved', 'cancellation' => 'approved', 'change' => false],
        ['name' => 'Fajar Simulasi', 'gender' => 'male', 'birth' => '2015-07-30', 'category' => 'Junior U12', 'races' => ['Skate Cross', 'Speed Slalom'], 'club' => null, 'paid' => false, 'status' => 'approved', 'cancellation' => null, 'change' => false],
        ['name' => 'Galih Simulasi', 'gender' => 'male', 'birth' => '2013-03-14', 'category' => 'Remaja U15', 'races' => ['Skate Cross', 'Speed Slalom'], 'club' => 'SIMULASI Skate Kediri', 'paid' => true, 'status' => 'approved', 'cancellation' => null, 'change' => false],
        ['name' => 'Intan Simulasi', 'gender' => 'female', 'birth' => '2012-12-02', 'category' => 'Remaja U15', 'races' => ['Skate Cross'], 'club' => 'SIMULASI Roda Jaya', 'paid' => false, 'status' => 'pending', 'cancellation' => null, 'change' => false],
    ];

    public function run(): void
    {
        $this->purge();

        $owner = $this->createOwner();
        $event = $this->createEvent($owner);
        $users = $this->createAthleteAccounts();
        $registrations = $this->createRegistrations($event, $users);
        $this->createFinances($event, $registrations, $owner);
        $this->createRaceArrangement($event);

        $this->command?->info('Simulasi dibuat: '.$event->name.' (status '.$event->status.', '.count($registrations).' pendaftaran).');
        $this->command?->info('Login owner: '.self::OWNER_EMAIL.' / '.self::PASSWORD);
        $this->command?->info('Login atlet: simulasi.atlet1@kilat.com ... simulasi.atlet8@kilat.com / '.self::PASSWORD);
    }

    /**
     * Hapus seluruh data simulasi tanpa menyentuh akun atau event asli.
     */
    public function purge(): void
    {
        $userIds = User::query()
            ->where('email', 'like', self::EMAIL_PREFIX.'%@'.self::EMAIL_DOMAIN)
            ->pluck('id')
            ->all();

        $eventIds = Event::query()
            ->where('name', self::EVENT_NAME)
            ->pluck('id')
            ->all();

        DB::transaction(function () use ($userIds, $eventIds): void {
            if ($eventIds !== []) {
                EventRaceEntry::whereIn('event_id', $eventIds)->delete();
                EventRaceHeat::whereIn('event_id', $eventIds)->delete();
                EventRaceGroup::whereIn('event_id', $eventIds)->delete();
                EventCategoryRaceSetting::whereIn('event_id', $eventIds)->delete();
                EventRegistrationFinancialEntry::whereIn('event_id', $eventIds)->delete();
                EventFinanceTransaction::whereIn('event_id', $eventIds)->delete();
                EventRegistration::whereIn('event_id', $eventIds)->delete();
                Event::whereIn('id', $eventIds)->delete();
            }

            if ($userIds !== []) {
                Athlete::whereIn('owner_user_id', $userIds)->delete();
                EventRegistration::whereIn('user_id', $userIds)->delete();
                EventRegistrationFinancialEntry::whereIn('recorded_by', $userIds)->delete();
                EventFinanceTransaction::whereIn('created_by', $userIds)->delete();
                Club::whereIn('owner_user_id', $userIds)->delete();
                User::whereIn('id', $userIds)->delete();
            }

            Club::query()->where('name', 'like', 'SIMULASI %')->delete();
        });
    }

    private function createOwner(): User
    {
        $owner = new User;
        $owner->forceFill([
            'name' => 'Owner Simulasi',
            'email' => self::OWNER_EMAIL,
            'password' => Hash::make(self::PASSWORD),
            'email_verified_at' => now(),
            'role' => 'club_manager',
            'phone' => '081234560001',
            'organization' => 'SIMULASI Skate Kediri',
            'address' => 'Kediri, Jawa Timur',
        ])->save();

        return $owner;
    }

    private function createEvent(User $owner): Event
    {
        $rules = $this->categoryRules();

        return Event::query()->create([
            'submitted_by' => $owner->id,
            'name' => self::EVENT_NAME,
            'start_date' => '2027-03-20',
            'end_date' => '2027-03-20',
            'venue' => 'SLG Convention Hall',
            'venue_address' => 'Simpang Lima Gumul, Kediri',
            'organizer_whatsapp' => '+6285800006248',
            'scale' => 'Open',
            'regulations' => 'DATA SIMULASI — Wajib helm dan pelindung lutut serta siku.',
            'officials' => 'Referee teknis, MC, dan koordinator peserta.',
            'venue_conditions' => 'Permukaan beton halus.',
            'awards' => 'Medali juara 1, 2, dan 3 serta e-sertifikat peserta.',
            'categories' => array_column($rules, 'name'),
            'category_rules' => $rules,
            'races' => ['Skate Cross', 'Speed Slalom'],
            'registration_deadline' => '2027-03-05',
            'registration_info' => 'Pendaftaran melalui portal Inlinesia. Data ini hanya simulasi.',
            'fees' => 'Tarif per kategori tersedia pada halaman atur kategori.',
            'payment_bank_name' => 'BCA',
            'payment_account_number' => '0332072810',
            'payment_account_holder' => 'Rekening Simulasi',
            'force_majeure' => 'Keputusan panitia bersifat mutlak.',
            'schedule' => ['Technical meeting dan verifikasi peserta', 'Pelaksanaan lomba'],
            'status' => 'pending',
            'registration_open' => true,
            'is_free' => false,
            'participants_published' => false,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function categoryRules(): array
    {
        $rows = [
            ['name' => 'Pemula U9', 'from' => '2018-01-01', 'to' => '2020-12-31', 'fee' => 150000],
            ['name' => 'Junior U12', 'from' => '2015-01-01', 'to' => '2017-12-31', 'fee' => 175000],
            ['name' => 'Remaja U15', 'from' => '2012-01-01', 'to' => '2014-12-31', 'fee' => 200000],
        ];

        return array_map(static fn (array $row): array => [
            'name' => $row['name'],
            'base_name' => $row['name'],
            'gender_group' => 'mixed',
            'eligibility_types' => ['birth_date'],
            'birth_date_from' => $row['from'],
            'birth_date_to' => $row['to'],
            'birth_years' => [],
            'genders' => ['male', 'female'],
            'races' => ['Skate Cross', 'Speed Slalom'],
            'required_races' => 1,
            'max_registrants' => 40,
            'registration_override' => 'auto',
            'package_fee' => $row['fee'],
            'extra_race_fee' => 50000,
            'outside_race_fee' => 0,
            'discount_percent' => null,
            'discount_amount' => null,
            'outside_obligation' => false,
            'pricing_waves' => [],
        ], $rows);
    }

    /**
     * @return array<int, User>
     */
    private function createAthleteAccounts(): array
    {
        $users = [];

        foreach (array_values(self::ATHLETES) as $index => $athlete) {
            $user = new User;
            $user->forceFill([
                'name' => $athlete['name'],
                'email' => 'simulasi.atlet'.($index + 1).'@'.self::EMAIL_DOMAIN,
                'password' => Hash::make(self::PASSWORD),
                'email_verified_at' => now(),
                'role' => 'individual',
                'phone' => '08123456'.str_pad((string) ($index + 10), 4, '0', STR_PAD_LEFT),
                'address' => 'Kediri, Jawa Timur',
            ])->save();

            $users[] = $user;
        }

        return $users;
    }

    /**
     * @param  array<int, User>  $users
     * @return array<int, EventRegistration>
     */
    private function createRegistrations(Event $event, array $users): array
    {
        $calculator = app(CategoryFeeCalculator::class);
        $rules = collect($event->category_rules)->keyBy('name');
        $athletes = array_merge(self::ATHLETES, $this->generatedAthletes());
        $registrations = [];

        foreach ($athletes as $index => $athlete) {
            $nik = $index < count(self::ATHLETES)
                ? sprintf('357101%010d', $index + 1)
                : sprintf('357101%010d', 1000 + $index + 1);
            $rule = $rules->get($athlete['category']);
            $breakdown = $calculator->breakdown($rule, $athlete['races']);
            $fee = (int) $breakdown['total'];

            $registrations[] = EventRegistration::query()->create([
                'event_id' => $event->id,
                'user_id' => $users[$index % count($users)]->id,
                'participant_name' => $athlete['name'],
                'nickname' => 'Simulasi '.($index + 1),
                'nik' => $nik,
                'nik_hash' => EventRegistration::identityHash($nik),
                'nationality' => 'ID',
                'gender' => $athlete['gender'],
                'date_of_birth' => $athlete['birth'],
                'address' => 'Kediri, Jawa Timur',
                'guardian_name' => 'Wali Simulasi',
                'whatsapp' => '08123456'.str_pad((string) ($index + 10), 4, '0', STR_PAD_LEFT),
                'birth_year' => (int) substr($athlete['birth'], 0, 4),
                'club_name' => $athlete['club'],
                'category' => $athlete['category'],
                'fee_total' => $fee,
                'fee_breakdown' => $breakdown,
                'races' => $athlete['races'],
                'status' => $athlete['status'],
                'payment_status' => $athlete['paid'] ? 'paid' : 'unpaid',
                'payment_channel' => $athlete['paid'] ? 'transfer' : null,
                'confirmed_paid_at' => $athlete['paid'] ? now()->subDays(1 + ($index % 20)) : null,
                'cancellation_status' => $athlete['cancellation'],
                'cancellation_requested_at' => $athlete['cancellation'] !== null ? now()->subDays(3) : null,
                'cancellation_decided_at' => $athlete['cancellation'] !== null ? now()->subDays(2) : null,
                'refund_amount' => $athlete['cancellation'] === 'approved' ? $fee : 0,
                'refunded_at' => $athlete['cancellation'] === 'approved' ? now()->subDays(1) : null,
                'participant_change_request' => null,
                'participant_change_status' => null,
                'participant_change_requested_at' => null,
            ]);
        }

        return $registrations;
    }

    /**
     * Peserta tambahan sampai total 100 pendaftaran. Status bayar dan status
     * pendaftaran disebar supaya halaman kelola peserta punya variasinya.
     *
     * @return array<int, array{name: string, gender: string, birth: string, category: string, races: array<int, string>, club: ?string, paid: bool, status: string, cancellation: ?string, change: bool}>
     */
    private function generatedAthletes(): array
    {
        $categories = array_merge(
            array_fill(0, 31, ['Pemula U9', [2018, 2019, 2020]]),
            array_fill(0, 30, ['Junior U12', [2015, 2016, 2017]]),
            array_fill(0, 31, ['Remaja U15', [2012, 2013, 2014]]),
        );
        $clubs = ['SIMULASI Skate Kediri', 'SIMULASI Roda Jaya', null];
        $rows = [];

        foreach ($categories as $index => [$category, $years]) {
            $number = count(self::ATHLETES) + $index + 1;
            $year = $years[$index % count($years)];
            $month = 1 + ($index % 12);
            $day = 1 + ($index % 27);
            $raceSet = $index % 3;
            $pending = $number % 10 === 0;

            $rows[] = [
                'name' => 'Peserta Simulasi '.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
                'gender' => $index % 2 === 0 ? 'male' : 'female',
                'birth' => sprintf('%04d-%02d-%02d', $year, $month, $day),
                'category' => $category,
                'races' => match ($raceSet) {
                    0 => ['Skate Cross'],
                    1 => ['Speed Slalom'],
                    default => ['Skate Cross', 'Speed Slalom'],
                },
                'club' => $clubs[$index % 3],
                'paid' => ! $pending && $index % 4 !== 0,
                'status' => $pending ? 'pending' : 'approved',
                'cancellation' => $index % 25 === 7 ? 'pending' : null,
                'change' => false,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, EventRegistration>  $registrations
     */
    private function createFinances(Event $event, array $registrations, User $owner): void
    {
        foreach ($registrations as $index => $registration) {
            if ($registration->payment_status !== 'paid') {
                continue;
            }

            EventRegistrationFinancialEntry::query()->create([
                'event_id' => $event->id,
                'event_registration_id' => $registration->id,
                'type' => 'payment',
                'amount' => (int) $registration->fee_total,
                'description' => 'Pembayaran pendaftaran '.$registration->participant_name,
                'occurred_at' => now()->subDays(1 + ($index % 20)),
                'recorded_by' => $owner->id,
            ]);

            if ($registration->cancellation_status === 'approved') {
                EventRegistrationFinancialEntry::query()->create([
                    'event_id' => $event->id,
                    'event_registration_id' => $registration->id,
                    'type' => 'refund',
                    'amount' => (int) $registration->refund_amount,
                    'description' => 'Pengembalian dana pendaftaran '.$registration->participant_name,
                    'occurred_at' => now()->subDay(),
                    'recorded_by' => $owner->id,
                ]);
            }
        }

        $rows = [
            ['type' => 'income', 'category' => 'sponsor', 'description' => 'Sponsor simulasi — KILAT', 'amount' => 2500000, 'days' => 40],
            ['type' => 'expense', 'category' => 'venue', 'description' => 'Sewa venue simulasi', 'amount' => 1200000, 'days' => 12],
            ['type' => 'expense', 'category' => 'prize', 'description' => 'Medali dan piala simulasi', 'amount' => 900000, 'days' => 5],
            ['type' => 'expense', 'category' => 'operations', 'description' => 'Operasional panitia simulasi', 'amount' => 450000, 'days' => 4],
        ];

        foreach ($rows as $row) {
            EventFinanceTransaction::query()->create([
                'event_id' => $event->id,
                'type' => $row['type'],
                'category' => $row['category'],
                'description' => $row['description'],
                'amount' => $row['amount'],
                'occurred_on' => now()->subDays($row['days'])->toDateString(),
                'notes' => 'Data simulasi.',
                'created_by' => $owner->id,
            ]);
        }
    }

    private function createRaceArrangement(Event $event): void
    {
        $builder = app(RaceHeatBuilder::class);

        foreach ($event->categories as $category) {
            foreach ($event->races as $race) {
                $setting = EventCategoryRaceSetting::query()->create([
                    'event_id' => $event->id,
                    'category' => $category,
                    'race' => $race,
                    'runners_per_heat' => 6,
                    'scoring_system' => 'qualification',
                    'qualify_count' => 2,
                    'stages' => 'no_final',
                    'semi_finalists' => 0,
                    'finalists' => 8,
                    'champion_count' => 3,
                    'opponent_scope' => 'mixed',
                    'grouping_mode' => 'manual',
                    'athletes_per_group' => 30,
                    'released_at' => $category === 'Pemula U9' && $race === 'Skate Cross'
                        ? now()
                        : null,
                ]);

                if ($builder->entrantsFor($event, $category, $race)->isEmpty()) {
                    continue;
                }

                $builder->buildHeats($event, $setting);
            }
        }

        $this->fillSampleResults($event);
    }

    private function fillSampleResults(Event $event): void
    {
        $group = EventRaceGroup::query()
            ->where('event_id', $event->id)
            ->where('category', 'Pemula U9')
            ->where('race', 'Skate Cross')
            ->where('stage', 'heat')
            ->orderBy('number')
            ->first();

        if (! $group instanceof EventRaceGroup) {
            return;
        }

        $group->update(['started_at' => now()->subHours(2)]);

        foreach ($group->heats()->with('entries')->get() as $heat) {
            $place = 0;

            foreach ($heat->entries as $entry) {
                $place++;

                $entry->update([
                    'status' => EventRaceEntry::STATUS_FINISHED,
                    'place' => $place,
                    'time_ms' => 45000 + ($place * 1250),
                    'points' => $place,
                    'is_qualified' => $place <= 2,
                ]);
            }

            $heat->update([
                'status' => 'done',
                'started_at' => $group->started_at,
                'finished_at' => now()->subHours(1),
            ]);
        }
    }
}
