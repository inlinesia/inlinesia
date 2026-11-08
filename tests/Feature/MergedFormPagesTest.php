<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Create dan edit sengaja dibuat satu halaman (satu controller, satu blade) supaya
 * perbaikan tidak perlu diulang di dua berkas terpisah. Tes di sini mengunci sifat itu.
 */
class MergedFormPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_create_and_edit_render_the_same_view(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner, 'Kediri Roller Cup');

        $this->actingAs($owner)->get(route('event-applications.create'))
            ->assertOk()
            ->assertViewIs('verified.events.pengajuan.form');

        $this->actingAs($owner)->get(route('event-applications.edit', $event))
            ->assertOk()
            ->assertViewIs('verified.events.pengajuan.form');
    }

    public function test_admin_sees_the_same_shell_for_event_create_and_edit(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $event = $this->createEvent($admin);

        $create = $this->actingAs($admin)->get(route('event-applications.create'))->assertOk();
        $edit = $this->actingAs($admin)->get(route('event-applications.edit', $event))->assertOk();

        foreach ([$create, $edit] as $response) {
            $response->assertSee('class="app-shell is-sidebarless"', false)
                ->assertDontSee('data-sidebar-toggle', false)
                ->assertDontSee('class="public-site"', false);
        }
    }

    public function test_event_edit_uses_the_unified_url_without_an_edit_suffix(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->actingAs($owner)
            ->get("/ajukan-event/{$event->id}")
            ->assertOk()
            ->assertViewIs('verified.events.pengajuan.form');

        $this->actingAs($owner)->get("/ajukan-event/{$event->id}/edit")->assertNotFound();
    }

    public function test_account_create_and_edit_render_the_same_view(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $account = User::factory()->create(['role' => 'individual']);

        $this->actingAs($admin)->get(route('admin.accounts.create'))
            ->assertOk()
            ->assertViewIs('admin.accounts.form');

        $this->actingAs($admin)->get(route('admin.accounts.edit', $account))
            ->assertOk()
            ->assertViewIs('admin.accounts.form');
    }

    public function test_account_form_switches_only_by_mode(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $account = User::factory()->create(['role' => 'individual', 'name' => 'Budi Santoso']);

        $create = $this->actingAs($admin)->get(route('admin.accounts.create'))->assertOk();
        $create->assertSee('Buat akun pengguna')
            ->assertSee('action="'.route('admin.accounts.store').'"', false)
            ->assertDontSee('name="_method"', false);

        $edit = $this->actingAs($admin)->get(route('admin.accounts.edit', $account))->assertOk();
        $edit->assertSee('Edit akun')
            ->assertSee('Budi Santoso')
            ->assertSee('action="'.route('admin.accounts.update', $account).'"', false)
            ->assertSee('name="_method" value="PUT"', false);
    }

    public function test_new_event_still_requires_a_future_start_date_and_deadline(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $payload = $this->validApplication();

        $pastDate = now()->subWeek()->format('Y-m-d');
        $payload['start_date'] = $pastDate;
        $payload['end_date'] = $pastDate;
        $payload['registration_deadline'] = $pastDate;

        $this->actingAs($owner)
            ->from(route('event-applications.create'))
            ->post(route('event-applications.store'), $payload)
            ->assertRedirect(route('event-applications.create'))
            ->assertSessionHasErrors(['start_date', 'registration_deadline']);
    }

    private function createEvent(User $owner, string $name = 'Kediri Roller Cup'): Event
    {
        $startDate = today()->addMonth();

        return Event::create([
            'submitted_by' => $owner->id,
            'name' => $name,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $startDate->copy()->addDay()->format('Y-m-d'),
            'venue' => 'GOR Padepokan PSHT',
            'venue_address' => 'Kediri',
            'organizer_whatsapp' => '081222333444',
            'scale' => 'Kota',
            'categories' => ['Junior'],
            'category_rules' => [],
            'races' => ['Classic Slalom'],
            'schedule' => ['Final'],
            'status' => 'approved',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validApplication(): array
    {
        $startDate = today()->addMonths(2);

        return [
            'name' => 'Kediri Roller Cup',
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $startDate->copy()->addDay()->format('Y-m-d'),
            'registration_deadline' => $startDate->copy()->subDay()->format('Y-m-d'),
            'venue' => 'Gelora Bengaluru',
            'venue_address' => 'Jl. Merdeka No. 1, Kediri',
            'organizer_whatsapp' => '081222333444',
            'scale' => 'Kota',
            'regulations' => 'Wajib memakai helm.',
            'officials' => 'Wasit A',
            'venue_conditions' => 'Lintasan datar.',
            'awards' => 'Piala',
            'categories' => "Beginner\nJunior",
            'races' => "Classic Slalom\nSpeed Slalom",
            'registration_info' => 'Datang 30 menit lebih awal.',
            'fees' => 'Rp 75.000',
            'is_free' => '0',
            'force_majeure' => 'Dibatalkan bila hujan deras.',
            'schedule' => "08.00 Registrasi\n09.00 Briefing",
        ];
    }
}
