// Pengisi cepat kolom detail event pada halaman "Kelola Kejuaraan".
//
// Setiap blok [data-preset-picker] punya daftar pilihan cepat (checkbox) dan
// satu textarea manual. Centang pada pilihan cepat langsung disisipkan ke
// textarea, dan sebaliknya: baris yang sudah ada di textarea akan menandai
// checkbox terkait sebagai tercentang.
document.querySelectorAll('[data-preset-picker]').forEach((picker) => {
    const textarea = picker.querySelector('[data-preset-target]');
    const customToggle = picker.querySelector('[data-preset-custom-toggle]');
    const customArea = picker.querySelector('[data-preset-custom-area]');
    const note = picker.querySelector('[data-preset-note]');
    const optionInputs = Array.from(picker.querySelectorAll('[data-preset-value]'));

    if (!(textarea instanceof HTMLTextAreaElement)) {
        return;
    }

    const lines = () =>
        textarea.value
            .split(/\r?\n/)
            .map((line) => line.trim())
            .filter((line) => line !== '');

    const setLines = (values) => {
        textarea.value = values.join('\n');
    };

    const setCustomOpen = (open) => {
        if (customToggle instanceof HTMLInputElement) customToggle.checked = open;
        if (customArea instanceof HTMLElement) customArea.hidden = !open;
        if (note instanceof HTMLElement) note.hidden = open;
    };

    const syncFromTextarea = () => {
        const current = lines();
        const currentSet = new Set(current);

        optionInputs.forEach((input) => {
            if (!(input instanceof HTMLInputElement)) return;
            input.checked = currentSet.has(input.dataset.presetValue ?? '');
        });

        const presetValues = new Set(optionInputs.map((input) => input.dataset.presetValue ?? ''));
        const hasCustom = current.some((line) => !presetValues.has(line));

        setCustomOpen(hasCustom || (customToggle instanceof HTMLInputElement && customToggle.checked));

        if (hasCustom && !current.some((line) => presetValues.has(line))) {
            setCustomOpen(true);
        }
    };

    const syncFromOptions = () => {
        const selected = optionInputs
            .filter((input) => input instanceof HTMLInputElement && input.checked)
            .map((input) => input.dataset.presetValue ?? '');

        const presetValues = new Set(optionInputs.map((input) => input.dataset.presetValue ?? ''));
        const manual = lines().filter((line) => !presetValues.has(line));

        setLines([...selected, ...manual]);

        if (selected.length > 0) {
            setCustomOpen(manual.length > 0);
        }
    };

    optionInputs.forEach((input) => {
        input.addEventListener('change', () => {
            if (input.checked && textarea.value.trim() === '') {
                setCustomOpen(true);
            }

            syncFromOptions();
        });
    });

    if (customToggle instanceof HTMLInputElement) {
        customToggle.addEventListener('change', () => {
            setCustomOpen(customToggle.checked);

            if (customToggle.checked) {
                textarea.focus();
            }
        });
    }

    textarea.addEventListener('input', syncFromTextarea);

    syncFromTextarea();
});
