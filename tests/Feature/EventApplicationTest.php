<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Notifications\EventDecisionNotification;
use App\Notifications\EventSubmittedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EventApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_applications_require_an_account_and_verified_email(): void
    {
        $this->get(route('event-applications.create'))
            ->assertRedirect(route('login'));

        $user = User::factory()->unverified()->create();
        $this->actingAs($user)
            ->get(route('event-applications.create'))
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('url.intended', route('event-applications.create'));

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(30),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())],
        );

        $this->get($verificationUrl)
            ->assertRedirect(route('event-applications.create'));

        $this->assertDatabaseCount('events', 0);
    }

    public function test_complete_event_application_is_saved_as_pending_and_not_public(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $applicant = User::factory()->create(['role' => 'club_manager']);

        $this->actingAs($applicant)
            ->get(route('event-applications.create'))
            ->assertOk()
            ->assertSee('Daftarkan event baru')
            ->assertSee('WhatsApp pengelola kejuaraan')
            ->assertSee('Kirim pengajuan');

        $this->actingAs($applicant)
            ->post(route('event-applications.store'), $this->validApplication())
            ->assertRedirect(route('guest.dashboard'))
            ->assertSessionHas('status');

        $event = Event::query()->firstOrFail();
        $this->assertSame('pending', $event->status);
        $this->assertSame($applicant->id, $event->submitted_by);
        $this->assertSame(['Beginner', 'Non-Beginner'], $event->categories);
        $this->assertSame('081222333444', $event->organizer_whatsapp);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $admin->id,
            'type' => EventSubmittedNotification::class,
        ]);
        $this->actingAs($applicant)
            ->get(route('guest.dashboard'))
            ->assertOk()
            ->assertSee('Status pengajuan event')
            ->assertSee('Menunggu persetujuan Admin')
            ->assertSee('Pengajuan Anda sudah diterima dan sedang ditinjau Admin.');

        $this->post(route('event-applications.store'), $this->validApplication())
            ->assertRedirect(route('guest.dashboard'))
            ->assertSessionHas('warning', 'Pengajuan dengan nama, tanggal, dan venue yang sama sudah tercatat. Tidak ada kejuaraan duplikat yang dibuat.');
        $this->assertDatabaseCount('events', 1);

        $this->get(route('events.index'))
            ->assertOk()
            ->assertDontSee('Kediri Roller Cup');
        $this->get(route('guest.dashboard'))
            ->assertOk()
            ->assertSee('Kediri Roller Cup')
            ->assertSee('WhatsApp pengelola: 081222333444')
            ->assertSee('Menunggu persetujuan')
            ->assertSee(route('event-applications.edit', $event), false);
    }

    public function test_event_application_requires_a_valid_organizer_whatsapp_number(): void
    {
        $payload = $this->validApplication();
        $payload['organizer_whatsapp'] = '08----';

        $this->actingAs(User::factory()->create(['role' => 'club_manager']))
            ->from(route('event-applications.create'))
            ->post(route('event-applications.store'), $payload)
            ->assertRedirect(route('event-applications.create'))
            ->assertSessionHasErrors('organizer_whatsapp');

        $this->assertDatabaseCount('events', 0);
    }

    public function test_admin_approval_notifies_submitter_and_publishes_event(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $applicant = User::factory()->create(['role' => 'government']);
        $event = $this->createEvent($applicant, 'pending');

        $this->actingAs($admin)
            ->get(route('admin.event-submissions.index'))
            ->assertOk()
            ->assertSee('Kediri Roller Cup')
            ->assertSee($applicant->email);

        $this->actingAs($admin)
            ->patch(route('admin.event-submissions.approve', $event))
            ->assertRedirect(route('admin.event-submissions.index'));

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'status' => 'approved',
            'reviewed_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $applicant->id,
            'type' => EventDecisionNotification::class,
        ]);

        $this->actingAs($applicant)
            ->get(route('guest.dashboard'))
            ->assertOk()
            ->assertSee('Pengajuan event “Kediri Roller Cup” disetujui dan telah tampil di daftar lomba.')
            ->assertSee('Disetujui · tampil di daftar lomba')
            ->assertSee('Atur kategori');

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Kediri Roller Cup')
            ->assertSee('KEJUARAAN AKTIF');

        $this->get(route('events.index'))
            ->assertOk()
            ->assertSee('Kediri Roller Cup')
            ->assertDontSee('Ubah event');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Kejuaraan yang akan datang.')
            ->assertSee('Kediri Roller Cup');
    }

    public function test_each_event_submission_is_shown_in_its_own_compact_card(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $firstEvent = $this->createEvent(User::factory()->create(), 'pending');
        $secondEvent = $this->createEvent(User::factory()->create(), 'pending');
        $secondEvent->update(['name' => 'Kejuaraan Roda Kediri']);

        $response = $this->actingAs($admin)
            ->get(route('admin.event-submissions.index'))
            ->assertOk()
            ->assertSee('Kediri Roller Cup')
            ->assertSee('Kejuaraan Roda Kediri');

        $this->assertSame(2, substr_count($response->getContent(), 'class="submission-card"'));
        $this->assertSame(2, substr_count($response->getContent(), 'class="submission-facts"'));
        $this->assertSame(2, substr_count($response->getContent(), 'Setujui &amp; tampilkan'));
        $this->assertNotSame($firstEvent->id, $secondEvent->id);
    }

    public function test_admin_rejection_keeps_event_private_and_sends_feedback_to_applicant(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $applicant = User::factory()->create();
        $event = $this->createEvent($applicant, 'pending');

        $this->actingAs($admin)
            ->patch(route('admin.event-submissions.reject', $event), [
                'decision_note' => 'Mohon lengkapi informasi venue.',
            ])
            ->assertRedirect(route('admin.event-submissions.index'));

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'status' => 'rejected',
            'decision_note' => 'Mohon lengkapi informasi venue.',
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $applicant->id,
            'type' => EventDecisionNotification::class,
        ]);
        $this->actingAs($applicant)
            ->get(route('guest.dashboard'))
            ->assertOk()
            ->assertSee('Pengajuan event “Kediri Roller Cup” belum disetujui')
            ->assertSee('Mohon lengkapi informasi venue.')
            ->assertSee('Belum disetujui');
        $this->get(route('events.index'))
            ->assertDontSee('Kediri Roller Cup');
        $this->get(route('guest.dashboard'))
            ->assertOk()
            ->assertSee('Kediri Roller Cup')
            ->assertSee('Belum disetujui');
    }

    public function test_participant_registration_requires_account_verification_and_saves_selected_races(): void
    {
        $event = $this->createEvent(User::factory()->create(), 'approved');

        $this->get(route('events.registration.create', $event))
            ->assertRedirect(route('login'));

        $unverifiedUser = User::factory()->unverified()->create();
        $this->actingAs($unverifiedUser)
            ->get(route('events.registration.create', $event))
            ->assertRedirect(route('verification.notice'));

        $participant = User::factory()->create(['role' => 'individual']);
        $this->actingAs($participant)
            ->get(route('events.registration.create', $event))
            ->assertOk()
            ->assertViewIs('verified.events.pendaftaran.daftar')
            ->assertSee('Kediri Roller Cup')
            ->assertSee('Classic Slalom')
            ->assertSee('Junior Putra')
            ->assertSee('NIK')
            ->assertSee('Nama orang tua / wali')
            ->assertSee('Kategori atlet akan ditetapkan setelah');

        $this->post(route('events.registration.store', $event), [
            'nik' => $this->nikFor('male', now()->subYears(12)->format('Y-m-d'), 1),
            'participant_name' => 'Atlet Contoh Lengkap',
            'nickname' => 'Contoh',
            'gender' => 'male',
            'date_of_birth' => now()->subYears(12)->format('Y-m-d'),
            'address' => 'Kediri, Jawa Timur',
            'guardian_name' => 'Wali Atlet',
            'whatsapp' => '081234567890',
            'club_name' => 'Klub Roda',
            'races' => ['Classic Slalom', 'Speed Slalom'],
        ])->assertRedirect(route('guest.dashboard'));

        $this->assertDatabaseHas('event_registrations', [
            'event_id' => $event->id,
            'user_id' => $participant->id,
            'participant_name' => 'Atlet Contoh Lengkap',
            'category' => null,
            'gender' => 'male',
            'birth_year' => now()->subYears(12)->year,
            'status' => 'pending',
        ]);
        $registration = EventRegistration::query()->where('event_id', $event->id)->firstOrFail();
        $this->assertSame($this->nikFor('male', now()->subYears(12)->format('Y-m-d'), 1), $registration->nik);
        $this->assertNotSame($this->nikFor('male', now()->subYears(12)->format('Y-m-d'), 1), DB::table('event_registrations')->where('id', $registration->id)->value('nik'));

        $this->post(route('events.registration.store', $event), [
            'nik' => $this->nikFor('male', now()->subYears(10)->format('Y-m-d'), 2),
            'participant_name' => 'Atlet Kedua',
            'gender' => 'male',
            'date_of_birth' => now()->subYears(10)->format('Y-m-d'),
            'address' => 'Kediri, Jawa Timur',
            'guardian_name' => 'Wali Atlet',
            'whatsapp' => '081234567890',
            'club_name' => 'Klub Roda',
            'races' => ['Skate Cross'],
        ])->assertRedirect(route('guest.dashboard'));

        $this->assertDatabaseCount('event_registrations', 2);
    }

    public function test_event_manager_can_update_age_gender_and_race_rules_and_only_owner_can_change_them(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $event = $this->createEvent($owner, 'approved');
        $otherManager = User::factory()->create(['role' => 'club_manager']);

        $this->actingAs($otherManager)
            ->get(route('owner.events.categories.edit', $event))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('owner.events.categories.edit', $event))
            ->assertOk()
            ->assertSee('Tanggal lahir dari')
            ->assertSee('Tanggal lahir sampai')
            ->assertSee('Usia minimal')
            ->assertSee('Usia maksimal')
            ->assertSee('Metode kelayakan')
            ->assertSee('Pilih satu')
            ->assertSee('data-eligibility-type="birth_date"', false)
            ->assertSee('data-eligibility-type="birth_year"', false)
            ->assertSee('data-eligibility-type="age"', false)
            ->assertSee('Tahun lahir')
            ->assertSee('Gender yang diizinkan')
            ->assertSee('Mata lomba yang diizinkan');

        $this->put(route('owner.events.categories.update', $event), [
            'category_rules' => [
                [
                    'name' => 'Junior Putra',
                    'eligibility_types' => ['age'],
                    'birth_date_from' => '2010-01-01',
                    'birth_date_to' => '2020-12-31',
                    'birth_years' => [],
                    'minimum_age' => 8,
                    'maximum_age' => 14,
                    'genders' => ['male'],
                    'races' => ['Classic Slalom', 'Speed Slalom'],
                ],
                [
                    'name' => 'Junior Putri',
                    'eligibility_types' => ['birth_year'],
                    'birth_date_from' => '2010-01-01',
                    'birth_date_to' => '2020-12-31',
                    'birth_years' => range(2010, 2020),
                    'genders' => ['female'],
                    'races' => ['Classic Slalom', 'Speed Slalom'],
                ],
            ],
        ])->assertRedirect(route('guest.dashboard'));

        $this->assertSame(['Junior Putra', 'Junior Putri'], $event->fresh()->categories);
        $this->assertSame([], $event->fresh()->category_rules[0]['birth_years']);
        $this->assertSame(8, $event->fresh()->category_rules[0]['minimum_age']);
        $this->assertSame(14, $event->fresh()->category_rules[0]['maximum_age']);
        $this->assertSame('age', $event->fresh()->category_rules[0]['eligibility_type']);
        $this->assertSame(null, $event->fresh()->category_rules[0]['birth_date_from']);
        $this->assertSame(range(2010, 2020), $event->fresh()->category_rules[1]['birth_years']);
        $this->assertSame('birth_year', $event->fresh()->category_rules[1]['eligibility_type']);
        $this->actingAs($owner)
            ->get(route('guest.dashboard'))
            ->assertOk()
            ->assertSee('Atur kategori')
            ->assertSee('2 kategori');
    }

    public function test_registration_uses_age_on_the_first_event_day_and_enforces_both_bounds(): void
    {
        $event = $this->createEvent(User::factory()->create(), 'approved');
        $event->update([
            'category_rules' => [[
                'name' => 'Usia 12 Tahun',
                'eligibility_type' => 'age',
                'birth_date_from' => '2000-01-01',
                'birth_date_to' => today()->format('Y-m-d'),
                'minimum_age' => 12,
                'maximum_age' => 12,
                'genders' => ['male'],
                'races' => ['Classic Slalom'],
            ]],
        ]);
        $participant = User::factory()->create(['role' => 'individual']);
        $birthdayOnEventDate = $event->start_date->copy()->subYears(12)->format('Y-m-d');

        $this->actingAs($participant)
            ->post(route('events.registration.store', $event), [
                'nik' => $this->nikFor('male', $birthdayOnEventDate, 80),
                'participant_name' => 'Atlet Genap 12 Tahun',
                'gender' => 'male',
                'date_of_birth' => $birthdayOnEventDate,
                'address' => 'Kediri',
                'guardian_name' => 'Wali Atlet',
                'whatsapp' => '081234567890',
                'races' => ['Classic Slalom'],
            ])
            ->assertRedirect(route('guest.dashboard'));

        $this->from(route('events.registration.create', $event))
            ->post(route('events.registration.store', $event), [
                'nik' => $this->nikFor('male', $event->start_date->copy()->subYears(12)->addDay()->format('Y-m-d'), 81),
                'participant_name' => 'Atlet Belum 12 Tahun',
                'gender' => 'male',
                'date_of_birth' => $event->start_date->copy()->subYears(12)->addDay()->format('Y-m-d'),
                'address' => 'Kediri',
                'guardian_name' => 'Wali Atlet',
                'whatsapp' => '081234567890',
                'races' => ['Classic Slalom'],
            ])
            ->assertRedirect(route('events.registration.create', $event))
            ->assertSessionHasErrors([
                'date_of_birth' => 'Tanggal lahir, usia pada hari pertama kejuaraan, dan gender atlet tidak memenuhi aturan kategori kejuaraan.',
            ]);

        $this->from(route('events.registration.create', $event))
            ->post(route('events.registration.store', $event), [
                'nik' => $this->nikFor('male', $event->start_date->copy()->subYears(13)->format('Y-m-d'), 82),
                'participant_name' => 'Atlet Sudah 13 Tahun',
                'gender' => 'male',
                'date_of_birth' => $event->start_date->copy()->subYears(13)->format('Y-m-d'),
                'address' => 'Kediri',
                'guardian_name' => 'Wali Atlet',
                'whatsapp' => '081234567890',
                'races' => ['Classic Slalom'],
            ])
            ->assertRedirect(route('events.registration.create', $event))
            ->assertSessionHasErrors('date_of_birth');

        $this->assertDatabaseCount('event_registrations', 1);

        // Aturan usia tidak lagi ditulis sebagai teks di halaman karena seluruh
        // penentuan kategori berjalan otomatis. Aturannya harus tetap ikut
        // di data-category-rules supaya pratinjau di browser bisa memakainya.
        $this->get(route('events.registration.create', $event))
            ->assertOk()
            ->assertDontSee('usia 12–12 tahun pada hari pertama')
            ->assertSee('&quot;eligibility_type&quot;:&quot;age&quot;', false)
            ->assertSee('&quot;minimum_age&quot;:12', false)
            ->assertSee('&quot;maximum_age&quot;:12', false);
    }

    public function test_category_age_bounds_must_be_in_ascending_order(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $event = $this->createEvent($owner, 'approved');

        $this->actingAs($owner)
            ->from(route('owner.events.categories.edit', $event))
            ->put(route('owner.events.categories.update', $event), [
                'category_rules' => [[
                    'name' => 'Rentang Usia Tidak Valid',
                    'eligibility_types' => ['age'],
                    'birth_date_from' => '2010-01-01',
                    'birth_date_to' => '2020-12-31',
                    'minimum_age' => 14,
                    'maximum_age' => 8,
                    'genders' => ['male'],
                    'races' => ['Classic Slalom'],
                ]],
            ])
            ->assertRedirect(route('owner.events.categories.edit', $event))
            ->assertSessionHasErrors('category_rules.0.maximum_age');

        $this->assertSame('Junior Putra', $event->fresh()->category_rules[0]['name']);
    }

    public function test_category_rule_rejects_more_than_one_eligibility_method(): void
    {
        $owner = User::factory()->create(['role' => 'club_manager']);
        $event = $this->createEvent($owner, 'approved');

        $this->actingAs($owner)
            ->from(route('owner.events.categories.edit', $event))
            ->put(route('owner.events.categories.update', $event), [
                'category_rules' => [[
                    'name' => 'Kategori dengan Metode Ganda',
                    'eligibility_types' => ['birth_date', 'age'],
                    'birth_date_from' => '2010-01-01',
                    'birth_date_to' => '2020-12-31',
                    'minimum_age' => 8,
                    'maximum_age' => 14,
                    'genders' => ['male'],
                    'races' => ['Classic Slalom'],
                ]],
            ])
            ->assertRedirect(route('owner.events.categories.edit', $event))
            ->assertSessionHasErrors('category_rules.0.eligibility_types');

        $this->assertSame('Junior Putra', $event->fresh()->category_rules[0]['name']);
    }

    public function test_registration_rejects_birthdate_or_gender_outside_all_event_category_rules(): void
    {
        $event = $this->createEvent(User::factory()->create(), 'approved');
        $participant = User::factory()->create(['role' => 'individual']);

        $this->actingAs($participant)
            ->from(route('events.registration.create', $event))
            ->post(route('events.registration.store', $event), [
                'nik' => $this->nikFor('female', '2005-01-01', 3),
                'participant_name' => 'Atlet Tidak Sesuai',
                'gender' => 'female',
                'date_of_birth' => '2005-01-01',
                'address' => 'Kediri',
                'guardian_name' => 'Wali Atlet',
                'whatsapp' => '081234567890',
                'races' => ['Classic Slalom'],
            ])
            ->assertRedirect(route('events.registration.create', $event))
            ->assertSessionHasErrors('date_of_birth');

        $this->assertDatabaseCount('event_registrations', 0);
    }

    public function test_registration_matches_only_birth_years_selected_for_a_category(): void
    {
        $event = $this->createEvent(User::factory()->create(), 'approved');
        $event->update([
            'category_rules' => [[
                'name' => 'Kelas 2014 dan 2016',
                'eligibility_type' => 'birth_year',
                'birth_date_from' => '2018-01-01',
                'birth_date_to' => '2020-12-31',
                'birth_years' => [2014, 2016],
                'genders' => ['male'],
                'races' => ['Classic Slalom'],
            ]],
        ]);
        $participant = User::factory()->create(['role' => 'individual']);

        $this->actingAs($participant)
            ->from(route('events.registration.create', $event))
            ->post(route('events.registration.store', $event), [
                'nik' => $this->nikFor('male', '2015-07-01', 10),
                'participant_name' => 'Atlet Tahun Tidak Dipilih',
                'gender' => 'male',
                'date_of_birth' => '2015-07-01',
                'address' => 'Kediri',
                'guardian_name' => 'Wali Atlet',
                'whatsapp' => '081234567890',
                'races' => ['Classic Slalom'],
            ])
            ->assertRedirect(route('events.registration.create', $event))
            ->assertSessionHasErrors('date_of_birth');

        $this->post(route('events.registration.store', $event), [
            'nik' => $this->nikFor('male', '2016-07-01', 11),
            'participant_name' => 'Atlet Tahun Terpilih',
            'gender' => 'male',
            'date_of_birth' => '2016-07-01',
            'address' => 'Kediri',
            'guardian_name' => 'Wali Atlet',
            'whatsapp' => '081234567890',
            'races' => ['Classic Slalom'],
        ])->assertRedirect(route('guest.dashboard'));

        $this->assertDatabaseHas('event_registrations', [
            'event_id' => $event->id,
            'user_id' => $participant->id,
            'birth_year' => 2016,
            'category' => null,
        ]);
    }

    public function test_birth_date_range_is_used_without_filtering_by_unselected_birth_years(): void
    {
        $event = $this->createEvent(User::factory()->create(), 'approved');
        $event->update([
            'category_rules' => [[
                'name' => 'Kelas Pilihan',
                'eligibility_type' => 'birth_date',
                'birth_date_from' => '2010-01-01',
                'birth_date_to' => '2020-12-31',
                'birth_years' => [2014, 2016],
                'genders' => ['male'],
                'races' => ['Classic Slalom'],
            ]],
        ]);
        $participant = User::factory()->create(['role' => 'individual']);

        $this->actingAs($participant)
            ->from(route('events.registration.create', $event))
            ->post(route('events.registration.store', $event), [
                'nik' => $this->nikFor('male', '2009-12-31', 20),
                'participant_name' => 'Atlet di Luar Rentang',
                'gender' => 'male',
                'date_of_birth' => '2009-12-31',
                'address' => 'Kediri',
                'guardian_name' => 'Wali Atlet',
                'whatsapp' => '081234567890',
                'races' => ['Classic Slalom'],
            ])
            ->assertRedirect(route('events.registration.create', $event))
            ->assertSessionHasErrors('date_of_birth');

        $this->post(route('events.registration.store', $event), [
            'nik' => $this->nikFor('male', '2015-07-01', 21),
            'participant_name' => 'Atlet di Rentang Tanggal',
            'gender' => 'male',
            'date_of_birth' => '2015-07-01',
            'address' => 'Kediri',
            'guardian_name' => 'Wali Atlet',
            'whatsapp' => '081234567890',
            'races' => ['Classic Slalom'],
        ])->assertRedirect(route('guest.dashboard'));

        $this->assertDatabaseHas('event_registrations', [
            'event_id' => $event->id,
            'user_id' => $participant->id,
            'birth_year' => 2015,
            'category' => null,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function test_pengelola_dapat_mengedit_pengajuan_miliknya_sendiri(): void
    {
        Notification::fake();

        $pengelola = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($pengelola, 'pending');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($pengelola)
            ->get(route('event-applications.edit', $event))
            ->assertOk()
            ->assertSee($event->name);

        $payload = $this->validApplication();
        $payload['name'] = 'Kediri Roller Cup Revisi';
        $payload['organizer_whatsapp'] = '+62 812 5555 9999';
        $payload['races'] = "Classic Slalom\nSpeed Slalom";

        $this->actingAs($pengelola)
            ->put(route('event-applications.update', $event), $payload)
            ->assertRedirect(route('guest.dashboard'));

        $event->refresh();
        $this->assertSame('Kediri Roller Cup Revisi', $event->name);
        $this->assertSame('+62 812 5555 9999', $event->organizer_whatsapp);
        $this->assertSame(['Classic Slalom', 'Speed Slalom'], $event->races);
        $this->assertSame('pending', $event->status, 'Status tidak boleh berubah otomatis saat pengelola mengedit.');

        Notification::assertSentTo($admin, EventSubmittedNotification::class);
    }

    public function test_event_edit_requires_a_valid_organizer_whatsapp_number(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner, 'approved');
        $payload = $this->validApplication();
        $payload['organizer_whatsapp'] = '';

        $this->actingAs($owner)
            ->from(route('event-applications.edit', $event))
            ->put(route('event-applications.update', $event), $payload)
            ->assertRedirect(route('event-applications.edit', $event))
            ->assertSessionHasErrors('organizer_whatsapp');

        $this->assertSame('081222333444', $event->fresh()->organizer_whatsapp);
    }

    public function test_pengelola_tidak_bisa_mengedit_pengajuan_milik_orang_lain(): void
    {
        Notification::fake();

        $pemilik = User::factory()->create(['role' => 'organizer']);
        $orang_lain = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($pemilik, 'approved');

        $this->actingAs($orang_lain)
            ->get(route('event-applications.edit', $event))
            ->assertForbidden();

        $payload = $this->validApplication();
        $payload['name'] = 'Dicoba Diubah Orang Lain';

        $this->actingAs($orang_lain)
            ->put(route('event-applications.update', $event), $payload)
            ->assertForbidden();

        $this->assertSame('Kediri Roller Cup', $event->fresh()->name);
        Notification::assertNothingSent();
    }

    public function test_admin_dapat_mengedit_event_milik_pengelola_manapun(): void
    {
        Notification::fake();

        $pengelola = User::factory()->create(['role' => 'organizer']);
        $admin = User::factory()->create(['role' => 'admin']);
        $event = $this->createEvent($pengelola, 'approved');

        $this->actingAs($admin)
            ->get(route('event-applications.edit', $event))
            ->assertOk()
            ->assertViewIs('verified.events.pengajuan.form')
            ->assertSee($event->name)
            ->assertSee('value="Kota"', false);

        $payload = $this->validApplication();
        $payload['name'] = 'Disunting Admin';
        $payload['scale'] = 'Provinsi';

        $this->actingAs($admin)
            ->put(route('event-applications.update', $event), $payload)
            ->assertRedirect(route('guest.dashboard'));

        $event->refresh();
        $this->assertSame('Disunting Admin', $event->name);
        $this->assertSame('Provinsi', $event->scale);
        $this->assertSame('approved', $event->status);
    }

    public function test_admin_tidak_perlu_mengisi_semua_kolom_tambahan_saat_mengedit(): void
    {
        $pengelola = User::factory()->create(['role' => 'organizer']);
        $admin = User::factory()->create(['role' => 'admin']);
        $event = $this->createEvent($pengelola, 'approved');

        $payload = $this->validApplication();
        $payload['name'] = 'Tanpa Batas Pendaftaran';
        $payload['registration_deadline'] = '';

        $this->actingAs($admin)
            ->put(route('event-applications.update', $event), $payload)
            ->assertSessionHasNoErrors();

        $this->assertNull($event->fresh()->registration_deadline);
    }

    public function test_admin_event_index_has_no_management_actions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $event = $this->createEvent(User::factory()->create(['role' => 'organizer']), 'pending');

        $this->actingAs($admin)
            ->get(route('events.index'))
            ->assertOk()
            ->assertDontSee('Pengajuan Menunggu')
            ->assertDontSee('Ubah event')
            ->assertDontSee('Hapus event')
            ->assertDontSee('Atur kategori');
    }

    public function test_event_owner_can_delete_an_event_and_its_registrations(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner, 'approved');
        $registration = EventRegistration::create([
            'event_id' => $event->id,
            'user_id' => User::factory()->create(['role' => 'individual'])->id,
            'participant_name' => 'Atlet Terdaftar',
            'birth_year' => 2014,
            'club_name' => 'Klub Roda',
            'category' => 'Junior Putra',
            'races' => ['Classic Slalom'],
            'status' => 'approved',
        ]);

        $this->actingAs($owner)
            ->delete(route('event-applications.destroy', $event))
            ->assertRedirect(route('guest.dashboard'))
            ->assertSessionHas('status', 'Event “Kediri Roller Cup” dan seluruh data terkait berhasil dihapus.');

        $this->assertDatabaseMissing('events', ['id' => $event->id]);
        $this->assertDatabaseMissing('event_registrations', ['id' => $registration->id]);
    }

    public function test_event_cannot_be_deleted_by_another_account(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $anotherAccount = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner, 'approved');

        $this->actingAs($anotherAccount)
            ->delete(route('event-applications.destroy', $event))
            ->assertForbidden();

        $this->assertModelExists($event);
    }

    public function test_admin_can_delete_an_event_submitted_by_another_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $event = $this->createEvent(User::factory()->create(['role' => 'organizer']), 'pending');

        $this->actingAs($admin)
            ->delete(route('event-applications.destroy', $event))
            ->assertRedirect(route('guest.dashboard'));

        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }

    private function validApplication(): array
    {
        $startDate = today()->addMonth();

        return [
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
            'categories' => "Beginner\nNon-Beginner",
            'races' => "Classic Slalom\nSpeed Slalom\nSkate Cross",
            'registration_deadline' => $startDate->copy()->subDay()->format('Y-m-d'),
            'registration_info' => 'Daftar melalui portal Inlinesia.',
            'fees' => 'Biaya dibayar kepada penyelenggara.',
            'is_free' => '0',
            'force_majeure' => 'Panitia akan mengumumkan perubahan jadwal.',
            'schedule' => "Hari pertama — pembukaan\nHari kedua — final",
        ];
    }

    private function createEvent(User $submitter, string $status): Event
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
            'categories' => ['Beginner', 'Non-Beginner'],
            'category_rules' => [
                [
                    'name' => 'Junior Putra',
                    'birth_date_from' => '2010-01-01',
                    'birth_date_to' => '2020-12-31',
                    'genders' => ['male'],
                    'races' => ['Classic Slalom', 'Speed Slalom', 'Skate Cross'],
                ],
                [
                    'name' => 'Junior Putri',
                    'birth_date_from' => '2010-01-01',
                    'birth_date_to' => '2020-12-31',
                    'genders' => ['female'],
                    'races' => ['Classic Slalom', 'Speed Slalom', 'Skate Cross'],
                ],
            ],
            'races' => ['Classic Slalom', 'Speed Slalom', 'Skate Cross'],
            'registration_deadline' => $startDate->copy()->subDay()->format('Y-m-d'),
            'registration_info' => 'Daftar melalui portal Inlinesia.',
            'fees' => 'Biaya dibayar kepada penyelenggara.',
            'force_majeure' => 'Panitia akan mengumumkan perubahan jadwal.',
            'schedule' => ['Hari pertama — pembukaan', 'Hari kedua — final'],
            'status' => $status,
            'registration_open' => true,
        ]);
    }
}
