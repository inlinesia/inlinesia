<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EventTierTest extends TestCase
{
    use RefreshDatabase;

    public function test_gratis_hanya_menyimpan_rincian_event_sederhana(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['role' => 'club_manager']);
        $application = $this->advertisementApplication();

        $this->actingAs($owner)
            ->post(route('event-applications.store'), $application)
            ->assertRedirect(route('guest.dashboard'));

        $event = Event::query()->firstOrFail();
        $this->assertSame(Event::TIER_ADVERTISEMENT, $event->event_tier);
        $this->assertSame('Deskripsi singkat kejuaraan.', $event->short_description);
        $this->assertSame([], $event->categories ?? []);
        $this->assertSame([], $event->races ?? []);
        $this->assertTrue($event->isAdvertisementOnly());
    }

    public function test_gratis_tidak_membutuhkan_field_lengkap_event_berbayar(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $application = $this->advertisementApplication();
        $application['short_description'] = '';

        $this->actingAs($owner)
            ->post(route('event-applications.store'), $application)
            ->assertSessionHasErrors('short_description');

        $this->assertDatabaseCount('events', 0);
    }

    public function test_event_gratis_tampil_sebagai_iklan_tanpa_pendaftaran(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $event = $this->createEvent($owner, Event::TIER_ADVERTISEMENT);

        $this->get(route('events.index'))
            ->assertOk()
            ->assertSee('Event Iklan')
            ->assertSee('Deskripsi singkat kejuaraan.')
            ->assertSee('Hubungi pengelola')
            ->assertSee('https://wa.me/6281222333444', false);

        $this->actingAs($owner)
            ->get(route('events.registration.create', $event))
            ->assertNotFound();
    }

    public function test_halaman_kelola_ditolak_untuk_event_gratis(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $event = $this->createEvent($owner, Event::TIER_ADVERTISEMENT);

        $this->actingAs($owner);
        $this->get(route('events.registrations.index', $event))->assertForbidden();
        $this->get(route('owner.events.categories.edit', $event))->assertForbidden();
        $this->get(route('owner.events.finance.index', $event))->assertForbidden();
        $this->get(route('owner.events.race.format', $event))->assertForbidden();
        $this->get(route('owner.events.race.day', $event))->assertForbidden();

        $this->actingAs($owner)
            ->patch(route('event-applications.toggle-registration', $event), ['registration_open' => '1'])
            ->assertForbidden();
    }

    public function test_dasbor_pemilik_menawarkan_upgrade_dan_menyembunyikan_kelola(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $event = $this->createEvent($owner, Event::TIER_ADVERTISEMENT);

        $this->actingAs($owner)
            ->get(route('guest.dashboard'))
            ->assertOk()
            ->assertSee('Iklan (Gratis)')
            ->assertSee('Upgrade ke berbayar')
            ->assertDontSee('Atur KU (Kelompok Usia)')
            ->assertDontSee('Kelola keuangan');

        $full = $this->fullApplication();
        $this->actingAs($owner)
            ->put(route('event-applications.update', $event), $full)
            ->assertRedirect(route('guest.dashboard'));

        $this->assertSame(Event::TIER_FULL, $event->fresh()->event_tier);
        $this->assertSame('approved', $event->fresh()->status);
    }

    private function createEvent(User $owner, string $tier, string $status = 'approved'): Event
    {
        $startDate = today()->addDays(30);

        return Event::create([
            'submitted_by' => $owner->id,
            'name' => $tier === Event::TIER_ADVERTISEMENT ? 'Event Iklan' : 'Event Berbayar',
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $startDate->copy()->addDay()->format('Y-m-d'),
            'venue' => 'GOR Jayabaya',
            'venue_address' => 'Kediri',
            'short_description' => 'Deskripsi singkat kejuaraan.',
            'organizer_whatsapp' => '081222333444',
            'scale' => 'Kota',
            'categories' => [],
            'category_rules' => [],
            'races' => [],
            'schedule' => ['Final'],
            'status' => $status,
            'event_tier' => $tier,
            'registration_open' => $tier === Event::TIER_FULL,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function advertisementApplication(): array
    {
        $startDate = today()->addMonth();

        return [
            'event_tier' => Event::TIER_ADVERTISEMENT,
            'name' => 'Kejuaraan Iklan Inlinesia',
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $startDate->copy()->addDay()->format('Y-m-d'),
            'venue' => 'GOR Jayabaya',
            'venue_address' => 'Kediri',
            'scale' => 'Kota',
            'short_description' => 'Deskripsi singkat kejuaraan.',
            'organizer_whatsapp' => '081222333444',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function fullApplication(): array
    {
        $startDate = today()->addMonth();

        return [
            'event_tier' => Event::TIER_FULL,
            'name' => 'Kejuaraan Berbayar Inlinesia',
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $startDate->copy()->addDay()->format('Y-m-d'),
            'venue' => 'GOR Jayabaya',
            'venue_address' => 'Kediri',
            'organizer_whatsapp' => '081222333444',
            'scale' => 'Kota',
            'regulations' => 'Peraturan pertandingan.',
            'officials' => 'Head Judge',
            'venue_conditions' => 'Lintasan siap.',
            'awards' => 'Medali juara 1-3.',
            'categories' => "Beginner\nNon-Beginner",
            'races' => "Classic Slalom\nSkate Cross",
            'registration_deadline' => $startDate->copy()->subDay()->format('Y-m-d'),
            'registration_info' => 'Pendaftaran melalui portal.',
            'fees' => 'Biaya sesuai nomor lomba.',
            'is_free' => '0',
            'force_majeure' => 'Panitia mengumumkan perubahan.',
            'schedule' => "Hari pertama\nHari kedua",
        ];
    }
}
