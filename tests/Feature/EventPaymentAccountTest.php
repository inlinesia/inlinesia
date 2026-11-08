<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventPaymentAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_organizer_can_submit_an_event_with_a_payment_account(): void
    {
        $organizer = User::factory()->create(['role' => 'club_manager']);

        $this->actingAs($organizer)
            ->post(route('event-applications.store'), [
                ...$this->validApplication(),
                'payment_bank_name' => 'Bank Central Asia',
                'payment_account_number' => '1234-5678-9012',
                'payment_account_holder' => ' Verifier  ',
            ])
            ->assertRedirect(route('guest.dashboard'));

        $event = Event::query()->where('name', 'Kediri Roller Cup')->firstOrFail();

        $this->assertSame('Bank Central Asia', $event->payment_bank_name);
        $this->assertSame('123456789012', $event->payment_account_number);
        $this->assertSame('Verifier', $event->payment_account_holder);
        $this->assertTrue($event->hasPaymentAccount());
    }

    public function test_payment_account_is_optional(): void
    {
        $organizer = User::factory()->create(['role' => 'club_manager']);

        $this->actingAs($organizer)
            ->post(route('event-applications.store'), $this->validApplication())
            ->assertRedirect(route('guest.dashboard'));

        $event = Event::query()->where('name', 'Kediri Roller Cup')->firstOrFail();

        $this->assertNull($event->payment_bank_name);
        $this->assertNull($event->payment_account_number);
        $this->assertNull($event->payment_account_holder);
        $this->assertFalse($event->hasPaymentAccount());
    }

    public function test_account_number_without_a_bank_name_is_rejected(): void
    {
        $organizer = User::factory()->create(['role' => 'club_manager']);

        $this->actingAs($organizer)
            ->from(route('event-applications.create'))
            ->post(route('event-applications.store'), [
                ...$this->validApplication(),
                'payment_bank_name' => '',
                'payment_account_number' => '1234567890',
            ])
            ->assertRedirect(route('event-applications.create'))
            ->assertSessionHasErrors('payment_bank_name');

        $this->assertDatabaseCount('events', 0);
    }

    public function test_account_number_with_letters_is_rejected(): void
    {
        $organizer = User::factory()->create(['role' => 'club_manager']);

        $this->actingAs($organizer)
            ->from(route('event-applications.create'))
            ->post(route('event-applications.store'), [
                ...$this->validApplication(),
                'payment_bank_name' => 'Bank Mandiri',
                'payment_account_number' => '12345-ABCDE',
            ])
            ->assertSessionHasErrors('payment_account_number');

        $this->assertDatabaseCount('events', 0);
    }

    public function test_owner_can_change_the_payment_account_from_the_edit_form(): void
    {
        $organizer = User::factory()->create(['role' => 'club_manager']);
        $event = $this->createEvent($organizer, [
            'payment_bank_name' => 'Bank Rakyat Indonesia',
            'payment_account_number' => '111222333',
            'payment_account_holder' => 'Panitia Lama',
        ]);

        $this->actingAs($organizer)
            ->get(route('event-applications.edit', $event))
            ->assertOk()
            ->assertSee('Nomor rekening pembayaran')
            ->assertSee('111222333')
            ->assertSee('Bank Rakyat Indonesia');

        $this->actingAs($organizer)
            ->put(route('event-applications.update', $event), [
                ...$this->validApplication(),
                'payment_bank_name' => 'Bank Mandiri',
                'payment_account_number' => '999 888 777',
                'payment_account_holder' => 'Panitia Baru',
            ])
            ->assertRedirect(route('guest.dashboard'));

        $fresh = $event->fresh();
        $this->assertSame('Bank Mandiri', $fresh->payment_bank_name);
        $this->assertSame('999888777', $fresh->payment_account_number);
        $this->assertSame('Panitia Baru', $fresh->payment_account_holder);
    }

    public function test_clearing_the_payment_account_removes_it(): void
    {
        $organizer = User::factory()->create(['role' => 'club_manager']);
        $event = $this->createEvent($organizer, [
            'payment_bank_name' => 'Bank BNI',
            'payment_account_number' => '555666777',
            'payment_account_holder' => 'Panitia',
        ]);

        $this->actingAs($organizer)
            ->put(route('event-applications.update', $event), [
                ...$this->validApplication(),
                'payment_bank_name' => '',
                'payment_account_number' => '',
                'payment_account_holder' => '',
            ])
            ->assertRedirect(route('guest.dashboard'));

        $fresh = $event->fresh();
        $this->assertNull($fresh->payment_bank_name);
        $this->assertNull($fresh->payment_account_number);
        $this->assertNull($fresh->payment_account_holder);
    }

    public function test_registration_page_shows_the_payment_account_to_the_athlete(): void
    {
        $organizer = User::factory()->create(['role' => 'club_manager']);
        $event = $this->createEvent($organizer, [
            'payment_bank_name' => 'Bank Mandiri',
            'payment_account_number' => '1234567890',
            'payment_account_holder' => 'Panitia Kejuaraan',
        ]);
        $athlete = User::factory()->create(['role' => 'individual']);

        $this->actingAs($athlete)
            ->get(route('events.registration.create', $event))
            ->assertOk()
            ->assertSee('Rekening pembayaran')
            ->assertSee('1234567890')
            ->assertSee('Bank Mandiri')
            ->assertSee('a.n. Panitia Kejuaraan');
    }

    public function test_registration_page_hides_the_payment_account_for_free_events(): void
    {
        $organizer = User::factory()->create(['role' => 'club_manager']);
        $event = $this->createEvent($organizer, [
            'is_free' => true,
            'payment_bank_name' => 'Bank Mandiri',
            'payment_account_number' => '1234567890',
            'payment_account_holder' => 'Panitia Kejuaraan',
        ]);
        $athlete = User::factory()->create(['role' => 'individual']);

        $this->actingAs($athlete)
            ->get(route('events.registration.create', $event))
            ->assertOk()
            ->assertDontSee('1234567890');
    }

    public function test_event_without_a_payment_account_does_not_render_the_block(): void
    {
        $organizer = User::factory()->create(['role' => 'club_manager']);
        $event = $this->createEvent($organizer);
        $athlete = User::factory()->create(['role' => 'individual']);

        $this->actingAs($athlete)
            ->get(route('events.registration.create', $event))
            ->assertOk()
            ->assertDontSee('Rekening pembayaran');
    }

    /**
     * @return array<string, mixed>
     */
    private function validApplication(): array
    {
        $startDate = today()->addMonth();

        return [
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
            'categories' => "Beginner\nNon-Beginner",
            'races' => "Classic Slalom\nSpeed Slalom",
            'registration_deadline' => $startDate->copy()->subDay()->format('Y-m-d'),
            'registration_info' => 'Daftar melalui portal Inlinesia.',
            'fees' => 'Biaya dibayar lewat transfer bank.',
            'is_free' => '0',
            'force_majeure' => 'Panitia akan mengumumkan perubahan jadwal.',
            'schedule' => "Hari pertama — pembukaan\nHari kedua — final",
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createEvent(User $submitter, array $overrides = []): Event
    {
        $startDate = today()->addMonth();

        return Event::create([
            'submitted_by' => $submitter->id,
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
            'categories' => ['Beginner', 'Non-Beginner'],
            'category_rules' => [[
                'name' => 'Beginner',
                'eligibility_type' => 'birth_date',
                'birth_date_from' => '2010-01-01',
                'birth_date_to' => '2020-12-31',
                'genders' => ['male', 'female'],
                'races' => ['Classic Slalom', 'Speed Slalom'],
            ]],
            'races' => ['Classic Slalom', 'Speed Slalom'],
            'registration_deadline' => $startDate->copy()->subDay()->format('Y-m-d'),
            'registration_info' => 'Daftar melalui portal Inlinesia.',
            'fees' => 'Biaya dibayar lewat transfer bank.',
            'is_free' => false,
            'force_majeure' => 'Panitia akan mengumumkan perubahan jadwal.',
            'schedule' => ['Hari pertama — pembukaan', 'Hari kedua — final'],
            'status' => 'approved',
            'registration_open' => true,
            ...$overrides,
        ]);
    }
}
