<?php

namespace Database\Seeders;

use App\Models\Club;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\EventRegistrationFinancialEntry;
use App\Models\User;
use App\Services\CategoryFeeCalculator;
use App\Services\NikParser;
use App\Services\ResolveEventRegistrationCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Membuat data peserta yang banyak untuk satu event, supaya daftar peserta,
 * keuangan, dan grafik dasbor bisa dicoba dengan volume yang masuk akal.
 *
 * Semua akun yang dibuat memakai pola demo.{nama}@kilat.com dan kata sandi
 * 12345678, sehingga mudah dihapus lagi: cukup hapus user yang email-nya
 * diawali "demo." karena pendaftaran dan catatan keuangannya ikut terhapus
 * mengikuti aturan cascade.
 */
class DemoEventRegistrationSeeder extends Seeder
{
    private const TOTAL_PARTICIPANTS = 300;

    private const CLUB_COUNT = 15;

    /** Porsi peserta yang mendaftar sendiri lewat akun pribadi. */
    private const INDEPENDENT_SHARE = 0.10;

    private const PASSWORD = '12345678';

    private const EMAIL_PREFIX = 'demo.';

    private const EMAIL_DOMAIN = 'kilat.com';

    /**
     * Usia paling tua yang boleh dibuat. Kategori Master terbuka sampai usia
     * 100, tetapi untuk dataset ini usia tertua dibatasi sampai 45.
     */
    private const MAX_SIMULATED_AGE = 45;

    /**
     * Kode kecamatan yang dipakai menyusun NIK. Semuanya kode wilayah nyata
     * Jawa Timur supaya NIK dapat dibaca dan alamatnya tetap masuk akal.
     *
     * @var array<int, string>
     */
    private const DISTRICT_CODES = [
        '350601', '350602', '350603', '350604', '350605', '350606',
        '357101', '357102', '357103',
        '350401', '350402', '350403', '350404', '350405', '350406',
        '350701', '350702', '350703', '350704', '350705', '350706',
        '357801', '357802', '357803', '357804', '357805', '357806',
        '350301', '350302', '350303', '350304', '350305', '350306',
    ];

    /** @var array<int, string> */
    private const CLUB_NAMES = [
        'KILAT Kediri',
        'Skate House Kediri',
        'Skate Point Kediri',
        'Roller Mania Tulungagung',
        'Roller Kids Trenggalek',
        'Skate Brotherhood Malang',
        'Derby Crew Malang',
        'VMX Warrior Surabaya',
        'Racer Gym Blitar',
        'Speed Roll Jombang',
        'Nitro Skate Team',
        'Freestyle Society',
        'Battle Slide Collective',
        'Rollers Muda Jatim',
        'Skate Line Nganjuk',
    ];

    /** @var array<int, string> */
    private const MALE_FIRST_NAMES = [
        'Rizky', 'Bagas', 'Dimas', 'Fajar', 'Yoga', 'Adi', 'Bayu', 'Galih',
        'Ilham', 'Khoirul', 'Reza', 'Satria', 'Taufik', 'Wawan', 'Lucky',
        'Randy', 'Hendry', 'Surya', 'Farhan', 'Bima', 'Agus', 'Dwi', 'Eko',
        'Hafiz', 'Irfan', 'Joko', 'Nanda', 'Oki', 'Putra', 'Rangga',
    ];

    /** @var array<int, string> */
    private const FEMALE_FIRST_NAMES = [
        'Ayu', 'Putri', 'Dela', 'Nadia', 'Rina', 'Sari', 'Wulan', 'Intan',
        'Dinda', 'Cantika', 'Kirana', 'Nabila', 'Sekar', 'Rahma', 'Lala',
        'Vina', 'Mega', 'Yuni', 'Fika', 'Nia', 'Rani', 'Tiara', 'Widya',
        'Zahra', 'Anggun', 'Bunga', 'Citra', 'Dewi', 'Elsa', 'Fitri',
    ];

    /**
     * Nama belakang dipisah jenis kelamin supaya combinationsinya tidak
     * menghasilkan nama yang tidak lazim, seperti "Taufik Ningrum".
     *
     * @var array<int, string>
     */
    private const MALE_LAST_NAMES = [
        'Prasetyo', 'Nugroho', 'Wibowo', 'Santoso', 'Kurniawan', 'Hidayat',
        'Firmansyah', 'Setiawan', 'Kusuma', 'Maulana', 'Ramadhani', 'Saputra',
        'Wicaksono', 'Prasetya', 'Ananta',
    ];

    /** @var array<int, string> */
    private const FEMALE_LAST_NAMES = [
        'Puspita', 'Safitri', 'Lestari', 'Wulandari', 'Permata', 'Rahmawati',
        'Handayani', 'Ningrum', 'Aprilia', 'Ananda', 'Maharani', 'Larasati',
        'Pertiwi', 'Sari', 'Utami',
    ];

    public function run(): void
    {
        $event = Event::query()->find(8);

        if (! $event instanceof Event) {
            $this->command?->error('Event dengan ID 8 tidak ditemukan. Seeder ini khusus untuk KILAT ANNERSARY.');

            return;
        }

        if (empty($event->category_rules)) {
            $this->command?->error('Event belum punya aturan kategori, sehingga peserta tidak dapat ditempatkan.');

            return;
        }

        $removed = $this->purgePreviousDemoData();
        if ($removed > 0) {
            $this->command?->info("{$removed} data demo sebelumnya dibersihkan.");
        }

        $organizer = $event->submitter ?? User::query()->where('role', 'admin')->first();

        DB::transaction(function () use ($event, $organizer): void {
            $clubOwners = $this->createClubAccounts($event);

            $this->createClubParticipants($event, $organizer, $clubOwners);
            $this->createIndependentParticipants($event, $organizer);
            $this->publishClubContacts($clubOwners);
        });
    }

    /**
     * Mengisi kontak klub demo supaya direktori terlihat lengkap.
     *
     * Pada pendaftaran biasa, kontak tidak pernah disalin otomatis dari akun
     * pengelola karena nomor pribadi tidak boleh dipublikasikan tanpa
     * persetujuan. Akun demo berbeda: seluruh isinya karangan, sehingga
     * dipublikasikan secara eksplisit di sini.
     *
     * @param  Collection<int, array{user: User, club_name: string}>  $clubOwners
     */
    private function publishClubContacts(Collection $clubOwners): void
    {
        foreach ($clubOwners as $owner) {
            /** @var User $user */
            $user = $owner['user'];

            Club::query()->updateOrCreate(
                ['name' => $owner['club_name']],
                [
                    'city' => 'Jawa Timur',
                    'contact_name' => 'Coach '.$this->fullName($user->id % 2 === 0 ? 'male' : 'female'),
                    'contact_phone' => $user->phone,
                    'owner_user_id' => $user->id,
                ],
            );
        }
    }

    /**
     * Menghapus sisa seeder lama supaya bisa dijalankan berulang tanpa menambah
     * duplikat. Pendaftaran dan catatan keuangan ikut terhapus lewat cascade.
     */
    private function purgePreviousDemoData(): int
    {
        $demoUserIds = User::query()
            ->where('email', 'like', self::EMAIL_PREFIX.'%@'.self::EMAIL_DOMAIN)
            ->pluck('id');

        if ($demoUserIds->isEmpty()) {
            return 0;
        }

        $removed = EventRegistration::query()->whereIn('user_id', $demoUserIds)->count();

        EventRegistrationFinancialEntry::query()->whereIn('recorded_by', $demoUserIds)->delete();
        User::query()->whereIn('id', $demoUserIds)->delete();

        return $removed;
    }

    /**
     * Membuat akun pengelola klub, satu akun untuk setiap klub.
     *
     * @return Collection<int, array{user: User, club_name: string}>
     */
    private function createClubAccounts(Event $event): Collection
    {
        return collect(self::CLUB_NAMES)
            ->take(self::CLUB_COUNT)
            ->map(function (string $clubName): array {
                $user = User::query()->create([
                    'name' => $clubName,
                    'email' => $this->emailFor($clubName),
                    'password' => Hash::make(self::PASSWORD),
                    // Akun demo langsung ditandai terverifikasi supaya tidak
                    // redirected ke halaman verifikasi email saat memakai
                    // halaman yang dijaga middleware event-access.
                    'email_verified_at' => now(),
                    'role' => 'club_manager',
                    'phone' => $this->whatsappNumber(),
                    'organization' => $clubName,
                    'address' => 'Jawa Timur',
                ]);

                return ['user' => $user, 'club_name' => $clubName];
            });
    }

    /**
     * Mengisi 90% kuota peserta lewat akun klub. Jumlahnya dibagi rata supaya
     * tiap klub punya tim yang mirip.
     *
     * @param  Collection<int, array{user: User, club_name: string}>  $clubOwners
     */
    private function createClubParticipants(Event $event, ?User $organizer, Collection $clubOwners): void
    {
        $clubParticipants = self::TOTAL_PARTICIPANTS - $this->independentCount();
        $perClub = intdiv($clubParticipants, max(1, $clubOwners->count()));
        $remainder = $clubParticipants % max(1, $clubOwners->count());

        foreach ($clubOwners->values() as $index => $club) {
            $quota = $perClub + ($index < $remainder ? 1 : 0);

            for ($i = 0; $i < $quota; $i++) {
                $this->registerParticipant($event, $organizer, $club['user'], [
                    ...$this->participantIdentity($event),
                    'club_name' => $club['club_name'],
                ]);
            }
        }
    }

    /**
     * Mengisi 10% kuota peserta lewat akun pribadi, satu akun per peserta
     * supaya tercermin pendaftaran mandiri.
     */
    private function createIndependentParticipants(Event $event, ?User $organizer): void
    {
        for ($i = 0; $i < $this->independentCount(); $i++) {
            $identity = $this->participantIdentity($event);

            $user = User::query()->create([
                'name' => $identity['participant_name'],
                'email' => $this->uniqueEmail($identity['participant_name']),
                'password' => Hash::make(self::PASSWORD),
                'email_verified_at' => now(),
                'role' => 'individual',
                'phone' => $identity['whatsapp'],
                'address' => $identity['address'],
            ]);

            $this->registerParticipant($event, $organizer, $user, $identity);
        }
    }

    private function independentCount(): int
    {
        return (int) round(self::TOTAL_PARTICIPANTS * self::INDEPENDENT_SHARE);
    }

    /**
     * Menyimpan satu pendaftaran lengkap: sudah lunas, sudah diterima, dan
     * sudah punya catatan keuangan seperti hasil konfirmasi pembayaran sungguhan.
     *
     * @param  array<string, mixed>  $identity
     */
    private function registerParticipant(Event $event, ?User $organizer, User $user, array $identity): void
    {
        $identity = $this->withUniqueNik($event, $user, $identity);
        $registeredAt = $this->registrationTimestamp();

        $registration = new EventRegistration([
            ...$identity,
            'event_id' => $event->id,
            'user_id' => $user->id,
            'status' => 'approved',
            'payment_status' => 'paid',
            'payment_channel' => 'whatsapp',
            'confirmed_paid_at' => $registeredAt->copy()->addMinutes(random_int(10, 2880)),
        ]);

        // Kategori sudah ditetapkan, jadi calculator memakai tanggal
        // pendaftaran yang baru saja dibuat untuk memilih gelombang tarif.
        $registration->created_at = $registeredAt;
        $registration->updated_at = $registeredAt;
        $registration->save();

        app(CategoryFeeCalculator::class)->sync($event, $registration);
        $registration->save();

        if ($registration->fee_total !== null && $registration->fee_total > 0) {
            EventRegistrationFinancialEntry::query()->create([
                'event_id' => $event->id,
                'event_registration_id' => $registration->id,
                'type' => 'payment',
                'amount' => $registration->fee_total,
                'description' => 'Pembayaran pendaftaran',
                'occurred_at' => $registration->confirmed_paid_at,
                'recorded_by' => $organizer?->id,
            ]);
        }

        $registration->updateQuietly([
            'updated_at' => $registration->confirmed_paid_at,
        ]);
    }

    /**
     * Menyusun data diri satu peserta: nama, gender, tanggal lahir, NIK, dan
     * alamat yang saling cocok dengan aturan NIK.
     *
     * @return array<string, mixed>
     */
    private function participantIdentity(Event $event): array
    {
        // Kategori "di luar kewajiban" sengaja dihindari supaya peserta demo
        // punya harga paket yang wajar, bukan cuma biaya mata lomba tambahan.
        $rule = collect($event->category_rules)
            ->filter(fn (array $candidate): bool => empty($candidate['outside_obligation']))
            ->values()
            ->random();

        $gender = $rule['genders'][0] ?? 'male';
        $dateOfBirth = $this->dateOfBirthFor($event, (int) $rule['minimum_age'], $rule['maximum_age'] ?? null);
        $races = $this->racesFor($event, $rule);

        // Kategori akhir tetap ditentukan resolver, sama seperti saat peserta
        // mendaftar sungguhan, supaya data demo memakai jalur perhitungan yang
        // sama dengan aplikasi.
        $match = app(ResolveEventRegistrationCategory::class)->bestMatch(
            $event,
            $dateOfBirth->toDateString(),
            $gender,
            $races,
        );

        $name = $this->fullName($gender);
        $district = self::DISTRICT_CODES[random_int(0, count(self::DISTRICT_CODES) - 1)];
        $dateOfBirthString = $dateOfBirth->toDateString();
        $nik = $this->makeNik($district, $dateOfBirth, $gender, random_int(1, 9999));

        $parsed = (new NikParser)->parse($nik);

        return [
            'participant_name' => $name,
            'nickname' => random_int(0, 1) === 1 ? Str::random(3, 6) : null,
            'nik' => $nik,
            'gender' => $gender,
            'date_of_birth' => $dateOfBirthString,
            'birth_year' => $dateOfBirth->year,
            'address' => $this->addressFrom($parsed),
            'guardian_name' => $this->fullName(random_int(0, 1) === 1 ? 'female' : 'male'),
            'whatsapp' => $this->whatsappNumber(),
            'races' => $races,
            'category' => $match['rule']['name'] ?? $rule['name'],
        ];
    }

    /**
     * Mengganti NIK bila nomor yang dibuat sudah dipakai atlet lain pada akun
     * dan event yang sama.
     *
     * Aplikasi menolak NIK ganda pada satu akun, jadi seeder wajib memastikan
     * NIK yang dibuat benar-benar baru. Identitas lainnya tidak diubah karena
     * NIK tetap harus cocok dengan gender dan tanggal lahir yang sudah dipilih.
     *
     * @param  array<string, mixed>  $identity
     * @return array<string, mixed>
     */
    private function withUniqueNik(Event $event, User $user, array $identity): array
    {
        $isTaken = fn (string $nik): bool => EventRegistration::query()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->where('nik_hash', EventRegistration::identityHash($nik))
            ->exists();

        if (! $isTaken($identity['nik'])) {
            return [...$identity, 'nik_hash' => EventRegistration::identityHash($identity['nik'])];
        }

        $dateOfBirth = Carbon::parse($identity['date_of_birth']);
        $prefix = substr($identity['nik'], 0, 12);

        for ($serial = 1; $serial <= 9999; $serial++) {
            $nik = $prefix.str_pad((string) $serial, 4, '0', STR_PAD_LEFT);

            if (! $isTaken($nik)) {
                return [...$identity, 'nik' => $nik, 'nik_hash' => EventRegistration::identityHash($nik)];
            }
        }

        throw new RuntimeException('Tidak ditemukan NIK kosong untuk '.$user->email.' pada event '.$event->id.'.');
    }

    /**
     * Memilih mata lomba: sejumlah mata lomba milik kategori, dan sesekali
     * satu mata lomba di luar kategori supaya fee di luar paket ikut terlihat.
     *
     * @param  array<string, mixed>  $rule
     * @return array<int, string>
     */
    private function racesFor(Event $event, array $rule): array
    {
        $categoryRaces = array_values(array_map('strval', $rule['races'] ?? []));
        $required = max(1, (int) ($rule['required_races'] ?? 1));
        $picked = array_slice($categoryRaces, 0, min($required, count($categoryRaces)));

        if (random_int(1, 100) > 20) {
            return $picked;
        }

        $outside = array_values(array_diff($event->races, $categoryRaces));

        if ($outside === []) {
            return $picked;
        }

        return [...$picked, $outside[random_int(0, count($outside) - 1)]];
    }

    /**
     * Tanggal lahir yang usianya pada hari lomba berada di dalam rentang
     * kategori yang dituju.
     */
    private function dateOfBirthFor(Event $event, int $minimumAge, ?int $maximumAge): Carbon
    {
        $maximum = min($maximumAge ?? self::MAX_SIMULATED_AGE, self::MAX_SIMULATED_AGE);
        $maximum = max($maximum, $minimumAge);
        $age = random_int($minimumAge, $maximum);

        return $event->start_date
            ->copy()
            ->subYears($age)
            ->subDays(random_int(0, 364));
    }

    /**
     * Menyusun NIK 16 digit dari kode kecamatan, tanggal lahir, gender, dan
     * nomor urut. Tanggal lahir perempuan selalu ditambah 40 sesuai aturan NIK.
     */
    private function makeNik(string $districtCode, Carbon $dateOfBirth, string $gender, int $serial): string
    {
        $day = $dateOfBirth->day + ($gender === 'female' ? 40 : 0);

        return $districtCode
            .str_pad((string) $day, 2, '0', STR_PAD_LEFT)
            .str_pad((string) $dateOfBirth->month, 2, '0', STR_PAD_LEFT)
            .str_pad((string) ($dateOfBirth->year % 100), 2, '0', STR_PAD_LEFT)
            .str_pad((string) $serial, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function addressFrom(array $parsed): string
    {
        $region = (new NikParser)->suggestAddress($parsed) ?? 'Jawa Timur';

        return $region.', RT '.str_pad((string) random_int(1, 9), 2, '0', STR_PAD_LEFT)
            .'/RW '.str_pad((string) random_int(1, 9), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Tanggal pendaftaran tersebar pada periode Early bird milik event, supaya
     * tarif yang tersimpan di setiap peserta benar-benar tarif gelombang itu.
     */
    private function registrationTimestamp(): Carbon
    {
        $earliest = Carbon::parse('2026-10-01')->startOfDay();
        $latest = now();

        return $earliest->copy()->addSeconds(random_int(0, max(1, $earliest->diffInSeconds($latest))));
    }

    /**
     * Nama lengkap peserta. Nama depan dan nama belakang diambil dari daftar
     * yang sesuai jenis kelaminnya supaya hasilnya tetap lazim dibaca.
     */
    private function fullName(string $gender): string
    {
        $isFemale = $gender === 'female';
        $firstNames = $isFemale ? self::FEMALE_FIRST_NAMES : self::MALE_FIRST_NAMES;
        $lastNames = $isFemale ? self::FEMALE_LAST_NAMES : self::MALE_LAST_NAMES;

        return $firstNames[random_int(0, count($firstNames) - 1)].' '
            .$lastNames[random_int(0, count($lastNames) - 1)];
    }

    private function whatsappNumber(): string
    {
        return '08'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
    }

    private function emailFor(string $source): string
    {
        return self::EMAIL_PREFIX.Str::slug($source).'@'.self::EMAIL_DOMAIN;
    }

    /**
     * Email akun pribadi kadang bentrok karena dua orang bisa bernama sama,
     * jadi diberi akhiran angka bila perlu.
     */
    private function uniqueEmail(string $name): string
    {
        $base = self::EMAIL_PREFIX.Str::slug($name).'@'.self::EMAIL_DOMAIN;

        if (! User::query()->where('email', $base)->exists()) {
            return $base;
        }

        $suffix = 2;
        while (User::query()->where('email', $this->emailFor($name.$suffix))->exists()) {
            $suffix++;
        }

        return $this->emailFor($name.$suffix);
    }
}
