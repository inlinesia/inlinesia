<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Sponsor;
use App\Models\User;
use App\Notifications\NewSponsorRequestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SponsorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Notification::fake();
    }

    public function test_form_sponsor_website_dan_event_dapat_dibuka(): void
    {
        $this->get(route('sponsor.web.form'))
            ->assertOk()
            ->assertSee('name="brand_name"', false)
            ->assertSee('name="package"', false)
            ->assertSee('name="logo"', false);

        $this->get(route('sponsor.event.form'))
            ->assertOk()
            ->assertSee('name="event_id"', false)
            ->assertSee('name="website_url"', false)
            ->assertSee('name="agreement"', false)
            ->assertDontSee('Event #0');
    }

    public function test_pengajuan_sponsor_website_tersimpan_dan_menghitung_tarif(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->post(route('sponsor.web.store'), [
            'brand_name' => 'Toko Skates',
            'contact_name' => 'Budi',
            'phone' => '081222333444',
            'email' => 'budi@example.com',
            'package' => Sponsor::PACKAGE_WEB_BANNER,
            'start_date' => today()->format('Y-m-d'),
            'end_date' => today()->addMonth()->format('Y-m-d'),
            'website_url' => 'https://tokoskates.example.com',
            'logo' => UploadedFile::fake()->image('logo.png'),
            'message' => 'Diskon sepatu roda.',
            'agreement' => '1',
        ])->assertRedirect(route('sponsor.web.form'));

        $sponsor = Sponsor::query()->firstOrFail();
        $this->assertSame(Sponsor::TYPE_WEB, $sponsor->type);
        $this->assertSame(Sponsor::STATUS_PENDING, $sponsor->status);
        $this->assertSame(500000, $sponsor->amount);
        Storage::disk('public')->assertExists($sponsor->logo_path);
        Notification::assertSentTo($admin, NewSponsorRequestNotification::class);
    }

    public function test_pengajuan_sponsor_event_menotifikasi_admin_dan_pemilik(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'club_manager']);
        $event = $this->createEvent($owner);

        $this->post(route('sponsor.event.store'), [
            'event_id' => $event->id,
            'brand_name' => 'Helm Keren',
            'contact_name' => 'Sari',
            'phone' => '081111222333',
            'email' => 'sari@example.com',
            'package' => Sponsor::PACKAGE_EVENT_MAIN,
            'website_url' => 'https://helmkeren.example.com',
            'logo' => UploadedFile::fake()->image('helm.png'),
            'message' => 'Perlengkapan balap.',
            'agreement' => '1',
        ])->assertRedirect(route('sponsor.event.form', ['event' => $event->id]));

        $sponsor = Sponsor::query()->firstOrFail();
        $this->assertSame(Sponsor::TYPE_EVENT, $sponsor->type);
        $this->assertSame($event->id, $sponsor->event_id);
        $this->assertSame(300000, $sponsor->amount);
        Notification::assertSentTo($admin, NewSponsorRequestNotification::class);
        Notification::assertSentTo($owner, NewSponsorRequestNotification::class);
    }

    public function test_admin_dapat_menyetujui_dan_menolak_sponsor(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $sponsor = Sponsor::query()->create($this->sponsorAttributes());

        $this->actingAs($admin)
            ->post(route('admin.sponsors.approve', $sponsor))
            ->assertRedirect(route('admin.sponsors.index'));

        $this->assertSame(Sponsor::STATUS_APPROVED, $sponsor->fresh()->status);

        $this->post(route('admin.sponsors.reject', $sponsor))
            ->assertRedirect(route('admin.sponsors.index'));

        $this->assertSame(Sponsor::STATUS_REJECTED, $sponsor->fresh()->status);
    }

    public function test_sponsor_aktif_tampil_di_beranda_listing_dan_pendaftaran(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $event = $this->createEvent($owner);
        Sponsor::query()->create($this->sponsorAttributes([
            'type' => Sponsor::TYPE_WEB,
            'package' => Sponsor::PACKAGE_WEB_BANNER,
            'status' => Sponsor::STATUS_APPROVED,
            'message' => 'Banner sponsor website',
        ]));
        Sponsor::query()->create($this->sponsorAttributes([
            'event_id' => $event->id,
            'status' => Sponsor::STATUS_APPROVED,
            'message' => 'Sponsor event aktif',
        ]));

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Banner sponsor website')
            ->assertSee('Didukung oleh Toko Skates');

        $this->get(route('events.index'))
            ->assertOk()
            ->assertSee('Banner sponsor website')
            ->assertSee('Didukung oleh Toko Skates');

        $this->actingAs($owner)
            ->get(route('events.registration.create', $event))
            ->assertOk()
            ->assertSee('Sponsor event aktif');
    }

    private function createEvent(User $owner): Event
    {
        $startDate = today()->addMonth();

        return Event::create([
            'submitted_by' => $owner->id,
            'name' => 'Kejuaraan Sponsor',
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $startDate->copy()->addDay()->format('Y-m-d'),
            'venue' => 'GOR Jayabaya',
            'venue_address' => 'Kediri',
            'organizer_whatsapp' => '081222333444',
            'scale' => 'Kota',
            'categories' => ['Junior'],
            'category_rules' => ['Junior' => []],
            'races' => ['Classic Slalom'],
            'schedule' => ['Final'],
            'registration_open' => true,
            'status' => 'approved',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function sponsorAttributes(array $overrides = []): array
    {
        return array_merge([
            'type' => Sponsor::TYPE_EVENT,
            'package' => Sponsor::PACKAGE_EVENT_LOGO,
            'brand_name' => 'Toko Skates',
            'contact_name' => 'Budi',
            'phone' => '081222333444',
            'website_url' => 'https://tokoskates.example.com',
            'logo_path' => 'sponsors/logo.png',
            'message' => 'Sponsor event aktif',
            'status' => Sponsor::STATUS_PENDING,
            'amount' => 150000,
        ], $overrides);
    }
}
