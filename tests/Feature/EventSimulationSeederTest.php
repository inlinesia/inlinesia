<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventRaceEntry;
use App\Models\EventRegistration;
use App\Models\User;
use Database\Seeders\EventSimulationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EventSimulationSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_pending_event_with_login_ready_accounts(): void
    {
        (new EventSimulationSeeder)->run();

        $event = Event::query()->where('name', 'SIMULASI Kediri Skatefest 2027')->firstOrFail();

        $this->assertSame('pending', $event->status);
        $this->assertCount(3, $event->category_rules);
        $this->assertSame(40, $event->category_rules[0]['max_registrants']);
        $this->assertSame(100, EventRegistration::query()->where('event_id', $event->id)->count());
        $this->assertSame(8, EventRegistration::query()->where('event_id', $event->id)->distinct('user_id')->count('user_id'));
        $this->assertGreaterThan(0, EventRaceEntry::query()->where('event_id', $event->id)->count());
        $this->assertGreaterThan(0, EventRaceEntry::query()->where('event_id', $event->id)->where('status', 'finished')->count());

        $owner = User::query()->where('email', 'simulasi.owner@kilat.com')->firstOrFail();
        $this->assertSame('club_manager', $owner->role);
        $this->assertNotNull($owner->email_verified_at);
        $this->assertTrue(Hash::check('12345678', $owner->password));
        $this->assertSame($owner->id, $event->submitted_by);

        $athlete = User::query()->where('email', 'simulasi.atlet1@kilat.com')->firstOrFail();
        $this->assertNotNull($athlete->email_verified_at);
        $this->assertTrue(Hash::check('12345678', $athlete->password));

        $this->post(route('login.store'), [
            'email' => 'simulasi.owner@kilat.com',
            'password' => '12345678',
        ])->assertRedirect(route('profile.show'));

        $this->assertAuthenticatedAs($owner);

        $this->get(route('guest.dashboard'))->assertOk()->assertSee('SIMULASI Kediri Skatefest 2027');
    }

    public function test_seeder_can_be_run_again_without_duplicating_data(): void
    {
        $seeder = new EventSimulationSeeder;

        $seeder->run();
        $seeder->run();

        $this->assertSame(1, Event::query()->where('name', 'SIMULASI Kediri Skatefest 2027')->count());
        $this->assertSame(9, User::query()->where('email', 'like', 'simulasi.%@kilat.com')->count());
        $this->assertSame(100, EventRegistration::query()->count());
    }

    public function test_purge_removes_simulation_but_keeps_real_accounts(): void
    {
        $seeder = new EventSimulationSeeder;
        $realUser = User::factory()->create(['role' => 'admin']);
        $seeder->run();
        $seeder->purge();

        $this->assertSame(0, Event::query()->where('name', 'SIMULASI Kediri Skatefest 2027')->count());
        $this->assertSame(0, User::query()->where('email', 'like', 'simulasi.%@kilat.com')->count());
        $this->assertSame(0, EventRegistration::query()->count());
        $this->assertDatabaseHas('users', ['id' => $realUser->id]);
    }
}
