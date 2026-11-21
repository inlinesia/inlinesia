<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_theme_settings_page_offers_the_ceria_theme(): void
    {
        $html = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.settings.theme.edit'))
            ->assertOk()
            ->assertSee('Tema tampilan')
            ->getContent();

        $this->assertStringContainsString('value="ceria"', $html);
        $this->assertStringContainsString('theme-preview-ceria', $html);
        $this->assertStringContainsString('Tema ramah anak', $html);
    }

    public function test_admin_can_save_the_ceria_theme(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->patch(route('admin.settings.theme.update'), ['theme' => 'ceria'])
            ->assertRedirect()
            ->assertSessionHas('theme', 'ceria')
            ->assertSessionHas('status', 'Tema ceria tersimpan. Pilihan berlaku di peramban ini.');
    }

    public function test_theme_settings_rejects_an_unknown_theme(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->patch(route('admin.settings.theme.update'), ['theme' => 'unknown'])
            ->assertSessionHasErrors('theme');
    }

    public function test_ceria_stickers_are_marked_only_on_public_pages(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('ceria-sticker-page', false);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('ceria-sticker-page', false);

        $this->actingAs(User::factory()->create(['role' => 'individual']))
            ->get(route('guest.dashboard'))
            ->assertOk()
            ->assertDontSee('ceria-sticker-page', false);
    }
}
