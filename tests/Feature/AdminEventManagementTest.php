<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminEventManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_reviews_submissions_in_notifications_and_manages_events_in_the_shared_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'club_manager']);
        $approvedEvent = $this->createEvent($owner, 'approved', 'Kejuaraan Aktif');
        $pendingEvent = $this->createEvent($owner, 'pending', 'Pengajuan Menunggu');

        $this->actingAs($admin)
            ->get(route('guest.dashboard'))
            ->assertOk()
            ->assertSee('class="public-site"', false)
            ->assertSee('class="public-header public-header-account"', false)
            ->assertDontSee('class="app-shell"', false)
            ->assertSee('PENGELOLAAN EVENT ADMIN')
            ->assertSee(route('profile.show'), false)
            ->assertSee('Semua event')
            ->assertSee('Kejuaraan Aktif')
            ->assertSee('Pengajuan Menunggu')
            ->assertSee(route('event-applications.destroy', $approvedEvent), false)
            ->assertSee('Hapus event')
            ->assertSee(route('event-applications.edit', $approvedEvent), false)
            ->assertSee(route('owner.events.categories.edit', $approvedEvent), false);

        // Dasbor bersama sengaja tidak menautkan panel admin; panel admin
        // punya tampilan sendiri dan menaut balik ke dasbor bersama.
        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('class="app-shell"', false)
            ->assertSee('Selamat datang, Panitia')
            ->assertSee(route('guest.dashboard'), false);

        $this->get(route('admin.event-submissions.index'))
            ->assertOk()
            ->assertSee('Pengajuan Menunggu')
            ->assertSee(route('admin.event-submissions.approve', $pendingEvent), false)
            ->assertDontSee(route('event-applications.edit', $pendingEvent), false)
            ->assertDontSee('Tambah event')
            ->assertDontSee('Ubah event')
            ->assertDontSee('Atur kategori')
            ->assertDontSee('Hapus event');

        $this->assertDatabaseHas('events', ['id' => $approvedEvent->id]);
        $this->assertDatabaseHas('events', ['id' => $pendingEvent->id]);
    }

    public function test_public_listing_hides_pending_events_and_management_actions_for_every_role(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $publicEvent = $this->createEvent($owner, 'approved', 'Kejuaraan Publik');
        $this->createEvent($owner, 'pending', 'Pengajuan Privat');

        $this->actingAs(User::factory()->create(['role' => 'individual']))
            ->get(route('events.index'))
            ->assertOk()
            ->assertSee('Kejuaraan Publik')
            ->assertDontSee('Pengajuan Privat')
            ->assertDontSee(route('event-applications.edit', $publicEvent), false)
            ->assertDontSee('Ubah event')
            ->assertDontSee('Hapus event')
            ->assertDontSee('Atur kategori');
    }

    public function test_the_legacy_admin_event_management_endpoint_is_removed(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/admin/kejuaraan')
            ->assertNotFound();
    }

    private function createEvent(User $owner, string $status, string $name): Event
    {
        $startDate = today()->addMonth();

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
        ]);
    }
}
