// Widget page-context: minimize/expand dan simpan posisi supaya tetap "di tempat"
// ketika pindah halaman. Gunakan pointer drag ringan untuk memindahkan kartu.
(() => {
    const STORAGE_KEY = 'inlinesia-page-context';

    const readState = () => {
        try {
            return JSON.parse(window.localStorage.getItem(STORAGE_KEY)) || {};
        } catch (error) {
            return {};
        }
    };

    const writeState = (state) => {
        try {
            window.localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
        } catch (error) {
            // Abaikan bila storage dibatasi browser.
        }
    };

    document.querySelectorAll('[data-page-context]').forEach((widget) => {
        const card = widget.querySelector('[data-page-context-card]');
        const toggle = widget.querySelector('[data-page-context-toggle]');

        if (!card || !toggle) {
            return;
        }

        const state = readState();

        // Pulihkan status minimize/expand.
        if (state.collapsed) {
            card.classList.add('is-collapsed');
            toggle.textContent = '+';
            toggle.setAttribute('aria-expanded', 'false');
        }

        toggle.addEventListener('click', () => {
            const collapsed = card.classList.toggle('is-collapsed');
            toggle.textContent = collapsed ? '+' : '–';
            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');

            const next = readState();
            next.collapsed = collapsed;
            writeState(next);
        });

        // Posisi kartu mengikuti sudut layout (kanan atas / kanan bawah) agar tidak
        // menutupi navigasi utama di layar kecil.
        if (state.position === 'top') {
            widget.style.bottom = 'auto';
            widget.style.top = '86px';
        }

        if (card.dataset.dragEnabled === 'false') {
            return;
        }

        // Drag sederhana untuk memindahkan widget ke sudut lain.
        let dragging = false;
        let offsetX = 0;
        let offsetY = 0;

        card.addEventListener('pointerdown', (event) => {
            if (event.target.closest('button')) {
                return;
            }

            dragging = true;
            const rect = widget.getBoundingClientRect();
            offsetX = event.clientX - rect.left;
            offsetY = event.clientY - rect.top;
            widget.style.right = 'auto';
            widget.style.bottom = 'auto';
            widget.style.left = `${rect.left}px`;
            widget.style.top = `${rect.top}px`;
            card.setPointerCapture(event.pointerId);
        });

        card.addEventListener('pointermove', (event) => {
            if (!dragging) {
                return;
            }

            const nextLeft = event.clientX - offsetX;
            const nextTop = event.clientY - offsetY;
            widget.style.left = `${Math.max(8, nextLeft)}px`;
            widget.style.top = `${Math.max(8, nextTop)}px`;
        });

        card.addEventListener('pointerup', (event) => {
            if (!dragging) {
                return;
            }

            dragging = false;
            card.releasePointerCapture(event.pointerId);

            // Simpan ke sudut terdekat supaya tidak menutupi konten penting.
            const verticalMiddle = window.innerHeight / 2;
            const currentTop = widget.getBoundingClientRect().top;
            const position = currentTop + widget.offsetHeight / 2 < verticalMiddle ? 'top' : 'bottom';

            const next = readState();
            next.position = position;
            writeState(next);

            widget.style.left = 'auto';
            if (position === 'top') {
                widget.style.top = '86px';
                widget.style.bottom = 'auto';
            } else {
                widget.style.top = 'auto';
                widget.style.bottom = '18px';
            }
        });
    });
})();