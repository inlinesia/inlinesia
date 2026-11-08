// Theme switcher Inlinesia.
//
// Tema disimpan di localStorage (per-browser, berlaku lintas halaman) dan
// diterapkan ke atribut data-theme pada elemen <html> sebelum halaman dirender
// oleh script di layouts/*.blade.php agar tidak ada kedipan tema.
(function () {
    const STORAGE_KEY = 'inlinesia-theme';
    const THEMES = ['glass', 'copper', 'neural', 'topic'];
    const DEFAULT_THEME = 'copper';

    const normalize = (value) => (THEMES.includes(value) ? value : DEFAULT_THEME);

    const readStored = () => {
        try {
            return window.localStorage.getItem(STORAGE_KEY);
        } catch {
            return null;
        }
    };

    const writeStored = (theme) => {
        try {
            window.localStorage.setItem(STORAGE_KEY, theme);
        } catch {
            /* localStorage mungkin diblokir; tema tetap berlaku di halaman ini. */
        }
    };

    const apply = (theme) => {
        const value = normalize(theme);
        document.documentElement.setAttribute('data-theme', value);
        document.documentElement.style.colorScheme = value === 'copper' ? 'dark' : 'dark';

        document.querySelectorAll('[data-theme-option]').forEach((option) => {
            const input = option.querySelector('input');
            const isChecked = input instanceof HTMLInputElement && input.value === value;
            if (input instanceof HTMLInputElement) {
                input.checked = isChecked;
            }
            option.classList.toggle('is-active', isChecked);
        });
    };

    const init = () => {
        const stored = normalize(readStored() || DEFAULT_THEME);
        apply(stored);

        document.querySelectorAll('[data-theme-form]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                event.preventDefault();
                const data = new FormData(form);
                const theme = normalize(data.get('theme'));
                writeStored(theme);
                apply(theme);

                const message = form.querySelector('[data-theme-status]');
                if (message) {
                    message.textContent =
                        theme === 'copper'
                            ? 'Tema tembaga aktif. Pilihan tersimpan di peramban ini.'
                            : 'Tema glass aktif. Pilihan tersimpan di peramban ini.';
                }
            });

            form.querySelectorAll('input[name="theme"]').forEach((input) => {
                input.addEventListener('change', () => {
                    apply(input.value);
                });
            });
        });
    };

    window.InlinesiaTheme = { apply, init, THEMES, DEFAULT_THEME };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
