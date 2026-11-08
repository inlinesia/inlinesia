// Pengaturan perlombaan + halaman hari lomba.
//
// Tiga hal yang perlu bantuan JavaScript di sini:
// 1. Kartu pengaturan menyembunyikan kolom semifinal kecuali format tahap
//    memakainya, dan menampilkan petunjuk format yang sesuai.
// 2. Mode gruping "Otomatis" menghitung angka babak dari jumlah peserta yang
//    benar-benar masuk, lalu menuliskannya ke kolom angka.
// 3. Tombol "Terapkan ke mata lomba yang sama" menyalin satu kartu ke kategori
//    lain yang memakai mata lomba yang sama.
// 4. Tombol cepat di tiap lane (Menang / Tidak muncul / DNF / DSQ) menulis
//    status tanpa panitia harus memilih dari daftar.

const STATUS_BY_QUICK = {
    win: 'finished',
    no_show: 'no_show',
    dnf: 'dnf',
    dsq: 'dsq',
    pending: 'pending',
};

const SCORING_HINTS = {
    qualification: 'Kualifikasi: pemenang tiap babak otomatis lolos, dan papan hasil memakai jumlah urutan finis.',
    points: 'Penilaian: tiap finis diberi poin sesuai urutan (misal 6,5,4,3,2,1) dan pemenang dari total poin.',
};

// Petunjuk format tahap dikirim dari server lewat window.RACE_STAGE_HINTS
// supaya teks di halaman dan teks di JavaScript tidak pernah berbeda.
const STAGE_HINTS = window.RACE_STAGE_HINTS ?? {
    qualification_round: 'Tangga paling panjang: babak awal → penyisihan → semifinal → final.',
    semi_final: 'Babak awal → semifinal → final.',
    direct_final: 'Babak awal → final langsung, tanpa semifinal.',
    no_final: 'Babak awal saja. Tiap babak mengumumkan juara 1, 2, 3 sendiri.',
};

const OPPONENT_HINTS = {
    mixed: 'Bebas bercampur antar klub; pembagian babak membuat tiap klub tersebar merata.',
    cross_club: 'Tiap babak wajib berisi atlet dari minimal dua klub.',
    same_club: 'Satu babak hanya berisi satu klub, jadi klub yang sama tidak saling bertanding sejak awal.',
};

// Kolom yang bukan aturan perlombaan sehingga tidak ikut disalin.
const COPIED_FIELDS = new Set([
    'category',
    'race',
]);

const fieldNameOf = (element) => /\[([a-z_]+)\]$/.exec(element.name ?? '')?.[1] ?? null;

const ruleInputsOf = (root) =>
    Array.from(root.querySelectorAll('input[name^="race_settings"], select[name^="race_settings"]'));

document.querySelectorAll('[data-race-format-card]').forEach((card) => {
    const scoringSelect = card.querySelector('[data-race-scoring]');
    const scoringHint = card.querySelector('[data-race-scoring-hint]');
    const stagesSelect = card.querySelector('[data-race-stages]');
    const stagesHint = card.querySelector('[data-race-stages-hint]');
    const semiField = card.querySelector('[data-race-semi-field]');
    const semiInput = card.querySelector('[data-race-semi]');
    const opponentSelect = card.querySelector('select[name$="[opponent_scope]"]');
    const opponentHint = card.querySelector('[data-race-opponent-hint]');
    const runnersInput = card.querySelector('[data-race-runners]');
    const groupSizeInput = card.querySelector('[data-race-group-size]');
    const qualifyInput = card.querySelector('[data-race-qualify]');
    const finalistsInput = card.querySelector('[data-race-finalists]');
    const championInput = card.querySelector('[data-race-champion]');
    const groupingRadios = Array.from(card.querySelectorAll('[data-race-grouping]'));
    const groupingHint = card.querySelector('[data-race-grouping-hint]');

    const entrants = Math.max(1, Number.parseInt(card.dataset.entrants ?? '1', 10) || 1);

    const manual = {
        runners: runnersInput?.value ?? '',
        groupSize: groupSizeInput?.value ?? '',
        qualify: qualifyInput?.value ?? '',
        semi: semiInput?.value ?? '',
        finalists: finalistsInput?.value ?? '',
    };

    const usesSemiFinalStage = () =>
        stagesSelect?.value === 'semi_final' || stagesSelect?.value === 'qualification_round';

    const autoNumbers = () => {
        const champions = Number.parseInt(championInput?.value ?? '3', 10) || 3;
        // Ukuran babak dibuat sama dengan jumlah juara supaya tiap anak di
        // satu babak pasti mendapat medali (90 peserta, 3 juara → 30 × 3).
        const runners = Math.max(2, Math.min(stagesSelect?.value === 'no_final' ? champions : 6, entrants));
        // Satu grup berisi kira-kira lima babak, jadi menjadi blok jadwal
        // yang wajar untuk disiapkan panitia sekaligus.
        const groupSize = runners * 5;

        return {
            runners,
            groupSize,
            qualify: Math.max(1, Math.min(runners, Math.ceil(runners / 2))),
            semi: Math.max(2, Math.min(entrants, 4)),
            finalists: Math.max(2, Math.min(entrants, 8)),
        };
    };

    const isAuto = () => groupingRadios.some((radio) => radio.checked && radio.value === 'auto');

    const applyAuto = () => {
        const values = autoNumbers();
        if (runnersInput instanceof HTMLInputElement) runnersInput.value = String(values.runners);
        if (groupSizeInput instanceof HTMLInputElement) groupSizeInput.value = String(values.groupSize);
        if (qualifyInput instanceof HTMLInputElement) qualifyInput.value = String(values.qualify);
        if (semiInput instanceof HTMLInputElement) semiInput.value = String(values.semi);
        if (finalistsInput instanceof HTMLInputElement) finalistsInput.value = String(values.finalists);
        if (groupingHint) {
            groupingHint.textContent = `Angka dihitung dari ${entrants} peserta yang masuk: ${values.runners} anak per babak, ${values.groupSize} atlet per grup, ${values.finalists} peserta final.`;
        }
    };

    const applyManual = () => {
        if (runnersInput instanceof HTMLInputElement) runnersInput.value = manual.runners;
        if (groupSizeInput instanceof HTMLInputElement) groupSizeInput.value = manual.groupSize;
        if (qualifyInput instanceof HTMLInputElement) qualifyInput.value = manual.qualify;
        if (semiInput instanceof HTMLInputElement) semiInput.value = manual.semi;
        if (finalistsInput instanceof HTMLInputElement) finalistsInput.value = manual.finalists;
        if (groupingHint) {
            groupingHint.textContent = 'Semua angka di bawah diisi sendiri. Pilih “Otomatis” supaya sistem menghitungnya dari jumlah peserta yang benar-benar masuk.';
        }
    };

    const syncStages = () => {
        const usesSemiFinal = usesSemiFinalStage();
        if (semiField instanceof HTMLElement) semiField.hidden = !usesSemiFinal;
        if (semiInput instanceof HTMLInputElement) semiInput.required = usesSemiFinal;
        if (stagesHint) stagesHint.textContent = STAGE_HINTS[stagesSelect?.value] ?? '';
        if (isAuto()) applyAuto();
    };

    const syncQualifyCeiling = () => {
        if (!(runnersInput instanceof HTMLInputElement) || !(qualifyInput instanceof HTMLInputElement)) return;

        const perHeat = Number.parseInt(runnersInput.value, 10) || 1;
        qualifyInput.max = String(perHeat);

        if (Number.parseInt(qualifyInput.value, 10) > perHeat) {
            qualifyInput.value = String(perHeat);
        }
    };

    // Satu grup harus memuat minimal satu babak penuh.
    const syncGroupCeiling = () => {
        if (!(runnersInput instanceof HTMLInputElement) || !(groupSizeInput instanceof HTMLInputElement)) return;

        const perHeat = Number.parseInt(runnersInput.value, 10) || 1;
        groupSizeInput.min = String(perHeat);

        if ((Number.parseInt(groupSizeInput.value, 10) || 0) < perHeat) {
            groupSizeInput.value = String(perHeat);
        }
    };

    const syncGrouping = () => {
        if (isAuto()) {
            applyAuto();
        } else {
            applyManual();
        }
        syncQualifyCeiling();
        syncGroupCeiling();
    };

    groupingRadios.forEach((radio) => radio.addEventListener('change', syncGrouping));
    championInput?.addEventListener('input', () => {
        if (isAuto()) applyAuto();
    });
    stagesSelect?.addEventListener('change', syncStages);
    scoringSelect?.addEventListener('change', () => {
        if (scoringHint) scoringHint.textContent = SCORING_HINTS[scoringSelect.value] ?? '';
    });
    opponentSelect?.addEventListener('change', () => {
        if (opponentHint) opponentHint.textContent = OPPONENT_HINTS[opponentSelect.value] ?? '';
    });
    runnersInput?.addEventListener('input', () => {
        syncQualifyCeiling();
        if (isAuto()) applyAuto();
        syncGroupCeiling();
    });

    // Kartu lain menerima salinan aturan lewat event ini.
    card.addEventListener('race:rules-copied', () => {
        syncStages();
        syncQualifyCeiling();
        syncGroupCeiling();
        if (isAuto()) applyAuto();
    });

    syncStages();
    syncQualifyCeiling();
    syncGroupCeiling();
    if (isAuto()) applyAuto();
});

document.querySelectorAll('[data-apply-race]').forEach((button) => {
    button.addEventListener('click', () => {
        const card = button.closest('[data-race-format-card]');
        if (!(card instanceof HTMLElement)) return;

        const race = card.dataset.race;
        const copied = new Map();

        ruleInputsOf(card).forEach((element) => {
            const field = fieldNameOf(element);
            if (!field || COPIED_FIELDS.has(field)) return;
            if (element instanceof HTMLInputElement && element.type === 'radio') {
                if (element.checked) copied.set(field, element.value);
                return;
            }
            copied.set(field, element.value);
        });

        let targets = 0;
        document.querySelectorAll('[data-race-format-card]').forEach((target) => {
            if (!(target instanceof HTMLElement) || target === card || target.dataset.race !== race) return;

            ruleInputsOf(target).forEach((element) => {
                const field = fieldNameOf(element);
                if (!field || COPIED_FIELDS.has(field) || !copied.has(field)) return;

                if (element instanceof HTMLInputElement && element.type === 'radio') {
                    element.checked = element.value === copied.get(field);
                    return;
                }

                if (element instanceof HTMLInputElement || element instanceof HTMLSelectElement) {
                    element.value = copied.get(field);
                }
            });

            target.dispatchEvent(new CustomEvent('race:rules-copied'));
            targets += 1;
        });

        const status = card.querySelector('[data-apply-status]');
        if (status) {
            status.textContent = targets > 0
                ? `Disalin ke ${targets} kartu lain. Tekan “Simpan pengaturan” untuk menyimpan.`
                : 'Belum ada kategori lain yang memakai mata lomba ini.';
        }
    });
});

document.querySelectorAll('[data-race-heat]').forEach((heat) => {
    const rows = Array.from(heat.querySelectorAll('[data-race-lane]'));
    if (rows.length === 0) return;

    const partsOf = (row) => ({
        select: row.querySelector(`[name="${row.dataset.statusSelect}"]`),
        place: row.querySelector(`[name="${row.dataset.placeInput}"]`),
    });

    const placeInputOf = (row) => {
        const input = partsOf(row).place;
        return input instanceof HTMLInputElement ? input : null;
    };

    const syncRow = (row) => {
        const { select, place } = partsOf(row);
        if (!(select instanceof HTMLSelectElement) || !(place instanceof HTMLInputElement)) return;

        const finished = select.value === 'finished';
        place.disabled = false;
        place.placeholder = finished ? 'urutan' : 'kosong';

        if (!finished) {
            place.value = '';
            row.dataset.raceStatus = select.value;
            return;
        }

        row.dataset.raceStatus = 'finished';
    };

    const freePlaceFor = (row) => {
        const taken = new Set(
            rows
                .filter((other) => other !== row)
                .map((other) => Number.parseInt(placeInputOf(other)?.value ?? '', 10))
                .filter((value) => Number.isFinite(value))
        );

        const maxPlace = Number.parseInt(row.dataset.heatSize ?? '1', 10) || 1;
        for (let candidate = 1; candidate <= maxPlace; candidate += 1) {
            if (!taken.has(candidate)) return candidate;
        }

        return maxPlace;
    };

    rows.forEach((row) => {
        const { select } = partsOf(row);
        select?.addEventListener('change', () => syncRow(row));

        placeInputOf(row)?.addEventListener('input', () => {
            const input = placeInputOf(row);
            if (!input || input.value === '') return;
            if (select instanceof HTMLSelectElement && select.value !== 'finished') {
                select.value = 'finished';
                syncRow(row);
            }
        });

        row.querySelectorAll('[data-race-quick]').forEach((button) => {
            if (!(button instanceof HTMLButtonElement)) return;

            button.addEventListener('click', () => {
                const { select } = partsOf(row);
                const action = button.dataset.raceQuick ?? 'pending';

                if (action === 'win' && select instanceof HTMLSelectElement) {
                    select.value = STATUS_BY_QUICK.win;
                    syncRow(row);

                    const place = placeInputOf(row);
                    if (place) place.value = String(freePlaceFor(row));

                    return;
                }

                if (select instanceof HTMLSelectElement) {
                    select.value = STATUS_BY_QUICK[action] ?? 'pending';
                }
                syncRow(row);
            });
        });

        syncRow(row);
    });
});

document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm ?? 'Lanjutkan?')) event.preventDefault();
    });
});
