<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminEventFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_submits_event_from_the_shared_dashboard_flow(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('guest.dashboard'))
            ->assertOk()
            ->assertSee(route('event-applications.create'), false)
            ->assertSee('Kelola event');

        $this->get(route('event-applications.create'))
            ->assertOk()
            ->assertSee('class="app-shell is-sidebarless"', false)
            ->assertDontSee('data-sidebar-toggle', false)
            ->assertDontSee('class="public-site"', false)
            ->assertViewIs('verified.events.pengajuan.form')
            ->assertSee('Daftarkan event baru');

        $this->post(route('event-applications.store'), $this->validApplication())
            ->assertRedirect(route('guest.dashboard'))
            ->assertSessionHas('status');

        $event = Event::query()->firstOrFail();
        $this->assertSame('pending', $event->status);
        $this->assertSame($admin->id, $event->submitted_by);
        Notification::assertNothingSent();

        $this->get(route('admin.event-submissions.index'))
            ->assertOk()
            ->assertSee($event->name);
    }

    public function test_admin_can_mark_an_event_as_free(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $application = $this->validApplication();
        $application['is_free'] = '1';

        $this->actingAs($admin)
            ->post(route('event-applications.store'), $application)
            ->assertRedirect(route('guest.dashboard'));

        $this->assertTrue(Event::query()->firstOrFail()->is_free);
    }

    public function test_admin_configures_categories_from_the_shared_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'club_manager']);
        $event = $this->createEvent($owner);

        $this->actingAs($admin)
            ->get(route('owner.events.categories.edit', $event))
            ->assertOk()
            ->assertViewIs('verified.events.categories')
            ->assertSee($event->name);

        $this->put(route('owner.events.categories.update', $event), [
            'category_rules' => [[
                'name' => 'Junior',
                'eligibility_types' => ['birth_year'],
                'birth_years' => [2012],
                'genders' => ['male', 'female'],
                'races' => ['Classic Slalom'],
            ]],
        ])
            ->assertRedirect(route('guest.dashboard'))
            ->assertSessionHas('status');

        $this->assertSame('Junior', $event->fresh()->category_rules[0]['name']);
    }

    public function test_event_forms_ask_only_for_categories_and_races(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $event = $this->createEvent($admin);

        // Kelompok usia digantikan aturan kategori, jadi form tidak boleh
        // menanyakannya lagi.
        $this->actingAs($admin)
            ->get(route('event-applications.create'))
            ->assertOk()
            ->assertSee('Kategori & nomor lomba')
            ->assertSee('name="categories"', false)
            ->assertSee('name="races"', false)
            ->assertDontSee('name="age_groups"', false)
            ->assertDontSee('Kelompok usia');

        $this->actingAs($admin)
            ->get(route('event-applications.edit', $event))
            ->assertOk()
            ->assertSee('name="categories"', false)
            ->assertSee('name="races"', false)
            ->assertDontSee('name="age_groups"', false)
            ->assertDontSee('Kelompok usia');
    }

    public function test_age_groups_can_no_longer_be_submitted_from_the_event_form(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $payload = $this->validApplication();
        $payload['age_groups'] = "Beginner\nJunior U-9";

        $this->actingAs($admin)
            ->post(route('event-applications.store'), $payload)
            ->assertRedirect(route('guest.dashboard'))
            ->assertSessionHasNoErrors();

        $event = Event::query()->firstOrFail();

        $this->assertSame(['Beginner', 'Non-Beginner'], $event->categories);
        // Kolomnya masih ada untuk data lama, tapi tidak lagi diisi.
        $this->assertNull($event->age_groups);
    }

    public function test_legacy_admin_create_mode_does_not_open_an_admin_event_form(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('events.index', ['mode' => 'create']))
            ->assertOk()
            ->assertViewIs('publik.lomba.index')
            ->assertDontSee('Buat event')
            ->assertDontSee('Hapus event');
    }

    public function test_event_end_date_cannot_precede_its_start_date(): void
    {
        $payload = $this->validApplication();
        $payload['start_date'] = today()->addMonths(2)->format('Y-m-d');
        $payload['end_date'] = today()->addMonth()->format('Y-m-d');

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->from(route('event-applications.create'))
            ->post(route('event-applications.store'), $payload)
            ->assertRedirect(route('event-applications.create'))
            ->assertSessionHasErrors('end_date');

        $this->assertDatabaseCount('events', 0);
    }

    private function validApplication(): array
    {
        $startDate = today()->addMonth();

        return [
            'name' => 'Kejurprov XI Piala Bupati Tulungagung',
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $startDate->copy()->addDay()->format('Y-m-d'),
            'venue' => 'GOR Padepokan PSHT',
            'venue_address' => 'Tulungagung, Jawa Timur',
            'organizer_whatsapp' => '081222333444',
            'scale' => 'Regional Jawa Timur',
            'regulations' => 'Peraturan pertandingan.',
            'officials' => 'Head Judge, Scoring Judge',
            'venue_conditions' => 'Lintasan siap digunakan.',
            'awards' => 'Medali juara 1-3.',
            'categories' => "Beginner\nNon-Beginner",
            'races' => "Classic Slalom\nSkate Cross",
            'registration_deadline' => $startDate->copy()->subDay()->format('Y-m-d'),
            'registration_info' => 'Pendaftaran melalui portal.',
            'fees' => 'Biaya sesuai nomor lomba.',
            'is_free' => '0',
            'force_majeure' => 'Panitia akan mengumumkan perubahan.',
            'schedule' => "Hari pertama\nHari kedua",
        ];
    }

    private function createEvent(User $owner): Event
    {
        $startDate = today()->addMonth();

        return Event::create([
            'submitted_by' => $owner->id,
            'name' => 'Kejurprov XI Piala Bupati Tulungagung',
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $startDate->copy()->addDay()->format('Y-m-d'),
            'venue' => 'GOR Padepokan PSHT',
            'venue_address' => 'Tulungagung',
            'organizer_whatsapp' => '081222333444',
            'scale' => 'Provinsi',
            'categories' => ['Junior'],
            'category_rules' => [],
            'races' => ['Classic Slalom'],
            'schedule' => ['Final'],
            'status' => 'approved',
        ]);
    }
}
