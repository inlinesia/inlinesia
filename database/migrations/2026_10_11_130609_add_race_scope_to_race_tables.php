<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pengaturan, babak, dan baris peserta dulu hanya dibedakan oleh kategori.
     * Padahal satu peserta boleh memilih beberapa mata lomba sekaligus, jadi
     * "berapa orang per babak" dan pembagian babaknya harus ikut dibedakan
     * per mata lomba — tanpa ini, atlet Classic Slalom bisa berada di babak
     * yang sama dengan atlet Speed Slalom.
     */
    public function up(): void
    {
        Schema::table('event_category_race_settings', function (Blueprint $table): void {
            $table->string('race', 120)->default('')->after('category');
            $table->string('grouping_mode', 20)->default('manual')->after('opponent_scope');
        });

        Schema::table('event_race_heats', function (Blueprint $table): void {
            $table->string('race', 120)->default('')->after('category');
        });

        Schema::table('event_race_entries', function (Blueprint $table): void {
            $table->string('race', 120)->default('')->after('category');
        });

        $this->assignExistingRowsToTheirFirstRace();

        Schema::table('event_category_race_settings', function (Blueprint $table): void {
            $table->dropForeign(['event_id']);
            $table->dropUnique(['event_id', 'category']);
            $table->unique(['event_id', 'category', 'race'], 'event_category_race_settings_identity');
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
        });

        Schema::table('event_race_heats', function (Blueprint $table): void {
            $table->dropForeign(['event_id']);
            $table->dropUnique('event_race_heat_identity');
            $table->dropIndex(['event_id', 'category', 'stage']);
            $table->unique(['event_id', 'category', 'race', 'stage', 'number'], 'event_race_heat_identity');
            $table->index(['event_id', 'category', 'race', 'stage'], 'event_race_heats_lookup');
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
        });

        Schema::table('event_race_entries', function (Blueprint $table): void {
            $table->dropForeign(['event_id']);
            $table->dropIndex(['event_id', 'category']);
            $table->index(['event_id', 'category', 'race'], 'event_race_entries_lookup');
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
        });
    }

    /**
     * Baris lama tidak punya mata lomba. Yang paling masuk akal adalah
     * mata lomba pertama kategori itu, supaya babak lama tetap muncul di
     * halaman hari lomba dan bukan jadi data yatim yang tak terlihat.
     */
    private function assignExistingRowsToTheirFirstRace(): void
    {
        foreach (DB::table('events')->get(['id', 'races', 'category_rules']) as $event) {
            $fallback = $this->firstRace(json_decode((string) $event->races, true));

            $firstRaceByCategory = [];
            foreach (json_decode((string) $event->category_rules, true) ?: [] as $rule) {
                $name = trim((string) ($rule['name'] ?? ''));
                if ($name !== '') {
                    $firstRaceByCategory[$name] = $this->firstRace($rule['races'] ?? []) ?: $fallback;
                }
            }

            foreach (['event_category_race_settings', 'event_race_heats', 'event_race_entries'] as $table) {
                foreach (DB::table($table)->where('event_id', $event->id)->get(['id', 'category']) as $row) {
                    DB::table($table)
                        ->where('id', $row->id)
                        ->update(['race' => $firstRaceByCategory[$row->category] ?? $fallback]);
                }
            }
        }
    }

    /**
     * @param  mixed  $races
     */
    private function firstRace($races): string
    {
        return is_array($races) && $races !== [] ? trim((string) reset($races)) : '';
    }

    public function down(): void
    {
        Schema::table('event_race_entries', function (Blueprint $table): void {
            $table->dropForeign(['event_id']);
            $table->dropIndex('event_race_entries_lookup');
            $table->index(['event_id', 'category']);
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->dropColumn('race');
        });

        Schema::table('event_race_heats', function (Blueprint $table): void {
            $table->dropForeign(['event_id']);
            $table->dropUnique('event_race_heat_identity');
            $table->dropIndex('event_race_heats_lookup');
            $table->unique(['event_id', 'category', 'stage', 'number'], 'event_race_heat_identity');
            $table->index(['event_id', 'category', 'stage']);
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->dropColumn('race');
        });

        Schema::table('event_category_race_settings', function (Blueprint $table): void {
            $table->dropForeign(['event_id']);
            $table->dropUnique('event_category_race_settings_identity');
            $table->unique(['event_id', 'category']);
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->dropColumn(['race', 'grouping_mode']);
        });
    }
};
