<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PageContextWidgetTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_user_badge_menampilkan_nama_dan_role_pada_setiap_halaman(): void
    {
        $individual = User::factory()->create(['role' => 'individual', 'name' => 'Atlet Rajin']);

        $this->actingAs($individual)
            ->get(route('guest.dashboard'))
            ->assertOk()
            ->assertSee('Atlet Rajin')
            ->assertSee('individual')
            ->assertSee('data-page-context', false);

        $this->actingAs(User::factory()->create(['role' => 'club_manager', 'name' => 'Pengelola Klub']))
            ->get(route('guest.dashboard'))
            ->assertOk()
            ->assertSee('Pengelola Klub')
            ->assertSee('club_manager');
    }

    #[Test]
    public function test_lokasi_blade_hanya_muncul_untuk_admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('admin/dashboard.blade.php')
            ->assertSee('data-blade-location', false);

        $this->actingAs(User::factory()->create(['role' => 'individual']))
            ->get(route('guest.dashboard'))
            ->assertOk()
            ->assertDontSee('data-blade-location', false);
    }

    #[Test]
    public function test_halaman_pendaftaran_menampilkan_navbar_di_atas_dan_konten_di_dalam_layout(): void
    {
        $submitter = User::factory()->create();
        $event = $this->createEvent($submitter);

        $response = $this->actingAs(User::factory()->create(['role' => 'individual']))
            ->get(route('events.registration.create', $event))
            ->assertOk();

        $html = $response->getContent();

        // Konten halaman harus berada di dalam <body>, bukan bocor sebelum layout.
        $this->assertStringStartsWith('<!DOCTYPE html>', ltrim($html));
        $this->assertSame(
            1,
            substr_count($html, '<body'),
            'Halaman harus memiliki tepat satu elemen body.'
        );

        // Navbar harus berada sebelum konten utama agar tidak terdorong ke bawah.
        $headerPosition = strpos($html, 'public-header');
        $contentPosition = strpos($html, 'account-page');

        $this->assertIsInt($headerPosition);
        $this->assertIsInt($contentPosition);
        $this->assertLessThan(
            $contentPosition,
            $headerPosition,
            'Navbar harus dirender sebelum konten halaman.'
        );
    }

    private function createEvent(User $submitter): Event
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
            'categories' => ['Beginner'],
            'category_rules' => [
                [
                    'name' => 'Junior Putra',
                    'birth_date_from' => '2010-01-01',
                    'birth_date_to' => '2020-12-31',
                    'genders' => ['male'],
                    'races' => ['Classic Slalom', 'Speed Slalom'],
                ],
            ],
            'races' => ['Classic Slalom', 'Speed Slalom'],
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
