<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_listing_contains_only_approved_events_and_no_management_controls(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $publicEvent = $this->createEvent($owner, 'approved', 'Event Publik');
        $publicEvent->update(['category_rules' => [['name' => 'Junior']]]);
        $this->createEvent($owner, 'pending', 'Event Menunggu');
        $this->createEvent($owner, 'approved', 'Event Lampau', -4);

        $response = $this->get(route('events.index'))
            ->assertOk()
            ->assertViewIs('publik.lomba.index')
            ->assertSee('Event Publik')
            ->assertDontSee('Event Menunggu')
            ->assertDontSee('Event Lampau')
            ->assertSee(route('register'), false)
            ->assertSee('Masuk untuk mendaftar')
            ->assertDontSee('Tambah event')
            ->assertDontSee('Ubah event')
            ->assertDontSee(route('event-applications.edit', $publicEvent), false)
            ->assertDontSee(route('events.registrations.index', $publicEvent), false);

        $this->assertSame(1, substr_count($response->getContent(), 'listing-event-card'));
    }

    public function test_verified_owner_manages_events_from_the_shared_dashboard_not_the_public_listing(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $anotherOwner = User::factory()->create(['role' => 'club_manager']);
        $ownedEvent = $this->createEvent($owner, 'approved', 'Event Saya');
        $otherEvent = $this->createEvent($anotherOwner, 'approved', 'Event Pengelola Lain');
        $pendingEvent = $this->createEvent($owner, 'pending', 'Pengajuan Saya');

        $this->actingAs($owner)
            ->get(route('events.index'))
            ->assertOk()
            ->assertSee('Event Saya')
            ->assertSee('Event Pengelola Lain')
            ->assertDontSee('Pengajuan Saya')
            ->assertDontSee(route('event-applications.edit', $ownedEvent), false)
            ->assertDontSee(route('owner.events.categories.edit', $ownedEvent), false);

        $this->get(route('guest.dashboard'))
            ->assertOk()
            ->assertSee('Event Saya')
            ->assertSee('Pengajuan Saya')
            ->assertSee(route('event-applications.edit', $ownedEvent), false)
            ->assertSee(route('owner.events.categories.edit', $ownedEvent), false)
            ->assertDontSee(route('event-applications.edit', $otherEvent), false);
    }

    public function test_unverified_user_is_prompted_to_verify_before_registering_or_applying(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner, 'approved', 'Event Terbuka');
        $event->update([
            'category_rules' => [['name' => 'Junior']],
            'registration_open' => true,
        ]);
        $unverifiedUser = User::factory()->unverified()->create(['role' => 'individual']);

        $this->actingAs($unverifiedUser)
            ->get(route('events.index'))
            ->assertOk()
            ->assertSee(route('verification.notice'), false)
            ->assertSee('Verifikasi email untuk mendaftar')
            ->assertDontSee(route('events.registration.create', $event), false)
            ->assertDontSee('Buat akun untuk mengajukan event');
    }

    public function test_admin_uses_shared_dashboard_for_management_and_notifications_for_review_only(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'organizer']);
        $approvedEvent = $this->createEvent($owner, 'approved', 'Event Disetujui');
        $pendingEvent = $this->createEvent($owner, 'pending', 'Pengajuan Menunggu');

        $this->actingAs($admin)
            ->get(route('guest.dashboard'))
            ->assertOk()
            ->assertSee('Semua event')
            ->assertSee('Event Disetujui')
            ->assertSee('Pengajuan Menunggu')
            ->assertSee(route('event-applications.edit', $approvedEvent), false)
            ->assertSee(route('owner.events.categories.edit', $approvedEvent), false)
            ->assertDontSee('Lihat peserta · read-only');

        $approvedEvent->update(['participants_published' => true]);
        $this->get(route('guest.dashboard'))
            ->assertSee(route('events.registrations.index', $approvedEvent), false)
            ->assertSee('Lihat peserta · read-only');

        $this->get(route('admin.event-submissions.index'))
            ->assertOk()
            ->assertSee('Pengajuan Menunggu')
            ->assertSee(route('admin.event-submissions.approve', $pendingEvent), false)
            ->assertDontSee('Ubah event')
            ->assertDontSee('Atur kategori')
            ->assertDontSee('Hapus event')
            ->assertDontSee('Tambah event')
            ->assertDontSee(route('event-applications.edit', $pendingEvent), false);

        $this->get(route('events.index'))
            ->assertOk()
            ->assertSee('Event Disetujui')
            ->assertDontSee('Pengajuan Menunggu')
            ->assertDontSee('Hapus event');

        $this->assertNotSame($approvedEvent->id, $pendingEvent->id);
    }

    public function test_admin_can_transfer_an_admin_owned_event_to_a_verified_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $newOwner = User::factory()->create([
            'role' => 'club_manager',
            'name' => 'Pengelola Baru',
        ]);
        $event = $this->createEvent($admin, 'approved', 'Event Milik Admin');

        $this->actingAs($admin)
            ->get(route('guest.dashboard'))
            ->assertOk()
            ->assertSee('Pemilik: '.$admin->name)
            ->assertSee(route('admin.events.ownership.update', $event), false)
            ->assertSee('Alihkan kepemilikan')
            ->assertSee($newOwner->email);

        $this->patch(route('admin.events.ownership.update', $event), [
            'owner_id' => $newOwner->id,
        ])
            ->assertRedirect(route('guest.dashboard'))
            ->assertSessionHas('status', "Kepemilikan event “{$event->name}” dialihkan kepada {$newOwner->name}.");

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'submitted_by' => $newOwner->id,
            'status' => 'approved',
        ]);

        $this->get(route('guest.dashboard'))
            ->assertOk()
            ->assertDontSee(route('admin.events.ownership.update', $event), false);

        $this->actingAs($newOwner)
            ->get(route('guest.dashboard'))
            ->assertOk()
            ->assertSee('Event Milik Admin')
            ->assertSee(route('event-applications.edit', $event), false);
    }

    public function test_admin_can_assign_an_unowned_legacy_event_to_a_verified_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $newOwner = User::factory()->create(['role' => 'individual']);
        $event = $this->createEvent($admin, 'approved', 'Event Legacy Admin');
        $event->update(['submitted_by' => null]);

        $this->actingAs($admin)
            ->patch(route('admin.events.ownership.update', $event), [
                'owner_id' => $newOwner->id,
            ])
            ->assertRedirect(route('guest.dashboard'));

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'submitted_by' => $newOwner->id,
        ]);
    }

    public function test_admin_cannot_transfer_an_event_owned_by_a_non_admin_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'organizer']);
        $newOwner = User::factory()->create(['role' => 'club_manager']);
        $event = $this->createEvent($owner, 'approved', 'Event Pengelola');

        $this->actingAs($admin)
            ->get(route('guest.dashboard'))
            ->assertDontSee(route('admin.events.ownership.update', $event), false);

        $this->patch(route('admin.events.ownership.update', $event), [
            'owner_id' => $newOwner->id,
        ])->assertNotFound();

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'submitted_by' => $owner->id,
        ]);
    }

    public function test_only_admin_can_transfer_event_ownership(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $newOwner = User::factory()->create(['role' => 'club_manager']);
        $event = $this->createEvent($admin, 'approved', 'Event Milik Admin');

        $this->actingAs($newOwner)
            ->patch(route('admin.events.ownership.update', $event), [
                'owner_id' => $newOwner->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'submitted_by' => $admin->id,
        ]);
    }

    public function test_admin_cannot_transfer_event_to_an_unverified_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $unverifiedUser = User::factory()->unverified()->create(['role' => 'individual']);
        $event = $this->createEvent($admin, 'approved', 'Event Milik Admin');

        $this->actingAs($admin)
            ->from(route('guest.dashboard'))
            ->patch(route('admin.events.ownership.update', $event), [
                'owner_id' => $unverifiedUser->id,
            ])
            ->assertRedirect(route('guest.dashboard'))
            ->assertSessionHasErrors('owner_id');

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'submitted_by' => $admin->id,
        ]);
    }

    public function test_admin_can_edit_categories_from_the_shared_management_route(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $admin = User::factory()->create(['role' => 'admin']);
        $event = $this->createEvent($owner, 'approved', 'Event Kelolaan');

        $this->actingAs($admin)
            ->get(route('owner.events.categories.edit', $event))
            ->assertOk()
            ->assertSee('Event Kelolaan');

        $this->put(route('owner.events.categories.update', $event), [
            'category_rules' => [[
                'name' => 'Junior',
                'eligibility_types' => ['birth_year'],
                'birth_date_from' => '',
                'birth_date_to' => '',
                'birth_years' => [2012],
                'minimum_age' => '',
                'maximum_age' => '',
                'genders' => ['male', 'female'],
                'races' => ['Classic Slalom'],
            ]],
        ])
            ->assertRedirect(route('guest.dashboard'))
            ->assertSessionHas('status');

        $this->assertSame('Junior', $event->fresh()->category_rules[0]['name']);
    }

    private function createEvent(User $owner, string $status, string $name, int $daysFromToday = 30): Event
    {
        $startDate = today()->addDays($daysFromToday);

        return Event::create([
            'submitted_by' => $owner->id,
            'name' => $name,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $startDate->copy()->addDay()->format('Y-m-d'),
            'venue' => 'GOR Jayabaya',
            'venue_address' => 'Kediri',
            'scale' => 'Kota',
            'categories' => ['Junior'],
            'category_rules' => [],
            'races' => ['Classic Slalom'],
            'schedule' => ['Final'],
            'status' => $status,
            'registration_open' => $status === 'approved',
        ]);
    }
}
