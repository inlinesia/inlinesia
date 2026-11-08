<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\RaceHeatBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventRegistrationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_owner_can_update_athlete_data_and_confirm_payment_from_the_roster(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $participant = User::factory()->create(['role' => 'individual']);
        $event = $this->createEvent($owner);
        $registration = $this->createRegistration($event, $participant);
        $registration->update([
            'payment_status' => 'pending',
            'payment_channel' => 'whatsapp',
            'category' => null,
        ]);

        $this->actingAs($owner)
            ->get(route('guest.dashboard'))
            ->assertSee(route('events.registrations.index', $event), false)
            ->assertSee('Kelola peserta & pembayaran');

        $this->get(route('events.registrations.index', $event))
            ->assertOk()
            ->assertSee($registration->participant_name)
            ->assertSee('Menunggu konfirmasi')
            ->assertSee('data-registration-modal-open="ubah-peserta-'.$registration->id.'"', false)
            ->assertSee(route('events.registrations.update', [$event, $registration]), false)
            ->assertSee(route('events.registrations.payment.confirm', [$event, $registration]), false)
            ->assertSee('Konfirmasi pembayaran');

        $this->from(route('events.registrations.index', $event))
            ->put(route('events.registrations.update', [$event, $registration]), $this->validUpdate([
                'nik' => $this->nikFor('female', '2014-01-01'),
                'gender' => 'female',
                'status' => 'approved',
            ]))
            ->assertRedirect(route('events.registrations.index', $event))
            ->assertSessionHas('status');

        $this->post(route('events.registrations.payment.confirm', [$event, $registration]))
            ->assertRedirect(route('events.registrations.index', $event))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('event_registrations', [
            'id' => $registration->id,
            'category' => 'Junior Putri',
            'gender' => 'female',
            'status' => 'approved',
            'payment_status' => 'paid',
        ]);
    }

    public function test_admin_can_edit_a_released_roster_but_not_publish_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'club_manager']);
        $event = $this->createEvent($owner);
        $event->update(['participants_published' => true]);
        $registration = $this->createRegistration($event, User::factory()->create(['role' => 'individual']));
        $registration->update(['payment_status' => 'paid']);

        $this->actingAs($admin)
            ->get(route('events.registrations.index', $event))
            ->assertOk()
            ->assertSee($registration->participant_name)
            ->assertSee('Menunggu konfirmasi')
            ->assertSee(route('events.registrations.update', [$event, $registration]), false)
            // Admin membuka pengaturan peserta, tetapi peran merilis daftar
            // tetap milik pemilik event.
            ->assertDontSee('Rilis daftar peserta');

        $this->from(route('events.registrations.index', $event))
            ->put(route('events.registrations.update', [$event, $registration]), $this->validUpdate([
                'participant_name' => 'Nama Diubah Admin',
            ]))->assertRedirect(route('events.registrations.index', $event));

        $this->assertDatabaseHas('event_registrations', [
            'id' => $registration->id,
            'participant_name' => 'Nama Diubah Admin',
        ]);
    }

    public function test_the_managed_roster_shows_full_biodata_and_a_whatsapp_link(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $event = $this->createEvent($owner);
        $registration = $this->createRegistration($event, User::factory()->create(['role' => 'individual']));
        $registration->update([
            'payment_status' => 'paid',
            'status' => 'approved',
            'category' => 'Junior Putra',
            // Alamat dibuat khas supaya tidak kebetulan sama dengan nama venue.
            'address' => 'Jalan Pribadi Nomor 7',
            'guardian_name' => 'Wali Pribadi',
        ]);

        $this->actingAs($owner)
            ->get(route('events.registrations.index', $event))
            ->assertOk()
            // Biodata lengkap yang tidak tampil di daftar publik.
            ->assertSee('NIK / paspor', false)
            ->assertSee('Tanggal lahir', false)
            ->assertSee('Asal daerah', false)
            ->assertSee('Wali', false)
            ->assertSee('Wali Pribadi')
            ->assertSee('Jalan Pribadi Nomor 7')
            // Kontak menjadi tombol yang mengarah langsung ke WhatsApp.
            ->assertSee('https://wa.me/6281234567890', false)
            ->assertSee('<span>Kontak</span>', false)
            ->assertSee('Cetak A4')
            ->assertSee(route('events.registrations.export', $event));
    }

    public function test_the_public_roster_stays_free_of_biodata_and_contact_details(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $participant = User::factory()->create(['role' => 'individual']);
        $event = $this->createEvent($owner);
        $registration = $this->createRegistration($event, $participant);
        // Alamat dibuat khas supaya tidak kebetulan sama dengan nama venue.
        $registration->update([
            'payment_status' => 'paid',
            'address' => 'Jalan Pribadi Nomor 7',
            'guardian_name' => 'Wali Pribadi',
            'whatsapp' => '0899999999',
        ]);
        $event->update(['participants_published' => true]);

        $this->actingAs($participant)
            ->get(route('events.registrations.index', $event))
            ->assertOk()
            ->assertSee('Atlet Terdaftar')
            ->assertDontSee('NIK / paspor', false)
            ->assertDontSee('Wali Pribadi')
            ->assertDontSee('Jalan Pribadi Nomor 7')
            ->assertDontSee('wa.me/6289999999', false)
            ->assertDontSee('0899999999');
    }

    public function test_the_public_roster_can_be_searched_by_name_or_club(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $participant = User::factory()->create(['role' => 'individual']);
        $event = $this->createEvent($owner);
        $first = $this->createRegistration($event, $participant, 'Rani Oktaviani');
        $second = $this->createRegistration($event, User::factory()->create(['role' => 'individual']), 'Bagas Pratama');
        $first->update(['payment_status' => 'paid', 'club_name' => 'Klub Speed']);
        $second->update(['payment_status' => 'paid', 'club_name' => 'Klub Freestyle']);
        $event->update(['participants_published' => true]);

        $this->actingAs($participant)
            ->get(route('events.registrations.index', ['event' => $event, 'q' => 'Rani']))
            ->assertOk()
            ->assertSee('Rani Oktaviani')
            ->assertDontSee('Bagas Pratama');

        $this->actingAs($participant)
            ->get(route('events.registrations.index', ['event' => $event, 'q' => 'Freestyle']))
            ->assertOk()
            ->assertSee('Bagas Pratama')
            ->assertDontSee('Rani Oktaviani');
    }

    /**
     * Peserta biasa tetap boleh menyaring daftar yang sudah dirilis, tetapi
     * hanya pada kolom yang memang mereka lihat.
     */
    public function test_the_released_roster_can_be_filtered_by_category_race_and_gender(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $participant = User::factory()->create(['role' => 'individual']);
        $event = $this->createEvent($owner);
        $first = $this->createRegistration($event, $participant, 'Atlet Mawar', '3201010101100011');
        $second = $this->createRegistration($event, User::factory()->create(['role' => 'individual']), 'Atlet Melati', '3201010101100022');
        $first->update([
            'payment_status' => 'paid',
            'category' => 'Junior Putra',
            'races' => ['Classic Slalom'],
            'gender' => 'male',
        ]);
        $second->update([
            'payment_status' => 'paid',
            'category' => 'Junior Putri',
            'races' => ['Speed Slalom'],
            'gender' => 'female',
        ]);
        $event->update(['participants_published' => true]);

        $this->actingAs($participant);

        $this->get(route('events.registrations.index', ['event' => $event, 'category' => 'Junior Putra']))
            ->assertOk()->assertSee('Atlet Mawar')->assertDontSee('Atlet Melati');

        $this->get(route('events.registrations.index', ['event' => $event, 'race' => 'Speed Slalom']))
            ->assertOk()->assertSee('Atlet Melati')->assertDontSee('Atlet Mawar');

        $this->get(route('events.registrations.index', ['event' => $event, 'gender' => 'female']))
            ->assertOk()->assertSee('Atlet Melati')->assertDontSee('Atlet Mawar');

        // Ketiga saringan ditawarkan, sementara saringan pembayaran dan status
        // tidak: kolom itu tidak pernah ditampilkan kepada peserta.
        $this->get(route('events.registrations.index', $event))
            ->assertOk()
            ->assertSee('name="category"', false)
            ->assertSee('name="race"', false)
            ->assertSee('name="gender"', false)
            ->assertDontSee('name="payment"', false)
            ->assertDontSee('name="status"', false);
    }

    public function test_the_fee_is_shown_only_to_the_event_manager(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $participant = User::factory()->create(['role' => 'individual']);
        $event = $this->createEvent($owner);
        $registration = $this->createRegistration($event, $participant);
        $registration->update([
            'payment_status' => 'paid',
            'category' => 'Junior Putra',
            'fee_total' => 175000,
        ]);
        $event->update(['participants_published' => true]);

        $this->actingAs($participant)
            ->get(route('events.registrations.index', $event))
            ->assertOk()
            ->assertDontSee('Biaya')
            ->assertDontSee('175.000');

        $this->actingAs($owner)
            ->get(route('events.registrations.index', $event))
            ->assertOk()
            ->assertSee('Biaya')
            ->assertSee('175.000');
    }

    /**
     * Pengelola memilih kategori mana yang ikut dicetak; peserta tidak
     * mengontrol apa pun karena daftar yang mereka cetak selalu lengkap.
     */
    public function test_only_a_manager_gets_the_printable_category_toggles(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $participant = User::factory()->create(['role' => 'individual']);
        $event = $this->createEvent($owner);
        $registration = $this->createRegistration($event, $participant);
        $registration->update(['payment_status' => 'paid', 'category' => 'Junior Putra']);
        $event->update(['participants_published' => true]);

        $this->actingAs($owner)
            ->get(route('events.registrations.index', $event))
            ->assertOk()
            ->assertSee('data-category-toggle="Junior Putra"', false)
            ->assertSee('Hanya kategori aktif yang ikut dicetak');

        $this->actingAs($participant)
            ->get(route('events.registrations.index', $event))
            ->assertOk()
            ->assertDontSee('data-category-toggle', false);
    }

    /**
     * Daftar di layar dibagi lima puluh per halaman; halaman Export PDF tidak
     * dipaginasi sama sekali, dan kolomnya mengikuti peran yang melihatnya.
     */
    public function test_the_export_page_lists_every_registration_without_pagination(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $participant = User::factory()->create(['role' => 'individual']);
        $event = $this->createEvent($owner);

        foreach (range(1, 55) as $index) {
            $registration = $this->createRegistration(
                $event,
                User::factory()->create(['role' => 'individual']),
                sprintf('Atlet %02d', $index),
                '3201010101100'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
            );
            $registration->update(['payment_status' => 'paid', 'category' => 'Junior Putra']);
        }

        $unpaid = $this->createRegistration($event, $participant, 'Atlet Belum Bayar', '3201010101100999');

        $this->actingAs($owner)
            ->get(route('events.registrations.index', $event))
            ->assertOk()
            ->assertSee('Atlet 50')
            ->assertDontSee('Atlet 51');

        // Pengelola melihat seluruh daftar beserta kolom lengkapnya.
        $this->actingAs($owner)
            ->get(route('events.registrations.export', $event))
            ->assertOk()
            ->assertSee('Atlet 51')
            ->assertSee('Atlet 55')
            ->assertSee('NIK / paspor')
            ->assertSee('Biaya')
            ->assertSee('Atlet Belum Bayar');

        // Sebelum daftar dirilis, peserta biasa tidak boleh membuka halaman
        // export — sama seperti daftar biasanya.
        $this->actingAs($participant)
            ->get(route('events.registrations.export', $event))
            ->assertNotFound();

        $event->update(['participants_published' => true]);

        // Peserta hanya melihat daftar yang sudah dirilis dan sudah dibayar,
        // tanpa kolom biodata dan biaya.
        $this->actingAs($participant)
            ->get(route('events.registrations.export', $event))
            ->assertOk()
            ->assertSee('Atlet 01')
            ->assertDontSee('Atlet Belum Bayar')
            ->assertDontSee('NIK / paspor')
            ->assertDontSee('Biaya');

        $this->assertNotNull($unpaid);
    }

    public function test_roster_rows_are_paginated_fifty_at_a_time_for_printing(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $event = $this->createEvent($owner);

        // Penomoran dipadding supaya urutan alfabet sama dengan urutan angka.
        foreach (range(1, 55) as $index) {
            $registration = $this->createRegistration(
                $event,
                User::factory()->create(['role' => 'individual']),
                sprintf('Atlet %03d', $index),
            );
            $registration->update(['payment_status' => 'paid']);
        }

        $this->actingAs($owner)
            ->get(route('events.registrations.index', $event))
            ->assertOk()
            ->assertSee('Atlet 001')
            ->assertSee('Atlet 050')
            ->assertDontSee('Atlet 051')
            ->assertSee('55 atlet terdaftar');

        $this->get(route('events.registrations.index', ['event' => $event, 'page' => 2]))
            ->assertOk()
            ->assertSee('Atlet 051')
            ->assertSee('Atlet 055')
            ->assertDontSee('Atlet 050');
    }

    public function test_verified_non_owner_can_read_only_a_released_roster_without_sensitive_fields(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $participant = User::factory()->create(['role' => 'individual']);
        $otherParticipant = User::factory()->create(['role' => 'individual']);
        $event = $this->createEvent($owner);
        $event->update(['participants_published' => true]);
        $ownRegistration = $this->createRegistration($event, $participant, 'Atlet Saya');
        $otherRegistration = $this->createRegistration($event, $otherParticipant, 'Atlet Orang Lain', $this->nikFor('male', '2014-01-01', 2));
        $ownRegistration->update(['payment_status' => 'paid']);
        $otherRegistration->update(['payment_status' => 'paid']);

        $this->actingAs($participant)
            ->get(route('events.registrations.index', $event))
            ->assertOk()
            ->assertSee('Atlet Saya')
            ->assertSee('Atlet Orang Lain')
            ->assertSee('Read-only')
            ->assertDontSee('Wali Atlet')
            ->assertDontSee('081234567890')
            ->assertDontSee($this->nikFor('male', '2014-01-01'))
            ->assertDontSee('Alamat pribadi sensitif')
            ->assertDontSee(route('events.registrations.update', [$event, $ownRegistration]), false);

        $this->put(route('events.registrations.update', [$event, $ownRegistration]), $this->validUpdate([
            'participant_name' => 'Nama yang diubah sendiri',
        ]))
            ->assertForbidden();

        $this->assertDatabaseHas('event_registrations', [
            'id' => $ownRegistration->id,
            'participant_name' => 'Atlet Saya',
        ]);
        $this->assertModelExists($otherRegistration);
    }

    public function test_public_visitor_cannot_view_any_participant_list(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $event = $this->createEvent($owner);
        $registration = $this->createRegistration($event, User::factory()->create(['role' => 'individual']));

        $this->get(route('events.index'))
            ->assertDontSee(route('events.registrations.index', $event), false)
            ->assertDontSee('Lihat peserta');

        $this->get(route('events.registrations.index', $event))
            ->assertRedirect(route('login'));

        $this->assertModelExists($registration);
    }

    public function test_unpublished_event_participant_list_is_not_publicly_accessible(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $event = $this->createEvent($owner);
        $event->update(['status' => 'pending']);

        $this->actingAs(User::factory()->create(['role' => 'individual']))
            ->get(route('events.registrations.index', $event))
            ->assertNotFound();
    }

    public function test_non_owner_with_registration_cannot_read_unreleased_roster(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $participant = User::factory()->create(['role' => 'individual']);
        $otherParticipant = User::factory()->create(['role' => 'individual']);
        $event = $this->createEvent($owner);
        $ownRegistration = $this->createRegistration($event, $participant, 'Atlet Saya');
        $otherRegistration = $this->createRegistration($event, $otherParticipant, 'Atlet Orang Lain', '3201010101100002');

        $this->actingAs($participant)
            ->get(route('events.registrations.index', $event))
            ->assertNotFound();

        $this->assertModelExists($ownRegistration);
        $this->assertModelExists($otherRegistration);
    }

    public function test_anonymous_visitor_cannot_view_an_admin_created_event_roster(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $event = $this->createEvent($admin);
        $event->update(['submitted_by' => null]);
        $registration = $this->createRegistration($event, User::factory()->create(['role' => 'individual']));

        $this->get(route('events.registrations.index', $event))
            ->assertRedirect(route('login'));

        $this->assertModelExists($registration);
    }

    public function test_event_owner_can_publish_and_revoke_a_roster_for_verified_accounts(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $viewer = User::factory()->create(['role' => 'individual']);
        $event = $this->createEvent($owner);
        $registration = $this->createRegistration($event, User::factory()->create(['role' => 'individual']));
        $registration->update(['payment_status' => 'paid']);

        $this->actingAs($owner)
            ->patch(route('events.registrations.publish', $event), ['participants_published' => '1'])
            ->assertRedirect(route('events.registrations.index', $event))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'participants_published' => true,
        ]);

        $this->actingAs($viewer)
            ->get(route('events.registrations.index', $event))
            ->assertOk()
            ->assertSee($registration->participant_name)
            ->assertDontSee('081234567890');

        $this->actingAs($owner)
            ->patch(route('events.registrations.publish', $event), ['participants_published' => '0'])
            ->assertRedirect(route('events.registrations.index', $event));

        $this->actingAs($viewer)
            ->get(route('events.registrations.index', $event))
            ->assertNotFound();
    }

    public function test_unverified_account_cannot_view_a_released_roster(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $viewer = User::factory()->unverified()->create(['role' => 'individual']);
        $event = $this->createEvent($owner);
        $event->update(['participants_published' => true]);

        $this->actingAs($viewer)
            ->get(route('events.registrations.index', $event))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_only_the_event_owner_can_publish_a_roster(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $admin = User::factory()->create(['role' => 'admin']);
        $event = $this->createEvent($owner);

        $this->actingAs($admin)
            ->patch(route('events.registrations.publish', $event), ['participants_published' => '1'])
            ->assertForbidden();

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'participants_published' => false,
        ]);
    }

    public function test_roster_cannot_be_published_before_the_event_is_approved(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $event = $this->createEvent($owner);
        $event->update(['status' => 'pending']);

        $this->actingAs($owner)
            ->patch(route('events.registrations.publish', $event), ['participants_published' => '1'])
            ->assertStatus(409);

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'participants_published' => false,
        ]);
    }

    public function test_participant_cannot_change_an_existing_registration_by_resubmitting_it(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $participant = User::factory()->create(['role' => 'individual']);
        $event = $this->createEvent($owner);
        $registration = $this->createRegistration($event, $participant, 'Nama Asli');

        $this->actingAs($participant)
            ->from(route('events.registration.create', $event))
            ->post(route('events.registration.store', $event), [
                'nik' => $this->nikFor('male', '2014-01-01'),
                'participant_name' => 'Nama Diganti',
                'nickname' => 'Atlet',
                'gender' => 'male',
                'date_of_birth' => '2014-01-01',
                'address' => 'Alamat pribadi sensitif',
                'guardian_name' => 'Wali Atlet',
                'whatsapp' => '081234567890',
                'club_name' => 'Klub Roda',
                'races' => ['Classic Slalom'],
            ])
            ->assertRedirect(route('events.registration.create', $event))
            ->assertSessionHasErrors('nik');

        $this->assertDatabaseCount('event_registrations', 1);
        $this->assertDatabaseHas('event_registrations', [
            'id' => $registration->id,
            'participant_name' => 'Nama Asli',
        ]);
    }

    public function test_owner_cannot_save_athlete_data_that_does_not_match_a_category(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $event = $this->createEvent($owner);
        $registration = $this->createRegistration($event, User::factory()->create(['role' => 'individual']));
        $registration->update(['payment_status' => 'paid']);

        $this->actingAs($owner)
            ->from(route('events.registrations.index', $event))
            ->put(route('events.registrations.update', [$event, $registration]), $this->validUpdate([
                'nik' => $this->nikFor('male', '2000-01-01'),
                'date_of_birth' => '2000-01-01',
            ]))
            ->assertRedirect(route('events.registrations.index', $event))
            ->assertSessionHasErrors('date_of_birth');

        $this->assertDatabaseHas('event_registrations', [
            'id' => $registration->id,
            'birth_year' => 2014,
            'payment_status' => 'paid',
        ]);
    }

    public function test_owner_cannot_change_payment_status_through_athlete_edit(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $event = $this->createEvent($owner);
        $registration = $this->createRegistration($event, User::factory()->create(['role' => 'individual']));

        $this->actingAs($owner)
            ->from(route('events.registrations.index', $event))
            ->put(route('events.registrations.update', [$event, $registration]), $this->validUpdate([
                'payment_status' => 'paid',
            ]))
            ->assertRedirect(route('events.registrations.index', $event))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('event_registrations', [
            'id' => $registration->id,

            'category' => null,
        ]);
    }

    public function test_owner_cannot_edit_a_registration_outside_the_event_in_the_url(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $event = $this->createEvent($owner);
        $otherEvent = $this->createEvent($owner, 'Kejuaraan Lain');
        $registration = $this->createRegistration($otherEvent, User::factory()->create(['role' => 'individual']));

        $this->actingAs($owner)
            ->put(route('events.registrations.update', [$event, $registration]), $this->validUpdate())
            ->assertNotFound();
    }

    public function test_payment_proof_is_private_and_category_is_assigned_only_after_owner_confirmation(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['role' => 'club_manager']);
        $participant = User::factory()->create(['role' => 'individual']);
        $event = $this->createEvent($owner);
        $registration = $this->createRegistration($event, $participant);
        $registration->update(['category' => null]);

        $this->actingAs(User::factory()->create(['role' => 'individual']))
            ->post(route('events.registration.proof.store', [$event, $registration]), [
                'payment_proof' => UploadedFile::fake()->createWithContent('transfer.pdf', "%PDF-1.4\nBukti transfer\n%%EOF"),
            ])
            ->assertForbidden();

        $this->actingAs($participant)
            ->post(route('events.registration.proof.store', [$event, $registration]), [
                'payment_proof' => UploadedFile::fake()->createWithContent('transfer.pdf', "%PDF-1.4\nBukti transfer\n%%EOF"),
            ])
            ->assertRedirect();

        $registration->refresh();
        $this->assertSame('pending', $registration->payment_status);
        $this->assertSame('upload', $registration->payment_channel);
        $this->assertNull($registration->category);
        Storage::disk('local')->assertExists($registration->payment_proof_path);

        $this->actingAs(User::factory()->create(['role' => 'individual']))
            ->get(route('events.registrations.proof', [$event, $registration]))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('events.registrations.proof', [$event, $registration]))
            ->assertOk();

        $this->actingAs($owner)
            ->post(route('events.registrations.payment.confirm', [$event, $registration]))
            ->assertRedirect(route('events.registrations.index', $event))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('event_registrations', [
            'id' => $registration->id,
            'payment_status' => 'paid',
            'category' => 'Junior Putra',
        ]);
    }

    public function test_owner_cannot_confirm_payment_before_the_participant_submits_proof_or_whatsapp(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $event = $this->createEvent($owner);
        $registration = $this->createRegistration($event, User::factory()->create(['role' => 'individual']));

        $this->actingAs($owner)
            ->post(route('events.registrations.payment.confirm', [$event, $registration]))
            ->assertStatus(409);

        $this->assertDatabaseHas('event_registrations', [
            'id' => $registration->id,
            'payment_status' => 'unpaid',
            'category' => null,
        ]);
    }

    public function test_payment_proof_upload_rejects_unsupported_file_types(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['role' => 'club_manager']);
        $participant = User::factory()->create(['role' => 'individual']);
        $event = $this->createEvent($owner);
        $registration = $this->createRegistration($event, $participant);

        $this->actingAs($participant)
            ->from(route('guest.dashboard'))
            ->post(route('events.registration.proof.store', [$event, $registration]), [
                'payment_proof' => UploadedFile::fake()->createWithContent('proof.svg', '<svg></svg>'),
            ])
            ->assertRedirect(route('guest.dashboard'))
            ->assertSessionHasErrors('payment_proof');

        $this->assertDatabaseHas('event_registrations', [
            'id' => $registration->id,
            'payment_status' => 'unpaid',
            'payment_proof_path' => null,
        ]);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_free_event_registration_can_be_confirmed_via_owner_whatsapp(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager', 'phone' => '081234567890']);
        $participant = User::factory()->create(['role' => 'individual']);
        $event = $this->createEvent($owner);
        $event->update(['is_free' => true]);
        $registration = $this->createRegistration($event, $participant);
        $registration->update(['category' => null]);

        $response = $this->actingAs($participant)
            ->post(route('events.registration.whatsapp', [$event, $registration]));
        $response->assertRedirect();
        $this->assertSame('wa.me', parse_url($response->headers->get('Location'), PHP_URL_HOST));
        $this->assertSame('/6281111111111', parse_url($response->headers->get('Location'), PHP_URL_PATH));

        $this->assertDatabaseHas('event_registrations', [
            'id' => $registration->id,
            'payment_status' => 'pending',
            'payment_channel' => 'whatsapp',
            'category' => null,
        ]);

        $this->actingAs($owner)
            ->post(route('events.registrations.payment.confirm', [$event, $registration]))
            ->assertRedirect(route('events.registrations.index', $event))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('event_registrations', [
            'id' => $registration->id,
            'payment_status' => 'paid',
            'category' => 'Junior Putra',
        ]);
    }

    public function test_only_the_event_owner_can_confirm_a_participant_payment(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $otherManager = User::factory()->create(['role' => 'club_manager']);
        $event = $this->createEvent($owner);
        $registration = $this->createRegistration($event, User::factory()->create(['role' => 'individual']));
        $registration->update([
            'payment_status' => 'pending',
            'payment_channel' => 'whatsapp',
            'category' => null,
        ]);

        $this->actingAs($otherManager)
            ->post(route('events.registrations.payment.confirm', [$event, $registration]))
            ->assertForbidden();

        $this->assertDatabaseHas('event_registrations', [
            'id' => $registration->id,
            'payment_status' => 'pending',
            'category' => null,
        ]);
    }

    public function test_owner_cannot_confirm_a_registration_from_another_event(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $event = $this->createEvent($owner);
        $otherEvent = $this->createEvent($owner, 'Kejuaraan Lain');
        $registration = $this->createRegistration($otherEvent, User::factory()->create(['role' => 'individual']));

        $this->actingAs($owner)
            ->post(route('events.registrations.payment.confirm', [$event, $registration]))
            ->assertNotFound();

        $this->assertDatabaseHas('event_registrations', [
            'id' => $registration->id,
            'payment_status' => 'unpaid',
            'category' => null,
        ]);
    }

    public function test_owner_cannot_confirm_payment_for_a_registration_with_no_matching_category(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $event = $this->createEvent($owner);
        $registration = $this->createRegistration($event, User::factory()->create(['role' => 'individual']));
        $registration->update([
            'payment_status' => 'pending',
            'payment_channel' => 'whatsapp',
            'nik' => $this->nikFor('male', '2000-01-01', 5),
            'date_of_birth' => '2000-01-01',
            'category' => null,
        ]);

        $this->actingAs($owner)
            ->from(route('events.registrations.index', $event))
            ->post(route('events.registrations.payment.confirm', [$event, $registration]))
            ->assertRedirect(route('events.registrations.index', $event))
            ->assertSessionHasErrors([
                'payment_status' => 'Data peserta tidak sesuai dengan kategori dan mata lomba event. Periksa data peserta sebelum konfirmasi.',
            ]);

        $this->assertDatabaseHas('event_registrations', [
            'id' => $registration->id,
            'payment_status' => 'pending',
            'category' => null,
        ]);
    }

    public function test_paid_payment_confirmation_opens_the_event_whatsapp_number(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager', 'phone' => '081234567890']);
        $participant = User::factory()->create(['role' => 'individual']);
        $event = $this->createEvent($owner);
        $registration = $this->createRegistration($event, $participant);

        $response = $this->actingAs($participant)
            ->post(route('events.registration.whatsapp', [$event, $registration]));
        $response->assertRedirect();

        $destination = $response->headers->get('Location');
        $this->assertSame('/6281111111111', parse_url($destination, PHP_URL_PATH));
        parse_str((string) parse_url($destination, PHP_URL_QUERY), $query);
        $this->assertStringContainsString('mengirimkan bukti transfer', $query['text']);
        $this->assertDatabaseHas('event_registrations', [
            'id' => $registration->id,
            'payment_status' => 'pending',
            'payment_channel' => 'whatsapp',
        ]);
    }

    public function test_owner_can_assign_categories_to_every_paid_participant_in_one_click(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $event = $this->createEvent($owner);

        $male = $this->createRegistration($event, User::factory()->create(['role' => 'individual']), 'Atlet Putra');
        $female = $this->createRegistration($event, User::factory()->create(['role' => 'individual']), 'Atlet Putri', '3201010101100009');
        $awaitingPayment = $this->createRegistration($event, User::factory()->create(['role' => 'individual']), 'Atlet Belum Bayar', '3201010101100010');

        $male->update(['payment_status' => 'paid']);
        $female->update(['payment_status' => 'paid', 'gender' => 'female']);

        $this->actingAs($owner)
            ->get(route('events.registrations.index', $event))
            ->assertOk()
            ->assertSee('Bagikan kategori otomatis')
            ->assertSee(route('events.registrations.categories.assign', $event), false);

        $this->actingAs($owner)
            ->from(route('events.registrations.index', $event))
            ->post(route('events.registrations.categories.assign', $event))
            ->assertRedirect(route('events.registrations.index', $event))
            ->assertSessionHas('status')
            ->assertSessionHas('category_summary');

        $this->assertDatabaseHas('event_registrations', [
            'id' => $male->id,
            'category' => 'Junior Putra',
        ]);
        $this->assertDatabaseHas('event_registrations', [
            'id' => $female->id,
            'category' => 'Junior Putri',
        ]);

        // Peserta yang pembayarannya belum terkonfirmasi tidak boleh ikut
        // diberi kategori, sama seperti aturan saat pendaftaran disimpan.
        $this->assertDatabaseHas('event_registrations', [
            'id' => $awaitingPayment->id,
            'category' => null,
        ]);
    }

    public function test_category_assignment_reports_participants_that_cannot_be_categorised(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $event = $this->createEvent($owner);

        $incomplete = $this->createRegistration($event, User::factory()->create(['role' => 'individual']), 'Atlet Tanpa Mata Lomba', '3201010101100011');
        $incomplete->update(['payment_status' => 'paid', 'races' => []]);

        $noMatch = $this->createRegistration($event, User::factory()->create(['role' => 'individual']), 'Atlet Terlalu Tua', $this->nikFor('male', '2000-01-01', 12));
        $noMatch->update(['payment_status' => 'paid', 'date_of_birth' => '2000-01-01']);

        $this->actingAs($owner)
            ->from(route('events.registrations.index', $event))
            ->post(route('events.registrations.categories.assign', $event))
            ->assertRedirect(route('events.registrations.index', $event))
            ->assertSessionHas('status');

        $summary = session('category_summary');

        $this->assertSame(1, $summary['incomplete']);
        $this->assertSame(1, $summary['no_match']);
        $this->assertSame(0, $summary['assigned']);
        $this->assertCount(2, $summary['unassigned']);
        $this->assertSame(
            ['Atlet Tanpa Mata Lomba', 'Atlet Terlalu Tua'],
            array_column($summary['unassigned'], 'participant_name')
        );

        $this->assertDatabaseHas('event_registrations', ['id' => $incomplete->id, 'category' => null]);
        $this->assertDatabaseHas('event_registrations', ['id' => $noMatch->id, 'category' => null]);
    }

    public function test_category_assignment_moves_participants_when_the_category_rules_change(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $event = $this->createEvent($owner);
        $registration = $this->createRegistration($event, User::factory()->create(['role' => 'individual']));
        $registration->update(['payment_status' => 'paid', 'category' => 'Junior Putri']);

        $rules = $event->category_rules;
        $rules[0]['name'] = 'Junior Putri';
        $rules[0]['genders'] = ['female'];
        $rules[1]['name'] = 'Putar Dewasa';
        $rules[1]['genders'] = ['male'];
        $event->update(['category_rules' => $rules]);

        $this->actingAs($owner)
            ->post(route('events.registrations.categories.assign', $event))
            ->assertRedirect(route('events.registrations.index', $event));

        $this->assertDatabaseHas('event_registrations', [
            'id' => $registration->id,
            'category' => 'Putar Dewasa',
        ]);
    }

    public function test_category_assignment_is_forbidden_for_accounts_that_do_not_manage_the_event(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $other = User::factory()->create(['role' => 'club_manager']);
        $event = $this->createEvent($owner);
        $registration = $this->createRegistration($event, User::factory()->create(['role' => 'individual']));
        $registration->update(['payment_status' => 'paid']);

        $this->actingAs($other)
            ->post(route('events.registrations.categories.assign', $event))
            ->assertForbidden();

        // Admin boleh membagi kategori karena turut mengatur peserta.
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('events.registrations.categories.assign', $event))
            ->assertRedirect(route('events.registrations.index', $event));

        $this->assertDatabaseHas('event_registrations', [
            'id' => $registration->id,
            'category' => 'Junior Putra',
        ]);
    }

    public function test_category_assignment_is_blocked_for_unapproved_events(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $event = $this->createEvent($owner);
        $event->update(['status' => 'pending']);

        $this->actingAs($owner)
            ->post(route('events.registrations.categories.assign', $event))
            ->assertStatus(409);
    }

    public function test_roster_can_be_filtered_by_column(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $event = $this->createEvent($owner);
        $first = $this->createRegistration($event, User::factory()->create(['role' => 'individual']), 'Atlet Mawar', '3201010101100011');
        $second = $this->createRegistration($event, User::factory()->create(['role' => 'individual']), 'Atlet Melati', '3201010101100022');
        $first->update(['category' => 'Junior Putra', 'races' => ['Classic Slalom'], 'payment_status' => 'paid', 'status' => 'approved']);
        $second->update(['category' => 'Junior Putri', 'races' => ['Speed Slalom'], 'payment_status' => 'pending', 'status' => 'rejected']);

        $this->actingAs($owner);

        $this->get(route('events.registrations.index', ['event' => $event, 'category' => 'Junior Putra']))
            ->assertOk()->assertSee('Atlet Mawar')->assertDontSee('Atlet Melati');

        $this->get(route('events.registrations.index', ['event' => $event, 'race' => 'Speed Slalom']))
            ->assertOk()->assertSee('Atlet Melati')->assertDontSee('Atlet Mawar');

        $this->get(route('events.registrations.index', ['event' => $event, 'payment' => 'paid']))
            ->assertOk()->assertSee('Atlet Mawar')->assertDontSee('Atlet Melati');

        $this->get(route('events.registrations.index', ['event' => $event, 'status' => 'approved']))
            ->assertOk()->assertSee('Atlet Mawar')->assertDontSee('Atlet Melati');

        $this->get(route('events.registrations.index', ['event' => $event, 'q' => 'Atlet Melati']))
            ->assertOk()->assertSee('Atlet Melati')->assertDontSee('Atlet Mawar');
    }

    public function test_roster_can_be_sorted_by_column(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $event = $this->createEvent($owner);
        $this->createRegistration($event, User::factory()->create(['role' => 'individual']), 'Atlet Bravo', '3201010101100033');
        $this->createRegistration($event, User::factory()->create(['role' => 'individual']), 'Atlet Alpha', '3201010101100044');

        $this->actingAs($owner);

        $this->get(route('events.registrations.index', ['event' => $event, 'sort' => 'nama', 'direction' => 'asc']))
            ->assertOk()
            ->assertSeeInOrder(['Atlet Alpha', 'Atlet Bravo']);

        $this->get(route('events.registrations.index', ['event' => $event, 'sort' => 'nama', 'direction' => 'desc']))
            ->assertOk()
            ->assertSeeInOrder(['Atlet Bravo', 'Atlet Alpha']);
    }

    public function test_participant_can_update_an_unpaid_registration_and_must_resubmit_payment_proof(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['role' => 'club_manager']);
        $participant = User::factory()->create(['role' => 'individual']);
        $event = $this->createEvent($owner);
        $registration = $this->createRegistration($event, $participant);
        Storage::disk('local')->put('payment-proofs/previous.pdf', 'previous proof');
        $registration->update([
            'payment_status' => 'pending',
            'payment_channel' => 'upload',
            'payment_proof_path' => 'payment-proofs/previous.pdf',
        ]);

        $this->actingAs($participant)
            ->put(route('events.registration.update-own', [$event, $registration]), $this->validUpdate([
                'participant_name' => 'Nama Atlet Baru',
            ]))
            ->assertRedirect(route('guest.dashboard'))
            ->assertSessionHas('status');

        $registration->refresh();
        $this->assertSame('Nama Atlet Baru', $registration->participant_name);
        $this->assertSame('unpaid', $registration->payment_status);
        $this->assertNull($registration->payment_proof_path);
        Storage::disk('local')->assertMissing('payment-proofs/previous.pdf');
    }

    public function test_paid_change_requires_owner_approval_and_collects_the_new_price_difference(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['role' => 'club_manager']);
        $participant = User::factory()->create(['role' => 'individual']);
        $event = $this->createEvent($owner);
        $this->setCategoryPricing($event);
        $registration = $this->createRegistration($event, $participant);
        $this->markPaid($registration, 100000, ['Classic Slalom']);

        $this->actingAs($participant)
            ->put(route('events.registration.update-own', [$event, $registration]), $this->validUpdate([
                'races' => ['Classic Slalom', 'Speed Slalom'],
                'participant_name' => 'Nama Atlet Diperbarui',
            ]))
            ->assertRedirect(route('guest.dashboard'));

        $registration->refresh();
        $this->assertSame('Atlet Terdaftar', $registration->participant_name);
        $this->assertSame('pending', $registration->participant_change_status);
        $this->assertSame($this->nikFor('male', '2014-01-01'), $registration->participant_change_request['nik']);
        $this->assertStringNotContainsString(
            $this->nikFor('male', '2014-01-01'),
            (string) DB::table('event_registrations')
                ->where('id', $registration->id)
                ->value('participant_change_request'),
        );

        $this->actingAs($owner)
            ->post(route('events.registrations.changes.review', [$event, $registration]), ['decision' => 'approve'])
            ->assertRedirect(route('events.registrations.index', $event));

        $registration->refresh();
        $this->assertSame('Nama Atlet Diperbarui', $registration->participant_name);
        $this->assertSame('approved', $registration->participant_change_status);
        $this->assertSame('pending', $registration->payment_status);
        $this->assertSame(150000, $registration->fee_total);
        $this->assertSame(50000, $registration->amountDue());
        $this->assertDatabaseHas('event_registration_financial_entries', [
            'event_registration_id' => $registration->id,
            'type' => 'payment',
            'amount' => 100000,
        ]);

        $this->actingAs($participant)
            ->post(route('events.registration.proof.store', [$event, $registration]), [
                'payment_proof' => UploadedFile::fake()->createWithContent('extra.pdf', "%PDF-1.4\nBukti pelunasan\n%%EOF"),
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->post(route('events.registrations.payment.confirm', [$event, $registration]))
            ->assertRedirect(route('events.registrations.index', $event));

        $this->assertSame(150000, $event->confirmedRegistrationIncome());
        $this->assertDatabaseHas('event_registration_financial_entries', [
            'event_registration_id' => $registration->id,
            'type' => 'payment',
            'amount' => 50000,
            'description' => 'Pelunasan selisih biaya pendaftaran',
        ]);
    }

    public function test_paid_change_with_lower_price_records_a_refund_in_event_finance(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $participant = User::factory()->create(['role' => 'individual']);
        $event = $this->createEvent($owner);
        $this->setCategoryPricing($event);
        $registration = $this->createRegistration($event, $participant);
        $this->markPaid($registration, 150000, ['Classic Slalom', 'Speed Slalom']);

        $this->actingAs($participant)
            ->put(route('events.registration.update-own', [$event, $registration]), $this->validUpdate([
                'races' => ['Classic Slalom'],
            ]))
            ->assertRedirect(route('guest.dashboard'));

        $this->actingAs($owner)
            ->post(route('events.registrations.changes.review', [$event, $registration]), ['decision' => 'approve'])
            ->assertRedirect(route('events.registrations.index', $event));

        $registration->refresh();
        $this->assertSame(100000, $registration->fee_total);
        $this->assertSame('paid', $registration->payment_status);
        $this->assertSame(50000, $registration->refund_amount);
        $this->assertSame(100000, $event->confirmedRegistrationIncome());

        $this->actingAs($owner)
            ->get(route('owner.events.finance.index', $event))
            ->assertSee('refund Rp 50.000');
    }

    public function test_owner_can_reject_a_paid_change_without_changing_registration_data(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $participant = User::factory()->create(['role' => 'individual']);
        $event = $this->createEvent($owner);
        $registration = $this->createRegistration($event, $participant);
        $this->markPaid($registration, 100000, ['Classic Slalom']);

        $this->actingAs($participant)
            ->put(route('events.registration.update-own', [$event, $registration]), $this->validUpdate([
                'participant_name' => 'Perubahan yang diminta',
            ]))
            ->assertRedirect(route('guest.dashboard'));

        $this->actingAs($owner)
            ->post(route('events.registrations.changes.review', [$event, $registration]), ['decision' => 'reject'])
            ->assertRedirect(route('events.registrations.index', $event));

        $registration->refresh();
        $this->assertSame('Atlet Terdaftar', $registration->participant_name);
        $this->assertSame('paid', $registration->payment_status);
        $this->assertSame('rejected', $registration->participant_change_status);
        $this->assertNull($registration->participant_change_request);
        $this->assertDatabaseMissing('event_registration_financial_entries', [
            'event_registration_id' => $registration->id,
        ]);
    }

    public function test_paid_cancellation_requires_owner_decision_and_refund_is_recorded(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $participant = User::factory()->create(['role' => 'individual']);
        $stranger = User::factory()->create(['role' => 'club_manager']);
        $event = $this->createEvent($owner);
        $registration = $this->createRegistration($event, $participant);
        $registration->update(['status' => 'approved']);
        $this->markPaid($registration, 150000, ['Classic Slalom']);

        $this->actingAs($participant)
            ->delete(route('events.registration.destroy-own', [$event, $registration]))
            ->assertRedirect(route('guest.dashboard'));

        $registration->refresh();
        $this->assertModelExists($registration);
        $this->assertSame('pending', $registration->cancellation_status);

        $this->actingAs($stranger)
            ->post(route('events.registrations.cancellations.review', [$event, $registration]), [
                'decision' => 'approve',
                'refund_amount' => 60000,
            ])
            ->assertForbidden();

        $this->actingAs($owner)
            ->post(route('events.registrations.cancellations.review', [$event, $registration]), [
                'decision' => 'approve',
                'refund_amount' => 60000,
            ])
            ->assertRedirect(route('events.registrations.index', $event));

        $registration->refresh();
        $this->assertSame('approved', $registration->cancellation_status);
        $this->assertSame(60000, $registration->refund_amount);
        $this->assertSame(90000, $event->confirmedRegistrationIncome());
        $this->assertDatabaseHas('event_registration_financial_entries', [
            'event_registration_id' => $registration->id,
            'type' => 'refund',
            'amount' => 60000,
        ]);
        $this->assertTrue(app(RaceHeatBuilder::class)->entrantsFor($event, 'Junior Putra', 'Classic Slalom')->isEmpty());
    }

    public function test_refund_cannot_exceed_the_participant_payment(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $participant = User::factory()->create(['role' => 'individual']);
        $event = $this->createEvent($owner);
        $registration = $this->createRegistration($event, $participant);
        $this->markPaid($registration, 50000, ['Classic Slalom']);
        $this->actingAs($participant)
            ->delete(route('events.registration.destroy-own', [$event, $registration]))
            ->assertRedirect(route('guest.dashboard'));

        $this->actingAs($owner)
            ->from(route('events.registrations.index', $event))
            ->post(route('events.registrations.cancellations.review', [$event, $registration]), [
                'decision' => 'approve',
                'refund_amount' => 50001,
            ])
            ->assertRedirect(route('events.registrations.index', $event))
            ->assertSessionHasErrors('refund_amount');

        $registration->refresh();
        $this->assertSame('pending', $registration->cancellation_status);
        $this->assertSame(0, $registration->refund_amount);
        $this->assertDatabaseMissing('event_registration_financial_entries', [
            'event_registration_id' => $registration->id,
            'type' => 'refund',
        ]);
    }

    public function test_participant_cannot_edit_or_delete_another_accounts_registration(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $participant = User::factory()->create(['role' => 'individual']);
        $otherParticipant = User::factory()->create(['role' => 'individual']);
        $event = $this->createEvent($owner);
        $registration = $this->createRegistration($event, $otherParticipant);

        $this->actingAs($participant)
            ->put(route('events.registration.update-own', [$event, $registration]), $this->validUpdate())
            ->assertForbidden();

        $this->delete(route('events.registration.destroy-own', [$event, $registration]))
            ->assertForbidden();

        $this->assertModelExists($registration);
    }

    public function test_dasbor_menampilkan_tombol_rilis_hanya_kepada_pemilik_event(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $admin = User::factory()->create(['role' => 'admin']);
        $event = $this->createEvent($owner);

        // Admin bukan pemilik memang tidak boleh melepas daftar, jadi tombol
        // tidak boleh muncul untuknya. Kalau tombol muncul, klik berikutnya
        // berakhir dengan 403.
        $this->actingAs($admin)
            ->get(route('guest.dashboard'))
            ->assertOk()
            ->assertDontSee('Rilis daftar peserta sekarang');

        $this->actingAs($owner)
            ->get(route('guest.dashboard'))
            ->assertOk()
            ->assertSee('Rilis daftar peserta sekarang');
    }

    public function test_daftar_peserta_dikelompokkan_berdasarkan_kategori(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $event = $this->createEvent($owner);
        $putra = $this->createRegistration($event, User::factory()->create(['role' => 'individual']), 'Atlet Putra');
        $putri = $this->createRegistration($event, User::factory()->create(['role' => 'individual']), 'Atlet Putri');

        $this->markPaid($putra, 100000, ['Classic Slalom']);
        $this->markPaid($putri, 100000, ['Classic Slalom']);
        $putri->update(['category' => 'Junior Putri']);

        $this->actingAs($owner)
            ->get(route('events.registrations.index', $event))
            ->assertOk()
            ->assertSee('event-roster-group-row')
            // "Junior Putra" mendahului "Junior Putri" secara alfabetis,
            // jadi urutan itu yang menentukan urutan kelompok.
            ->assertSeeInOrder(['Atlet Putra', 'Atlet Putri']);

        $this->actingAs($owner)
            ->get(route('events.registrations.index', ['event' => $event, 'group' => '0']))
            ->assertOk()
            ->assertDontSee('event-roster-group-row');
    }

    public function test_peserta_hanya_melihat_tombol_daftar_peserta_untuk_event_yang_dirilis(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $participant = User::factory()->create(['role' => 'individual']);
        $bystander = User::factory()->create(['role' => 'individual']);
        $event = $this->createEvent($owner);
        $this->markPaid($this->createRegistration($event, $participant), 100000, ['Classic Slalom']);

        $this->actingAs($participant)
            ->get(route('guest.dashboard'))
            ->assertOk()
            ->assertDontSee('Lihat daftar peserta');

        $event->update(['participants_published' => true, 'participants_published_at' => now()]);

        $this->actingAs($participant)
            ->get(route('guest.dashboard'))
            ->assertOk()
            ->assertSee('Lihat daftar peserta');

        // Akun yang tidak mendaftar tidak boleh mendapat tautan tersebut.
        $this->actingAs($bystander)
            ->get(route('guest.dashboard'))
            ->assertOk()
            ->assertDontSee('Lihat daftar peserta');
    }

    private function setCategoryPricing(Event $event): void
    {
        $rules = $event->category_rules;
        $rules[0]['required_races'] = 1;
        $rules[0]['package_fee'] = 100000;
        $rules[0]['extra_race_fee'] = 50000;
        $event->update(['category_rules' => $rules]);
    }

    private function markPaid(EventRegistration $registration, int $feeTotal, array $races): void
    {
        $registration->update([
            'category' => 'Junior Putra',
            'races' => $races,
            'fee_total' => $feeTotal,
            'payment_status' => 'paid',
            'confirmed_paid_at' => now(),
        ]);
    }

    private function createEvent(User $owner, string $name = 'Kejuaraan Tulungagung'): Event
    {
        $startDate = today()->addMonth();

        return Event::create([
            'submitted_by' => $owner->id,
            'name' => $name,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $startDate->copy()->addDay()->format('Y-m-d'),
            'venue' => 'GOR Tulungagung',
            'venue_address' => 'Tulungagung',
            'organizer_whatsapp' => '081111111111',
            'scale' => 'Kabupaten',
            'categories' => ['Junior Putra', 'Junior Putri'],
            'category_rules' => [
                [
                    'name' => 'Junior Putra',
                    'birth_date_from' => '2010-01-01',
                    'birth_date_to' => '2020-12-31',
                    'genders' => ['male'],
                    'races' => ['Classic Slalom', 'Speed Slalom'],
                ],
                [
                    'name' => 'Junior Putri',
                    'birth_date_from' => '2010-01-01',
                    'birth_date_to' => '2020-12-31',
                    'genders' => ['female'],
                    'races' => ['Classic Slalom', 'Speed Slalom'],
                ],
            ],
            'races' => ['Classic Slalom', 'Speed Slalom'],
            'registration_deadline' => $startDate->copy()->subDay()->format('Y-m-d'),
            'schedule' => ['Pembukaan', 'Final'],
            'status' => 'approved',
            'registration_open' => true,
        ]);
    }

    private function createRegistration(
        Event $event,
        User $participant,
        string $name = 'Atlet Terdaftar',
        ?string $nik = null,
    ): EventRegistration {
        $nik ??= $this->nikFor('male', '2014-01-01');

        return EventRegistration::create([
            'event_id' => $event->id,
            'user_id' => $participant->id,
            'participant_name' => $name,
            'nik' => $nik,
            'nik_hash' => hash_hmac('sha256', $nik, (string) config('app.key')),
            'gender' => 'male',
            'date_of_birth' => '2014-01-01',
            'address' => 'Tulungagung',
            'guardian_name' => 'Wali Atlet',
            'whatsapp' => '081234567890',
            'birth_year' => 2014,
            'club_name' => 'Klub Roda',
            'category' => null,
            'races' => ['Classic Slalom'],
            'status' => 'pending',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validUpdate(array $overrides = []): array
    {
        return [
            'nik' => $this->nikFor('male', '2014-01-01'),
            'participant_name' => 'Atlet Terdaftar',
            'nickname' => 'Atlet',
            'gender' => 'male',
            'date_of_birth' => '2014-01-01',
            'address' => 'Tulungagung',
            'guardian_name' => 'Wali Atlet',
            'whatsapp' => '081234567890',
            'club_name' => 'Klub Roda',
            'races' => ['Classic Slalom'],
            'status' => 'pending',
            ...$overrides,
        ];
    }
}
