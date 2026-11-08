<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\CategoryFeeCalculator;
use App\Services\ResolveEventRegistrationCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_fee_calculator_applies_package_extra_races_outside_races_and_discount(): void
    {
        $calculator = app(CategoryFeeCalculator::class);
        $rule = $this->pricedRule();

        $twoInside = $calculator->breakdown($rule, ['Classic Slalom', 'Speed Slalom']);
        $this->assertSame(150000, $twoInside['subtotal']);
        $this->assertSame(0, $twoInside['extra_races']);
        $this->assertSame(25000, $twoInside['discount_total']);
        $this->assertSame(125000, $twoInside['total']);

        $threeInside = $calculator->breakdown($rule, ['Classic Slalom', 'Speed Slalom', 'Relay']);
        $this->assertSame(1, $threeInside['extra_races']);
        $this->assertSame(50000, $threeInside['extra_race_total']);
        $this->assertSame(200000, $threeInside['subtotal']);
        $this->assertSame(30000, $threeInside['discount_total']);
        $this->assertSame(170000, $threeInside['total']);

        $withOutside = $calculator->breakdown($rule, ['Classic Slalom', 'Speed Slalom', 'Downhill']);
        $this->assertSame(['Downhill'], $withOutside['outside_races']);
        $this->assertSame(5000, $withOutside['outside_race_total']);
        $this->assertSame(155000, $withOutside['subtotal']);
        $this->assertSame(129500, $withOutside['total']);
    }

    public function test_fee_calculator_never_returns_a_negative_total(): void
    {
        $breakdown = app(CategoryFeeCalculator::class)->breakdown(
            $this->pricedRule(['package_fee' => 5000, 'discount_amount' => 500000]),
            ['Classic Slalom', 'Speed Slalom'],
        );

        $this->assertSame(0, $breakdown['total']);
    }

    public function test_outside_obligation_category_charges_the_outside_fee_for_each_selected_race(): void
    {
        $rule = $this->pricedRule([
            'outside_obligation' => true,
            'races' => ['Downhill', 'Slalom Cross'],
            'required_races' => 1,
            'package_fee' => 0,
            'extra_race_fee' => 0,
            'outside_race_fee' => 5000,
            'discount_percent' => null,
            'discount_amount' => null,
        ]);

        $breakdown = app(CategoryFeeCalculator::class)->breakdown($rule, ['Downhill', 'Slalom Cross']);

        $this->assertSame(['Downhill', 'Slalom Cross'], $breakdown['outside_races']);
        $this->assertSame(10000, $breakdown['outside_race_total']);
        $this->assertSame(10000, $breakdown['total']);
    }

    public function test_organizer_can_store_required_races_and_pricing_per_category(): void
    {
        [$owner, $event] = $this->createEventWithRules();

        $this->actingAs($owner)
            ->put(route('owner.events.categories.update', $event), [
                'category_rules' => [[
                    'name' => 'Junior Putra',
                    'eligibility_types' => ['birth_date'],
                    'birth_date_from' => '2010-01-01',
                    'birth_date_to' => '2020-12-31',
                    'genders' => ['male'],
                    'races' => ['Classic Slalom', 'Speed Slalom'],
                    'required_races' => 2,
                    'package_fee' => 150000,
                    'extra_race_fee' => 50000,
                    'outside_race_fee' => 5000,
                    'discount_percent' => 10,
                    'discount_amount' => 10000,
                ]],
            ])
            ->assertRedirect(route('guest.dashboard'));

        $rule = $event->fresh()->category_rules[0];
        $this->assertSame(2, $rule['required_races']);
        $this->assertSame(150000, $rule['package_fee']);
        $this->assertSame(50000, $rule['extra_race_fee']);
        $this->assertSame(5000, $rule['outside_race_fee']);
        $this->assertSame(10, $rule['discount_percent']);
        $this->assertSame(10000, $rule['discount_amount']);
    }

    public function test_organizer_can_save_separate_outside_obligation_category_and_fee(): void
    {
        [$owner, $event] = $this->createEventWithRules();
        $event->update([
            'races' => ['Classic Slalom', 'Speed Slalom', 'Relay', 'Downhill', 'Slalom Cross'],
        ]);

        $this->actingAs($owner)
            ->put(route('owner.events.categories.update', $event), [
                'category_rules' => [
                    [
                        'base_name' => 'Junior',
                        'gender_group' => 'male',
                        'eligibility_types' => ['birth_date'],
                        'birth_date_from' => '2010-01-01',
                        'birth_date_to' => '2020-12-31',
                        'races' => ['Classic Slalom', 'Speed Slalom', 'Relay'],
                        'required_races' => 2,
                        'package_fee' => 150000,
                        'extra_race_fee' => 50000,
                        'outside_race_fee' => 5000,
                    ],
                    [
                        'base_name' => 'Junior',
                        'gender_group' => 'male',
                        'outside_obligation' => true,
                        'eligibility_types' => ['birth_date'],
                        'birth_date_from' => '2010-01-01',
                        'birth_date_to' => '2020-12-31',
                        'races' => ['Downhill', 'Slalom Cross'],
                        'required_races' => 1,
                        'package_fee' => 0,
                        'extra_race_fee' => 0,
                        'outside_race_fee' => 5000,
                    ],
                ],
            ])
            ->assertRedirect(route('guest.dashboard'));

        $rules = $event->fresh()->category_rules;
        $this->assertSame(['Junior Putra', 'Junior Putra — di luar kewajiban'], array_column($rules, 'name'));
        $this->assertSame(false, $rules[0]['outside_obligation']);
        $this->assertSame(true, $rules[1]['outside_obligation']);
        $this->assertSame(5000, $rules[1]['outside_race_fee']);
        $this->assertSame('Junior Putra — di luar kewajiban', $rules[1]['name']);

        $this->actingAs($owner)
            ->get(route('owner.events.categories.edit', $event))
            ->assertSee('tambahan di luar kewajiban')
            ->assertSee('data-outside-obligation="true"', false)
            ->assertSee('<option value="Junior"', false)
            ->assertDontSee('<option value="Junior Putra — di luar kewajiban"', false);

        $match = app(ResolveEventRegistrationCategory::class)->bestMatch(
            $event->fresh(),
            '2014-01-01',
            'male',
            ['Downhill'],
        );
        $this->assertNotNull($match);
        $this->assertSame('Junior Putra — di luar kewajiban', $match['rule']['name']);

        $registration = $this->createRegistration($event, User::factory()->create(['role' => 'individual']));
        $registration->update([
            'category' => 'Junior Putra — di luar kewajiban',
            'races' => ['Downhill', 'Slalom Cross'],
        ]);
        app(CategoryFeeCalculator::class)->sync($event->fresh(), $registration);

        $this->assertSame(10000, $registration->fee_total);
    }

    public function test_overlapping_categories_report_the_shared_birth_gender_and_race_details(): void
    {
        [$owner, $event] = $this->createEventWithRules();
        $event->update(['start_date' => '2027-02-05']);

        $this->actingAs($owner)
            ->from(route('owner.events.categories.edit', $event))
            ->put(route('owner.events.categories.update', $event), [
                'category_rules' => [
                    [
                        'name' => 'Usia 11 tahun',
                        'eligibility_types' => ['age'],
                        'minimum_age' => 11,
                        'maximum_age' => 11,
                        'genders' => ['male'],
                        'races' => ['Classic Slalom'],
                    ],
                    [
                        'name' => 'Tahun lahir 2015',
                        'eligibility_types' => ['birth_year'],
                        'birth_years' => [2015],
                        'genders' => ['male'],
                        'races' => ['Classic Slalom', 'Speed Slalom'],
                    ],
                ],
            ])
            ->assertRedirect(route('owner.events.categories.edit', $event))
            ->assertSessionHasErrors([
                'category_rules.1.birth_date_from' => 'Kategori "Usia 11 tahun" bertumpang tindih dengan kategori "Tahun lahir 2015" untuk tanggal lahir 2015-02-06 sampai 2015-12-31, gender laki-laki, dan mata lomba Classic Slalom.',
            ]);

        $this->assertSame('Junior Putra', $event->fresh()->category_rules[0]['name']);
    }

    public function test_category_form_builds_the_final_name_from_category_and_gender_dropdowns(): void
    {
        [$owner, $event] = $this->createEventWithRules();
        $event->update(['categories' => ['Beginner', 'Non-Beginner']]);

        $this->actingAs($owner)
            ->get(route('owner.events.categories.edit', $event))
            ->assertOk()
            ->assertSee('data-category-base-name', false)
            ->assertSee('data-category-gender-group', false)
            ->assertSee('data-category-name-preview', false)
            ->assertSee('data-category-pair-prompt', false)
            ->assertSee('data-outside-race-picker', false)
            ->assertSee('data-outside-race-option', false)
            ->assertSee('Mata lomba di luar paket')
            ->assertSee('data-create-counterpart', false)
            ->assertSee('data-dismiss-counterpart', false)
            ->assertSee('Apakah ingin membuat kategori yang sama untuk', false)
            ->assertSee('>Campuran</option>', false)
            ->assertSee('<option value="Beginner"', false)
            ->assertSee('<option value="Non-Beginner"', false)
            ->assertDontSee('<option value="Junior Putra"', false);

        $this->actingAs($owner)
            ->put(route('owner.events.categories.update', $event), [
                'category_rules' => collect([
                    ['gender_group' => 'male', 'birth_year' => 2014],
                    ['gender_group' => 'female', 'birth_year' => 2013],
                    ['gender_group' => 'mixed', 'birth_year' => 2012],
                ])->map(fn (array $selection): array => [
                    'base_name' => 'Beginner',
                    'gender_group' => $selection['gender_group'],
                    'eligibility_types' => ['birth_year'],
                    'birth_years' => [$selection['birth_year']],
                    'races' => ['Classic Slalom'],
                ])->all(),
            ])
            ->assertRedirect(route('guest.dashboard'));

        $rules = $event->fresh()->category_rules;
        $this->assertSame(['Beginner Putra', 'Beginner Putri', 'Beginner Campuran'], array_column($rules, 'name'));
        $this->assertSame([['male'], ['female'], ['male', 'female']], array_column($rules, 'genders'));
    }

    public function test_category_form_rejects_a_category_name_not_registered_for_the_event(): void
    {
        [$owner, $event] = $this->createEventWithRules();

        $this->actingAs($owner)
            ->from(route('owner.events.categories.edit', $event))
            ->put(route('owner.events.categories.update', $event), [
                'category_rules' => [[
                    'base_name' => 'Kategori Palsu',
                    'gender_group' => 'male',
                    'eligibility_types' => ['birth_date'],
                    'birth_date_from' => '2010-01-01',
                    'birth_date_to' => '2020-12-31',
                    'races' => ['Classic Slalom'],
                ]],
            ])
            ->assertRedirect(route('owner.events.categories.edit', $event))
            ->assertSessionHasErrors('category_rules.0.base_name');

        $this->assertSame('Junior Putra', $event->fresh()->category_rules[0]['name']);
    }

    public function test_required_races_cannot_exceed_the_races_allowed_for_the_category(): void
    {
        [$owner, $event] = $this->createEventWithRules();

        $this->actingAs($owner)
            ->from(route('owner.events.categories.edit', $event))
            ->put(route('owner.events.categories.update', $event), [
                'category_rules' => [[
                    'name' => 'Junior Putra',
                    'eligibility_types' => ['birth_date'],
                    'birth_date_from' => '2010-01-01',
                    'birth_date_to' => '2020-12-31',
                    'genders' => ['male'],
                    'races' => ['Classic Slalom'],
                    'required_races' => 3,
                    'package_fee' => 100000,
                ]],
            ])
            ->assertRedirect(route('owner.events.categories.edit', $event))
            ->assertSessionHasErrors('category_rules.0.required_races');
    }

    public function test_registration_is_blocked_when_fewer_races_than_required_are_selected(): void
    {
        [$owner, $event] = $this->createEventWithRules();
        $this->pricingRules($event);
        $participant = User::factory()->create(['role' => 'individual']);

        $this->actingAs($participant)
            ->from(route('events.registration.create', $event))
            ->post(route('events.registration.store', $event), [
                'nik' => $this->nikFor('male', '2014-01-01', 100),
                'participant_name' => 'Atlet Kurang Mata Lomba',
                'gender' => 'male',
                'date_of_birth' => '2014-01-01',
                'address' => 'Kediri',
                'guardian_name' => 'Wali Atlet',
                'whatsapp' => '081234567890',
                'races' => ['Classic Slalom'],
            ])
            ->assertRedirect(route('events.registration.create', $event))
            ->assertSessionHasErrors('races');

        $this->assertDatabaseCount('event_registrations', 0);
    }

    public function test_registration_accepts_the_required_number_of_races(): void
    {
        [$owner, $event] = $this->createEventWithRules();
        $this->pricingRules($event);
        $participant = User::factory()->create(['role' => 'individual']);

        $this->actingAs($participant)
            ->post(route('events.registration.store', $event), [
                'nik' => $this->nikFor('male', '2014-01-01', 101),
                'participant_name' => 'Atlet Tepat Mata Lomba',
                'gender' => 'male',
                'date_of_birth' => '2014-01-01',
                'address' => 'Kediri',
                'guardian_name' => 'Wali Atlet',
                'whatsapp' => '081234567890',
                'races' => ['Classic Slalom', 'Speed Slalom'],
            ])
            ->assertRedirect(route('guest.dashboard'));

        $this->assertDatabaseCount('event_registrations', 1);
    }

    public function test_confirming_payment_stores_the_computed_fee_on_the_registration(): void
    {
        [$owner, $event] = $this->createEventWithRules();
        $this->pricingRules($event);
        $registration = $this->createRegistration($event, User::factory()->create(['role' => 'individual']));
        $registration->update([
            'races' => ['Classic Slalom', 'Speed Slalom'],
            'payment_status' => 'pending',
            'payment_channel' => 'whatsapp',
            'category' => null,
        ]);

        $this->actingAs($owner)
            ->post(route('events.registrations.payment.confirm', [$event, $registration]))
            ->assertRedirect(route('events.registrations.index', $event));

        $fresh = $registration->fresh();
        $this->assertSame('Junior Putra', $fresh->category);
        $this->assertSame(125000, $fresh->fee_total);
        $this->assertSame(150000, $fresh->fee_breakdown['package_fee']);
        $this->assertSame(25000, $fresh->fee_breakdown['discount_total']);
    }

    public function test_auto_assignment_stores_the_fee_for_mixed_race_selections(): void
    {
        [$owner, $event] = $this->createEventWithRules();
        $this->pricingRules($event);
        $registration = $this->createRegistration($event, User::factory()->create(['role' => 'individual']));
        $registration->update([
            'races' => ['Classic Slalom', 'Downhill'],
            'payment_status' => 'paid',
            'category' => null,
        ]);

        $this->actingAs($owner)
            ->post(route('events.registrations.categories.assign', $event))
            ->assertRedirect(route('events.registrations.index', $event));

        $fresh = $registration->fresh();
        $this->assertSame('Junior Putra', $fresh->category);
        $this->assertSame(['Downhill'], $fresh->fee_breakdown['outside_races']);
        $this->assertSame(129500, $fresh->fee_total);
    }

    public function test_updating_participant_races_from_the_roster_recalculates_the_fee(): void
    {
        [$owner, $event] = $this->createEventWithRules();
        $this->pricingRules($event);
        $registration = $this->createRegistration($event, User::factory()->create(['role' => 'individual']));
        $registration->update([
            'races' => ['Classic Slalom', 'Speed Slalom'],
            'payment_status' => 'paid',
            'category' => 'Junior Putra',
        ]);
        app(CategoryFeeCalculator::class)->sync($event->fresh(), $registration);
        $registration->save();

        $this->actingAs($owner)
            ->put(route('events.registrations.update', [$event, $registration]), [
                'nik' => $this->nikFor('male', '2014-01-01', 200),
                'participant_name' => 'Atlet Berbayar',
                'gender' => 'male',
                'date_of_birth' => '2014-01-01',
                'address' => 'Tulungagung',
                'guardian_name' => 'Wali Atlet',
                'whatsapp' => '081234567890',
                'club_name' => 'Klub Roda',
                'status' => 'pending',
                'races' => ['Classic Slalom', 'Speed Slalom', 'Relay'],
            ])
            ->assertRedirect(route('events.registrations.index', $event));

        $fresh = $registration->fresh();
        $this->assertSame(1, $fresh->fee_breakdown['extra_races']);
        $this->assertSame(170000, $fresh->fee_total);
    }

    public function test_updating_pricing_rules_recalculates_existing_registration_fees(): void
    {
        [$owner, $event] = $this->createEventWithRules();
        $this->pricingRules($event);
        $registration = $this->createRegistration($event, User::factory()->create(['role' => 'individual']));
        $registration->update([
            'races' => ['Classic Slalom', 'Speed Slalom'],
            'payment_status' => 'paid',
            'category' => 'Junior Putra',
        ]);
        app(CategoryFeeCalculator::class)->sync($event->fresh(), $registration);
        $registration->save();
        $this->assertSame(125000, $registration->fresh()->fee_total);

        $this->actingAs($owner)
            ->put(route('owner.events.categories.update', $event), [
                'category_rules' => [[
                    'name' => 'Junior Putra',
                    'eligibility_types' => ['birth_date'],
                    'birth_date_from' => '2010-01-01',
                    'birth_date_to' => '2020-12-31',
                    'genders' => ['male'],
                    'races' => ['Classic Slalom', 'Speed Slalom'],
                    'required_races' => 2,
                    'package_fee' => 200000,
                    'extra_race_fee' => 50000,
                    'outside_race_fee' => 5000,
                    'discount_percent' => null,
                    'discount_amount' => null,
                ]],
            ])
            ->assertRedirect(route('guest.dashboard'));

        $this->assertSame(200000, $registration->fresh()->fee_total);
    }

    public function test_form_kategori_menampilkan_tombol_terapkan_gelombang_ke_semua_kategori(): void
    {
        [$owner, $event] = $this->createEventWithRules();

        $this->actingAs($owner)
            ->get(route('owner.events.categories.update', $event))
            ->assertOk()
            ->assertSee('Terapkan ke semua kategori')
            ->assertSee('data-apply-wave-to-all', false);
    }

    public function test_gelombang_harga_yang_sama_dapat_disimpan_ke_semua_kategori(): void
    {
        [$owner, $event] = $this->createEventWithRules();

        $wave = [
            'label' => 'Gelombang Awal',
            'starts_on' => today()->format('Y-m-d'),
            'ends_on' => today()->addWeek()->format('Y-m-d'),
            'package_fee' => 200000,
            'extra_race_fee' => null,
            'outside_race_fee' => null,
            'discount_percent' => null,
            'discount_amount' => null,
        ];

        $this->actingAs($owner)
            ->put(route('owner.events.categories.update', $event), [
                'category_rules' => [
                    [
                        'name' => 'Junior Putra',
                        'eligibility_types' => ['birth_date'],
                        'birth_date_from' => '2010-01-01',
                        'birth_date_to' => '2020-12-31',
                        'genders' => ['male'],
                        'races' => ['Classic Slalom', 'Speed Slalom'],
                        'required_races' => 2,
                        'package_fee' => 250000,
                        'extra_race_fee' => 50000,
                        'outside_race_fee' => 5000,
                        'discount_percent' => null,
                        'discount_amount' => null,
                        'pricing_waves' => [$wave],
                    ],
                    [
                        'name' => 'Junior Putri',
                        'eligibility_types' => ['birth_date'],
                        'birth_date_from' => '2010-01-01',
                        'birth_date_to' => '2020-12-31',
                        'genders' => ['female'],
                        'races' => ['Classic Slalom', 'Speed Slalom'],
                        'required_races' => 2,
                        'package_fee' => 250000,
                        'extra_race_fee' => 50000,
                        'outside_race_fee' => 5000,
                        'discount_percent' => null,
                        'discount_amount' => null,
                        'pricing_waves' => [$wave],
                    ],
                ],
            ])
            ->assertRedirect(route('guest.dashboard'));

        $rules = $event->fresh()->category_rules;

        $this->assertCount(2, $rules);
        $this->assertSame('Gelombang Awal', $rules[0]['pricing_waves'][0]['label']);
        $this->assertSame('Gelombang Awal', $rules[1]['pricing_waves'][0]['label']);
        $this->assertSame(200000, $rules[1]['pricing_waves'][0]['package_fee']);
    }

    public function test_tarif_utama_bisa_diberi_rentang_tanggal_dan_tidak_bertolak_dengan_gelombang(): void
    {
        [$owner, $event] = $this->createEventWithRules();

        $this->actingAs($owner)
            ->put(route('owner.events.categories.update', $event), [
                'category_rules' => [[
                    'name' => 'Junior Putra',
                    'eligibility_types' => ['birth_date'],
                    'birth_date_from' => '2010-01-01',
                    'birth_date_to' => '2020-12-31',
                    'genders' => ['male'],
                    'races' => ['Classic Slalom', 'Speed Slalom'],
                    'required_races' => 2,
                    'package_fee' => 300000,
                    'extra_race_fee' => 50000,
                    'outside_race_fee' => 5000,
                    'discount_percent' => null,
                    'discount_amount' => null,
                    'base_starts_on' => '2026-12-01',
                    'base_ends_on' => '2026-12-31',
                    'pricing_waves' => [[
                        'label' => 'Early Bird',
                        'starts_on' => '2026-10-01',
                        'ends_on' => '2026-11-30',
                        'package_fee' => 250000,
                    ]],
                ]],
            ])
            ->assertRedirect(route('guest.dashboard'));

        $rule = $event->fresh()->category_rules[0];

        $this->assertSame('2026-12-01', $rule['base_starts_on']);
        $this->assertSame('2026-12-31', $rule['base_ends_on']);
    }

    public function test_tarif_utama_ditolak_bila_rentangnya_tumpang_tindih_dengan_gelombang(): void
    {
        [$owner, $event] = $this->createEventWithRules();

        $this->actingAs($owner)
            ->put(route('owner.events.categories.update', $event), [
                'category_rules' => [[
                    'name' => 'Junior Putra',
                    'eligibility_types' => ['birth_date'],
                    'birth_date_from' => '2010-01-01',
                    'birth_date_to' => '2020-12-31',
                    'genders' => ['male'],
                    'races' => ['Classic Slalom', 'Speed Slalom'],
                    'required_races' => 2,
                    'package_fee' => 300000,
                    'base_starts_on' => '2026-10-15',
                    'base_ends_on' => '2026-12-31',
                    'pricing_waves' => [[
                        'label' => 'Early Bird',
                        'starts_on' => '2026-10-01',
                        'ends_on' => '2026-11-30',
                        'package_fee' => 250000,
                    ]],
                ]],
            ])
            ->assertSessionHasErrors('category_rules.0.pricing_waves.0.starts_on');
    }

    public function test_gelombang_yang_saling_tumpang_tindih_ditolak(): void
    {
        [$owner, $event] = $this->createEventWithRules();

        $this->actingAs($owner)
            ->put(route('owner.events.categories.update', $event), [
                'category_rules' => [[
                    'name' => 'Junior Putra',
                    'eligibility_types' => ['birth_date'],
                    'birth_date_from' => '2010-01-01',
                    'birth_date_to' => '2020-12-31',
                    'genders' => ['male'],
                    'races' => ['Classic Slalom', 'Speed Slalom'],
                    'required_races' => 2,
                    'package_fee' => 300000,
                    'pricing_waves' => [
                        [
                            'label' => 'Gelombang 1',
                            'starts_on' => '2026-10-01',
                            'ends_on' => '2026-11-30',
                            'package_fee' => 250000,
                        ],
                        [
                            'label' => 'Gelombang 2',
                            'starts_on' => '2026-11-15',
                            'ends_on' => '2026-12-31',
                            'package_fee' => 300000,
                        ],
                    ],
                ]],
            ])
            ->assertSessionHasErrors('category_rules.0.pricing_waves.1.starts_on');
    }

    public function test_tarif_utama_yang_sudah_kedaluwarsa_tidak_dipakai_untuk_hitung_harga(): void
    {
        $calculator = app(CategoryFeeCalculator::class);

        $rule = [
            'name' => 'Junior Putra',
            'required_races' => 2,
            'package_fee' => 300000,
            'extra_race_fee' => 50000,
            'outside_race_fee' => 5000,
            'discount_percent' => null,
            'discount_amount' => null,
            'base_starts_on' => '2026-01-01',
            'base_ends_on' => '2026-01-31',
            'races' => ['Classic Slalom', 'Speed Slalom'],
            'pricing_waves' => [],
        ];

        $inRange = $calculator->breakdown($rule, ['Classic Slalom', 'Speed Slalom'], now()->parse('2026-01-15'));
        $this->assertSame(300000, $inRange['total']);

        $afterExpiry = $calculator->breakdown($rule, ['Classic Slalom', 'Speed Slalom'], now()->parse('2026-02-15'));
        $this->assertSame(0, $afterExpiry['total'], 'Tarif utama yang sudah lewat tidak boleh tetap dipakai.');
    }

    public function test_harga_early_bird_tetap_berlaku_saat_dibayar_sebelum_batas_akhir(): void
    {
        [$owner, $event] = $this->createEventWithRules();
        $this->pricingRules($event, [
            'discount_percent' => null,
            'discount_amount' => null,
            'pricing_waves' => [
                [
                    'label' => 'Early Bird',
                    'starts_on' => '2026-10-01',
                    'ends_on' => '2026-10-31',
                    'package_fee' => 250000,
                ],
                [
                    'label' => 'Latest',
                    'starts_on' => '2026-11-01',
                    'ends_on' => '2026-11-30',
                    'package_fee' => 350000,
                ],
            ],
        ]);

        $registration = $this->createRegistration($event, User::factory()->create(['role' => 'individual']));
        $registration->forceFill([
            'category' => 'Junior Putra',
            'races' => ['Classic Slalom', 'Speed Slalom'],
            'payment_status' => 'pending',
            'created_at' => now()->parse('2026-10-05'),   // daftar saat Early Bird
        ])->save();

        $calculator = app(CategoryFeeCalculator::class);
        $event = $event->fresh();

        $onTime = $calculator->breakdownForPayment($event, $registration->fresh(), now()->parse('2026-10-28'));
        $this->assertSame(250000, $onTime['total'], 'Bayar sebelum batas akhir Early Bird harus memakai harga Early Bird.');
        $this->assertSame('Early Bird', $onTime['pricing_wave']['label']);
    }

    public function test_harga_naik_ke_periode_berikutnya_bila_bayar_lewat_batas_akhir_gelombang(): void
    {
        [$owner, $event] = $this->createEventWithRules();
        $this->pricingRules($event, [
            'discount_percent' => null,
            'discount_amount' => null,
            'pricing_waves' => [
                [
                    'label' => 'Early Bird',
                    'starts_on' => '2026-10-01',
                    'ends_on' => '2026-10-31',
                    'package_fee' => 250000,
                ],
                [
                    'label' => 'Latest',
                    'starts_on' => '2026-11-01',
                    'ends_on' => '2026-11-30',
                    'package_fee' => 350000,
                ],
            ],
        ]);

        $registration = $this->createRegistration($event, User::factory()->create(['role' => 'individual']));
        $registration->forceFill([
            'category' => 'Junior Putra',
            'races' => ['Classic Slalom', 'Speed Slalom'],
            'payment_status' => 'pending',
            'created_at' => now()->parse('2026-10-05'),
        ])->save();

        $calculator = app(CategoryFeeCalculator::class);
        $event = $event->fresh();

        $late = $calculator->breakdownForPayment($event, $registration->fresh(), now()->parse('2026-11-10'));
        $this->assertSame(350000, $late['total'], 'Bayar lewat Early Bird harus memakai tarif periode yang berlaku.');
        $this->assertSame('Latest', $late['pricing_wave']['label']);

        // Selisih terhadap harga daftar adalah bahan pelunasan yang harus
        // ditagihkan ke peserta, bukan hilang diam-diam.
        $locked = $calculator->breakdown(
            $event->category_rules[0],
            ['Classic Slalom', 'Speed Slalom'],
            now()->parse('2026-10-05'),
        );
        $this->assertSame(250000, $locked['total'], 'Harga daftar saat pendaftaran adalah Early Bird.');
        $this->assertSame(100000, $late['total'] - $locked['total'], 'Peserta perlu melunasi selisih Early Bird.');
    }

    public function test_tarif_utama_tanpa_batas_tanggal_tidak_pernah_kedaluwarsa(): void
    {
        [$owner, $event] = $this->createEventWithRules();
        $this->pricingRules($event, [
            'discount_percent' => null,
            'discount_amount' => null,
        ]);

        $registration = $this->createRegistration($event, User::factory()->create(['role' => 'individual']));
        $registration->forceFill([
            'category' => 'Junior Putra',
            'races' => ['Classic Slalom', 'Speed Slalom'],
            'payment_status' => 'pending',
            'created_at' => now()->parse('2026-10-05'),
        ])->save();

        $calculator = app(CategoryFeeCalculator::class);

        $muchLater = $calculator->breakdownForPayment(
            $event->fresh(),
            $registration->fresh(),
            now()->parse('2030-01-01'),
        );

        $this->assertSame(150000, $muchLater['total']);
        $this->assertNull($muchLater['pricing_lock_expires_on']);
    }

    public function test_pengelola_dapat_memilih_harga_yang_dipakai_saat_konfirmasi(): void
    {
        [$owner, $event] = $this->createEventWithRules();
        $this->pricingRules($event, [
            'discount_percent' => null,
            'discount_amount' => null,
            'pricing_waves' => [
                [
                    'label' => 'Early Bird',
                    'starts_on' => '2026-10-01',
                    'ends_on' => '2026-10-31',
                    'package_fee' => 250000,
                ],
                [
                    'label' => 'Latest',
                    'starts_on' => '2026-11-01',
                    'ends_on' => '2026-11-30',
                    'package_fee' => 350000,
                ],
            ],
        ]);

        $registration = $this->createRegistration($event, User::factory()->create(['role' => 'individual']));
        $registration->forceFill([
            'category' => 'Junior Putra',
            'races' => ['Classic Slalom', 'Speed Slalom'],
            'payment_status' => 'pending',
            'payment_channel' => 'whatsapp',
            'created_at' => now()->parse('2026-10-05'),
        ])->save();

        $calculator = app(CategoryFeeCalculator::class);
        $event = $event->fresh();
        $registration = $registration->fresh();

        // Didaftarkan 5 Oktober (Early Bird), dibayar 10 November (Latest).
        $paidAt = now()->parse('2026-11-10');

        // Tanpa pilihan, aturan otomatis menaikkan tarif ke periode berjalan.
        $automatic = $calculator->breakdownForPayment($event, $registration, $paidAt, 'auto');
        $this->assertSame(350000, $automatic['total']);

        // Pengelola boleh memaksa harga periode berjalan untuk menagih kekurangan.
        $current = $calculator->breakdownForPayment($event, $registration, $paidAt, 'current');
        $this->assertSame(350000, $current['total'], 'Pengola bisa memilih harga periode berjalan.');

        // Pengelola boleh memaksa harga daftar sebagai kebijakan baik.
        $locked = $calculator->breakdownForPayment($event, $registration, $paidAt, 'registration');
        $this->assertSame(250000, $locked['total'], 'Pengola bisa memilih harga saat pendaftaran.');
    }

    public function test_pengelola_dapat_menentukan_nominal_harga_sendiri(): void
    {
        [$owner, $event] = $this->createEventWithRules();
        $this->pricingRules($event, [
            'discount_percent' => null,
            'discount_amount' => null,
        ]);

        $registration = $this->createRegistration($event, User::factory()->create(['role' => 'individual']));
        $registration->forceFill([
            'category' => 'Junior Putra',
            'races' => ['Classic Slalom', 'Speed Slalom'],
            'payment_status' => 'pending',
            'payment_channel' => 'whatsapp',
        ])->save();

        $this->actingAs($owner)
            ->post(route('events.registrations.payment.confirm', [$event, $registration]), [
                'pricing_basis' => 'manual',
                'manual_fee' => 120000,
                'paid_amount' => 120000,
            ]);

        $this->assertSame(120000, $registration->fresh()->fee_total);
        $this->assertSame('paid', $registration->fresh()->payment_status);
    }

    public function test_pembayaran_kurang_tidak_dianggap_lunas(): void
    {
        [$owner, $event] = $this->createEventWithRules();
        $this->pricingRules($event, [
            'discount_percent' => null,
            'discount_amount' => null,
        ]);

        $registration = $this->createRegistration($event, User::factory()->create(['role' => 'individual']));
        $registration->forceFill([
            'category' => 'Junior Putra',
            'races' => ['Classic Slalom', 'Speed Slalom'],
            'payment_status' => 'pending',
            'payment_channel' => 'whatsapp',
        ])->save();

        $this->actingAs($owner)
            ->post(route('events.registrations.payment.confirm', [$event, $registration]), [
                'paid_amount' => 50000,
            ]);

        $registration = $registration->fresh();

        $this->assertSame(50000, $registration->amountPaid());
        $this->assertSame(100000, $registration->amountDue());
        $this->assertSame('pending', $registration->payment_status, 'Pembayaran kurang tidak boleh dianggap lunas.');
    }

    public function test_free_events_ignore_submitted_pricing_values(): void
    {
        [$owner, $event] = $this->createEventWithRules();
        $event->update(['is_free' => true]);

        $this->actingAs($owner)
            ->put(route('owner.events.categories.update', $event), [
                'category_rules' => [[
                    'name' => 'Junior Putra',
                    'eligibility_types' => ['birth_date'],
                    'birth_date_from' => '2010-01-01',
                    'birth_date_to' => '2020-12-31',
                    'genders' => ['male'],
                    'races' => ['Classic Slalom', 'Speed Slalom'],
                    'required_races' => 2,
                    'package_fee' => 150000,
                    'extra_race_fee' => 50000,
                    'outside_race_fee' => 5000,
                    'discount_percent' => 10,
                    'discount_amount' => 10000,
                ]],
            ])
            ->assertRedirect(route('guest.dashboard'));

        $rule = $event->fresh()->category_rules[0];
        $this->assertSame(0, $rule['package_fee']);
        $this->assertSame(0, $rule['extra_race_fee']);
        $this->assertSame(0, $rule['outside_race_fee']);
        $this->assertNull($rule['discount_percent']);
        $this->assertNull($rule['discount_amount']);
    }

    public function test_registration_page_tidak_menampilkan_ringkasan_harga_siautomatis(): void
    {
        [$owner, $event] = $this->createEventWithRules();
        $this->pricingRules($event);
        $participant = User::factory()->create(['role' => 'individual']);

        // Ringkasan harga tidak lagi ditampilkan. Harga tetap dihitung otomatis
        // di browser dari data-category-rules, jadi aturan harga harus ikut
        // tersembunyi di atribut tersebut agar pratinjau kategori tetap benar.
        $this->actingAs($participant)
            ->get(route('events.registration.create', $event))
            ->assertOk()
            ->assertDontSee('paket Rp 150.000 untuk 2 mata lobb')
            ->assertDontSee('diskon 10% + Rp 10.000')
            ->assertDontSee('Aturan kategori kejuaraan')
            ->assertDontSee('Gelombang tarif')
            ->assertSee('data-category-rules', false)
            ->assertSee('&quot;package_fee&quot;:150000', false)
            ->assertSee('&quot;discount_percent&quot;:10', false);
    }

    public function test_roster_shows_the_stored_fee_for_each_participant(): void
    {
        [$owner, $event] = $this->createEventWithRules();
        $this->pricingRules($event);
        $registration = $this->createRegistration($event, User::factory()->create(['role' => 'individual']));
        $event->update([
            'races' => ['Classic Slalom', 'Speed Slalom', 'Relay', 'Downhill'],
            'categories' => ['Junior Putra'],
            'category_rules' => [$this->pricedRule([
                'races' => ['Classic Slalom', 'Speed Slalom', 'Relay'],
            ])],
        ]);
        $registration->update([
            'races' => ['Classic Slalom', 'Speed Slalom', 'Relay', 'Downhill'],
            'payment_status' => 'paid',
            'category' => 'Junior Putra',
        ]);
        app(CategoryFeeCalculator::class)->sync($event->fresh(), $registration);
        $registration->save();

        $this->actingAs($owner)
            ->get(route('events.registrations.index', $event))
            ->assertOk()
            ->assertSee('Biaya')
            ->assertSee('Rp 174.500')
            ->assertSee('Tambahan Rp 50.000')
            ->assertSee('Luar kategori Rp 5.000');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function pricedRule(array $overrides = []): array
    {
        return [
            'name' => 'Junior Putra',
            'genders' => ['male'],
            'races' => ['Classic Slalom', 'Speed Slalom', 'Relay'],
            'required_races' => 2,
            'package_fee' => 150000,
            'extra_race_fee' => 50000,
            'outside_race_fee' => 5000,
            'discount_percent' => 10,
            'discount_amount' => 10000,
            ...$overrides,
        ];
    }

    private function pricingRules(Event $event, array $overrides = []): void
    {
        $event->update([
            'races' => ['Classic Slalom', 'Speed Slalom', 'Relay', 'Downhill'],
            'categories' => ['Junior Putra'],
            'category_rules' => [$this->pricedRule($overrides)],
        ]);
    }

    /**
     * @return array{0: User, 1: Event}
     */
    private function createEventWithRules(): array
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
            ]],
            'races' => ['Classic Slalom', 'Speed Slalom'],
            'registration_deadline' => $startDate->copy()->subDay()->format('Y-m-d'),
            'schedule' => ['Pembukaan', 'Final'],
            'status' => 'approved',
            'registration_open' => true,
        ]);

        return [$owner, $event];
    }

    private function createRegistration(Event $event, User $participant): EventRegistration
    {
        $nik = $this->nikFor('male', '2014-01-01', 200);

        return EventRegistration::create([
            'event_id' => $event->id,
            'user_id' => $participant->id,
            'participant_name' => 'Atlet Berbayar',
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
}
