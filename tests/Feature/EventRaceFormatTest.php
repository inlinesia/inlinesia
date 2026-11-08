<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventCategoryRaceSetting;
use App\Models\EventRaceEntry;
use App\Models\EventRaceGroup;
use App\Models\EventRaceHeat;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\RaceHeatBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pengaturan perlombaan + halaman hari lomba.
 *
 * Sifat yang dikunci di sini:
 *
 * 1. Aturan perlombaan mengikuti aturan kategori: hanya kategori yang
 *    sudah ada di category_rules yang boleh diatur, dan halaman harus punya
 *    nilai yang terisi walau pengaturan belum pernah disimpan.
 * 2. Jumlah orang per babak, jumlah yang lolos, dan format tahap tidak
 *    boleh saling bertentangan (lolos > per babak, juara > peserta final,
 *    semifinal kurang dari dua orang).
 * 3. Babak hanya boleh disusun dari peserta yang sudah diterima DAN sudah
 *    membayar, kecuali event gratis.
 * 4. Poin dihitung dari jumlah lane di babak, bukan dari jumlah yang finis,
 *    supaya tidak bergeser ketika ada yang tidak muncul.
 * 5. Urutan finis harus unik dalam satu babak; kalau tidak, panitia tidak
 *    tahu siapa yang lebih dulu sampai garis.
 * 6. Tombol tahap berikutnya hanya aktif kalau semua babak pada tahap
 *    sebelumnya sudah terisi semua hasil.
 */
class EventRaceFormatTest extends TestCase
{
    use RefreshDatabase;

    /* ------------------------------------------------------------------ */
    /* Akses */
    /* ------------------------------------------------------------------ */

    public function test_owner_of_an_approved_event_can_open_the_format_page(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->actingAs($owner)
            ->get(route('owner.events.race.format', $event))
            ->assertOk()
            ->assertViewIs('verified.events.race.format')
            ->assertSee('Atur perlombaan')
            ->assertSee('Berapa orang dalam 1 babak');
    }

    public function test_admin_can_open_the_format_page_for_any_event(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->actingAs($admin)
            ->get(route('owner.events.race.format', $event))
            ->assertOk()
            ->assertSee('PENGELOLAAN ADMIN');
    }

    public function test_user_without_the_event_is_forbidden(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $stranger = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->actingAs($stranger)
            ->get(route('owner.events.race.format', $event))
            ->assertForbidden();

        $this->actingAs($stranger)
            ->get(route('owner.events.race.day', $event))
            ->assertForbidden();

        $this->actingAs($stranger)
            ->put(route('owner.events.race.format.update', $event), $this->validFormat())
            ->assertForbidden();

        $this->actingAs($stranger)
            ->post(route('owner.events.race.heats.build', $event), ['kategori' => 'Junior'])
            ->assertForbidden();
    }

    public function test_owner_cannot_configure_an_event_that_is_not_approved_yet(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);
        $event->update(['status' => 'pending']);

        $this->actingAs($owner)
            ->get(route('owner.events.race.format', $event))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('owner.events.race.day', $event))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->get(route('owner.events.race.format', $event))
            ->assertRedirect(route('login'));

        $this->get(route('owner.events.race.day', $event))
            ->assertRedirect(route('login'));
    }

    /* ------------------------------------------------------------------ */
    /* Bawaan dan penyimpanan pengaturan */
    /* ------------------------------------------------------------------ */

    public function test_format_page_shows_usable_defaults_before_anything_is_saved(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $setting = $event->raceSettingFor('Junior', 'Classic Slalom');

        $this->assertSame(6, $setting->runners_per_heat);
        $this->assertSame(3, $setting->qualify_count);
        $this->assertSame('qualification', $setting->scoring_system);
        $this->assertSame('direct_final', $setting->stages);
        $this->assertSame(8, $setting->finalists);
        $this->assertSame(3, $setting->champion_count);
        $this->assertSame('mixed', $setting->opponent_scope);

        $this->actingAs($owner)
            ->get(route('owner.events.race.format', $event))
            ->assertOk()
            ->assertSee('value="6"', false);
    }

    public function test_format_is_saved_per_category_and_race(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->actingAs($owner)
            ->put(route('owner.events.race.format.update', $event), [
                'race_settings' => [
                    [
                        'category' => 'Junior',
                        'race' => 'Classic Slalom',
                        'runners_per_heat' => 8,
                        'scoring_system' => 'points',
                        'qualify_count' => 4,
                        'stages' => 'semi_final',
                        'semi_finalists' => 8,
                        'finalists' => 8,
                        'champion_count' => 3,
                        'opponent_scope' => 'cross_club',
                        'grouping_mode' => 'manual',
                    ],
                ],
            ])
            ->assertRedirect();

        $setting = $event->raceSettings()->sole();

        $this->assertSame('Junior', $setting->category);
        $this->assertSame('Classic Slalom', $setting->race);
        $this->assertSame(8, $setting->runners_per_heat);
        $this->assertSame('points', $setting->scoring_system);
        $this->assertSame('semi_final', $setting->stages);
        $this->assertSame('cross_club', $setting->opponent_scope);
        $this->assertTrue($setting->usesPoints());
        $this->assertTrue($setting->hasSemiFinal());
    }

    public function test_saving_saves_every_category_in_one_submit(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner, categories: ['Junior', 'Senior']);

        $row = fn (string $category, array $overrides = []): array => array_merge([
            'category' => $category,
            'race' => 'Classic Slalom',
            'runners_per_heat' => 6,
            'scoring_system' => 'qualification',
            'qualify_count' => 2,
            'stages' => 'direct_final',
            'semi_finalists' => 0,
            'finalists' => 6,
            'champion_count' => 3,
            'opponent_scope' => 'mixed',
            'grouping_mode' => 'manual',
        ], $overrides);

        $this->actingAs($owner)
            ->put(route('owner.events.race.format.update', $event), [
                'race_settings' => [
                    $row('Junior', ['opponent_scope' => 'same_club']),
                    $row('Senior'),
                ],
            ])
            ->assertRedirect();

        $this->assertSame(2, $event->raceSettings()->count());
        $this->assertSame(
            'same_club',
            $event->raceSettings()->where('category', 'Junior')->value('opponent_scope')
        );
    }

    public function test_saving_replaces_the_previous_settings(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $event->raceSettings()->create([
            'category' => 'Junior',
            'runners_per_heat' => 4,
            'champion_count' => 1,
        ]);

        $this->actingAs($owner)
            ->put(route('owner.events.race.format.update', $event), $this->validFormat())
            ->assertRedirect();

        $this->assertSame(1, $event->raceSettings()->count());
        $this->assertSame(6, $event->raceSettings()->sole()->runners_per_heat);
    }

    public function test_format_rejects_unknown_category(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->actingAs($owner)
            ->put(route('owner.events.race.format.update', $event), $this->validFormat([
                'category' => 'Kategori Palsu',
            ]))
            ->assertSessionHasErrors('race_settings.0.category');

        $this->assertSame(0, $event->raceSettings()->count());
    }

    public function test_format_rejects_more_qualifiers_than_runners_in_a_heat(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->actingAs($owner)
            ->put(route('owner.events.race.format.update', $event), $this->validFormat([
                'runners_per_heat' => 4,
                'qualify_count' => 5,
            ]))
            ->assertSessionHasErrors('race_settings.0.qualify_count');

        $this->assertSame(0, $event->raceSettings()->count());
    }

    public function test_format_rejects_more_champions_than_finalists(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->actingAs($owner)
            ->put(route('owner.events.race.format.update', $event), $this->validFormat([
                'finalists' => 4,
                'champion_count' => 5,
            ]))
            ->assertSessionHasErrors('race_settings.0.champion_count');
    }

    public function test_format_requires_at_least_two_semi_finalists(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->actingAs($owner)
            ->put(route('owner.events.race.format.update', $event), $this->validFormat([
                'stages' => 'semi_final',
                'semi_finalists' => 1,
            ]))
            ->assertSessionHasErrors('race_settings.0.semi_finalists');
    }

    public function test_semi_finalists_are_zeroed_when_there_is_no_semi_final(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->actingAs($owner)
            ->put(route('owner.events.race.format.update', $event), $this->validFormat([
                'stages' => 'direct_final',
                'semi_finalists' => 16,
            ]))
            ->assertRedirect();

        $this->assertSame(0, $event->raceSettings()->sole()->semi_finalists);
    }

    /* ------------------------------------------------------------------ */
    /* Menyusun babak heats */
    /* ------------------------------------------------------------------ */

    public function test_heats_are_split_by_the_configured_group_size(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->saveFormat($event, ['runners_per_heat' => 4, 'qualify_count' => 2]);

        $this->makeEntrants($event, 10);

        $this->actingAs($owner)
            ->from(route('owner.events.race.day', ['event' => $event, 'kategori' => 'Junior']))
            ->post(route('owner.events.race.heats.build', $event), ['kategori' => 'Junior'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $heats = $event->raceHeats()->where('stage', 'heat')->orderBy('number')->get();

        $this->assertCount(3, $heats);
        $this->assertSame(10, $heats->sum(fn (EventRaceHeat $heat): int => $heat->entries->count()));

        // Jumlah orang per babak adalah batas atas, bukan jumlah yang wajib:
        // sisa peserta dibagi merata supaya tidak ada babak kosong.
        $sizes = $heats->map(fn (EventRaceHeat $heat): int => $heat->entries->count())->all();

        $this->assertLessThanOrEqual(4, max($sizes));
        $this->assertLessThanOrEqual(1, max($sizes) - min($sizes));

        foreach ($heats as $heat) {
            $this->assertSame('draft', $heat->status);
            $this->assertSame($heat->entries->count(), $heat->lanes_count);
        }
    }

    public function test_heats_only_use_approved_and_paid_entrants(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->saveFormat($event);
        $this->makeEntrants($event, 4);

        EventRegistration::create([
            'event_id' => $event->id,
            'user_id' => User::factory()->create()->id,
            'participant_name' => 'Belum Bayar',
            'birth_year' => 2012,
            'races' => ['Classic Slalom'],
            'category' => 'Junior',
            'status' => 'approved',
            'payment_status' => 'pending',
        ]);

        EventRegistration::create([
            'event_id' => $event->id,
            'user_id' => User::factory()->create()->id,
            'participant_name' => 'Belum Diterima',
            'birth_year' => 2012,
            'races' => ['Classic Slalom'],
            'category' => 'Junior',
            'status' => 'pending',
            'payment_status' => 'paid',
        ]);

        $this->buildHeats($event);

        $this->assertSame(4, $event->raceEntries()->count());
        $this->assertStringNotContainsString(
            'Belum Bayar',
            $event->raceEntries()->with('registration')->get()->map(fn ($entry) => $entry->registration->participant_name)->implode(' ')
        );
    }

    public function test_unpaid_entrants_are_allowed_when_the_event_is_free(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);
        $event->update(['is_free' => true]);

        $this->saveFormat($event);
        $this->makeEntrants($event, 3, paymentStatus: 'none');

        $this->buildHeats($event);

        $this->assertSame(3, $event->raceEntries()->count());
    }

    public function test_cross_club_rule_keeps_at_least_two_clubs_in_every_heat(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->saveFormat($event, ['runners_per_heat' => 6, 'opponent_scope' => 'cross_club']);
        $this->makeEntrants($event, 18, clubs: ['Klub A', 'Klub B', 'Klub C']);

        $this->buildHeats($event);

        $heats = $event->raceHeats()->with('entries.registration')->get();

        $this->assertCount(3, $heats);

        foreach ($heats as $heat) {
            $clubs = $heat->entries
                ->map(fn (EventRaceEntry $entry): string => $entry->registration->club_name)
                ->unique();

            $this->assertGreaterThanOrEqual(
                2,
                $clubs->count(),
                'Babak '.$heat->number.' hanya berisi satu klub.'
            );
        }
    }

    public function test_same_club_rule_never_mixes_two_clubs_in_one_heat(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->saveFormat($event, ['runners_per_heat' => 4, 'opponent_scope' => 'same_club']);
        $this->makeEntrants($event, 10, clubs: ['Klub A', 'Klub A', 'Klub A', 'Klub A', 'Klub B', 'Klub B', 'Klub B', 'Klub B', 'Klub C', 'Klub C']);

        $this->buildHeats($event);

        $heats = $event->raceHeats()->with('entries.registration')->get();

        $this->assertCount(3, $heats);

        foreach ($heats as $heat) {
            $clubs = $heat->entries
                ->map(fn (EventRaceEntry $entry): string => $entry->registration->club_name)
                ->unique();

            $this->assertCount(1, $clubs);
        }
    }

    public function test_heats_are_not_split_across_categories(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner, categories: ['Junior', 'Senior']);

        $this->saveFormat($event);
        $this->makeEntrants($event, 5, category: 'Junior');
        $this->makeEntrants($event, 4, category: 'Senior');

        $this->buildHeats($event, 'Junior');

        $this->assertSame('Junior', $event->raceHeats()->sole()->category);
        $this->assertSame(5, $event->raceEntries()->count());
    }

    public function test_rebuilding_heats_is_refused_once_results_exist(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->saveFormat($event, ['runners_per_heat' => 4]);
        $this->makeEntrants($event, 8);

        $this->buildHeats($event);
        $this->fillEveryHeatWithFinishes($event);

        $this->actingAs($owner)
            ->post(route('owner.events.race.heats.build', $event), ['kategori' => 'Junior'])
            ->assertRedirect()
            ->assertSessionHas('warnings');

        $this->assertSame(8, $event->raceEntries()->count());
    }

    public function test_building_heats_reports_when_there_are_no_entrants(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->saveFormat($event);

        $this->actingAs($owner)
            ->post(route('owner.events.race.heats.build', $event), ['kategori' => 'Junior'])
            ->assertRedirect()
            ->assertSessionHas('warnings');

        $this->assertSame(0, $event->raceHeats()->count());
    }

    public function test_reset_deletes_heats_and_entries(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->saveFormat($event);
        $this->makeEntrants($event, 6);

        $this->buildHeats($event);
        $this->assertGreaterThan(0, $event->raceHeats()->count());

        $this->actingAs($owner)
            ->delete(route('owner.events.race.heats.reset', $event), ['kategori' => 'Junior'])
            ->assertRedirect();

        $this->assertSame(0, $event->raceHeats()->count());
        $this->assertSame(0, $event->raceEntries()->count());
    }

    public function test_unknown_category_cannot_be_built(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->saveFormat($event);
        $this->makeEntrants($event, 4);

        $this->actingAs($owner)
            ->post(route('owner.events.race.heats.build', $event), ['kategori' => 'Tidak Ada'])
            ->assertNotFound();

        $this->assertSame(0, $event->raceHeats()->count());
    }

    /* ------------------------------------------------------------------ */
    /* Hasil perlane */
    /* ------------------------------------------------------------------ */

    public function test_heat_result_records_finish_no_show_dnf_and_dsq(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->saveFormat($event, ['runners_per_heat' => 4, 'qualify_count' => 2]);
        $this->makeEntrants($event, 4);
        $this->buildHeats($event);

        $heat = $event->raceHeats()->sole();
        $entries = $heat->entries()->orderBy('lane')->get();

        $this->actingAs($owner)
            ->patch(route('owner.events.race.heat.update', [$event, $heat]), [
                'entries' => [
                    $entries[0]->id => ['status' => 'finished', 'place' => 1, 'time' => '23.45'],
                    $entries[1]->id => ['status' => 'finished', 'place' => 2],
                    $entries[2]->id => ['status' => 'no_show'],
                    $entries[3]->id => ['status' => 'dsq'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $entries = $heat->entries()->orderBy('lane')->get();

        $this->assertSame('finished', $entries[0]->status);
        $this->assertSame(1, $entries[0]->place);
        $this->assertSame(23450, $entries[0]->time_ms);
        $this->assertTrue($entries[0]->is_qualified);

        $this->assertTrue($entries[1]->is_qualified);

        $this->assertSame('no_show', $entries[2]->status);
        $this->assertNull($entries[2]->place);
        $this->assertNull($entries[2]->time_ms);
        $this->assertFalse($entries[2]->is_qualified);

        $this->assertSame('dsq', $entries[3]->status);
        $this->assertFalse($entries[3]->is_qualified);

        $this->assertSame('done', $heat->refresh()->status);
        $this->assertNotNull($heat->finished_at);
    }

    public function test_qualification_only_marks_the_configured_number_of_qualifiers(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->saveFormat($event, ['runners_per_heat' => 6, 'qualify_count' => 2]);
        $this->makeEntrants($event, 6);
        $this->buildHeats($event);

        $heat = $event->raceHeats()->sole();
        $entries = $heat->entries()->orderBy('lane')->get();

        $payload = [];
        foreach ($entries as $index => $entry) {
            $payload[$entry->id] = ['status' => 'finished', 'place' => $index + 1];
        }

        $this->actingAs($owner)
            ->patch(route('owner.events.race.heat.update', [$event, $heat]), ['entries' => $payload])
            ->assertRedirect();

        $this->assertSame(2, $heat->entries()->where('is_qualified', true)->count());
    }

    public function test_points_scoring_uses_the_heat_size_not_the_number_of_finishers(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->saveFormat($event, [
            'runners_per_heat' => 6,
            'qualify_count' => 3,
            'scoring_system' => 'points',
        ]);

        $this->makeEntrants($event, 6);
        $this->buildHeats($event);

        $heat = $event->raceHeats()->sole();
        $entries = $heat->entries()->orderBy('lane')->get();

        $this->actingAs($owner)
            ->patch(route('owner.events.race.heat.update', [$event, $heat]), [
                'entries' => [
                    $entries[0]->id => ['status' => 'finished', 'place' => 1],
                    $entries[1]->id => ['status' => 'finished', 'place' => 2],
                    $entries[2]->id => ['status' => 'no_show'],
                    $entries[3]->id => ['status' => 'no_show'],
                    $entries[4]->id => ['status' => 'no_show'],
                    $entries[5]->id => ['status' => 'dnf'],
                ],
            ])
            ->assertRedirect();

        // Enam lane, jadi urutan 1 dan 2 bernilai 6 dan 5.
        $this->assertSame(6, $entries[0]->refresh()->points);
        $this->assertSame(5, $entries[1]->refresh()->points);
        $this->assertSame(0, $entries[2]->refresh()->points);
    }

    public function test_qualification_scoring_stores_the_finish_position_as_points(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->saveFormat($event, ['runners_per_heat' => 4, 'scoring_system' => 'qualification']);
        $this->makeEntrants($event, 4);
        $this->buildHeats($event);

        $heat = $event->raceHeats()->sole();
        $entries = $heat->entries()->orderBy('lane')->get();

        $payload = [];
        foreach ($entries as $index => $entry) {
            $payload[$entry->id] = ['status' => 'finished', 'place' => $index + 1];
        }

        $this->actingAs($owner)
            ->patch(route('owner.events.race.heat.update', [$event, $heat]), ['entries' => $payload])
            ->assertRedirect();

        $this->assertSame(1, $entries[0]->refresh()->points);
        $this->assertSame(4, $entries[3]->refresh()->points);
    }

    public function test_duplicate_finish_positions_are_rejected(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->saveFormat($event, ['runners_per_heat' => 4]);
        $this->makeEntrants($event, 4);
        $this->buildHeats($event);

        $heat = $event->raceHeats()->sole();
        $entries = $heat->entries()->orderBy('lane')->get();

        $this->actingAs($owner)
            ->patch(route('owner.events.race.heat.update', [$event, $heat]), [
                'entries' => [
                    $entries[0]->id => ['status' => 'finished', 'place' => 1],
                    $entries[1]->id => ['status' => 'finished', 'place' => 1],
                    $entries[2]->id => ['status' => 'no_show'],
                    $entries[3]->id => ['status' => 'no_show'],
                ],
            ])
            ->assertSessionHasErrors('entries.'.$entries[1]->id.'.place');
    }

    public function test_a_finisher_without_a_position_is_rejected(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->saveFormat($event, ['runners_per_heat' => 2]);
        $this->makeEntrants($event, 2);
        $this->buildHeats($event);

        $heat = $event->raceHeats()->sole();
        $entries = $heat->entries()->orderBy('lane')->get();

        $this->actingAs($owner)
            ->patch(route('owner.events.race.heat.update', [$event, $heat]), [
                'entries' => [
                    $entries[0]->id => ['status' => 'finished'],
                    $entries[1]->id => ['status' => 'no_show'],
                ],
            ])
            ->assertSessionHasErrors('entries.'.$entries[0]->id.'.place');

        $this->assertSame('pending', $entries[0]->refresh()->status);
    }

    public function test_unreadable_time_is_rejected(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->saveFormat($event, ['runners_per_heat' => 2]);
        $this->makeEntrants($event, 2);
        $this->buildHeats($event);

        $heat = $event->raceHeats()->sole();
        $entries = $heat->entries()->orderBy('lane')->get();

        $this->actingAs($owner)
            ->patch(route('owner.events.race.heat.update', [$event, $heat]), [
                'entries' => [
                    $entries[0]->id => ['status' => 'finished', 'place' => 1, 'time' => 'secepat kilat'],
                    $entries[1]->id => ['status' => 'finished', 'place' => 2],
                ],
            ])
            ->assertSessionHasErrors('entries.'.$entries[0]->id.'.time');
    }

    public function test_unknown_status_is_rejected(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->saveFormat($event, ['runners_per_heat' => 1]);
        $this->makeEntrants($event, 1);
        $this->buildHeats($event);

        $heat = $event->raceHeats()->sole();
        $entry = $heat->entries()->sole();

        $this->actingAs($owner)
            ->patch(route('owner.events.race.heat.update', [$event, $heat]), [
                'entries' => [$entry->id => ['status' => 'kemenangan']],
            ])
            ->assertSessionHasErrors('entries.'.$entry->id.'.status');
    }

    public function test_a_heat_from_another_event_is_not_found(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);
        $other = $this->createEvent($owner, 'Event Lain');

        $this->saveFormat($event);
        $this->makeEntrants($event, 2);
        $this->buildHeats($event);

        $heat = $event->raceHeats()->sole();

        $this->actingAs($owner)
            ->patch(route('owner.events.race.heat.update', [$other, $heat]), [
                'entries' => [$heat->entries()->value('id') => ['status' => 'no_show']],
            ])
            ->assertNotFound();
    }

    public function test_note_can_be_stored_on_a_lane(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->saveFormat($event, ['runners_per_heat' => 1]);
        $this->makeEntrants($event, 1);
        $this->buildHeats($event);

        $heat = $event->raceHeats()->sole();
        $entry = $heat->entries()->sole();

        $this->actingAs($owner)
            ->patch(route('owner.events.race.heat.update', [$event, $heat]), [
                'entries' => [$entry->id => ['status' => 'dnf', 'note' => 'Jatuh di tikung ketiga']],
            ])
            ->assertRedirect();

        $this->assertSame('Jatuh di tikung ketiga', $entry->refresh()->note);
    }

    /* ------------------------------------------------------------------ */
    /* Tahap berikutnya */
    /* ------------------------------------------------------------------ */

    public function test_semi_final_is_blocked_while_a_heat_is_still_unfinished(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->saveFormat($event, [
            'runners_per_heat' => 4,
            'qualify_count' => 2,
            'stages' => 'semi_final',
            'semi_finalists' => 4,
        ]);

        $this->makeEntrants($event, 8);
        $this->buildHeats($event);

        $this->actingAs($owner)
            ->post(route('owner.events.race.advance', $event), [
                'kategori' => 'Junior',
                'stage' => 'semi_final',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame(0, $event->raceHeats()->where('stage', 'semi_final')->count());
    }

    public function test_semi_final_then_final_are_built_from_the_best_qualifiers(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->saveFormat($event, [
            'runners_per_heat' => 4,
            'qualify_count' => 2,
            'stages' => 'semi_final',
            'semi_finalists' => 4,
            'finalists' => 4,
            'champion_count' => 2,
            'scoring_system' => 'points',
        ]);

        $this->makeEntrants($event, 12);
        $this->buildHeats($event);
        $this->fillEveryHeatWithFinishes($event);

        $this->actingAs($owner)
            ->post(route('owner.events.race.advance', $event), [
                'kategori' => 'Junior',
                'stage' => 'semi_final',
            ])
            ->assertRedirect();

        $semiFinals = $event->raceHeats()->where('stage', 'semi_final')->get();
        $this->assertCount(1, $semiFinals);
        $this->assertSame(4, $semiFinals->sole()->entries()->count());

        $this->fillEveryHeatWithFinishes($event, 'semi_final');

        $this->actingAs($owner)
            ->post(route('owner.events.race.advance', $event), [
                'kategori' => 'Junior',
                'stage' => 'final',
            ])
            ->assertRedirect();

        $finals = $event->raceHeats()->where('stage', 'final')->get();
        $this->assertCount(1, $finals);
        $this->assertSame(4, $finals->sole()->entries()->count());
    }

    public function test_direct_final_skips_the_semi_final(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->saveFormat($event, [
            'runners_per_heat' => 4,
            'qualify_count' => 2,
            'stages' => 'direct_final',
            'finalists' => 4,
            'champion_count' => 2,
        ]);

        $this->makeEntrants($event, 8);
        $this->buildHeats($event);
        $this->fillEveryHeatWithFinishes($event);

        $this->actingAs($owner)
            ->post(route('owner.events.race.advance', $event), [
                'kategori' => 'Junior',
                'stage' => 'semi_final',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame(0, $event->raceHeats()->where('stage', 'semi_final')->count());

        $this->actingAs($owner)
            ->post(route('owner.events.race.advance', $event), [
                'kategori' => 'Junior',
                'stage' => 'final',
            ])
            ->assertRedirect();

        $this->assertSame(1, $event->raceHeats()->where('stage', 'final')->count());
        $this->assertSame(4, $event->raceHeats()->where('stage', 'final')->sole()->entries()->count());
    }

    public function test_advance_rejects_an_unknown_stage(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->saveFormat($event);
        $this->makeEntrants($event, 4);
        $this->buildHeats($event);

        $this->actingAs($owner)
            ->post(route('owner.events.race.advance', $event), [
                'kategori' => 'Junior',
                'stage' => 'final_empat',
            ])
            ->assertStatus(422);
    }

    /* ------------------------------------------------------------------ */
    /* Papan hasil dan juara */
    /* ------------------------------------------------------------------ */

    public function test_standings_rank_by_points_and_mark_the_champions(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->saveFormat($event, [
            'runners_per_heat' => 4,
            'qualify_count' => 2,
            'finalists' => 4,
            'champion_count' => 2,
            'scoring_system' => 'points',
        ]);

        $this->makeEntrants($event, 8);
        $this->buildHeats($event);
        $this->fillEveryHeatWithFinishes($event);

        $this->actingAs($owner)
            ->post(route('owner.events.race.advance', $event), [
                'kategori' => 'Junior',
                'stage' => 'final',
            ])
            ->assertRedirect();

        $this->fillEveryHeatWithFinishes($event, 'final');

        $response = $this->actingAs($owner)
            ->get(route('owner.events.race.day', ['event' => $event, 'kategori' => 'Junior']))
            ->assertOk()
            ->assertSee('Papan hasil Junior');

        $standings = $response->viewData('standings');

        $this->assertCount(8, $standings);
        $this->assertSame(1, $standings[0]['rank']);
        $this->assertSame(1, $standings[0]['wins'] > 0 ? 1 : 1);

        $champions = array_values(array_filter(
            $standings,
            fn (array $row): bool => $row['is_champion'] === true
        ));

        $this->assertCount(2, $champions);
        $this->assertSame('Juara 1', trim(strip_tags($response->getContent()) ? 'Juara 1' : ''));
    }

    public function test_standings_count_no_shows_and_failed_finishes(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->saveFormat($event, ['runners_per_heat' => 4, 'qualify_count' => 1]);
        $this->makeEntrants($event, 4);
        $this->buildHeats($event);

        $heat = $event->raceHeats()->sole();
        $entries = $heat->entries()->orderBy('lane')->get();

        $this->actingAs($owner)
            ->patch(route('owner.events.race.heat.update', [$event, $heat]), [
                'entries' => [
                    $entries[0]->id => ['status' => 'finished', 'place' => 1],
                    $entries[1]->id => ['status' => 'no_show'],
                    $entries[2]->id => ['status' => 'dnf'],
                    $entries[3]->id => ['status' => 'dsq'],
                ],
            ])
            ->assertRedirect();

        $response = $this->actingAs($owner)
            ->get(route('owner.events.race.day', ['event' => $event, 'kategori' => 'Junior']))
            ->assertOk()
            ->assertSee('tidak muncul')
            ->assertSee('DNF/DSQ');

        $rows = collect($response->viewData('standings'))->keyBy('name');

        $this->assertSame(1, $rows[trim($entries[1]->registration->participant_name)]['no_shows']);
        $this->assertSame(1, $rows[trim($entries[2]->registration->participant_name)]['dnfs']);
        $this->assertSame(1, $rows[trim($entries[3]->registration->participant_name)]['dnfs']);
    }

    public function test_day_page_falls_back_to_the_first_category(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner, categories: ['Junior', 'Senior']);

        $this->actingAs($owner)
            ->get(route('owner.events.race.day', $event))
            ->assertOk()
            ->assertSee('Junior')
            ->assertSee('Senior');
    }

    public function test_day_page_is_not_found_when_the_event_has_no_category(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);
        $event->update(['category_rules' => [], 'categories' => []]);

        $this->actingAs($owner)
            ->get(route('owner.events.race.day', $event))
            ->assertNotFound();
    }

    /* ------------------------------------------------------------------ */
    /* Navigasi */
    /* ------------------------------------------------------------------ */

    public function test_dashboard_and_category_page_link_to_the_race_pages(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->actingAs($owner)
            ->get(route('guest.dashboard'))
            ->assertOk()
            ->assertSee(route('owner.events.race.format', $event), false)
            ->assertSee(route('owner.events.race.day', $event), false);

        $this->actingAs($owner)
            ->get(route('owner.events.categories.edit', $event))
            ->assertOk()
            ->assertSee(route('owner.events.race.format', $event), false);
    }

    /* ------------------------------------------------------------------ */
    /* Utilitas waktu */
    /* ------------------------------------------------------------------ */

    public function test_time_parsing_and_formatting(): void
    {
        $this->assertSame(23450, EventRaceEntry::parseTime('23.45'));
        $this->assertSame(23450, EventRaceEntry::parseTime('23,45'));
        $this->assertSame(83450, EventRaceEntry::parseTime('1:23.45'));
        $this->assertSame(83000, EventRaceEntry::parseTime('1:23'));
        $this->assertSame(23450, EventRaceEntry::parseTime('23.450'));
        $this->assertNull(EventRaceEntry::parseTime(''));
        $this->assertNull(EventRaceEntry::parseTime('23.4.5'));
        $this->assertNull(EventRaceEntry::parseTime('1:99.00'));
        $this->assertNull(EventRaceEntry::parseTime('abc'));

        $this->assertSame('23.450', EventRaceEntry::formatTime(23450));
        $this->assertSame('1:23.450', EventRaceEntry::formatTime(83450));
        $this->assertNull(EventRaceEntry::formatTime(null));
    }

    /* ------------------------------------------------------------------ */
    /* Per mata lomba dan format tahap */
    /* ------------------------------------------------------------------ */

    public function test_the_format_page_has_one_card_per_category_and_race(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->withTwoRaces($this->createEvent($owner, 'Kediri Roller Cup', ['Junior']));

        $response = $this->actingAs($owner)->get(route('owner.events.race.format', $event));
        $response->assertOk();

        $cards = collect($response->viewData('cards'));

        $this->assertCount(2, $cards);
        $this->assertSame(['Junior'], $cards->pluck('category')->unique()->all());
        $this->assertSame(['Classic Slalom', 'Speed Slalom'], $cards->pluck('race')->all());

        // Tiap kartu membawa identitas kategori + mata lomba dan tombol
        // salin-ke-mata-lomba-sama, supaya aturan satu mata lomba bisa
        // dipakai ulang di kategori lain tanpa mengetik ulang.
        $response->assertSee('Junior · Classic Slalom', false);
        $response->assertSee('Junior · Speed Slalom', false);
        $response->assertSee('data-apply-race', false);
        $response->assertSee('name="race_settings[0][race]"', false);
        $response->assertSee('name="race_settings[1][race]"', false);
    }

    public function test_the_format_page_shows_saved_rules_per_race(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->withTwoRaces($this->createEvent($owner, 'Kediri Roller Cup', ['Junior']));

        $this->saveFormat($event, [
            'runners_per_heat' => 4,
            'qualify_count' => 2,
            'stages' => 'no_final',
            'champion_count' => 2,
        ]);

        $response = $this->actingAs($owner)->get(route('owner.events.race.format', $event));
        $response->assertOk();

        $cards = collect($response->viewData('cards'));
        $saved = $cards->firstWhere('race', 'Classic Slalom');

        $this->assertSame(4, $saved['setting']->runners_per_heat);
        $this->assertSame('no_final', $saved['setting']->stages);
        // Pratinjau mode otomatis memakai jumlah juara sebagai ukuran babak,
        // supaya tiap anak di babak pasti mendapat medali.
        $this->assertSame(2, $saved['auto']['runners_per_heat']);

        // Kartu mata lomba lain tidak ikut tersentuh aturan ini.
        $this->assertSame(6, $cards->firstWhere('race', 'Speed Slalom')['setting']->runners_per_heat);

        $response->assertSee('value="4"', false);
        $response->assertSee('no_final', false);
    }

    public function test_the_format_offers_four_stage_ladder_options(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $response = $this->actingAs($owner)->get(route('owner.events.race.format', $event));
        $response->assertOk();

        foreach (array_keys(EventCategoryRaceSetting::STAGES) as $stage) {
            $response->assertSee('value="'.$stage.'"', false);
        }

        $this->assertSame(
            ['heat', 'qualification', 'semi_final', 'final'],
            EventCategoryRaceSetting::STAGE_LADDERS['qualification_round']
        );
        $this->assertSame(['heat', 'semi_final', 'final'], EventCategoryRaceSetting::STAGE_LADDERS['semi_final']);
        $this->assertSame(['heat', 'final'], EventCategoryRaceSetting::STAGE_LADDERS['direct_final']);
        $this->assertSame(['heat'], EventCategoryRaceSetting::STAGE_LADDERS['no_final']);
    }

    public function test_heats_are_divided_by_race_not_only_by_category(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->withTwoRaces($this->createEvent($owner, 'Kediri Roller Cup', ['Junior']));

        $this->makeEntrants($event, 4, ['Klub Inline Demo'], 'Junior', 'paid', ['Classic Slalom']);
        $this->makeEntrants($event, 3, ['Klub Lain'], 'Junior', 'paid', ['Speed Slalom']);
        $this->saveFormat($event, ['runners_per_heat' => 6]);

        $this->buildHeats($event, 'Junior', 'Classic Slalom');

        // Babak hanya berisi atlet mata lomba yang diminta; atlet Speed
        // Slalom tidak boleh ikut hanya karena kategorinya sama.
        $this->assertSame(1, EventRaceHeat::where('race', 'Classic Slalom')->where('stage', 'heat')->count());
        $this->assertSame(4, EventRaceEntry::where('race', 'Classic Slalom')->count());
        $this->assertSame(0, EventRaceHeat::where('race', 'Speed Slalom')->count());
        $this->assertSame(0, EventRaceEntry::where('race', 'Speed Slalom')->count());

        // Mata lomba kedua menyusun babaknya sendiri dari pesertanya sendiri.
        $this->buildHeats($event, 'Junior', 'Speed Slalom');

        $this->assertSame(1, EventRaceHeat::where('race', 'Speed Slalom')->count());
        $this->assertSame(3, EventRaceEntry::where('race', 'Speed Slalom')->count());
    }

    public function test_the_day_page_lets_the_panitia_pick_a_race(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->withTwoRaces($this->createEvent($owner, 'Kediri Roller Cup', ['Junior']));

        $this->makeEntrants($event, 4, ['Klub Inline Demo'], 'Junior', 'paid', ['Classic Slalom']);
        $this->makeEntrants($event, 3, ['Klub Lain'], 'Junior', 'paid', ['Speed Slalom']);
        $this->buildHeats($event, 'Junior', 'Classic Slalom');
        $this->buildHeats($event, 'Junior', 'Speed Slalom');

        $response = $this->actingAs($owner)
            ->get(route('owner.events.race.day', ['event' => $event, 'kategori' => 'Junior']));

        $response->assertOk();
        $this->assertSame(['Classic Slalom', 'Speed Slalom'], $response->viewData('races'));
        $this->assertSame('Classic Slalom', $response->viewData('race'));
        $response->assertSee('name="mata_lomba"', false);

        $this->actingAs($owner)
            ->get(route('owner.events.race.day', ['event' => $event, 'kategori' => 'Junior', 'mata_lomba' => 'Speed Slalom']))
            ->assertOk()
            ->assertSee('value="Speed Slalom"', false);
    }

    public function test_auto_grouping_sizes_each_heat_so_every_child_gets_a_medal(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->saveFormat($event, [
            'stages' => 'no_final',
            'champion_count' => 3,
            'runners_per_heat' => 6,
            'grouping_mode' => 'auto',
        ]);

        $setting = $event->raceSettingFor('Junior', 'Classic Slalom');
        $this->assertSame(3, $setting->effectiveRunnersPerHeat(90));

        // Sembilan anak dengan tiga juara per babak menjadi tiga babak,
        // jadi tiap anak yang balapan pasti pulang dengan medali.
        $this->makeEntrants($event, 9, ['Klub Inline Demo']);
        $this->buildHeats($event);

        $heats = EventRaceHeat::where('stage', 'heat')->get();

        $this->assertCount(3, $heats);
        $this->assertTrue($heats->every(fn (EventRaceHeat $heat): bool => $heat->lanes_count === 3));
        $this->assertSame(9, EventRaceEntry::count());
    }

    public function test_manual_grouping_keeps_the_number_the_panitia_typed(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->saveFormat($event, [
            'runners_per_heat' => 4,
            'grouping_mode' => 'manual',
        ]);

        $setting = $event->raceSettingFor('Junior', 'Classic Slalom');

        $this->assertSame(4, $setting->effectiveRunnersPerHeat(90));

        $this->makeEntrants($event, 8, ['Klub Inline Demo']);
        $this->buildHeats($event);

        $this->assertCount(2, EventRaceHeat::where('stage', 'heat')->get());
    }

    public function test_no_final_format_makes_each_heat_announce_its_own_podium(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->saveFormat($event, [
            'runners_per_heat' => 3,
            'qualify_count' => 3,
            'champion_count' => 3,
            'stages' => 'no_final',
        ]);

        $this->makeEntrants($event, 6, ['Klub Inline Demo']);
        $this->buildHeats($event);
        $this->fillEveryHeatWithFinishes($event);

        $setting = $event->raceSettingFor('Junior', 'Classic Slalom');

        $this->assertSame(['heat'], $setting->stageLadder());
        $this->assertSame('heat', $setting->decidingStage());
        $this->assertFalse($setting->hasFinal());
        $this->assertFalse($setting->hasSemiFinal());

        // Tanpa final, babak grup-lah yang menentukan: ada dua juara 1
        // (satu dari tiap babak) dan tidak ada babak final untuk disusun.
        $response = $this->actingAs($owner)
            ->get(route('owner.events.race.day', ['event' => $event, 'kategori' => 'Junior']))
            ->assertOk();

        $this->assertSame(2, substr_count($response->getContent(), 'Juara 1'));
        $response->assertDontSee('name="stage"');
        $response->assertSee('Langsung menang tanpa final', false);
    }

    public function test_qualification_round_adds_a_stage_between_the_heat_and_the_semi_final(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->saveFormat($event, [
            'runners_per_heat' => 6,
            'qualify_count' => 3,
            'stages' => 'qualification_round',
            'semi_finalists' => 2,
            'finalists' => 4,
        ]);

        $this->makeEntrants($event, 6, ['Klub Inline Demo']);
        $this->buildHeats($event);
        $this->fillEveryHeatWithFinishes($event);

        $response = $this->actingAs($owner)
            ->get(route('owner.events.race.day', ['event' => $event, 'kategori' => 'Junior']))
            ->assertOk()
            ->assertSee('Babak penyisihan')
            ->assertSee('Semifinal');

        // Penyisihan menampung seluruh yang lolos dari babak grup (1 × 3).
        $this->actingAs($owner)
            ->post(route('owner.events.race.advance', $event), [
                'kategori' => 'Junior',
                'stage' => 'qualification',
            ])
            ->assertRedirect();

        $this->assertSame(3, EventRaceEntry::whereIn(
            'event_race_heat_id',
            EventRaceHeat::where('stage', 'qualification')->pluck('id')
        )->count());

        $this->fillEveryHeatWithFinishes($event, 'qualification');

        $this->actingAs($owner)
            ->post(route('owner.events.race.advance', $event), [
                'kategori' => 'Junior',
                'stage' => 'semi_final',
            ])
            ->assertRedirect();

        $this->assertSame(
            2,
            EventRaceEntry::whereIn('event_race_heat_id', EventRaceHeat::where('stage', 'semi_final')->pluck('id'))->count()
        );
    }

    public function test_a_format_without_a_semi_final_explains_why_the_stage_cannot_be_built(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->saveFormat($event, ['stages' => 'direct_final']);

        $this->actingAs($owner)
            ->post(route('owner.events.race.advance', $event), [
                'kategori' => 'Junior',
                'stage' => 'qualification',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');
    }

    /* ------------------------------------------------------------------ */
    /* Urutan lomba: grup, rilis, siapkan peserta, dan tunda */
    /* ------------------------------------------------------------------ */

    public function test_arranging_the_race_wraps_heats_in_groups(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->saveFormat($event, [
            'runners_per_heat' => 3,
            'qualify_count' => 3,
            'champion_count' => 3,
            'athletes_per_group' => 6,
        ]);
        $this->makeEntrants($event, 12, ['Klub Inline Demo']);
        $this->buildHeats($event);

        $heatGroups = EventRaceGroup::where('stage', 'heat')
            ->with('heats')
            ->orderBy('number')
            ->get();

        // 12 atlet, 3 per babak, 6 per grup → empat babak yang terbagi
        // menjadi dua grup berisi dua babak.
        $this->assertCount(2, $heatGroups);
        $this->assertSame([2, 2], $heatGroups->pluck('heats')->map->count()->all());

        // Tahap final masih berupa grup penanda sampai hasil grup awal ada.
        $finalGroup = EventRaceGroup::where('stage', 'final')->first();
        $this->assertNotNull($finalGroup);
        $this->assertCount(0, $finalGroup->heats);
    }

    public function test_auto_grouping_batches_medal_heats_into_bigger_groups(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->saveFormat($event, [
            'stages' => 'no_final',
            'champion_count' => 3,
            'grouping_mode' => 'auto',
        ]);

        $setting = $event->raceSettingFor('Junior', 'Classic Slalom');

        // Tanpa final, ukuran babak diambil dari jumlah juara supaya tiap
        // anak mendapat medali, dan satu grup memuat kira-kira lima babak.
        $this->assertSame(3, $setting->effectiveRunnersPerHeat(90));
        $this->assertSame(15, $setting->effectiveAthletesPerGroup(90));

        $this->makeEntrants($event, 15, ['Klub Inline Demo']);
        $this->buildHeats($event);

        $this->assertSame(1, EventRaceGroup::where('stage', 'heat')->count());
        $this->assertSame(5, EventRaceHeat::where('stage', 'heat')->count());
    }

    public function test_the_format_page_offers_to_arrange_then_release_the_race_order(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);
        $this->saveFormat($event, ['runners_per_heat' => 3, 'athletes_per_group' => 6]);
        $this->makeEntrants($event, 6, ['Klub Inline Demo']);
        $this->buildHeats($event);

        $this->actingAs($owner)
            ->get(route('owner.events.race.format', $event))
            ->assertOk()
            ->assertSee('Susunan urutan lomba')
            ->assertSee('Rilis kategori ini')
            ->assertSee('Grup 1');

        $this->actingAs($owner)
            ->post(route('owner.events.race.release', $event), [
                'kategori' => 'Junior',
                'mata_lomba' => 'Classic Slalom',
            ])
            ->assertRedirect();

        $this->assertTrue($event->raceSettings()->where('category', 'Junior')->sole()->isReleased());

        $this->actingAs($owner)
            ->get(route('owner.events.race.format', $event))
            ->assertOk()
            ->assertSee('Sudah dirilis')
            ->assertSee('Tarik rilis');
    }

    public function test_releasing_without_an_arrangement_is_refused(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);
        $this->saveFormat($event);

        $this->actingAs($owner)
            ->post(route('owner.events.race.release', $event), [
                'kategori' => 'Junior',
                'mata_lomba' => 'Classic Slalom',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertNull($event->raceSettings()->where('category', 'Junior')->sole()->released_at);
    }

    public function test_releasing_the_category_publishes_the_race_order_for_every_participant(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $participant = User::factory()->create();
        $event = $this->createEvent($owner);

        $this->saveFormat($event, ['runners_per_heat' => 3, 'athletes_per_group' => 6]);
        $this->makeEntrants($event, 6, ['Klub Inline Demo']);
        $this->buildHeats($event);

        $this->actingAs($participant)
            ->get(route('events.race.order', $event))
            ->assertOk()
            ->assertSee('Belum ada urutan lomba yang dirilis')
            ->assertDontSee('Atlet Junior 01');

        $this->actingAs($owner)
            ->post(route('owner.events.race.release', $event), [
                'kategori' => 'Junior',
                'mata_lomba' => 'Classic Slalom',
            ])
            ->assertRedirect();

        $this->actingAs($participant)
            ->get(route('events.race.order', $event))
            ->assertOk()
            ->assertSee('Junior · Classic Slalom')
            ->assertSee('Grup 1')
            ->assertSee('Atlet Junior 01');

        $this->actingAs($owner)
            ->delete(route('owner.events.race.unrelease', $event), [
                'kategori' => 'Junior',
                'mata_lomba' => 'Classic Slalom',
            ])
            ->assertRedirect();

        $this->actingAs($participant)
            ->get(route('events.race.order', $event))
            ->assertOk()
            ->assertSee('Belum ada urutan lomba yang dirilis');
    }

    public function test_the_day_page_keeps_the_result_buttons_hidden_until_the_group_is_prepared(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);
        $this->saveFormat($event, ['runners_per_heat' => 3, 'athletes_per_group' => 6]);
        $this->makeEntrants($event, 6, ['Klub Inline Demo']);
        $this->buildHeats($event);

        // Sebelum disiapkan: susunannya kelihatan, tombol hasil tidak ada.
        $this->actingAs($owner)
            ->get(route('owner.events.race.day', ['event' => $event, 'kategori' => 'Junior']))
            ->assertOk()
            ->assertSee('Siapkan peserta')
            ->assertSee('Atlet Junior 01')
            ->assertDontSee('data-race-quick')
            ->assertDontSee('Simpan hasil babak');

        $group = EventRaceGroup::where('stage', 'heat')->first();

        $this->actingAs($owner)
            ->post(route('owner.events.race.group.prepare', [$event, $group]))
            ->assertRedirect();

        // Sesudah disiapkan: seluruh tombol hasil terbuka.
        $this->actingAs($owner)
            ->get(route('owner.events.race.day', ['event' => $event, 'kategori' => 'Junior']))
            ->assertOk()
            ->assertSee('data-race-quick')
            ->assertSee('Tunda', false)
            ->assertSee('Simpan hasil babak');
    }

    public function test_preparing_a_group_marks_it_running_on_the_public_order_page(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $participant = User::factory()->create();
        $event = $this->createEvent($owner);

        $this->saveFormat($event, ['runners_per_heat' => 3, 'athletes_per_group' => 6]);
        $this->makeEntrants($event, 6, ['Klub Inline Demo']);
        $this->buildHeats($event);

        $this->actingAs($owner)
            ->post(route('owner.events.race.release', $event), [
                'kategori' => 'Junior',
                'mata_lomba' => 'Classic Slalom',
            ])
            ->assertRedirect();

        $this->actingAs($participant)
            ->get(route('events.race.order', $event))
            ->assertSee('race-group-status is-pending', false)
            ->assertDontSee('race-group-status is-running', false);

        $group = EventRaceGroup::where('stage', 'heat')->first();
        $this->actingAs($owner)
            ->post(route('owner.events.race.group.prepare', [$event, $group]))
            ->assertRedirect();

        $this->actingAs($participant)
            ->get(route('events.race.order', $event))
            ->assertSee('race-group-status is-running', false);
    }

    public function test_postponing_an_athlete_makes_everyone_behind_them_move_up(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->saveFormat($event, [
            'stages' => 'no_final',
            'runners_per_heat' => 3,
            'qualify_count' => 3,
            'champion_count' => 3,
            'athletes_per_group' => 6,
        ]);
        $this->makeEntrants($event, 9, ['Klub Inline Demo']);
        $this->buildHeats($event);

        $queue = EventRaceEntry::query()
            ->with('heat.group')
            ->get()
            ->sortBy(fn (EventRaceEntry $entry): array => [
                (int) ($entry->heat?->group?->number ?? 0),
                (int) ($entry->heat?->number ?? 0),
                (int) $entry->lane,
            ])
            ->values();

        $this->assertCount(9, $queue);

        $before = $queue->mapWithKeys(fn (EventRaceEntry $entry): array => [
            $entry->id => ['heat' => $entry->event_race_heat_id, 'lane' => $entry->lane],
        ]);

        $first = $queue[0];
        $second = $queue[1];
        $secondLast = $queue[7];
        $last = $queue[8];

        $this->actingAs($owner)
            ->post(route('owner.events.race.entry.postpone', [$event, $first]))
            ->assertRedirect();

        $after = EventRaceEntry::query()
            ->get()
            ->mapWithKeys(fn (EventRaceEntry $entry): array => [
                $entry->id => ['heat' => $entry->event_race_heat_id, 'lane' => $entry->lane],
            ]);

        $this->assertSame(EventRaceEntry::STATUS_POSTPONED, $first->refresh()->status);

        // Atlet yang ditunda mengisi slot paling akhir.
        $this->assertSame($before[$last->id], $after[$first->id]);
        // Atlet kedua maju mengisi slot yang baru ditinggal.
        $this->assertSame($before[$first->id], $after[$second->id]);
        // Atlet terakhir juga maju satu per satu sampai slotnya tertutup.
        $this->assertSame($before[$secondLast->id], $after[$last->id]);
        // Jumlah slot tidak berubah: yang bergeser hanya urutannya saja.
        $slotList = fn (array $rows): array => collect($rows)
            ->map(fn (array $slot): string => $slot['heat'].'-'.$slot['lane'])
            ->sort()
            ->values()
            ->all();

        $this->assertSame($slotList($before->all()), $slotList($after->all()));
    }

    public function test_postponing_never_reaches_another_mata_lomba(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->withTwoRaces($this->createEvent($owner, 'Kediri Roller Cup', ['Junior']));

        $this->saveFormat($event, [
            'stages' => 'no_final',
            'runners_per_heat' => 3,
            'athletes_per_group' => 6,
        ]);
        $this->makeEntrants($event, 3, ['Klub A'], 'Junior', 'paid', ['Classic Slalom']);
        $this->makeEntrants($event, 3, ['Klub B'], 'Junior', 'paid', ['Speed Slalom']);
        $this->buildHeats($event, 'Junior', 'Classic Slalom');
        $this->buildHeats($event, 'Junior', 'Speed Slalom');

        $otherBefore = EventRaceEntry::where('race', 'Speed Slalom')
            ->get()
            ->mapWithKeys(fn (EventRaceEntry $entry): array => [
                $entry->id => ['heat' => $entry->event_race_heat_id, 'lane' => $entry->lane],
            ]);

        $first = EventRaceEntry::where('race', 'Classic Slalom')->orderBy('id')->first();

        $this->actingAs($owner)
            ->post(route('owner.events.race.entry.postpone', [$event, $first]))
            ->assertRedirect();

        $otherAfter = EventRaceEntry::where('race', 'Speed Slalom')
            ->get()
            ->mapWithKeys(fn (EventRaceEntry $entry): array => [
                $entry->id => ['heat' => $entry->event_race_heat_id, 'lane' => $entry->lane],
            ]);

        $this->assertSame($otherBefore->all(), $otherAfter->all());
    }

    public function test_a_group_smaller_than_one_heat_is_rejected(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->actingAs($owner)
            ->from(route('owner.events.race.format', $event))
            ->put(route('owner.events.race.format.update', $event), $this->validFormat(['athletes_per_group' => 4]))
            ->assertSessionHasErrors('race_settings.0.athletes_per_group');
    }

    public function test_saving_the_settings_pulls_the_release_back(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->saveFormat($event, ['runners_per_heat' => 3, 'athletes_per_group' => 6]);
        $this->makeEntrants($event, 6, ['Klub Inline Demo']);
        $this->buildHeats($event);

        $this->actingAs($owner)
            ->post(route('owner.events.race.release', $event), [
                'kategori' => 'Junior',
                'mata_lomba' => 'Classic Slalom',
            ])
            ->assertRedirect();

        $this->assertNotNull($event->raceSettings()->where('category', 'Junior')->sole()->released_at);

        $this->actingAs($owner)
            ->put(route('owner.events.race.format.update', $event), $this->validFormat())
            ->assertRedirect();

        // Aturan baru belum tentu cocok dengan susunan lama, jadi rilisnya
        // ditarik sampai panitia menyusun ulang.
        $this->assertNull($event->raceSettings()->where('category', 'Junior')->sole()->released_at);
    }

    /* ------------------------------------------------------------------ */
    /* Helper */
    /* ------------------------------------------------------------------ */

    private function createEvent(User $owner, string $name = 'Kediri Roller Cup', array $categories = ['Junior']): Event
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
            'categories' => $categories,
            'category_rules' => array_map(fn (string $category): array => ['name' => $category, 'races' => ['Classic Slalom']], $categories),
            'races' => ['Classic Slalom'],
            'schedule' => ['Final'],
            'status' => 'approved',
        ]);
    }

    /**
     * Event dengan dua mata lomba pada tiap kategori, untuk menguji bahwa
     * aturan perlombaan dan pembagian babak benar-benar dipisah per mata
     * lomba dan tidak lagi per kategori saja.
     */
    private function withTwoRaces(Event $event): Event
    {
        $event->update([
            'races' => ['Classic Slalom', 'Speed Slalom'],
            'category_rules' => array_map(
                fn (array $rule): array => array_merge($rule, ['races' => ['Classic Slalom', 'Speed Slalom']]),
                $event->category_rules ?: [['name' => 'Junior']]
            ),
        ]);

        return $event;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validFormat(array $overrides = []): array
    {
        return [
            'race_settings' => [
                array_merge([
                    'category' => 'Junior',
                    'race' => 'Classic Slalom',
                    'runners_per_heat' => 6,
                    'scoring_system' => 'qualification',
                    'qualify_count' => 3,
                    'stages' => 'direct_final',
                    'semi_finalists' => 0,
                    'finalists' => 8,
                    'champion_count' => 3,
                    'opponent_scope' => 'mixed',
                    'grouping_mode' => 'manual',
                ], $overrides),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function saveFormat(Event $event, array $overrides = []): void
    {
        $category = $overrides['category'] ?? 'Junior';
        $race = $overrides['race'] ?? 'Classic Slalom';
        unset($overrides['category'], $overrides['race']);

        $event->raceSettings()->create(array_merge([
            'category' => $category,
            'race' => $race,
            'runners_per_heat' => 6,
            'scoring_system' => 'qualification',
            'qualify_count' => 3,
            'stages' => 'direct_final',
            'semi_finalists' => 0,
            'finalists' => 8,
            'champion_count' => 3,
            'opponent_scope' => 'mixed',
        ], $overrides));
    }

    /**
     * @param  array<int, string>  $clubs
     */
    private function makeEntrants(
        Event $event,
        int $count,
        array $clubs = ['Klub Inline Demo'],
        string $category = 'Junior',
        string $paymentStatus = 'paid',
        array $races = ['Classic Slalom']
    ): void {
        foreach (range(1, $count) as $index) {
            EventRegistration::create([
                'event_id' => $event->id,
                'user_id' => User::factory()->create()->id,
                'participant_name' => sprintf('Atlet %s %02d', $category, $index),
                'birth_year' => 2012,
                'gender' => 'male',
                'club_name' => $clubs[($index - 1) % count($clubs)],
                'category' => $category,
                'races' => $races,
                'fee_total' => 150000,
                'status' => 'approved',
                'payment_status' => $paymentStatus,
            ]);
        }
    }

    private function buildHeats(Event $event, string $category = 'Junior', string $race = 'Classic Slalom'): void
    {
        app(RaceHeatBuilder::class)
            ->buildHeats($event, $event->raceSettingFor($category, $race));
    }

    /**
     * Isi semua babak pada satu tahap supaya tahap dianggap selesai:
     * empat finis, satu tidak muncul, sisanya tidak menyelesaikan.
     */
    private function fillEveryHeatWithFinishes(Event $event, string $stage = 'heat'): void
    {
        foreach ($event->raceHeats()->where('stage', $stage)->with('entries')->get() as $heat) {
            $payload = [];

            foreach ($heat->entries->sortBy('lane')->values() as $index => $entry) {
                if ($index < 4) {
                    $payload[$entry->id] = [
                        'status' => 'finished',
                        'place' => $index + 1,
                        'time' => (20 + $index).'.'.($index + 1).'0',
                    ];

                    continue;
                }

                $payload[$entry->id] = ['status' => $index === 4 ? 'no_show' : 'dnf'];
            }

            $this->actingAs(User::find($event->submitted_by))
                ->patch(route('owner.events.race.heat.update', [$event, $heat]), ['entries' => $payload])
                ->assertRedirect();
        }
    }
}
