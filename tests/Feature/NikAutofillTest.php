<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NikAutofillTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_read_a_nik_into_region_gender_and_birth_date(): void
    {
        // 3504 = Tulungagung, 12 = kecamatan, 120399 = 12-03-1999 laki-laki.
        $this->actingAs(User::factory()->create())
            ->postJson(route('nik.lookup'), ['nik' => '3504121203990001'])
            ->assertOk()
            ->assertJson([
                'valid' => true,
                'gender' => 'male',
                'date_of_birth' => '1999-03-12',
                'province' => 'Jawa Timur',
            ])
            ->assertJsonPath('regency', fn ($value) => str_contains((string) $value, 'Tulungagung'))
            ->assertJsonPath('address_suggestion', fn ($value) => str_contains((string) $value, 'Tulungagung'));
    }

    public function test_nik_lookup_reads_female_nik_from_a_day_above_forty(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('nik.lookup'), ['nik' => '3504125203990001'])
            ->assertOk()
            ->assertJson([
                'valid' => true,
                'gender' => 'female',
                'date_of_birth' => '1999-03-12',
            ]);
    }

    public function test_nik_lookup_rejects_unknown_region_code(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('nik.lookup'), ['nik' => '9904121203990001'])
            ->assertStatus(422)
            ->assertJson([
                'valid' => false,
                'message' => 'Kode provinsi pada NIK tidak dikenali.',
            ]);
    }

    public function test_nik_lookup_requires_sixteen_digits(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('nik.lookup'), ['nik' => '35041212039900'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('nik');
    }

    public function test_nik_lookup_requires_an_account(): void
    {
        $this->postJson(route('nik.lookup'), ['nik' => '3504121203990001'])
            ->assertUnauthorized();
    }

    public function test_registration_page_exposes_the_nik_feedback_container_for_the_region_notice(): void
    {
        $event = $this->createApprovedEvent();

        $this->actingAs(User::factory()->create(['role' => 'individual']))
            ->get(route('events.registration.create', $event))
            ->assertOk()
            ->assertSee('data-nik-feedback', false)
            ->assertSee('data-nik-autofill-endpoint', false);
    }

    public function test_registration_rejects_nik_that_does_not_match_the_given_gender(): void
    {
        $event = $this->createApprovedEvent();
        $participant = User::factory()->create(['role' => 'individual']);

        // NIK ini terbaca laki-laki, tetapi peserta memilih perempuan.
        $this->actingAs($participant)
            ->from(route('events.registration.create', $event))
            ->post(route('events.registration.store', $event), [
                'nik' => '3504121203990001',
                'participant_name' => 'Atlet Contoh',
                'gender' => 'female',
                'date_of_birth' => '1999-03-12',
                'address' => 'Tulungagung',
                'guardian_name' => 'Wali Atlet',
                'whatsapp' => '081234567890',
                'races' => ['Classic Slalom'],
            ])
            ->assertRedirect(route('events.registration.create', $event))
            ->assertSessionHasErrors('nik');

        $this->assertDatabaseCount('event_registrations', 0);
    }

    public function test_registration_rejects_nik_that_does_not_match_the_given_birth_date(): void
    {
        $event = $this->createApprovedEvent();
        $participant = User::factory()->create(['role' => 'individual']);

        // NIK ini terbaca lahir 12-03-1999, tetapi tanggal yang diisi berbeda.
        $this->actingAs($participant)
            ->from(route('events.registration.create', $event))
            ->post(route('events.registration.store', $event), [
                'nik' => '3504121203990001',
                'participant_name' => 'Atlet Contoh',
                'gender' => 'male',
                'date_of_birth' => '1999-03-13',
                'address' => 'Tulungagung',
                'guardian_name' => 'Wali Atlet',
                'whatsapp' => '081234567890',
                'races' => ['Classic Slalom'],
            ])
            ->assertRedirect(route('events.registration.create', $event))
            ->assertSessionHasErrors('nik');

        $this->assertDatabaseCount('event_registrations', 0);
    }

    public function test_registration_accepts_nik_that_matches_gender_and_birth_date(): void
    {
        $event = $this->createApprovedEvent();
        $participant = User::factory()->create(['role' => 'individual']);

        $this->actingAs($participant)
            ->post(route('events.registration.store', $event), [
                'nik' => '3504121203990001',
                'participant_name' => 'Atlet Contoh',
                'gender' => 'male',
                'date_of_birth' => '1999-03-12',
                'address' => 'Tulungagung',
                'guardian_name' => 'Wali Atlet',
                'whatsapp' => '081234567890',
                'races' => ['Classic Slalom'],
            ])
            ->assertRedirect(route('guest.dashboard'));

        $this->assertDatabaseCount('event_registrations', 1);
    }

    private function createApprovedEvent(): Event
    {
        $startDate = today()->addMonth();

        return Event::create([
            'submitted_by' => User::factory()->create(['role' => 'club_manager'])->id,
            'name' => 'Kediri Roller Cup',
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $startDate->copy()->addDay()->format('Y-m-d'),
            'venue' => 'GOR Jayabaya',
            'venue_address' => 'Kota Kediri',
            'organizer_whatsapp' => '081222333444',
            'scale' => 'Kota',
            'regulations' => 'Peraturan pertandingan standar.',
            'officials' => 'Juri dan pencatat waktu.',
            'venue_conditions' => 'Permukaan lintasan telah diperiksa.',
            'awards' => 'Medali untuk juara.',
            'categories' => ['Dewasa'],
            'category_rules' => [
                [
                    'name' => 'Dewasa Putra',
                    'birth_date_from' => '1990-01-01',
                    'birth_date_to' => '2015-12-31',
                    'genders' => ['male'],
                    'races' => ['Classic Slalom'],
                ],
                [
                    'name' => 'Dewasa Putri',
                    'birth_date_from' => '1990-01-01',
                    'birth_date_to' => '2015-12-31',
                    'genders' => ['female'],
                    'races' => ['Classic Slalom'],
                ],
            ],
            'races' => ['Classic Slalom'],
            'registration_deadline' => $startDate->copy()->subDay()->format('Y-m-d'),
            'registration_info' => 'Daftar melalui portal Inlinesia.',
            'fees' => 'Biaya dibayar kepada penyelenggara.',
            'force_majeure' => 'Panitia akan mengumumkan perubahan jadwal.',
            'schedule' => ['Hari pertama — pembukaan'],
            'status' => 'approved',
            'registration_open' => true,
        ]);
    }
}
