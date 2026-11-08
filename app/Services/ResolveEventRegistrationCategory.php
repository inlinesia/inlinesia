<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ResolveEventRegistrationCategory
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function eligibleCategories(Event $event, string $dateOfBirth, string $gender): Collection
    {
        $birthYear = (int) substr($dateOfBirth, 0, 4);
        $ageOnEventDate = Carbon::parse($dateOfBirth)->diff($event->start_date)->y;

        return collect($event->category_rules)
            ->filter(function (array $rule) use ($dateOfBirth, $birthYear, $ageOnEventDate, $gender): bool {
                $eligibilityType = $rule['eligibility_type']
                    ?? ((($rule['minimum_age'] ?? null) !== null || ($rule['maximum_age'] ?? null) !== null)
                        ? 'age'
                        : (! empty($rule['birth_years']) ? 'birth_year' : 'birth_date'));
                $meetsEligibilityRule = match ($eligibilityType) {
                    'birth_year' => in_array($birthYear, array_map('intval', $rule['birth_years'] ?? []), true),
                    'age' => (($rule['minimum_age'] ?? null) === null || $ageOnEventDate >= $rule['minimum_age'])
                        && (($rule['maximum_age'] ?? null) === null || $ageOnEventDate <= $rule['maximum_age']),
                    default => (
                        ! isset($rule['birth_date_from'], $rule['birth_date_to'])
                        || ($dateOfBirth >= $rule['birth_date_from'] && $dateOfBirth <= $rule['birth_date_to'])
                    ),
                };

                return $meetsEligibilityRule && in_array($gender, $rule['genders'], true);
            })
            ->values();
    }

    /**
     * @param  array<int, string>  $races
     * @return array<string, mixed>|null
     */
    public function matchingCategory(Event $event, string $dateOfBirth, string $gender, array $races): ?array
    {
        return $this->bestMatch($event, $dateOfBirth, $gender, $races)['rule'] ?? null;
    }

    /**
     * Mencari kategori terbaik untuk satu peserta.
     *
     * Kategori yang memuat seluruh mata lomba pilihan atlet selalu dipilih
     * lebih dulu. Kalau tidak ada, kategori dengan mata lomba terbanyak yang
     * dimasuki atlet dipakai, dan sisa mata lomba di luar kategori itu tetap
     * boleh diikuti dengan biaya tambahan dari outside_race_fee.
     *
     * @param  array<int, string>  $races
     * @return array{rule: array<string, mixed>, in_category_races: array<int, string>, outside_races: array<int, string>}|null
     */
    public function bestMatch(Event $event, string $dateOfBirth, string $gender, array $races): ?array
    {
        $selected = array_values(array_map('strval', $races));

        return $this->eligibleCategories($event, $dateOfBirth, $gender)
            ->map(function (array $rule) use ($selected): array {
                $categoryRaces = array_map('strval', $rule['races'] ?? []);

                return [
                    'rule' => $rule,
                    'in_category_races' => array_values(array_intersect($selected, $categoryRaces)),
                    'outside_races' => array_values(array_diff($selected, $categoryRaces)),
                ];
            })
            ->filter(fn (array $candidate): bool => $candidate['in_category_races'] !== [])
            ->sortBy(fn (array $candidate): int => count($candidate['outside_races']))
            ->first();
    }
}
