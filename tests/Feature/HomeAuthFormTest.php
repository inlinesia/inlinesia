<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeAuthFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_login_and_registration_forms_on_homepage(): void
    {
        $html = $this->get('/')
            ->assertOk()
            ->assertSee('Masuk')
            ->assertSee('Daftar')
            ->getContent();

        $this->assertStringContainsString('class="home-auth"', $html);
        $this->assertStringContainsString(route('login.store'), $html);
        $this->assertStringContainsString(route('register.store'), $html);
        $this->assertStringContainsString('name="email"', $html);
        $this->assertStringContainsString('name="password"', $html);
        $this->assertStringContainsString('name="password_confirmation"', $html);
        $this->assertStringContainsString('name="role"', $html);
    }

    public function test_authenticated_user_does_not_see_homepage_auth_forms(): void
    {
        $html = $this->actingAs(User::factory()->create(['role' => 'individual']))
            ->get('/')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('class="home-auth"', $html);
        $this->assertStringNotContainsString(route('login.store'), $html);
    }

    public function test_failed_login_shows_the_error_in_the_homepage_form(): void
    {
        $this->from('/')
            ->followingRedirects()
            ->post(route('login.store'), [
                '_form' => 'login',
                'email' => 'tidakada@example.com',
                'password' => 'kata-sandi-salah',
            ])
            ->assertOk()
            ->assertSee('home-auth-errors', false)
            ->assertSee('Email atau kata sandi tidak sesuai.');
    }
}
