<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_user_can_open_their_dashboard_and_see_only_their_registrations(): void
    {
        $user = User::factory()->create(['role' => 'individual']);
        $anotherUser = User::factory()->create(['role' => 'club_manager']);
        $event = $this->createEvent($user, 'approved');
        $registration = $this->createRegistration($event, $user, 'Atlet Milik Saya');
        $this->createRegistration($event, $anotherUser, 'Atlet Milik Orang Lain');
        $this->createEvent($user, 'pending');

        $this->actingAs($user)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee(route('guest.dashboard'), false)
            ->assertSee('Buka dasbor peserta');

        $this->get(route('guest.dashboard'))
            ->assertOk()
            ->assertSee('Halo, '.$user->name)
            ->assertSee('DASBOR AKUN TERVERIFIKASI')
            ->assertSee('Pendaftaran saya')
            ->assertSee(route('event-applications.create'), false)
            ->assertSee('Daftarkan event')
            ->assertSee('Kediri Roller Cup')
            ->assertSee('Atlet Milik Saya')
            ->assertDontSee('Atlet Milik Orang Lain')
            ->assertSee(route('events.registration.create', $event), false)
            ->assertSee('Classic Slalom');

        $this->assertSame($user->id, $registration->user_id);

        $this->actingAs($anotherUser)
            ->get(route('guest.dashboard'))
            ->assertOk()
            ->assertSee('DASBOR AKUN TERVERIFIKASI')
            ->assertSee('Atlet Milik Orang Lain')
            ->assertDontSee('Atlet Milik Saya');
    }

    public function test_unverified_user_is_prompted_to_verify_and_does_not_see_dashboard_button(): void
    {
        $user = User::factory()->unverified()->create(['role' => 'individual']);

        $this->actingAs($user)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Verifikasi email')
            ->assertDontSee(route('guest.dashboard'), false);

        $this->get(route('guest.dashboard'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_individual_and_club_accounts_can_register_when_registration_is_open(): void
    {
        foreach (['individual', 'club_manager'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $event = $this->createEvent(User::factory()->create(['role' => 'organizer']), 'approved');

            $this->actingAs($user)
                ->get(route('events.index'))
                ->assertSee(route('events.registration.create', $event), false)
                ->assertSee('Daftar lomba');

            $this->get(route('events.registration.create', $event))
                ->assertOk()
                ->assertSee($event->name);
        }
    }

    public function test_dashboard_hides_registration_link_until_manager_opens_registration(): void
    {
        $user = User::factory()->create(['role' => 'individual']);
        $event = $this->createEvent($user, 'approved');
        $event->update(['registration_open' => false]);

        $this->actingAs($user)
            ->get(route('guest.dashboard'))
            ->assertDontSee(route('events.registration.create', $event), false)
            ->assertSee('Pendaftaran segera dibuka');
    }

    private function createEvent(User $submitter, string $status): Event
    {
        $startDate = today()->addMonth();

        return Event::create([
            'submitted_by' => $submitter->id,
            'name' => 'Kediri Roller Cup',
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $startDate->copy()->addDay()->format('Y-m-d'),
            'venue' => 'GOR Jayabaya',
            'venue_address' => 'Kota Kediri',
            'scale' => 'Kota',
            'categories' => ['Beginner', 'Non-Beginner'],
            'category_rules' => [
                [
                    'name' => 'Kategori Umum',
                    'birth_date_from' => '1900-01-01',
                    'birth_date_to' => today()->format('Y-m-d'),
                    'genders' => ['male', 'female'],
                    'races' => ['Classic Slalom', 'Speed Slalom'],
                ],
            ],
            'races' => ['Classic Slalom', 'Speed Slalom'],
            'registration_deadline' => $startDate->copy()->subDay()->format('Y-m-d'),
            'schedule' => ['Pembukaan', 'Final'],
            'status' => $status,
            'registration_open' => true,
        ]);
    }

    private function createRegistration(Event $event, User $user, string $participantName): EventRegistration
    {
        return EventRegistration::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'participant_name' => $participantName,
            'birth_year' => now()->subYears(12)->year,
            'club_name' => 'Klub Roda',
            'category' => 'Beginner',
            'races' => ['Classic Slalom'],
            'status' => 'pending',
        ]);
    }
}
