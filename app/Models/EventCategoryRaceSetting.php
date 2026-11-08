<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventCategoryRaceSetting extends Model
{
    public const SCORING_SYSTEMS = [
        'qualification' => 'Kualifikasi (urutan finis)',
        'points' => 'Penilaian (akumulasi poin)',
    ];

    /**
     * Format tahap menentukan seberapa panjang tangga babak, dihitung dari
     * grup (babak heats) sampai babak yang menentukan juara.
     *
     * Tiap pilihan memilih salah satu titik berhenti:
     * - qualification_round: grup → penyisihan → semifinal → final
     * - semi_final:          grup → semifinal → final
     * - direct_final:        grup → final
     * - no_final:            grup saja, juara diumumkan per babak
     */
    public const STAGES = [
        'qualification_round' => 'Babak penyisihan',
        'semi_final' => 'Ada semifinal',
        'direct_final' => 'Langsung ke final',
        'no_final' => 'Langsung menang tanpa final',
    ];

    public const STAGE_HINTS = [
        'qualification_round' => 'Tangga paling panjang: babak awal → penyisihan → semifinal → final.',
        'semi_final' => 'Babak awal → semifinal → final.',
        'direct_final' => 'Babak awal → final langsung, tanpa semifinal.',
        'no_final' => 'Babak awal saja. Tiap babak mengumumkan juara 1, 2, 3 sendiri, jadi anak yang pesertanya sedikit tetap mendapat medali.',
    ];

    public const STAGE_LADDERS = [
        'qualification_round' => ['heat', 'qualification', 'semi_final', 'final'],
        'semi_final' => ['heat', 'semi_final', 'final'],
        'direct_final' => ['heat', 'final'],
        'no_final' => ['heat'],
    ];

    public const GROUPING_MODES = [
        'manual' => 'Manual',
        'auto' => 'Otomatis dari peserta',
    ];

    public const OPPONENT_SCOPES = [
        'mixed' => 'Campuran (bebas bercampur)',
        'cross_club' => 'Wajib antar klub',
        'same_club' => 'Satu klub per babak',
    ];

    /**
     * Bawaan untuk kategori yang belum pernah disimpan. Tanpa ini, model
     * sementara dari Event::raceSettingFor() akan null sehingga form perlombaan
     * tampil dengan kolom kosong.
     */
    protected $attributes = [
        'runners_per_heat' => 6,
        'scoring_system' => 'qualification',
        'qualify_count' => 3,
        'stages' => 'direct_final',
        'semi_finalists' => 0,
        'finalists' => 8,
        'champion_count' => 3,
        'opponent_scope' => 'mixed',
        'grouping_mode' => 'manual',
        'athletes_per_group' => 15,
    ];

    protected $fillable = [
        'event_id',
        'category',
        'race',
        'runners_per_heat',
        'scoring_system',
        'qualify_count',
        'stages',
        'semi_finalists',
        'finalists',
        'champion_count',
        'opponent_scope',
        'grouping_mode',
        'athletes_per_group',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'runners_per_heat' => 'integer',
            'qualify_count' => 'integer',
            'semi_finalists' => 'integer',
            'finalists' => 'integer',
            'champion_count' => 'integer',
            'athletes_per_group' => 'integer',
            'released_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function usesPoints(): bool
    {
        return $this->scoring_system === 'points';
    }

    /**
     * Babak yang dijalankan format ini, berurutan dari grup sampai final.
     *
     * @return array<int, string>
     */
    public function stageLadder(): array
    {
        return self::STAGE_LADDERS[$this->stages] ?? self::STAGE_LADDERS['direct_final'];
    }

    /**
     * Babak yang menghasilkan peserta untuk babak ini, atau null bila babak
     * ini yang paling awal.
     */
    public function stageBefore(string $stage): ?string
    {
        $ladder = $this->stageLadder();
        $index = array_search($stage, $ladder, true);

        return $index === false || $index === 0 ? null : $ladder[$index - 1];
    }

    /**
     * Babak yang menerima peserta dari babak ini, atau null bila babak ini
     * yang paling akhir.
     */
    public function stageAfter(string $stage): ?string
    {
        $ladder = $this->stageLadder();
        $index = array_search($stage, $ladder, true);

        return $index === false || $index === count($ladder) - 1 ? null : $ladder[$index + 1];
    }

    public function hasQualificationRound(): bool
    {
        return in_array('qualification', $this->stageLadder(), true);
    }

    public function hasSemiFinal(): bool
    {
        return in_array('semi_final', $this->stageLadder(), true);
    }

    public function hasFinal(): bool
    {
        return in_array('final', $this->stageLadder(), true);
    }

    /**
     * Babak yang menentukan juara. Pada format biasa itu babak final, tetapi
     * pada "langsung menang tanpa final" juaranya diumumkan per babak, jadi
     * babak grup yang menentukan.
     */
    public function decidingStage(): string
    {
        return $this->hasFinal() ? 'final' : 'heat';
    }

    /**
     * Orang per babak pada mode otomatis.
     *
     * Untuk aturan "langsung menang tanpa final" ukuran babak dibuat sama
     * dengan jumlah juara supaya setiap anak yang masuk satu babak pasti
     * mendapat medali: 90 peserta dengan 3 juara menjadi 30 babak × 3 anak.
     */
    public function autoRunnersPerHeat(int $entrantCount): int
    {
        $entrants = max(1, $entrantCount);
        $wanted = $this->stages === 'no_final' ? (int) $this->champion_count : 6;

        return max(2, min($wanted, $entrants));
    }

    /**
     * Angka gruping yang dihitung dari jumlah peserta yang benar-benar masuk.
     * Dipakai untuk pratinjau di form dan, pada mode otomatis, saat babak
     * benar-benar disusun.
     *
     * Ukuran grup otomatis diambil dari lima babak supaya satu grup selalu
     * berupa satu blok jadwal yang wajar: 3 orang per babak menjadi 15 atlet
     * per grup, 6 orang per babak menjadi 30 atlet per grup.
     *
     * @return array{runners_per_heat: int, qualify_count: int, semi_finalists: int, finalists: int, athletes_per_group: int}
     */
    public function autoGrouping(int $entrantCount): array
    {
        $entrants = max(1, $entrantCount);
        $runners = $this->autoRunnersPerHeat($entrants);

        return [
            'runners_per_heat' => $runners,
            'qualify_count' => max(1, min($runners, (int) ceil($runners / 2))),
            'semi_finalists' => max(2, min($entrants, 4)),
            'finalists' => max(2, min($entrants, 8)),
            'athletes_per_group' => $runners * 5,
        ];
    }

    /**
     * Orang per babak yang benar-benar dipakai saat babak disusun: hasil
     * hitungan otomatis bila mode otomatis, atau angka yang diisi panitia.
     */
    public function effectiveRunnersPerHeat(int $entrantCount): int
    {
        return $this->grouping_mode === 'auto'
            ? $this->autoRunnersPerHeat($entrantCount)
            : (int) $this->runners_per_heat;
    }

    /**
     * Orang per grup yang dipakai saat urutan lomba disusun. Grup adalah
     * blok yang disiapkan panitia pada hari lomba, jadi ukurannya tidak
     * boleh lebih kecil daripada satu babak.
     */
    public function effectiveAthletesPerGroup(int $entrantCount): int
    {
        $perHeat = $this->effectiveRunnersPerHeat($entrantCount);
        $wanted = $this->grouping_mode === 'auto'
            ? $this->autoGrouping($entrantCount)['athletes_per_group']
            : (int) $this->athletes_per_group;

        return max($perHeat, min(max(1, $wanted), max($perHeat, $entrantCount)));
    }

    /** Susunan babak untuk kategori dan mata lomba ini sudah dirilis. */
    public function isReleased(): bool
    {
        return $this->released_at !== null;
    }

    public function scoringLabel(): string
    {
        return self::SCORING_SYSTEMS[$this->scoring_system] ?? self::SCORING_SYSTEMS['qualification'];
    }

    public function stageLabel(): string
    {
        return self::STAGES[$this->stages] ?? self::STAGES['direct_final'];
    }

    public function stageHint(): string
    {
        return self::STAGE_HINTS[$this->stages] ?? self::STAGE_HINTS['direct_final'];
    }

    public function opponentLabel(): string
    {
        return self::OPPONENT_SCOPES[$this->opponent_scope] ?? self::OPPONENT_SCOPES['mixed'];
    }
}
