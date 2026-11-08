<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSidebarTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sidebar_has_a_toggle_and_no_help_card(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('data-sidebar-toggle', false)
            ->assertSee('aria-label="Sembunyikan sidebar"', false)
            ->assertDontSee('Pusat bantuan panitia');
    }

    public function test_admin_sidebar_only_links_to_modules_that_exist(): void
    {
        $html = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->getContent();

        foreach ([
            'admin.dashboard',
            'admin.event-submissions.index',
            'admin.accounts.index',
            'admin.settings.theme.edit',
            'admin.data.backup',
            'admin.data.delete',
        ] as $routeName) {
            $this->assertStringContainsString(route($routeName), $html, "Sidebar harus memuat tautan {$routeName}.");
        }

        $this->assertStringContainsString('>Setting</span>', $html);
        $this->assertStringNotContainsString('>Tampilan</span>', $html);
    }

    public function test_placeholder_admin_module_pages_have_been_removed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        foreach (['/admin/klub-atlet', '/admin/mata-lomba', '/admin/hasil-podium', '/admin/keuangan'] as $url) {
            $this->actingAs($admin)->get($url)->assertNotFound();
        }
    }
}
