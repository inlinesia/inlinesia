<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_hero_text_is_readable_in_ceria_theme(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('hero-banner', false)
            ->assertSee('hero-kicker', false)
            ->assertSee('Kelola kejuaraan, atlet, dan hasil lomba dengan lebih rapi—dari pendaftaran sampai podium.')
            ->assertSee('Jelajahi kejuaraan');
    }
}
