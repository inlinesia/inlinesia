<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\Club;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_public_dashboard_links_to_the_directory(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Daftarkan club')
            ->assertSee('Daftarkan peserta')
            ->assertSee(route('directory.clubs.create'))
            ->assertSee(route('directory.index'));
    }

    public function test_a_guest_can_browse_the_club_list_and_search_by_club_name(): void
    {
        $manager = User::factory()->create(['role' => 'club_manager']);
        $club = Club::query()->create([
            'name' => 'Skate House Kediri',
            'city' => 'Kediri',
            'contact_name' => 'Pak Budi',
            'contact_phone' => '081200000001',
            'owner_user_id' => $manager->id,
        ]);
        Athlete::query()->create([
            'name' => 'Rani Oktaviani',
            'nik' => '3515014402100001',
            'nik_hash' => hash_hmac('sha256', '3515014402100001', (string) config('app.key')),
            'club_id' => $club->id,
            'owner_user_id' => $manager->id,
        ]);

        // Daftar klub terbuka untuk tamu, daftar peserta tidak: satu nama pun
        // dari peserta tidak boleh bocor, termasuk lewat kotak cari.
        $this->get(route('directory.index'))
            ->assertOk()
            ->assertSee('Skate House Kediri')
            ->assertSee('081200000001')
            ->assertDontSee('Rani Oktaviani')
            ->assertSee('Daftar peserta hanya terlihat oleh pemilik klub');

        $this->get(route('directory.index', ['q' => 'rani']))
            ->assertOk()
            ->assertDontSee('Rani Oktaviani')
            ->assertDontSee('Pak Budi')
            ->assertSee('Tidak ada club bernama');

        $this->get(route('directory.index', ['q' => 'skate house']))
            ->assertOk()
            ->assertSee('Skate House Kediri')
            ->assertDontSee('Rani Oktaviani');

        $this->get(route('directory.index', ['q' => 'tidak-ada']))
            ->assertOk()
            ->assertSee('Tidak ada club bernama');
    }

    /**
     * Daftar peserta hanya terbuka untuk tiga pihak: pemilik klubnya, pemilik
     * akunnya, dan admin Inlinesia. Pihak lain tidak melihat satu nama pun.
     */
    public function test_the_participant_list_is_limited_to_the_club_owner_the_account_owner_and_admin(): void
    {
        $manager = User::factory()->create(['role' => 'club_manager']);
        $accountOwner = User::factory()->create(['role' => 'individual']);
        $admin = User::factory()->create(['role' => 'admin']);
        $stranger = User::factory()->create(['role' => 'individual']);

        $club = Club::query()->create([
            'name' => 'Klub Kediri',
            'owner_user_id' => $manager->id,
        ]);
        $athlete = Athlete::query()->create([
            'name' => 'Bagas Prasetyo',
            'nik' => '3515014402100009',
            'nik_hash' => hash_hmac('sha256', '3515014402100009', (string) config('app.key')),
            'club_id' => $club->id,
            'owner_user_id' => $accountOwner->id,
        ]);

        // Klub lain yang tidak dikelola siapa pun yang sudah disebut di atas.
        $otherClub = Club::query()->create(['name' => 'Klub Malang']);
        Athlete::query()->create([
            'name' => 'Athlete Lain',
            'nik' => '3515014402100011',
            'nik_hash' => hash_hmac('sha256', '3515014402100011', (string) config('app.key')),
            'club_id' => $otherClub->id,
            'owner_user_id' => $stranger->id,
        ]);

        // Tamu dan akun yang tidak berkepentingan tidak melihat daftar peserta.
        $this->get(route('directory.index'))
            ->assertOk()
            ->assertDontSee('Bagas Prasetyo')
            ->assertDontSee('Athlete Lain');

        $this->actingAs($stranger)
            ->get(route('directory.index'))
            ->assertOk()
            ->assertDontSee('Bagas Prasetyo');

        // Pemilik klub hanya melihat atlet klubnya sendiri.
        $this->actingAs($manager)
            ->get(route('directory.index'))
            ->assertOk()
            ->assertSee('Bagas Prasetyo')
            ->assertDontSee('Athlete Lain');

        // Pemilik akun tetap melihat atletnya sendiri.
        $this->actingAs($accountOwner)
            ->get(route('directory.index'))
            ->assertOk()
            ->assertSee('Bagas Prasetyo');

        // Admin melihat seluruh direktori.
        $this->actingAs($admin)
            ->get(route('directory.index'))
            ->assertOk()
            ->assertSee('Bagas Prasetyo')
            ->assertSee('Athlete Lain');

        $this->assertNotNull($athlete);
    }

    public function test_the_nik_is_hidden_from_everyone_except_the_owner_and_the_club_owner(): void
    {
        $manager = User::factory()->create([
            'role' => 'club_manager',
            'organization' => 'Klub Roda Kediri',
        ]);
        $club = Club::query()->firstOrCreate(
            ['name' => 'Klub Roda Kediri'],
            ['owner_user_id' => $manager->id],
        );
        $athlete = Athlete::query()->create([
            'name' => 'Bagas Prasetyo',
            'nik' => '3515014402100009',
            'nik_hash' => hash_hmac('sha256', '3515014402100009', (string) config('app.key')),
            'club_id' => $club->id,
            'owner_user_id' => $manager->id,
        ]);

        // Tamu tidak melihat daftar peserta sama sekali, jadi NIK yang
        // disamarkan pun tidak ikut tampil.
        $this->get(route('directory.index'))
            ->assertOk()
            ->assertDontSee('3515014402100009')
            ->assertDontSee($athlete->maskedNik());

        $stranger = User::factory()->create(['role' => 'individual']);

        $this->actingAs($stranger)
            ->get(route('directory.index'))
            ->assertOk()
            ->assertDontSee('3515014402100009')
            ->assertDontSee($athlete->maskedNik());

        // Pemilik akunnya dan pemilik klubnya boleh melihat NIK utuh.
        $this->actingAs($manager)
            ->get(route('directory.index'))
            ->assertOk()
            ->assertSee('3515014402100009');
    }

    public function test_an_event_registration_is_mirrored_into_the_directory(): void
    {
        $event = $this->createEvent();
        $participant = User::factory()->create(['role' => 'individual']);
        $nik = $this->nikFor('male', '2014-01-01', 7);

        EventRegistration::create([
            'event_id' => $event->id,
            'user_id' => $participant->id,
            'participant_name' => 'Atlet Cermin',
            'nik' => $nik,
            'nik_hash' => hash_hmac('sha256', $nik, (string) config('app.key')),
            'gender' => 'male',
            'date_of_birth' => '2014-01-01',
            'address' => 'Tulungagung',
            'guardian_name' => 'Wali Atlet',
            'whatsapp' => '081234567893',
            'birth_year' => 2014,
            'club_name' => 'Klub Papan',
            'category' => 'Junior Putra',
            'races' => ['Classic Slalom'],
            'status' => 'pending',
        ]);

        $athlete = Athlete::query()->where('name', 'Atlet Cermin')->first();

        $this->assertNotNull($athlete);
        $this->assertSame($participant->id, $athlete->owner_user_id);
        $this->assertSame($nik, $athlete->nik);
        $this->assertSame('Klub Papan', $athlete->club?->name);

        // Pendaftaran lomba bukan satu-satunya sumber, jadi halaman langsung
        // menampilkannya tanpa perlu sinkronisasi manual. Yang melihatnya
        // adalah pemilik akunnya sendiri, bukan pengunjung biasa.
        $this->get(route('directory.index'))
            ->assertOk()
            ->assertSee('Klub Papan')
            ->assertDontSee('Atlet Cermin');

        $this->actingAs($participant)
            ->get(route('directory.index'))
            ->assertOk()
            ->assertSee('Atlet Cermin')
            ->assertSee('Klub Papan');
    }

    public function test_a_foreign_national_registration_is_mirrored_with_its_passport(): void
    {
        $event = $this->createEvent();
        $participant = User::factory()->create(['role' => 'individual']);
        $passport = 'C1234567';

        EventRegistration::create([
            'event_id' => $event->id,
            'user_id' => $participant->id,
            'participant_name' => 'Atlet Asing',
            'passport_number' => $passport,
            'passport_hash' => hash_hmac('sha256', $passport, (string) config('app.key')),
            'nationality' => 'Singapura',
            'gender' => 'female',
            'date_of_birth' => '2013-05-04',
            'address' => 'Surabaya',
            'whatsapp' => '081234567894',
            'birth_year' => 2013,
            'club_name' => '',
            'category' => 'Junior Putri',
            'races' => ['Classic Slalom'],
            'status' => 'pending',
        ]);

        $athlete = Athlete::query()->where('name', 'Atlet Asing')->first();

        $this->assertNotNull($athlete);
        $this->assertSame($passport, $athlete->nik);
        $this->assertNull($athlete->club_id);
    }

    public function test_a_club_can_be_registered_without_any_event_and_shows_its_contact(): void
    {
        $this->post(route('directory.clubs.store'), [
            'name' => 'Skate Community Blitar',
            'city' => 'Blitar',
            'contact_name' => 'Coach Anwar',
            'contact_phone' => '081399999999',
            'contact_email' => 'coach@blitarskate.id',
        ])->assertRedirect(route('directory.index'));

        $this->assertDatabaseHas('clubs', [
            'name' => 'Skate Community Blitar',
            'contact_phone' => '081399999999',
        ]);

        $this->get(route('directory.index'))
            ->assertOk()
            ->assertSee('Skate Community Blitar')
            ->assertSee('Coach Anwar')
            ->assertSee('081399999999');
    }

    public function test_the_same_club_cannot_be_registered_twice(): void
    {
        Club::query()->create([
            'name' => 'Skate House',
            'contact_phone' => '081200000002',
        ]);

        $this->post(route('directory.clubs.store'), [
            'name' => 'skate  house',
            'contact_name' => 'Pemilik',
            'contact_phone' => '081200000003',
        ])->assertSessionHasErrors('name');

        $this->assertSame(1, Club::query()->count());
    }

    public function test_an_athlete_requires_a_signed_in_account(): void
    {
        $this->get(route('directory.athletes.create'))->assertRedirect(route('login'));

        $this->post(route('directory.athletes.store'), $this->athletePayload())
            ->assertRedirect(route('login'));

        $this->assertSame(0, Athlete::query()->count());
    }

    public function test_an_athlete_can_be_registered_without_any_event(): void
    {
        $user = User::factory()->create(['role' => 'individual']);
        $nik = $this->nikFor('male', '2012-03-04', 21);

        $this->actingAs($user)
            ->post(route('directory.athletes.store'), $this->athletePayload([
                'nik' => $nik,
                'name' => 'Atlet Tanpa Lomba',
                'phone' => '081234567895',
            ]))
            ->assertRedirect(route('directory.index'));

        $athlete = Athlete::query()->where('name', 'Atlet Tanpa Lomba')->first();

        $this->assertNotNull($athlete);
        $this->assertSame($user->id, $athlete->owner_user_id);
        $this->assertSame($nik, $athlete->nik);
        $this->assertNull($athlete->club_id);

        $this->actingAs($user)
            ->get(route('directory.index'))
            ->assertOk()
            ->assertSee('Atlet Tanpa Lomba');
    }

    public function test_a_foreign_national_can_be_registered_with_a_passport_only(): void
    {
        $user = User::factory()->create(['role' => 'individual']);
        $passport = 'X9876543';

        $this->actingAs($user)
            ->post(route('directory.athletes.store'), $this->athletePayload([
                'nik' => null,
                'passport_number' => $passport,
                'nationality' => 'Malaysia',
                'gender' => 'female',
                'date_of_birth' => '2011-07-08',
                'name' => 'Atlet Paspor',
            ]))
            ->assertRedirect(route('directory.index'));

        $athlete = Athlete::query()->where('name', 'Atlet Paspor')->first();

        $this->assertNotNull($athlete);
        $this->assertSame($passport, $athlete->nik);
    }

    public function test_one_account_cannot_record_the_same_athlete_twice(): void
    {
        $user = User::factory()->create(['role' => 'individual']);
        $nik = $this->nikFor('male', '2012-03-04', 33);

        $payload = $this->athletePayload(['nik' => $nik]);

        $this->actingAs($user)->post(route('directory.athletes.store'), $payload)
            ->assertRedirect(route('directory.index'));

        $this->actingAs($user)->post(route('directory.athletes.store'), $payload)
            ->assertSessionHasErrors('nik');

        $this->assertSame(1, Athlete::query()->count());
    }

    public function test_an_identity_without_a_passport_and_a_nationality_is_rejected(): void
    {
        $user = User::factory()->create(['role' => 'individual']);

        $this->actingAs($user)
            ->post(route('directory.athletes.store'), $this->athletePayload([
                'nik' => null,
                'passport_number' => 'P1111111',
                'nationality' => null,
            ]))
            ->assertSessionHasErrors('nationality');

        $this->assertSame(0, Athlete::query()->count());
    }

    public function test_a_mirrored_club_never_publishes_the_owners_private_contact(): void
    {
        $manager = User::factory()->create([
            'role' => 'club_manager',
            'organization' => 'Klub Tanpa Izin',
            'phone' => '081200000777',
        ]);
        $event = $this->createEvent();
        $nik = $this->nikFor('male', '2014-01-01', 55);

        EventRegistration::create([
            'event_id' => $event->id,
            'user_id' => $manager->id,
            'participant_name' => 'Atlet Klub',
            'nik' => $nik,
            'nik_hash' => hash_hmac('sha256', $nik, (string) config('app.key')),
            'gender' => 'male',
            'date_of_birth' => '2014-01-01',
            'address' => 'Tulungagung',
            'whatsapp' => '081234567897',
            'birth_year' => 2014,
            'club_name' => 'Klub Tanpa Izin',
            'category' => 'Junior Putra',
            'races' => ['Classic Slalom'],
            'status' => 'pending',
        ]);

        $club = Club::query()->where('name', 'Klub Tanpa Izin')->first();

        $this->assertNotNull($club);
        $this->assertSame($manager->id, $club->owner_user_id);

        // Nomor dan email pribadi tidak ikut tersalin ke halaman publik.
        $this->assertNull($club->contact_phone);
        $this->assertNull($club->contact_email);
        $this->assertNull($club->contact_name);

        // Klubnya tetap muncul di direktori, tetapi nomor dan email pribadi
        // pemiliknya tidak ikut terpublikasi.
        $this->get(route('directory.index'))
            ->assertOk()
            ->assertSee('Klub Tanpa Izin')
            ->assertDontSee('081200000777')
            ->assertDontSee($manager->email);
    }

    public function test_only_the_club_owner_sees_the_manage_link(): void
    {
        $manager = User::factory()->create(['role' => 'club_manager', 'organization' => 'Klub Dikuasai']);
        $club = Club::query()->create([
            'name' => 'Klub Dikuasai',
            'owner_user_id' => $manager->id,
        ]);

        $this->get(route('directory.index'))
            ->assertOk()
            ->assertDontSee('Ubah data klub');

        $this->actingAs($manager)
            ->get(route('directory.index'))
            ->assertOk()
            ->assertSee('Ubah data klub')
            ->assertSee(route('directory.clubs.edit', $club));
    }

    public function test_only_the_club_owner_can_change_the_club(): void
    {
        $manager = User::factory()->create(['role' => 'club_manager', 'organization' => 'Klub Dikuasai']);
        $club = Club::query()->create([
            'name' => 'Klub Dikuasai',
            'owner_user_id' => $manager->id,
        ]);

        $payload = [
            'name' => 'Klub Dikuasai',
            'city' => 'Kediri',
            'contact_name' => 'Coach Baru',
            'contact_phone' => '081200000888',
        ];

        $stranger = User::factory()->create(['role' => 'club_manager']);

        $this->actingAs($stranger)
            ->get(route('directory.clubs.edit', $club))
            ->assertForbidden();

        $this->actingAs($stranger)
            ->patch(route('directory.clubs.update', $club), $payload)
            ->assertForbidden();

        $this->assertNull($club->fresh()->contact_phone);

        $this->actingAs($manager)
            ->get(route('directory.clubs.edit', $club))
            ->assertOk()
            ->assertSee('Klub Dikuasai');

        $this->actingAs($manager)
            ->patch(route('directory.clubs.update', $club), $payload)
            ->assertRedirect(route('directory.index'));

        $this->assertSame('081200000888', $club->fresh()->contact_phone);
    }

    public function test_a_club_owner_may_publish_no_contact_at_all(): void
    {
        $manager = User::factory()->create(['role' => 'club_manager', 'organization' => 'Klub Diam']);
        $club = Club::query()->create([
            'name' => 'Klub Diam',
            'owner_user_id' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->patch(route('directory.clubs.update', $club), ['name' => 'Klub Diam'])
            ->assertRedirect(route('directory.index'));

        $this->assertNull($club->fresh()->contact_phone);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function athletePayload(array $overrides = []): array
    {
        return [
            'nik' => $this->nikFor('male', '2012-03-04', 1),
            'name' => 'Atlet Direktori',
            'gender' => 'male',
            'date_of_birth' => '2012-03-04',
            'phone' => '081234567896',
            'club_id' => null,
            ...$overrides,
        ];
    }

    private function createEvent(): Event
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $startDate = today()->addMonth();

        return Event::create([
            'submitted_by' => $owner->id,
            'name' => 'Kejuaraan Direktori',
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $startDate->copy()->addDay()->format('Y-m-d'),
            'venue' => 'GOR Tulungagung',
            'venue_address' => 'Tulungagung',
            'organizer_whatsapp' => '081111111111',
            'scale' => 'Kabupaten',
            'categories' => ['Junior Putra', 'Junior Putri'],
            'category_rules' => [
                [
                    'name' => 'Junior Putra',
                    'eligibility_type' => 'birth_date',
                    'birth_date_from' => '2010-01-01',
                    'birth_date_to' => '2020-12-31',
                    'genders' => ['male'],
                    'races' => ['Classic Slalom'],
                ],
                [
                    'name' => 'Junior Putri',
                    'eligibility_type' => 'birth_date',
                    'birth_date_from' => '2010-01-01',
                    'birth_date_to' => '2020-12-31',
                    'genders' => ['female'],
                    'races' => ['Classic Slalom'],
                ],
            ],
            'races' => ['Classic Slalom'],
            'registration_deadline' => $startDate->copy()->subDay()->format('Y-m-d'),
            'schedule' => ['Pembukaan', 'Final'],
            'status' => 'approved',
            'registration_open' => true,
        ]);
    }
}
