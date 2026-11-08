<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_public_homepage_highlights_available_event_platform_features(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = Event::query()->create([
            'submitted_by' => $owner->id,
            'name' => 'Kejuaraan Inlinesia',
            'start_date' => today()->addDays(30)->format('Y-m-d'),
            'end_date' => today()->addDays(31)->format('Y-m-d'),
            'venue' => 'GOR Jayabaya',
            'venue_address' => 'Kediri',
            'scale' => 'Kota',
            'categories' => ['Junior'],
            'category_rules' => [['name' => 'Junior']],
            'races' => ['Classic Slalom'],
            'schedule' => ['Final'],
            'status' => 'approved',
            'registration_open' => true,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertViewIs('publik.beranda')
            ->assertSee('Inlinesia — Indonesia Inline Skate Events')
            ->assertSeeText('INLINESIA')
            ->assertSee('SKATING ORGANIZER')
            ->assertSee('Jelajahi kejuaraan')
            ->assertSee('Beranda utama')
            ->assertSee('Agenda kejuaraan')
            ->assertSee('Pendaftaran lomba')
            ->assertSee('Kelola event')
            ->assertSee('Kejuaraan Inlinesia')
            ->assertSee('Masuk untuk mendaftar')
            ->assertSee('Daftar kejuaraan')
            ->assertSee('Fitur platform')
            ->assertSee('Alur penggunaan')
            ->assertSee('Tentang kami')
            ->assertSee('KEMITRAAN & IKLAN')
            ->assertSee('Iklan di website')
            ->assertSee('Sponsor event')
            ->assertSee('mailto:inlinesia@gmail.com?subject=Informasi%20Sponsor%20Inlinesia', false)
            ->assertSee(route('events.index'), false);

        $this->actingAs($owner)
            ->get('/')
            ->assertSee('Beranda utama')
            ->assertSee('Daftar kejuaraan')
            ->assertSee('Fitur platform')
            ->assertSee('Alur penggunaan')
            ->assertSee('Tentang kami')
            ->assertSee(route('events.registration.create', $event), false);
    }

    public function test_the_public_homepage_shows_the_event_empty_state_when_no_events_are_upcoming(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Kejuaraan yang akan datang.')
            ->assertSee('Belum ada kejuaraan mendatang')
            ->assertSee(route('events.index'), false);
    }

    public function test_the_public_event_listing_only_displays_approved_events(): void
    {
        $this->get('/lomba')
            ->assertOk()
            ->assertViewIs('publik.lomba.index')
            ->assertSee('Daftar Kejuaraan — Inlinesia')
            ->assertSee('Belum ada kejuaraan yang tersedia')
            ->assertSee('Buat akun')
            ->assertDontSee('Kediri Skate Challenge');

    }

    public function test_the_panitia_dashboard_remains_available_separately(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->get('/admin')
            ->assertOk()
            ->assertViewIs('admin.dashboard')
            ->assertSee('Panel Event — Inlinesia')
            ->assertSeeText('INLINESIA')
            ->assertSee('Selamat datang, Panitia')
            ->assertSee('Kejuaraan mendatang')
            ->assertSee('Data kejuaraan dan pendaftaran terbaru')
            ->assertDontSee('PRATINJAU · DATA SIMULASI');
    }
}
