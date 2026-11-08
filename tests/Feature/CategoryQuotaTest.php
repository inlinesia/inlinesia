<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryQuotaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_category_without_a_quota_is_never_full(): void
    {
        $event = $this->createEvent();

        $this->assertNull($event->categoryQuota('Junior Putra'));
        $this->assertFalse($event->categoryIsFull('Junior Putra'));
        $this->assertTrue($event->isCategoryRegistrationOpen('Junior Putra'));
        $this->assertNull($event->categoryRemainingQuota('Junior Putra'));
    }

    public function test_only_paid_and_accepted_athletes_consume_a_quota_slot(): void
    {
        $event = $this->createEvent(maxRegistrants: 2);
        $owner = User::factory()->create(['role' => 'club_manager']);

        // Menunggu pembayaran dan sudah lunas tetapi belum diterima tidak
        // memakai slot, karena yang dihitung baru atlet terkonfirmasi.
        $this->createRegistration($event, User::factory()->create(), ['payment_status' => 'pending', 'status' => 'pending']);
        $this->createRegistration($event, User::factory()->create(), ['payment_status' => 'paid', 'status' => 'pending']);

        $this->assertSame(0, $event->confirmedCategoryCount('Junior Putra'));
        $this->assertSame(2, $event->categoryRemainingQuota('Junior Putra'));
        $this->assertTrue($event->isCategoryRegistrationOpen('Junior Putra'));

        $this->createRegistration($event, User::factory()->create(), ['payment_status' => 'paid', 'status' => 'approved']);

        $this->assertSame(1, $event->confirmedCategoryCount('Junior Putra'));
        $this->assertTrue($event->isCategoryRegistrationOpen('Junior Putra'));
        $this->assertSame(1, $event->categoryRemainingQuota('Junior Putra'));
    }

    public function test_a_full_category_closes_itself_once_the_quota_is_reached(): void
    {
        $event = $this->createEvent(maxRegistrants: 2);
        $confirmed = ['payment_status' => 'paid', 'status' => 'approved'];

        $this->createRegistration($event, User::factory()->create(), $confirmed);
        $this->createRegistration($event, User::factory()->create(), $confirmed);

        $this->assertTrue($event->categoryIsFull('Junior Putra'));
        $this->assertFalse($event->isCategoryRegistrationOpen('Junior Putra'));
        $this->assertSame(0, $event->categoryRemainingQuota('Junior Putra'));
    }

    public function test_an_approved_cancellation_frees_the_slot_again(): void
    {
        $event = $this->createEvent(maxRegistrants: 1);
        $registration = $this->createRegistration($event, User::factory()->create(), [
            'payment_status' => 'paid',
            'status' => 'approved',
        ]);

        $this->assertTrue($event->categoryIsFull('Junior Putra'));

        $registration->update(['cancellation_status' => 'approved']);

        $this->assertSame(0, $event->confirmedCategoryCount('Junior Putra'));
        $this->assertFalse($event->categoryIsFull('Junior Putra'));
        $this->assertTrue($event->isCategoryRegistrationOpen('Junior Putra'));
    }

    public function test_the_organizer_can_force_a_category_closed_without_setting_a_quota(): void
    {
        $event = $this->createEvent();
        $this->assertNull($event->categoryQuota('Junior Putra'));
        $this->assertTrue($event->isCategoryRegistrationOpen('Junior Putra'));

        $rules = $event->category_rules;
        $rules[0]['registration_override'] = 'closed';
        $event->update(['category_rules' => $rules]);

        $this->assertFalse($event->fresh()->isCategoryRegistrationOpen('Junior Putra'));
    }

    public function test_the_organizer_can_reopen_a_full_category(): void
    {
        $event = $this->createEvent(maxRegistrants: 1);
        $this->createRegistration($event, User::factory()->create(), [
            'payment_status' => 'paid',
            'status' => 'approved',
        ]);
        $this->assertFalse($event->isCategoryRegistrationOpen('Junior Putra'));

        $rules = $event->category_rules;
        $rules[0]['registration_override'] = 'open';
        $event->update(['category_rules' => $rules]);

        $this->assertTrue($event->categoryIsFull('Junior Putra'));
        $this->assertTrue($event->isCategoryRegistrationOpen('Junior Putra'));
    }

    public function test_the_organizer_can_store_the_quota_and_the_override_from_the_category_form(): void
    {
        $event = $this->createEvent();
        $owner = $this->ownerOf($event);

        $this->actingAs($owner)
            ->put(route('owner.events.categories.update', $event), $this->categoryFormPayload([
                'max_registrants' => '12',
                'registration_override' => 'closed',
            ]))
            ->assertRedirect(route('guest.dashboard'));

        $rule = $event->fresh()->category_rules[0];

        $this->assertSame(12, $rule['max_registrants']);
        $this->assertSame('closed', $rule['registration_override']);
    }

    public function test_an_empty_quota_is_stored_as_no_limit(): void
    {
        $event = $this->createEvent();
        $owner = $this->ownerOf($event);

        $this->actingAs($owner)
            ->put(route('owner.events.categories.update', $event), $this->categoryFormPayload(['max_registrants' => '']))
            ->assertRedirect(route('guest.dashboard'));

        $this->assertNull($event->fresh()->category_rules[0]['max_registrants']);
    }

    public function test_a_full_category_still_accepts_a_new_registration(): void
    {
        $event = $this->createEvent(maxRegistrants: 1);
        $this->createRegistration($event, User::factory()->create(), [
            'payment_status' => 'paid',
            'status' => 'approved',
        ]);
        $this->assertTrue($event->categoryIsFull('Junior Putra'));

        $rules = $event->category_rules;
        $rules[0]['required_races'] = 1;
        $rules[0]['package_fee'] = 100000;
        $event->update(['category_rules' => $rules]);

        $participant = User::factory()->create(['role' => 'individual']);

        $this->actingAs($participant)
            ->post(route('events.registration.store', $event), [
                'nik' => $this->nikFor('male', '2014-01-01', 999),
                'participant_name' => 'Atlet Menunggu Slot',
                'gender' => 'male',
                'date_of_birth' => '2014-01-01',
                'address' => 'Tulungagung',
                'guardian_name' => 'Wali Atlet',
                'whatsapp' => '081234567891',
                'races' => ['Classic Slalom'],
            ])
            ->assertRedirect(route('guest.dashboard'));

        // Biodata tetap tersimpan walau kategori sudah penuh, supaya NIK-nya
        // bisa dipakai lagi pada event lain.
        $this->assertDatabaseHas('event_registrations', [
            'event_id' => $event->id,
            'user_id' => $participant->id,
            'participant_name' => 'Atlet Menunggu Slot',
        ]);
    }

    public function test_a_closed_event_rejects_new_registrations(): void
    {
        $event = $this->createEvent();
        $event->update(['registration_open' => false]);

        $participant = User::factory()->create(['role' => 'individual']);

        $this->actingAs($participant)
            ->post(route('events.registration.store', $event), [
                'nik' => $this->nikFor('male', '2014-01-01', 111),
                'participant_name' => 'Atlet Terlambat',
                'gender' => 'male',
                'date_of_birth' => '2014-01-01',
                'address' => 'Tulungagung',
                'guardian_name' => 'Wali Atlet',
                'whatsapp' => '081234567892',
                'races' => ['Classic Slalom'],
            ])
            ->assertStatus(409);

        $this->assertDatabaseMissing('event_registrations', [
            'event_id' => $event->id,
            'user_id' => $participant->id,
        ]);
    }

    public function test_the_organizer_can_close_and_reopen_the_whole_event_from_the_dashboard(): void
    {
        $event = $this->createEvent();
        $owner = $this->ownerOf($event);

        $this->actingAs($owner)
            ->from(route('guest.dashboard'))
            ->patch(route('event-applications.toggle-registration', $event), ['registration_open' => '0'])
            ->assertRedirect(route('guest.dashboard'));

        $this->assertFalse($event->fresh()->registration_open);

        $this->actingAs($owner)
            ->from(route('guest.dashboard'))
            ->patch(route('event-applications.toggle-registration', $event), ['registration_open' => '1'])
            ->assertRedirect(route('guest.dashboard'));

        $this->assertTrue($event->fresh()->registration_open);
    }

    public function test_a_stranger_cannot_toggle_the_event_registration(): void
    {
        $event = $this->createEvent();
        $stranger = User::factory()->create(['role' => 'club_manager']);

        $this->actingAs($stranger)
            ->patch(route('event-applications.toggle-registration', $event), ['registration_open' => '0'])
            ->assertForbidden();

        $this->assertTrue($event->fresh()->registration_open);
    }

    public function test_the_dashboard_shows_the_release_and_registration_buttons_to_the_owner(): void
    {
        $event = $this->createEvent();
        $owner = $this->ownerOf($event);

        $this->actingAs($owner)
            ->get(route('guest.dashboard'))
            ->assertOk()
            ->assertSee('Rilis daftar peserta sekarang')
            ->assertSee('Tutup pendaftaran event');

        $event->update(['registration_open' => false]);

        $this->actingAs($owner)
            ->get(route('guest.dashboard'))
            ->assertOk()
            ->assertSee('Buka pendaftaran event');
    }

    public function test_the_registration_form_labels_quota_status_by_category_name(): void
    {
        $event = $this->createEvent();
        $this->createRegistration($event, User::factory()->create(), [
            'payment_status' => 'paid',
            'status' => 'approved',
        ]);
        $rules = $event->category_rules;
        $rules[0]['max_registrants'] = 1;
        $event->update(['category_rules' => $rules]);

        $participant = User::factory()->create(['role' => 'individual']);

        // Pencocokan di sisi klien memakai nama kategori sebagai kunci, sehingga
        // JSON-nya harus berupa objek bernama kategori, bukan daftar bernomor.
        $statuses = $this->categoryStatusesFrom($participant, $event);

        $this->assertArrayHasKey('Junior Putra', $statuses);
        $this->assertFalse($statuses['Junior Putra']['open']);
        $this->assertSame(1, $statuses['Junior Putra']['quota']);
    }

    /**
     * Membaca atribut data-category-statuses dari halaman pendaftaran dan
     * mengembalikannya sebagai array, persis seperti yang dibaca skrip klien.
     *
     * @return array<string, array<string, mixed>>
     */
    private function categoryStatusesFrom(User $participant, Event $event): array
    {
        $html = $this->actingAs($participant)
            ->get(route('events.registration.create', $event))
            ->assertOk()
            ->getContent();

        preg_match('/data-category-statuses="([^"]*)"/', $html, $matches);

        $this->assertNotEmpty($matches, 'Halaman pendaftaran tidak menyertakan status batas peserta.');

        return json_decode(html_entity_decode($matches[1]), true);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function categoryFormPayload(array $overrides = []): array
    {
        return [
            'category_rules' => [[
                'name' => 'Junior Putra',
                'base_name' => 'Junior',
                'gender_group' => 'male',
                'eligibility_types' => ['birth_date'],
                'birth_date_from' => '2010-01-01',
                'birth_date_to' => '2020-12-31',
                'genders' => ['male'],
                'races' => ['Classic Slalom', 'Speed Slalom'],
                'required_races' => 1,
                'package_fee' => 0,
                ...$overrides,
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createRegistration(Event $event, User $participant, array $attributes = []): EventRegistration
    {
        $nik = $this->nikFor('male', '2014-01-01', random_int(1, 9999));

        return EventRegistration::create([
            'event_id' => $event->id,
            'user_id' => $participant->id,
            'participant_name' => 'Atlet Berpaket',
            'nik' => $nik,
            'nik_hash' => hash_hmac('sha256', $nik, (string) config('app.key')),
            'gender' => 'male',
            'date_of_birth' => '2014-01-01',
            'address' => 'Tulungagung',
            'guardian_name' => 'Wali Atlet',
            'whatsapp' => '081234567890',
            'birth_year' => 2014,
            'club_name' => 'Klub Roda',
            'category' => 'Junior Putra',
            'races' => ['Classic Slalom'],
            'status' => 'pending',
            ...$attributes,
        ]);
    }

    private function createEvent(?int $maxRegistrants = null): Event
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $startDate = today()->addMonth();

        $event = Event::create([
            'submitted_by' => $owner->id,
            'name' => 'Kejuaraan Papan Kucing',
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $startDate->copy()->addDay()->format('Y-m-d'),
            'venue' => 'GOR Tulungagung',
            'venue_address' => 'Tulungagung',
            'organizer_whatsapp' => '081111111111',
            'scale' => 'Kabupaten',
            'categories' => ['Junior Putra'],
            'category_rules' => [[
                'name' => 'Junior Putra',
                'eligibility_type' => 'birth_date',
                'birth_date_from' => '2010-01-01',
                'birth_date_to' => '2020-12-31',
                'genders' => ['male'],
                'races' => ['Classic Slalom', 'Speed Slalom'],
                'required_races' => 1,
                'max_registrants' => $maxRegistrants,
                'registration_override' => 'auto',
                'package_fee' => 0,
            ]],
            'races' => ['Classic Slalom', 'Speed Slalom'],
            'registration_deadline' => $startDate->copy()->subDay()->format('Y-m-d'),
            'schedule' => ['Pembukaan', 'Final'],
            'status' => 'approved',
            'registration_open' => true,
        ]);

        return $event;
    }

    private function ownerOf(Event $event): User
    {
        return User::query()->findOrFail($event->submitted_by);
    }
}
