<?php

namespace Tests\Unit;

use App\Services\CategoryFeeCalculator;
use App\Services\PricingWaveResolver;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PricingWaveTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function rule(array $overrides = []): array
    {
        return [
            'name' => 'Dewasa Putra',
            'races' => ['Classic Slalom', 'Speed Slalom'],
            'required_races' => 2,
            'package_fee' => 300000,
            'extra_race_fee' => 50000,
            'outside_race_fee' => 10000,
            'discount_percent' => null,
            'discount_amount' => null,
            'pricing_waves' => [
                [
                    'label' => 'Early bird',
                    'starts_on' => '2026-08-01',
                    'ends_on' => '2026-08-31',
                    'package_fee' => 250000,
                    'extra_race_fee' => null,
                    'outside_race_fee' => null,
                    'discount_percent' => null,
                    'discount_amount' => null,
                ],
                [
                    'label' => 'Gelombang 2',
                    'starts_on' => '2026-09-01',
                    'ends_on' => '2026-09-30',
                    'package_fee' => 350000,
                    'extra_race_fee' => 60000,
                    'outside_race_fee' => null,
                    'discount_percent' => null,
                    'discount_amount' => null,
                ],
            ],
            ...$overrides,
        ];
    }

    #[Test]
    public function it_selects_the_wave_matching_the_given_date(): void
    {
        $resolver = new PricingWaveResolver;
        $rule = $this->rule();

        $this->assertSame('Early bird', $resolver->activeFor($rule, Carbon::parse('2026-08-15'))['label']);
        $this->assertSame('Gelombang 2', $resolver->activeFor($rule, Carbon::parse('2026-09-15'))['label']);
        $this->assertNull($resolver->activeFor($rule, Carbon::parse('2026-10-15')));
    }

    #[Test]
    public function it_treats_wave_boundaries_as_inclusive(): void
    {
        $resolver = new PricingWaveResolver;
        $rule = $this->rule();

        $this->assertSame('Early bird', $resolver->activeFor($rule, Carbon::parse('2026-08-01'))['label']);
        $this->assertSame('Early bird', $resolver->activeFor($rule, Carbon::parse('2026-08-31'))['label']);
        $this->assertSame('Gelombang 2', $resolver->activeFor($rule, Carbon::parse('2026-09-01'))['label']);
    }

    #[Test]
    public function active_wave_overrides_only_the_fields_it_fills(): void
    {
        $calculator = new CategoryFeeCalculator;

        // Gelombang early bird hanya menimpa harga paket; biaya tambahan dan
        // luar kategori tetap mengikuti tarif dasar.
        $pricing = $calculator->pricing($this->rule(), Carbon::parse('2026-08-15'));

        $this->assertSame(250000, $pricing['package_fee']);
        $this->assertSame(50000, $pricing['extra_race_fee']);
        $this->assertSame(10000, $pricing['outside_race_fee']);
        $this->assertSame('Early bird', $pricing['pricing_wave']['label']);
    }

    #[Test]
    public function pricing_falls_back_to_base_fee_outside_any_wave(): void
    {
        $calculator = new CategoryFeeCalculator;

        $pricing = $calculator->pricing($this->rule(), Carbon::parse('2026-12-01'));

        $this->assertSame(300000, $pricing['package_fee']);
        $this->assertNull($pricing['pricing_wave']);
    }

    #[Test]
    public function breakdown_uses_the_wave_active_on_the_registration_date(): void
    {
        $calculator = new CategoryFeeCalculator;
        $rule = $this->rule();

        $early = $calculator->breakdown($rule, ['Classic Slalom', 'Speed Slalom'], Carbon::parse('2026-08-15'));
        $later = $calculator->breakdown($rule, ['Classic Slalom', 'Speed Slalom'], Carbon::parse('2026-09-15'));

        $this->assertSame(250000, $early['total']);
        $this->assertSame(350000, $later['total']);
    }

    #[Test]
    public function a_rule_with_no_base_fee_but_a_wave_still_counts_as_priced(): void
    {
        $calculator = new CategoryFeeCalculator;
        $rule = $this->rule([
            'package_fee' => 0,
            'extra_race_fee' => 0,
            'outside_race_fee' => 0,
        ]);

        $this->assertTrue($calculator->hasAnyPricing($rule));
    }

    #[Test]
    public function wave_summaries_flag_the_wave_active_on_the_given_date(): void
    {
        $calculator = new CategoryFeeCalculator;

        $summaries = $calculator->waveSummaries($this->rule(), Carbon::parse('2026-09-15'));

        $this->assertCount(2, $summaries);
        $this->assertFalse($summaries[0]['is_active']);
        $this->assertTrue($summaries[1]['is_active']);
    }
}
