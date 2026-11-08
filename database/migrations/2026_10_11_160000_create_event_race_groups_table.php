<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Urutan lomba punya tiga lapisan: tahap → grup → babak (race).
     *
     * Lapisan grup dibutuhkan supaya panitia bisa menyiapkan satu blok
     * atlet sekaligus ("Siapkan peserta") dan supaya peserta bisa melihat
     * urutan lawannya jauh sebelum hari lomba. Grup juga yang menampung
     * atlet yang ditunda — atlet didorong ke grup paling akhir, sementara
     * atlet lain di antreannya maju satu per satu.
     */
    public function up(): void
    {
        Schema::create('event_race_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('category', 120);
            $table->string('race', 120)->default('');
            $table->string('stage', 30)->default('heat');
            $table->unsignedInteger('number')->default(1);
            // Null berarti grup belum disiapkan, jadi tombol hasil belum muncul.
            $table->timestamp('started_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'category', 'race', 'stage', 'number'], 'event_race_group_identity');
            $table->index(['event_id', 'category', 'race'], 'event_race_groups_lookup');
        });

        Schema::table('event_race_heats', function (Blueprint $table): void {
            $table->foreignId('event_race_group_id')
                ->nullable()
                ->after('event_id')
                ->constrained('event_race_groups')
                ->cascadeOnDelete();
        });

        Schema::table('event_category_race_settings', function (Blueprint $table): void {
            $table->unsignedSmallInteger('athletes_per_group')->default(15)->after('grouping_mode');
            // Rilis membuat susunan terlihat di halaman urutan lomba publik.
            $table->timestamp('released_at')->nullable()->after('athletes_per_group');
        });

        $this->wrapExistingHeatsInGroups();
    }

    /**
     * Babak lama sudah tersusun tetapi belum punya grup. Semua babak pada
     * satu tahap dibungkus jadi satu grup supaya tampilan hari lomba tidak
     * berubah, dan pengaturannya langsung dianggap sudah dirilis karena
     * selama ini memang bisa dilihat panitia.
     */
    private function wrapExistingHeatsInGroups(): void
    {
        $heats = DB::table('event_race_heats')
            ->orderBy('id')
            ->get(['id', 'event_id', 'category', 'race', 'stage', 'started_at']);

        foreach ($heats->groupBy(fn (object $heat): string => $heat->event_id.'|'.$heat->category.'|'.$heat->race.'|'.$heat->stage) as $rows) {
            $first = $rows->first();

            $groupId = DB::table('event_race_groups')->insertGetId([
                'event_id' => $first->event_id,
                'category' => $first->category,
                'race' => $first->race,
                'stage' => $first->stage,
                'number' => 1,
                'started_at' => $rows->min('started_at'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($rows as $row) {
                DB::table('event_race_heats')->where('id', $row->id)->update(['event_race_group_id' => $groupId]);
            }
        }

        $arranged = DB::table('event_race_heats')->distinct()->get(['event_id', 'category', 'race']);

        foreach ($arranged as $pair) {
            DB::table('event_category_race_settings')
                ->where('event_id', $pair->event_id)
                ->where('category', $pair->category)
                ->where('race', $pair->race)
                ->whereNull('released_at')
                ->update(['released_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('event_category_race_settings', function (Blueprint $table): void {
            $table->dropColumn(['athletes_per_group', 'released_at']);
        });

        Schema::table('event_race_heats', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('event_race_group_id');
        });

        Schema::dropIfExists('event_race_groups');
    }
};
