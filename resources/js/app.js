const appShell = document.querySelector('.app-shell');
const sidebarToggle = document.querySelector('[data-sidebar-toggle]');

if (appShell instanceof HTMLElement && sidebarToggle instanceof HTMLButtonElement) {
    const sidebarStorageKey = 'inlinesia-admin-sidebar-collapsed';

    try {
        appShell.classList.toggle('is-sidebar-collapsed', window.localStorage.getItem(sidebarStorageKey) === 'true');
    } catch (error) {
        console.warn('Preferensi sidebar tidak dapat dibaca dari penyimpanan lokal.', error);
    }

    const updateSidebarToggle = () => {
        const isCollapsed = appShell.classList.contains('is-sidebar-collapsed');
        sidebarToggle.setAttribute('aria-expanded', String(!isCollapsed));
        sidebarToggle.setAttribute('aria-label', isCollapsed ? 'Tampilkan sidebar' : 'Sembunyikan sidebar');
    };

    updateSidebarToggle();

    sidebarToggle.addEventListener('click', () => {
        const isCollapsed = appShell.classList.toggle('is-sidebar-collapsed');
        sidebarToggle.setAttribute('aria-expanded', String(!isCollapsed));
        sidebarToggle.setAttribute('aria-label', isCollapsed ? 'Tampilkan sidebar' : 'Sembunyikan sidebar');

        try {
            window.localStorage.setItem(sidebarStorageKey, String(isCollapsed));
        } catch (error) {
            console.warn('Preferensi sidebar tidak dapat disimpan ke penyimpanan lokal.', error);
        }
    });
}

document.querySelectorAll('[data-category-rules-form]').forEach((form) => {
    const list = form.querySelector('[data-category-rule-list]');
    const template = document.querySelector('[data-category-rule-template]');
    const addButton = form.querySelector('[data-add-category]');

    const waveTemplate = document.querySelector('[data-pricing-wave-template]');

    // Menambahkan satu baris gelombang tarif ke sebuah kategori. Nomor gelombang
    // diambil dari jumlah baris yang sudah ada supaya indeks form tetap unik.
    const addPricingWave = (category) => {
        const waveList = category.querySelector('[data-pricing-wave-list]');
        if (!(waveList instanceof HTMLElement) || !(waveTemplate instanceof HTMLTemplateElement)) return;

        const waveIndex = waveList.querySelectorAll('[data-pricing-wave]').length;
        const wrapper = document.createElement('div');
        wrapper.innerHTML = waveTemplate.innerHTML
            .replaceAll('__INDEX__', category.dataset.categoryIndex ?? '0')
            .replaceAll('__WAVE__', String(waveIndex));
        const row = wrapper.firstElementChild;
        if (row instanceof HTMLElement) waveList.append(row);
    };

    const refreshPricingWaves = (category) => {
        const waveList = category.querySelector('[data-pricing-wave-list]');
        if (!(waveList instanceof HTMLElement)) return;

        // Nomor gelombang dirapikan ulang setelah ada penghapusan supaya tidak
        // ada celah indeks yang membingungkan validasi di server.
        waveList.querySelectorAll('[data-pricing-wave]').forEach((row, index) => {
            row.querySelectorAll('[name]').forEach((input) => {
                const name = input.getAttribute('name');
                if (name) {
                    input.setAttribute('name', name.replace(/pricing_waves\[\d+\]/g, `pricing_waves[${index}]`));
                }
            });
        });
    };

    // Menyalin satu baris gelombang tarif ke seluruh kategori lain. Baris tujuan
    // yang isinya sama persis dilewati supaya tidak ada duplikasi, sedangkan
    // kategori tanpa gelombang mendapat satu baris baru berisi nilai yang sama.
    const applyPricingWaveToAll = (category, waveRow) => {
        const waveFields = ['label', 'starts_on', 'ends_on', 'package_fee', 'extra_race_fee', 'outside_race_fee', 'discount_percent', 'discount_amount'];
        // Setiap baris gelombang hanya punya satu input per nama field, jadi
        // pencarian cukup lewat akhiran "[field]". Kurz siku tidak perlu
        // di-escape karena dipakai sebagai nilai string, bukan selector.
        const waveInput = (row, field) => Array.from(row.querySelectorAll('[name]'))
            .find((input) => input.getAttribute('name')?.endsWith(`[${field}]`));

        const readWave = (row) => {
            const values = {};
            waveFields.forEach((field) => {
                const input = waveInput(row, field);
                values[field] = input instanceof HTMLInputElement ? input.value : '';
            });
            return values;
        };

        const writeWave = (waveList, values, targetCategory) => {
            const waveIndex = waveList.querySelectorAll('[data-pricing-wave]').length;
            const wrapper = document.createElement('div');
            wrapper.innerHTML = waveTemplate.innerHTML
                .replaceAll('__INDEX__', targetCategory.dataset.categoryIndex ?? '0')
                .replaceAll('__WAVE__', String(waveIndex));
            const row = wrapper.firstElementChild;
            if (!(row instanceof HTMLElement)) return false;

            waveFields.forEach((field) => {
                const input = waveInput(row, field);
                if (input instanceof HTMLInputElement) input.value = values[field];
            });

            waveList.append(row);
            return true;
        };

        const isSameWave = (values, existing) => waveFields.every((field) => (existing[field] ?? '') === values[field]);

        const sourceValues = readWave(waveRow);
        const categories = Array.from(list.querySelectorAll('[data-category-rule]'));
        let updated = 0;
        let created = 0;

        categories.forEach((targetCategory) => {
            if (targetCategory === category) return;

            const waveList = targetCategory.querySelector('[data-pricing-wave-list]');
            if (!(waveList instanceof HTMLElement)) return;

            const existingRows = Array.from(waveList.querySelectorAll('[data-pricing-wave]'));
            const alreadyExists = existingRows.some((row) => isSameWave(sourceValues, readWave(row)));

            if (alreadyExists) {
                updated += 1;
                return;
            }

            if (writeWave(waveList, sourceValues, targetCategory)) created += 1;
        });

        return { updated, created };
    };

    if (!(list instanceof HTMLElement) || !(template instanceof HTMLTemplateElement) || !(addButton instanceof HTMLButtonElement)) {
        return;
    }

    const syncEligibilityMethod = (category, selectedInput = null) => {
        const picker = category.querySelector('[data-eligibility-picker]');
        if (!(picker instanceof HTMLElement)) return;

        const options = Array.from(picker.querySelectorAll('[data-eligibility-type]'));
        if (selectedInput?.checked) {
            options.forEach((option) => {
                option.checked = option === selectedInput;
            });
        } else if (!options.some((option) => option.checked)) {
            const defaultOption = options[0];
            if (defaultOption) defaultOption.checked = true;
        } else {
            const firstSelected = options.find((option) => option.checked);
            options.forEach((option) => {
                option.checked = option === firstSelected;
            });
        }

        const selectedType = options.find((option) => option.checked)?.value;
        category.querySelectorAll('[data-eligibility-panel]').forEach((panel) => {
            const isActive = panel.dataset.eligibilityPanel === selectedType;
            panel.hidden = !isActive;
            panel.querySelectorAll('input, select, textarea').forEach((input) => {
                input.disabled = !isActive;
                if (input.hasAttribute('data-required-when-active')) {
                    input.required = isActive;
                }
            });
        });
    };

    const syncCategoryName = (category) => {
        const baseName = category.querySelector('[data-category-base-name]');
        const genderGroup = category.querySelector('[data-category-gender-group]');
        const preview = category.querySelector('[data-category-name-preview]');
        const typeLabel = category.querySelector('[data-category-type-label]');
        if (!(baseName instanceof HTMLSelectElement)
            || !(genderGroup instanceof HTMLSelectElement)
            || !(preview instanceof HTMLInputElement)) {
            return;
        }

        const genderLabels = {
            male: 'Putra',
            female: 'Putri',
            mixed: 'Campuran',
        };

        const outsideLabel = category.dataset.outsideObligation === 'true' ? ' — di luar kewajiban' : '';
        preview.value = `${baseName.value} ${genderLabels[genderGroup.value] ?? ''}${outsideLabel}`.trim();
        if (typeLabel instanceof HTMLElement) {
            typeLabel.textContent = category.dataset.outsideObligation === 'true' ? ' tambahan di luar kewajiban' : '';
        }
    };

    const syncCounterpartPrompt = (category) => {
        const baseName = category.querySelector('[data-category-base-name]');
        const genderGroup = category.querySelector('[data-category-gender-group]');
        const prompt = category.querySelector('[data-category-pair-prompt]');
        const counterpartLabel = category.querySelector('[data-counterpart-gender-label]');
        if (!(baseName instanceof HTMLSelectElement)
            || !(genderGroup instanceof HTMLSelectElement)
            || !(prompt instanceof HTMLElement)
            || !(counterpartLabel instanceof HTMLElement)) {
            return;
        }

        const counterpartGender = genderGroup.value === 'male' ? 'female' : 'male';
        const isOutsideObligation = category.dataset.outsideObligation === 'true';
        const hasCounterpart = Array.from(list.querySelectorAll('[data-category-rule]')).some((otherCategory) => {
            if (otherCategory === category) return false;

            const otherBaseName = otherCategory.querySelector('[data-category-base-name]');
            const otherGenderGroup = otherCategory.querySelector('[data-category-gender-group]');
            const otherOutsideInput = otherCategory.querySelector('[data-outside-obligation-input]');

            return otherBaseName instanceof HTMLSelectElement
                && otherGenderGroup instanceof HTMLSelectElement
                && (otherOutsideInput instanceof HTMLInputElement ? otherOutsideInput.value === '1' : false) === isOutsideObligation
                && otherBaseName.value === baseName.value
                && otherGenderGroup.value === counterpartGender;
        });

        counterpartLabel.textContent = counterpartGender === 'male' ? 'Putra' : 'Putri';
        prompt.hidden = genderGroup.value === 'mixed'
            || hasCounterpart
            || category.dataset.pairPromptDismissed === 'true';
    };

    // Notifikasi singkat pada tombol yang ditekan supaya pengelola tahu berapa
    // kategori yang tertambah dan berapa yang sudah punya gelombang sama.
    const showPricingWaveFeedback = (button, result) => {
        const original = button.textContent;
        const parts = [];

        if (result.created > 0) {
            parts.push(`${result.created} kategori ditambahkan`);
        }

        if (result.updated > 0) {
            parts.push(`${result.updated} kategori sudah sama`);
        }

        button.textContent = parts.length > 0 ? parts.join(' · ') : 'Semua kategori sudah punya';
        button.classList.add('is-applied');

        window.setTimeout(() => {
            button.textContent = original;
            button.classList.remove('is-applied');
        }, 2600);
    };

    const refreshCategories = () => {
        list.querySelectorAll('[data-category-rule]').forEach((category, index) => {
            const number = category.querySelector('[data-category-number]');
            if (number) number.textContent = String(index + 1);
            const remove = category.querySelector('[data-remove-category]');
            if (remove instanceof HTMLButtonElement) {
                remove.disabled = list.querySelectorAll('[data-category-rule]').length === 1;
                remove.setAttribute('aria-label', `Hapus kategori ${index + 1}`);
            }
            syncEligibilityMethod(category);
            syncCategoryName(category);
            syncCounterpartPrompt(category);
        });
    };

    const nextCategoryIndex = () => Math.max(0, ...Array.from(list.querySelectorAll('[name]')).map((input) => {
        const match = input.getAttribute('name')?.match(/^category_rules\[(\d+)\]/);
        return match ? Number(match[1]) + 1 : 0;
    }));

    const reindexCategory = (category, index) => {
        [category, ...category.querySelectorAll('*')].forEach((element) => {
            Array.from(element.attributes).forEach((attribute) => {
                const value = attribute.value
                    .replace(/category_rules\[\d+\]/g, `category_rules[${index}]`)
                    .replace(/category-\d+-/g, `category-${index}-`);
                element.setAttribute(attribute.name, value);
            });
        });
    };

    const cloneCategoryWithCurrentValues = (category) => {
        const clone = category.cloneNode(true);
        const sourceControls = category.querySelectorAll('input, select, textarea');
        const clonedControls = clone.querySelectorAll('input, select, textarea');
        sourceControls.forEach((source, index) => {
            const target = clonedControls[index];
            if (!target) return;

            if (source instanceof HTMLInputElement && target instanceof HTMLInputElement) {
                if (source.type === 'checkbox' || source.type === 'radio') {
                    target.checked = source.checked;
                } else {
                    target.value = source.value;
                }
            } else if (source instanceof HTMLSelectElement && target instanceof HTMLSelectElement) {
                target.value = source.value;
            } else if (source instanceof HTMLTextAreaElement && target instanceof HTMLTextAreaElement) {
                target.value = source.value;
            }
        });

        return clone;
    };

    const categoryEligibilitySignature = (category) => {
        const method = category.querySelector('[data-eligibility-type]:checked')?.value ?? '';
        const values = Array.from(category.querySelectorAll(`[data-eligibility-panel="${method}"] input`))
            .map((input) => input.value)
            .filter(Boolean);

        return JSON.stringify([method, values]);
    };

    const syncOutsideRaceOptions = (category, updateGeneratedCategory = false) => {
        const picker = category.querySelector('[data-outside-race-picker]');
        const feeInput = category.querySelector('[data-outside-race-fee]');
        const baseName = category.querySelector('[data-category-base-name]');
        const genderGroup = category.querySelector('[data-category-gender-group]');
        const outsideInput = category.querySelector('[data-outside-obligation-input]');
        if (!(picker instanceof HTMLElement)
            || !(feeInput instanceof HTMLInputElement)
            || !(baseName instanceof HTMLSelectElement)
            || !(genderGroup instanceof HTMLSelectElement)
            || !(outsideInput instanceof HTMLInputElement)) {
            return;
        }

        if (outsideInput.value === '1') {
            picker.hidden = true;
            return;
        }

        const allowedRaces = new Set(Array.from(category.querySelectorAll('[data-allowed-race]:checked'))
            .map((input) => input.value));
        const selectedOutsideRaces = new Set(Array.from(picker.querySelectorAll('[data-outside-race-option]:checked'))
            .map((input) => input.value));
        const categorySignature = categoryEligibilitySignature(category);
        const existingOutsideCategory = Array.from(list.querySelectorAll('[data-category-rule]')).find((otherCategory) => {
            if (otherCategory === category) return false;
            const otherOutsideInput = otherCategory.querySelector('[data-outside-obligation-input]');
            const otherBaseName = otherCategory.querySelector('[data-category-base-name]');
            const otherGenderGroup = otherCategory.querySelector('[data-category-gender-group]');

            return otherOutsideInput instanceof HTMLInputElement
                && otherOutsideInput.value === '1'
                && otherBaseName instanceof HTMLSelectElement
                && otherGenderGroup instanceof HTMLSelectElement
                && otherBaseName.value === baseName.value
                && otherGenderGroup.value === genderGroup.value
                && categoryEligibilitySignature(otherCategory) === categorySignature;
        });
        if (existingOutsideCategory instanceof HTMLElement) {
            existingOutsideCategory.dataset.autoGeneratedOutside = 'true';
            existingOutsideCategory.dataset.outsideParentIndex = category.dataset.categoryIndex ?? '';
            const existingOutsideFee = existingOutsideCategory.querySelector('[data-outside-race-fee]');
            if (updateGeneratedCategory && existingOutsideFee instanceof HTMLInputElement) {
                existingOutsideFee.value = feeInput.value;
            }
        }
        const outsideCategoryRaces = new Set(Array.from(existingOutsideCategory?.querySelectorAll('[data-allowed-race]:checked') ?? [])
            .map((input) => input.value));

        picker.querySelectorAll('[data-outside-race-option]').forEach((option) => {
            if (!(option instanceof HTMLInputElement)) return;
            const isAllowed = allowedRaces.has(option.value);
            option.disabled = isAllowed;
            if (isAllowed) {
                option.checked = false;
            } else {
                option.checked = updateGeneratedCategory
                    ? selectedOutsideRaces.has(option.value)
                    : outsideCategoryRaces.has(option.value);
            }
        });

        const hasSelectedRaces = picker.querySelector('[data-outside-race-option]:checked') !== null;
        picker.hidden = Number(feeInput.value) <= 0 && !hasSelectedRaces;

        if (!updateGeneratedCategory) return;

        const selectedRaces = Array.from(picker.querySelectorAll('[data-outside-race-option]:checked'))
            .map((input) => input.value);

        if (selectedRaces.length === 0) {
            if (existingOutsideCategory?.dataset.autoGeneratedOutside === 'true') {
                existingOutsideCategory.remove();
            }
            return;
        }

        const outsideCategory = existingOutsideCategory ?? cloneCategoryWithCurrentValues(category);
        if (!existingOutsideCategory) {
            const outsideIndex = nextCategoryIndex();
            reindexCategory(outsideCategory, outsideIndex);
            outsideCategory.dataset.categoryIndex = String(outsideIndex);
            outsideCategory.dataset.outsideParentIndex = category.dataset.categoryIndex ?? '';
            outsideCategory.dataset.autoGeneratedOutside = 'true';
            category.after(outsideCategory);
        }

        outsideCategory.dataset.outsideObligation = 'true';
        const clonedOutsideInput = outsideCategory.querySelector('[data-outside-obligation-input]');
        if (clonedOutsideInput instanceof HTMLInputElement) clonedOutsideInput.value = '1';

        const selectedRaceSet = new Set(selectedRaces);
        outsideCategory.querySelectorAll('[data-allowed-race]').forEach((input) => {
            if (input instanceof HTMLInputElement) {
                input.checked = selectedRaceSet.has(input.value);
            }
        });

        const requiredRaces = outsideCategory.querySelector('[data-required-races]');
        const packageFee = outsideCategory.querySelector('[data-package-fee]');
        const extraRaceFee = outsideCategory.querySelector('[data-extra-race-fee]');
        const discountPercent = outsideCategory.querySelector('[data-discount-percent]');
        const discountAmount = outsideCategory.querySelector('[data-discount-amount]');
        if (requiredRaces instanceof HTMLInputElement) requiredRaces.value = '1';
        if (packageFee instanceof HTMLInputElement) packageFee.value = '0';
        if (extraRaceFee instanceof HTMLInputElement) extraRaceFee.value = '0';
        if (discountPercent instanceof HTMLInputElement) discountPercent.value = '';
        if (discountAmount instanceof HTMLInputElement) discountAmount.value = '';

        syncCategoryName(outsideCategory);
        const outsidePicker = outsideCategory.querySelector('[data-outside-race-picker]');
        if (outsidePicker instanceof HTMLElement) outsidePicker.hidden = true;
    };

    addButton.addEventListener('click', () => {
        const index = nextCategoryIndex();
        const category = template.content.cloneNode(true);
        const wrapper = document.createElement('div');
        wrapper.append(category);
        wrapper.innerHTML = wrapper.innerHTML.replaceAll('__INDEX__', String(index));
        const newCategory = wrapper.firstElementChild;
        if (newCategory instanceof HTMLElement) {
            newCategory.dataset.categoryIndex = String(index);
            list.append(newCategory);
        }
        refreshCategories();
    });

    list.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) return;

        const category = target.closest('[data-category-rule]');
        if (!category) return;

        if (target.closest('[data-add-pricing-wave]')) {
            addPricingWave(category);
            return;
        }

        if (target.closest('[data-apply-wave-to-all]')) {
            // Gelombang yang jadi sumber adalah baris terakhir, yaitu baris yang
            // baru saja diisi pengelola sebelum menekan tombol ini.
            const waves = target.closest('[data-pricing-waves]')?.querySelectorAll('[data-pricing-wave]');
            const sourceRow = waves?.item(waves.length - 1);
            if (!(sourceRow instanceof HTMLElement)) return;

            const result = applyPricingWaveToAll(category, sourceRow);
            showPricingWaveFeedback(target, result);
            return;
        }

        if (target.closest('[data-remove-pricing-wave]')) {
            target.closest('[data-pricing-wave]')?.remove();
            refreshPricingWaves(category);
            return;
        }

        if (target.closest('[data-remove-category]') && list.querySelectorAll('[data-category-rule]').length > 1) {
            const categoryIndex = category.dataset.categoryIndex;
            list.querySelectorAll(`[data-outside-parent-index="${categoryIndex}"]`).forEach((outsideCategory) => outsideCategory.remove());
            category.remove();
            refreshCategories();
            return;
        }

        if (target.closest('[data-dismiss-counterpart]')) {
            category.dataset.pairPromptDismissed = 'true';
            syncCounterpartPrompt(category);
            return;
        }

        if (target.closest('[data-create-counterpart]')) {
            const baseName = category.querySelector('[data-category-base-name]');
            const genderGroup = category.querySelector('[data-category-gender-group]');
            if (!(baseName instanceof HTMLSelectElement)
                || !(genderGroup instanceof HTMLSelectElement)
                || !['male', 'female'].includes(genderGroup.value)) {
                return;
            }

            const counterpartGender = genderGroup.value === 'male' ? 'female' : 'male';
            const hasCounterpart = Array.from(list.querySelectorAll('[data-category-rule]')).some((otherCategory) => {
                if (otherCategory === category) return false;

                const otherBaseName = otherCategory.querySelector('[data-category-base-name]');
                const otherGenderGroup = otherCategory.querySelector('[data-category-gender-group]');

                return otherBaseName instanceof HTMLSelectElement
                    && otherGenderGroup instanceof HTMLSelectElement
                    && otherBaseName.value === baseName.value
                    && otherGenderGroup.value === counterpartGender;
            });

            if (hasCounterpart) {
                refreshCategories();
                return;
            }

            const counterpart = cloneCategoryWithCurrentValues(category);
            const counterpartIndex = nextCategoryIndex();
            reindexCategory(counterpart, counterpartIndex);
            counterpart.dataset.categoryIndex = String(counterpartIndex);
            const counterpartSelect = counterpart.querySelector('[data-category-gender-group]');
            if (!(counterpartSelect instanceof HTMLSelectElement)) return;

            counterpartSelect.value = counterpartGender;
            if (category.dataset.outsideObligation !== 'true') {
                counterpart.querySelectorAll('[data-outside-race-option]').forEach((option) => {
                    if (option instanceof HTMLInputElement) option.checked = false;
                });
            }
            delete counterpart.dataset.pairPromptDismissed;
            category.after(counterpart);
            syncOutsideRaceOptions(counterpart);
            refreshCategories();
            counterpart.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        const yearSelect = category.querySelector('[data-birth-year-select]');
        const yearList = category.querySelector('[data-birth-year-list]');
        if (!(yearSelect instanceof HTMLSelectElement) || !(yearList instanceof HTMLElement)) return;

        if (target.closest('[data-add-birth-year]') && yearSelect.value) {
            const alreadyAdded = Array.from(yearList.querySelectorAll('input')).some((input) => input.value === yearSelect.value);
            if (!alreadyAdded) {
                const chip = document.createElement('span');
                chip.className = 'birth-year-chip';
                const value = document.createElement('input');
                value.type = 'hidden';
                value.name = yearSelect.dataset.inputName ?? '';
                value.value = yearSelect.value;
                const label = document.createElement('span');
                label.textContent = yearSelect.value;
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.dataset.removeBirthYear = '';
                remove.setAttribute('aria-label', `Hapus tahun ${yearSelect.value}`);
                remove.textContent = '×';
                chip.append(value, label, remove);
                yearList.append(chip);
            }
            yearSelect.value = '';
        } else if (target.closest('[data-remove-birth-year]')) {
            target.closest('.birth-year-chip')?.remove();
        }
    });

    list.addEventListener('change', (event) => {
        const target = event.target;
        const category = target.closest('[data-category-rule]');
        if (!category) return;

        if (target instanceof HTMLInputElement && target.matches('[data-eligibility-type]')) {
            syncEligibilityMethod(category, target);
        } else if (target instanceof HTMLInputElement
            && target.matches('[data-allowed-race], [data-outside-race-option], [data-outside-race-fee]')) {
            const sourceCategory = target.matches('[data-allowed-race], [data-outside-race-option]')
                ? category.closest('[data-category-rule]')
                : category;
            if (sourceCategory instanceof HTMLElement) {
                syncOutsideRaceOptions(sourceCategory, true);
                refreshCategories();
            }
        } else if (target instanceof HTMLSelectElement
            && target.matches('[data-category-base-name], [data-category-gender-group]')) {
            delete category.dataset.pairPromptDismissed;
            list.querySelectorAll(`[data-outside-parent-index="${category.dataset.categoryIndex}"]`).forEach((outsideCategory) => {
                if (outsideCategory.dataset.autoGeneratedOutside === 'true') outsideCategory.remove();
            });
            category.querySelectorAll('[data-outside-race-option]').forEach((option) => {
                if (option instanceof HTMLInputElement) option.checked = false;
            });
            syncCategoryName(category);
            syncOutsideRaceOptions(category, true);
            refreshCategories();
        }
    });

    list.querySelectorAll('[data-category-rule]').forEach((category) => {
        if (category instanceof HTMLElement) {
            syncOutsideRaceOptions(category);
        }
    });

    refreshCategories();
});

document.querySelectorAll('[data-athlete-registration]').forEach((form) => {
    if (!(form instanceof HTMLFormElement)) return;
    const dateInput = form.elements.namedItem('date_of_birth');
    const genderInput = form.elements.namedItem('gender');
    const preview = form.querySelector('[data-category-assignment-preview]');
    const encodedRules = form.dataset.categoryRules;
    const eventDate = form.dataset.eventDate;

    if (!(dateInput instanceof HTMLInputElement) || !(genderInput instanceof HTMLSelectElement) || !(preview instanceof HTMLElement) || !encodedRules) {
        return;
    }
    const rules = JSON.parse(encodedRules);
    // Status batas peserta per kategori dikirim terpisah karena bergantung pada
    // pembayaran yang sudah masuk, sedangkan isi category_rules hanya aturan.
    let categoryStatuses = {};
    try {
        categoryStatuses = JSON.parse(form.dataset.categoryStatuses || '{}');
    } catch (error) {
        console.warn('Status batas peserta tidak dapat dibaca.', error);
    }

    const formatRupiah = (amount) => `Rp ${new Intl.NumberFormat('id-ID').format(amount)}`;

    // Tanggal hari ini dalam format YYYY-MM-DD waktu lokal, supaya cocok dengan
    // format tanggal yang disimpan di server.
    const todayIso = () => {
        const now = new Date();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');

        return `${now.getFullYear()}-${month}-${day}`;
    };

    const dateFallsWithin = (day, startsOn, endsOn) => {
        const start = startsOn || null;
        const end = endsOn || null;

        if (start && day < start) return false;
        if (end && day > end) return false;

        return true;
    };

    const formatShortDate = (value) => {
        if (!value) return null;

        const [year, month, day] = value.split('-');

        return `${day}/${month}/${year}`;
    };

    // Meniru CategoryFeeCalculator::waveRangeLabel() supaya label rentang
    // tanggal gelombang tampil sama dengan yang dipakai di halaman lain.
    const waveRangeLabel = (wave) => {
        const from = formatShortDate(wave.starts_on);
        const to = formatShortDate(wave.ends_on);

        if (from && to) return `${from}–${to}`;
        if (from) return `mulai ${from}`;
        if (to) return `sampai ${to}`;

        return 'tanpa batas tanggal';
    };

    // Meniru PricingWaveResolver::activeFor() dan CategoryFeeCalculator::pricing()
    // agar pratinjau di browser memakai tarif yang sama dengan hitungan server.
    const resolvePricing = (rule) => {
        const day = todayIso();
        const waves = Array.isArray(rule.pricing_waves) ? rule.pricing_waves : [];
        const activeWave = waves.find((wave) => dateFallsWithin(day, wave.starts_on, wave.ends_on)) ?? null;
        const baseApplies = dateFallsWithin(day, rule.base_starts_on, rule.base_ends_on);

        // Gelombang hanya menimpa kolom yang benar-benar diisi. Kalau tidak ada
        // gelombang yang cocok, tarif utama hanya dipakai selama masanya masih
        // berlaku; lewat masa berlaku angkanya dikosongkan, bukan dipaksa nol.
        const amount = (key) => {
            if (activeWave && activeWave[key] != null) return Number(activeWave[key]);
            if (!baseApplies) return 0;

            return Number(rule[key] ?? 0);
        };

        const nullableAmount = (key) => {
            if (activeWave && activeWave[key] != null) return Number(activeWave[key]);
            if (!baseApplies) return null;

            return rule[key] == null ? null : Number(rule[key]);
        };

        return {
            wave: activeWave,
            baseApplies,
            packageFee: amount('package_fee'),
            extraRaceFee: amount('extra_race_fee'),
            outsideRaceFee: amount('outside_race_fee'),
            discountPercent: nullableAmount('discount_percent'),
            discountAmount: nullableAmount('discount_amount'),
        };
    };

    const ageOnEventDate = (birthDate) => {
        const [birthYear, birthMonth, birthDay] = birthDate.split('-').map(Number);
        const [eventYear, eventMonth, eventDay] = eventDate.split('-').map(Number);
        const birthdayIsAfterEventDate = eventMonth < birthMonth
            || (eventMonth === birthMonth && eventDay < birthDay);

        return eventYear - birthYear - Number(birthdayIsAfterEventDate);
    };

    const updateCategoryPreview = () => {
        const hasEligibilityDetails = Boolean(dateInput.value && genderInput.value);
        const birthYear = Number(dateInput.value.slice(0, 4));
        const age = dateInput.value && eventDate ? ageOnEventDate(dateInput.value) : null;
        const eligibleRules = rules.filter((rule) => {
            const eligibilityType = rule.eligibility_type
                ?? (rule.minimum_age != null || rule.maximum_age != null
                    ? 'age'
                    : Array.isArray(rule.birth_years) && rule.birth_years.length > 0
                        ? 'birth_year'
                        : 'birth_date');
            const meetsEligibilityRule = eligibilityType === 'birth_year'
                ? Array.isArray(rule.birth_years) && rule.birth_years.map(Number).includes(birthYear)
                : eligibilityType === 'age'
                    ? (rule.minimum_age == null || age >= Number(rule.minimum_age))
                        && (rule.maximum_age == null || age <= Number(rule.maximum_age))
                    : (!rule.birth_date_from || !rule.birth_date_to
                        || (dateInput.value >= rule.birth_date_from && dateInput.value <= rule.birth_date_to));

            return meetsEligibilityRule && rule.genders.includes(genderInput.value);
        });
        const raceOptions = Array.from(form.querySelectorAll('input[name="races[]"]'));

        raceOptions.forEach((input) => {
            const allowed = !hasEligibilityDetails || eligibleRules.some((rule) => rule.races.includes(input.value));
            const label = input.closest('label');
            input.disabled = !allowed;
            if (label) label.hidden = !allowed;
            if (!allowed) input.checked = false;
        });

        const selectedRaces = raceOptions.filter((input) => input.checked).map((input) => input.value);
        const candidates = eligibleRules
            .map((rule) => {
                const inCategory = selectedRaces.filter((race) => rule.races.includes(race));
                const outside = selectedRaces.filter((race) => !rule.races.includes(race));

                return { rule, inCategory, outside };
            })
            .filter((candidate) => candidate.inCategory.length > 0)
            .sort((a, b) => a.outside.length - b.outside.length);
        const matchingRule = candidates[0]?.rule ?? null;
        const requiredRaces = Math.max(1, Number(matchingRule?.required_races ?? 1));

        if (matchingRule) {
            const pricing = resolvePricing(matchingRule);
            const packageFee = pricing.packageFee;
            const extraRaceFee = pricing.extraRaceFee;
            const outsideRaceFee = pricing.outsideRaceFee;
            const extraRaces = Math.max(0, candidates[0].inCategory.length - requiredRaces);
            const extraTotal = extraRaces * extraRaceFee;
            const outsideRaces = matchingRule.outside_obligation
                ? selectedRaces.length
                : candidates[0].outside.length;
            const outsideTotal = outsideRaces * outsideRaceFee;
            const subtotal = packageFee + extraTotal + outsideTotal;
            const percent = pricing.discountPercent ?? 0;
            const fixed = pricing.discountAmount ?? 0;
            const discountTotal = Math.round(subtotal * percent / 100) + fixed;
            const total = Math.max(0, subtotal - discountTotal);
            const minimumMet = candidates[0].inCategory.length >= requiredRaces;
            const inCategory = candidates[0].inCategory;
            const outside = candidates[0].outside;

            const lines = [];
            lines.push(`Kategori: ${matchingRule.name}`);
            lines.push(`Minimal ${requiredRaces} mata lomba wajib`);
            if (pricing.wave) {
                lines.push(`Gelombang: ${pricing.wave.label || 'Tanpa nama'} (${waveRangeLabel(pricing.wave)})`);

                const lockUntil = formatShortDate(pricing.wave.ends_on);
                if (lockUntil) {
                    lines.push(`Bayar sebelum ${lockUntil} agar harga ini tetap berlaku`);
                }
            }
            if (packageFee > 0) {
                lines.push(`Harga paket: ${formatRupiah(packageFee)}`);
            }
            if (inCategory.length > 0) {
                lines.push(`Mata lomba paket: ${inCategory.join(', ')}`);
            }
            if (outsideRaces > 0 && outsideRaceFee > 0) {
                lines.push(`Mata lomba luar kategori: ${outside.join(', ')}`);
                lines.push(`Biaya luar kategori: ${formatRupiah(outsideRaceFee)} × ${outsideRaces} = ${formatRupiah(outsideTotal)}`);
            }
            if (extraRaces > 0 && extraRaceFee > 0) {
                lines.push(`Biaya tambahan: ${formatRupiah(extraRaceFee)} × ${extraRaces} = ${formatRupiah(extraTotal)}`);
            }
            if (discountTotal > 0) {
                const parts = [];
                if (percent > 0) parts.push(`${percent}%`);
                if (fixed > 0) parts.push(formatRupiah(fixed));
                lines.push(`Diskon: ${parts.join(' + ')} (${formatRupiah(discountTotal)})`);
            }

            // Tanpa gelombang yang cocok dan tarif utama sudah lewat masa
            // berlaku, harga tidak bisa dihitung. Kalau diam-diam ditulis Rp 0
            // peserta akan mengira event gratis, jadi pesannya ditulis terpisah.
            if (!pricing.wave && !pricing.baseApplies) {
                lines.push('Tarif belum berlaku untuk hari ini. Hubungi pengelola kejuaraan.');
                preview.textContent = lines.join('\n');
                preview.classList.toggle('is-assigned', false);

                return;
            }

            lines.push(`Total: ${formatRupiah(total)}`);

            // Kategori yang sudah penuh tetap boleh diisi. Peserta tetap
            // boleh mengirim biodata supaya datanya tersimpan dan bisa
            // dipakai pada event berikutnya, jadi pesannya hanya peringatan.
            const quota = categoryStatuses[matchingRule.name];
            if (quota && quota.open === false) {
                const reason = quota.override === 'closed'
                    ? 'ditutup oleh pengelola'
                    : 'penuh';
                lines.push(quota.quota
                    ? `Kategori ini ${reason}. Kuota ${quota.quota} peserta, sudah ${quota.confirmed} terkonfirmasi.`
                    : `Kategori ini ${reason}.`);
                lines.push('Anda tetap boleh mengajukan pendaftaran supaya biodata tersimpan.');
            }

            preview.textContent = minimumMet
                ? lines.join('\n')
                : `Kategori ${matchingRule.name} wajib diikuti minimal ${requiredRaces} mata lomba.\n${lines.slice(1).join('\n')}`;
            preview.classList.toggle('is-assigned', minimumMet);

            return;
        }

        preview.textContent = !hasEligibilityDetails
            ? 'Kategori atlet akan ditetapkan setelah pemilik event mengonfirmasi pendaftaran.'
            : eligibleRules.length === 0
                ? 'Tidak ada kategori yang sesuai dengan metode kelayakan dan gender atlet.'
                : 'Pilih mata lomba yang diizinkan untuk kategori atlet.';
        preview.classList.toggle('is-assigned', false);
    };

    form.addEventListener('change', updateCategoryPreview);
    updateCategoryPreview();
});

// Mengisi biodata otomatis dari NIK: wilayah (nilai awal alamat, tetap bisa
// disunting), gender, dan tanggal lahir (dikunci karena sudah terwakili di NIK).
document.querySelectorAll('[data-nik-autofill]').forEach((form) => {
    if (!(form instanceof HTMLFormElement)) return;

    const nikInput = form.querySelector('[data-identity-input]') ?? form.elements.namedItem('nik');
    const genderInput = form.elements.namedItem('gender');
    const dateInput = form.elements.namedItem('date_of_birth');
    const addressInput = form.elements.namedItem('address');
    const feedback = form.querySelector('[data-nik-feedback]');

    if (!(nikInput instanceof HTMLInputElement) || !(genderInput instanceof HTMLSelectElement) || !(dateInput instanceof HTMLInputElement)) {
        return;
    }

    const endpoint = form.dataset.nikAutofillEndpoint;
    const csrfToken = form.querySelector('input[name="_token"]')?.value ?? '';

    // Field ini satu buah input yang berganti nama antara "nik" dan
    // "passport_number". Pembacaan NIK hanya boleh berjalan ketika field sedang
    // menyimpan NIK, supaya nomor paspor tidak menghapus gender dan tanggal
    // lahir yang sudah diisi peserta secara manual.
    const isNikField = () => nikInput.name === 'nik';

    // Simpan nilai awal saat halaman dimuat: ini berasal dari input peserta
    // sebelumnya (old()) dan tidak boleh ditimpa oleh NIK yang baru diketik.
    const initialAddress = addressInput instanceof HTMLTextAreaElement ? addressInput.value : '';
    const initialGender = genderInput.value;
    const initialDate = dateInput.value;
    let addressTouchedByUser = initialAddress.trim() !== '';
    let requestId = 0;

    if (addressInput instanceof HTMLTextAreaElement) {
        addressInput.addEventListener('input', () => {
            addressTouchedByUser = true;
        });
    }

    const setFeedback = (message, isError) => {
        if (!(feedback instanceof HTMLElement)) return;
        feedback.textContent = message ?? '';
        feedback.hidden = !message;
        feedback.classList.toggle('is-error', Boolean(isError));
    };

    const lockIdentityFields = (locked) => {
        genderInput.disabled = locked;
        dateInput.disabled = locked;
        const lockedFields = [genderInput.closest('label'), dateInput.closest('label')];
        lockedFields.forEach((label) => {
            if (label instanceof HTMLElement) label.classList.toggle('is-locked', locked);
        });
    };

    // Field yang di-disable tidak ikut terkirim bersama form. Karena itu
    // gender dan tanggal lahir dibuka kembali secara diam-diam tepat sebelum
    // form dikirim, supaya nilainya tetap sampai ke server.
    form.addEventListener('submit', () => {
        if (form.dataset.nikResolved) {
            genderInput.disabled = false;
            dateInput.disabled = false;
        }
    });

    const applyParsedIdentity = (data) => {
        genderInput.value = data.gender;
        dateInput.value = data.date_of_birth;
        lockIdentityFields(true);

        if (addressInput instanceof HTMLTextAreaElement && data.address_suggestion && !addressTouchedByUser) {
            addressInput.value = data.address_suggestion;
        }

        // Beri tahu blok preview kategori bahwa gender dan tanggal lahir berubah.
        form.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const clearIdentityLock = () => {
        // NIK tidak sah, jadi identity yang berasal darinya tidak boleh dipertahankan.
        // Field dibuka kembali supaya peserta dapat mengisinya secara manual.
        delete form.dataset.nikResolved;
        lockIdentityFields(false);
    };

    // Peserta beralih ke warga negara lain: identitas dari NIK tidak lagi
    // berlaku, jadi gender dan tanggal lahir harus bisa diisi manual.
    form.addEventListener('nik-identity-cleared', () => {
        clearIdentityLock();
        form.dispatchEvent(new Event('change', { bubbles: true }));
    });

    const resetIdentityFromNik = () => {
        genderInput.value = '';
        dateInput.value = '';
        lockIdentityFields(false);
        form.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const lookupNik = async () => {
        if (!isNikField()) {
            setFeedback('', false);
            return;
        }

        const nik = nikInput.value.trim();

        if (nik.length !== 16 || !/^[0-9]{16}$/.test(nik)) {
            setFeedback('', false);
            if (!initialGender || !initialDate) {
                resetIdentityFromNik();
            }
            return;
        }

        if (nik === form.dataset.nikResolved) {
            return;
        }

        const currentRequest = ++requestId;
        setFeedback('Membaca NIK…', false);

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ nik }),
            });

            const data = await response.json().catch(() => null);

            if (currentRequest !== requestId) return;

            if (!response.ok || !data?.valid) {
                setFeedback(data?.message ?? 'NIK tidak dapat dibaca. Pastikan 16 digit sudah benar.', true);
                clearIdentityLock();
                return;
            }

            form.dataset.nikResolved = nik;
            applyParsedIdentity(data);

            const region = [data.district, data.regency, data.province].filter(Boolean).join(', ');
            setFeedback(
                region
                    ? `Wilayah terdaftar ${region}. Silakan lengkapi atau ubah pada biodata di bawah ini jika telah berpindah alamat KK.`
                    : '',
                false,
            );
        } catch (error) {
            if (currentRequest !== requestId) return;
            console.warn('Pembacaan NIK gagal.', error);
            setFeedback('Gagal membaca NIK. Isi gender dan tanggal lahir secara manual.', true);
            clearIdentityLock();
        }
    };

    // Bila halaman dimuat dengan NIK yang sudah valid (misalnya setelah error
    // validasi lain), langsung terapkan identitas dari NIK tersebut.
    const hasPrefilledIdentity = initialGender !== '' && initialDate !== '';
    if (hasPrefilledIdentity && isNikField() && /^[0-9]{16}$/.test(nikInput.value.trim())) {
        lockIdentityFields(true);
    } else {
        lockIdentityFields(false);
    }

    nikInput.addEventListener('input', () => {
        if (!isNikField()) return;

        // NIK berubah: identity yang lama tidak lagi relevan.
        if (nikInput.value.trim() !== form.dataset.nikResolved) {
            delete form.dataset.nikResolved;
            resetIdentityFromNik();
        }
    });

    nikInput.addEventListener('blur', () => {
        void lookupNik();
    });
});

// Mengganti jenis identitas peserta. Satu field dipakai untuk dua keperluan:
// NIK 16 digit bagi warga Indonesia, nomor paspor bagi warga negara lain.
// Nama field, judul label, petunjuk, dan aturan isian semuanya ikut berubah
// supaya peserta tidak salah mengisi.
const IDENTITY_LOCAL = {
    label: 'NIK',
    field: 'nik',
    hint: '16 digit. Wilayah, jenis kelamin, dan tanggal lahir akan terisi otomatis. Nomor ini disimpan terenkripsi dan hanya digunakan untuk identifikasi pendaftaran.',
    pattern: '[0-9]{16}',
    minLength: '16',
    maxLength: '16',
    inputMode: 'numeric',
};

const IDENTITY_FOREIGN = {
    label: 'Nomor paspor',
    field: 'passport_number',
    hint: 'Nomor paspor sesuai dokumen yang Anda miliki. Tanpa NIK, jenis kelamin dan tanggal lahir diisi sendiri di bawah. Nomor ini disimpan terenkripsi dan hanya dipakai untuk identifikasi pendaftaran.',
    pattern: null,
    minLength: null,
    maxLength: '60',
    inputMode: 'text',
};

document.querySelectorAll('[data-foreign-toggle]').forEach((toggle) => {
    if (!(toggle instanceof HTMLSelectElement)) return;

    const form = toggle.closest('form');
    if (!(form instanceof HTMLFormElement)) return;

    const localBlocks = form.querySelectorAll('[data-identity-local]');
    const foreignBlocks = form.querySelectorAll('[data-identity-foreign]');
    const identityInput = form.querySelector('[data-identity-input]');
    const identityLabel = form.querySelector('[data-identity-label]');
    const identityHint = form.querySelector('[data-identity-hint]');
    const nationalityInput = form.elements.namedItem('nationality');

    let applied = null;

    const apply = () => {
        const mode = toggle.value === '1' ? IDENTITY_FOREIGN : IDENTITY_LOCAL;
        const becameForeign = applied !== null && (toggle.value === '1') !== (applied === IDENTITY_FOREIGN);

        // Nilai identitas dari mode sebelumnya tidak boleh ikut terkirim ke
        // mode sekarang, karena formatnya berbeda dan akan ditolak server.
        if (becameForeign && identityInput instanceof HTMLInputElement) {
            identityInput.value = '';
        }

        if (identityInput instanceof HTMLInputElement) {
            identityInput.name = mode.field;
            identityInput.inputMode = mode.inputMode;
            identityInput.required = true;

            mode.pattern === null
                ? identityInput.removeAttribute('pattern')
                : identityInput.setAttribute('pattern', mode.pattern);
            mode.minLength === null
                ? identityInput.removeAttribute('minlength')
                : identityInput.setAttribute('minlength', mode.minLength);
            identityInput.setAttribute('maxlength', mode.maxLength);
        }

        if (identityLabel) identityLabel.textContent = mode.label;
        if (identityHint) identityHint.textContent = mode.hint;

        localBlocks.forEach((block) => {
            if (block instanceof HTMLElement) block.hidden = toggle.value === '1';
        });
        foreignBlocks.forEach((block) => {
            if (block instanceof HTMLElement) block.hidden = toggle.value !== '1';
        });

        if (nationalityInput instanceof HTMLInputElement) {
            nationalityInput.required = toggle.value === '1';
        }

        if (becameForeign && toggle.value === '1') {
            // Gender dan tanggal lahir tidak lagi bisa diturunkan dari NIK.
            form.dispatchEvent(new CustomEvent('nik-identity-cleared'));
        }

        applied = mode;
    };

    toggle.addEventListener('change', apply);
    apply();
});

document.querySelectorAll('[data-registration-modal-open]').forEach((button) => {
    if (!(button instanceof HTMLButtonElement)) return;
    const dialog = document.getElementById(button.dataset.registrationModalOpen ?? '');
    if (!(dialog instanceof HTMLDialogElement)) return;

    button.addEventListener('click', () => dialog.showModal());
});

// Dialog edit akun dipakai bersama oleh seluruh baris, jadi formulirnya diisi
// ulang dari atribut data-account-* milik tombol yang ditekan.
const accountModal = document.querySelector('[data-account-modal]');
const accountForm = accountModal?.querySelector('[data-account-modal-form]');
const accountFromDataset = (dataset) => ({
    id: dataset.accountId ?? '',
    name: dataset.accountName ?? '',
    email: dataset.accountEmail ?? '',
    phone: dataset.accountPhone ?? '',
    organization: dataset.accountOrganization ?? '',
    address: dataset.accountAddress ?? '',
    role: dataset.accountRole ?? '',
    is_primary_admin: dataset.accountPrimary === '1',
});
const updateAccountModal = (account) => {
    if (!(accountForm instanceof HTMLFormElement) || !account) return;

    const field = (name) => accountForm.elements.namedItem(name);

    for (const name of ['name', 'email', 'phone', 'organization', 'address', 'role']) {
        const input = field(name);
        if (input instanceof HTMLInputElement || input instanceof HTMLSelectElement || input instanceof HTMLTextAreaElement) {
            input.value = account[name] ?? '';
        }
    }

    const accountId = field('account_id');
    if (accountId instanceof HTMLInputElement) {
        accountId.value = account.id ?? '';
    }

    // Tautan tujuan berisi id akun, jadi id diganti dengan milik baris ini.
    if (accountForm instanceof HTMLFormElement) {
        accountForm.action = accountForm.action.replace(/\/akun\/[^/?]+$/, `/akun/${account.id}`);
    }

    // Kata sandi tidak pernah dibaca dari server; dikosongkan agar tidak
    // berubah tanpa disengaja.
    for (const name of ['password', 'password_confirmation']) {
        const input = field(name);
        if (input instanceof HTMLInputElement) {
            input.value = '';
        }
    }

    // Admin utama punya email dan jenis akun yang terkunci.
    const isPrimaryAdmin = Boolean(account.is_primary_admin);
    for (const name of ['email', 'role']) {
        const input = field(name);
        if (input instanceof HTMLInputElement || input instanceof HTMLSelectElement) {
            input.readOnly = isPrimaryAdmin;
            input.disabled = isPrimaryAdmin && name === 'role';
            if (isPrimaryAdmin && name === 'email') {
                input.value = account.email ?? '';
            }
        }
    }

    accountModal?.querySelector('[data-account-primary-note]')?.toggleAttribute('hidden', !isPrimaryAdmin);
};

document.querySelectorAll('[data-account-modal-open]').forEach((button) => {
    if (!(button instanceof HTMLButtonElement) || !(accountModal instanceof HTMLDialogElement)) return;

    button.addEventListener('click', () => {
        updateAccountModal(accountFromDataset(button.dataset));
        accountModal.showModal();
    });
});

accountModal?.querySelector('[data-account-modal-close]')?.addEventListener('click', () => {
    if (accountModal instanceof HTMLDialogElement) {
        accountModal.close();
    }
});

// Klik luar panel dihitung sebagai menutup dialog.
accountModal?.addEventListener('click', (event) => {
    if (event.target === accountModal && accountModal instanceof HTMLDialogElement) {
        accountModal.close();
    }
});

// Setelah validasi gagal, dialog dibuka kembali berisi isian terakhir.
const accountRestore = document.querySelector('[data-account-modal-restore]');
if (accountRestore instanceof HTMLElement && accountModal instanceof HTMLDialogElement) {
    updateAccountModal(accountFromDataset(accountRestore.dataset));
    accountModal.showModal();
}

document.querySelectorAll('[data-print-page]').forEach((button) => {
    button.addEventListener('click', () => window.print());
});

// Panel "Kategori dicetak": pengelola mematikan kategori yang tidak ikut
// dicetak. Barisnya hilang dari layar dan otomatis ikut terpotong saat dicetak
// atau di-export ke PDF, karena aturannya ada di CSS biasa.
document.querySelectorAll('[data-category-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const value = button.dataset.categoryToggle ?? '';
        const isActive = button.getAttribute('aria-pressed') !== 'true';

        button.setAttribute('aria-pressed', String(isActive));
        button.classList.toggle('is-active', isActive);

        document.querySelectorAll('[data-category]').forEach((row) => {
            if ((row.dataset.category ?? '') === value) {
                row.classList.toggle('event-roster-category-off', !isActive);
            }
        });
    });
});

// Halaman Export PDF tidak terikat ukuran A4: lebar kertasnya mengikuti lebar
// tabel supaya tidak ada kolom yang dipotong kertas. @page tidak bisa diatur
// lewat kelas, jadi aturannya ditulis langsung saat halaman dimuat.
const exportPage = document.querySelector('[data-export-page]');

if (exportPage instanceof HTMLElement) {
    const paperStyle = document.createElement('style');
    document.head.append(paperStyle);

    const fitPaperToTable = () => {
        const table = exportPage.querySelector('.event-roster-table');
        if (!(table instanceof HTMLTableElement)) return;

        // 96dpi: piksel dibagi 96 dikali 25,4 menjadi milimeter, ditambah
        // margin kiri-kanan 10mm.
        const widthMm = Math.ceil((Math.max(table.offsetWidth, table.scrollWidth) * 25.4) / 96) + 20;
        paperStyle.textContent = `@page { size: ${widthMm}mm 297mm; margin: 10mm; }`;
    };

    fitPaperToTable();

    // Menyembunyikan kategori mengubah lebar tabel, jadi kertas ikut
    // menyesuaikan diri.
    document.querySelectorAll('[data-category-toggle]').forEach((button) => {
        button.addEventListener('click', () => window.setTimeout(fitPaperToTable, 60));
    });
}

// Form konfirmasi pembayaran: pilihan "Tentukan sendiri" memunculkan input
// nominal manual, dan memilih "Harga periode berjalan" isi otomatis ke tagihan.
document.querySelectorAll('select[name="pricing_basis"]').forEach((select) => {
    if (!(select instanceof HTMLSelectElement)) return;

    const form = select.closest('form');
    if (!(form instanceof HTMLFormElement)) return;

    const manualField = form.querySelector('[data-manual-fee-field]');
    const manualInput = manualField?.querySelector('[name="manual_fee"]');
    const paidInput = form.querySelector('[name="paid_amount"]');

    const syncManualField = () => {
        if (!(manualField instanceof HTMLElement)) return;

        const isManual = select.value === 'manual';
        manualField.hidden = !isManual;
        if (manualInput instanceof HTMLInputElement) {
            manualInput.required = isManual;
        }
    };

    select.addEventListener('change', () => {
        syncManualField();

        // Harga periode berjalan chosen dipakai sebagai nominal acuan.
        if (select.value === 'current' && paidInput instanceof HTMLInputElement) {
            const option = select.selectedOptions[0];
            const nominal = option?.textContent?.match(/Rp\s([\d.]+)/);
            if (nominal) {
                paidInput.max = nominal[1].replace(/\./g, '');
                paidInput.value = paidInput.max;
            }
        }
    });

    syncManualField();
});

document.querySelectorAll('[data-registration-modal-close]').forEach((button) => {
    if (!(button instanceof HTMLButtonElement)) return;
    const dialog = button.closest('dialog');
    if (!(dialog instanceof HTMLDialogElement)) return;

    button.addEventListener('click', () => dialog.close());
});

document.querySelectorAll('dialog.event-registration-modal').forEach((dialog) => {
    if (!(dialog instanceof HTMLDialogElement)) return;
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
    });
});

document.querySelectorAll('dialog[data-open-on-load]').forEach((dialog) => {
    if (dialog instanceof HTMLDialogElement) dialog.showModal();
});

const PASSWORD_EYE_OPEN =
    '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.6 12S5.6 5.6 12 5.6 21.4 12 21.4 12 18.4 18.4 12 18.4 2.6 12 2.6 12Z"/><circle cx="12" cy="12" r="2.9"/></svg>';
const PASSWORD_EYE_CLOSED =
    '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9.9 9.9a2.9 2.9 0 0 0 4.2 4.2"/><path d="M10.7 5.3A9.9 9.9 0 0 1 12 5.1c6.4 0 9.4 6.9 9.4 6.9a16.6 16.6 0 0 1-1.7 2.7"/><path d="M6.6 6.6A16.9 16.9 0 0 0 2.6 12s3 6.9 9.4 6.9a9.5 9.5 0 0 0 5.4-1.6"/><path d="m3.4 3.4 17.2 17.2"/></svg>';

// Setiap field kata sandi mendapat tombol mata, baik yang sudah ada di markup
// maupun field baru yang ditambahkan lewat JavaScript.
function enablePasswordToggles(root) {
    root.querySelectorAll('input[type="password"]').forEach((input) => {
        if (!(input instanceof HTMLInputElement)) return;
        if (input.closest('.password-field')) return;

        const wrapper = document.createElement('span');
        wrapper.className = 'password-field';
        input.parentNode.insertBefore(wrapper, input);
        wrapper.append(input);

        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'password-toggle';
        toggle.innerHTML = PASSWORD_EYE_OPEN;
        wrapper.append(toggle);

        const setState = (revealed) => {
            input.type = revealed ? 'text' : 'password';
            toggle.setAttribute('aria-pressed', revealed ? 'true' : 'false');
            const label = revealed ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi';
            toggle.setAttribute('aria-label', label);
            toggle.title = label;
            toggle.innerHTML = revealed ? PASSWORD_EYE_CLOSED : PASSWORD_EYE_OPEN;
        };

        setState(false);

        toggle.addEventListener('click', () => setState(input.type !== 'text'));
    });
}

enablePasswordToggles(document);
