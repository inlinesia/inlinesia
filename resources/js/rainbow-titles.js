//Judul halaman warna-warni bergaya Kids Roller Skate.
//
//Setiap kata pada judul dibungkus span warna-warni. Saat pointer diarahkan,
//kata tersebut "pop" ke atas (efek CSS di ceria-theme.css). Berjalan di semua
//halaman publik maupun panel karena dimuat dari kedua layout.

(function () {
    const COLORS = ['c-yellow', 'c-sky', 'c-mint', 'c-orange', 'c-purple', 'c-pink'];
    const TITLE_SELECTOR = 'h1, h2#listing-heading, [data-rainbow-title]';

    const wrapTextNode = (node, state) => {
        const fragment = document.createDocumentFragment();

        node.textContent.split(/(\s+)/).forEach((part) => {
            if (part === '') {
                return;
            }

            if (/^\s+$/.test(part)) {
                fragment.appendChild(document.createTextNode(part));

                return;
            }

            const word = document.createElement('span');
            word.className = 'skate-word ' + COLORS[state.index % COLORS.length];
            state.index += 1;
            word.textContent = part;
            fragment.appendChild(word);
        });

        node.replaceWith(fragment);
    };

    const colorize = (element, state) => {
        Array.from(element.childNodes).forEach((node) => {
            if (node.nodeType === Node.TEXT_NODE) {
                if (node.textContent.trim() !== '') {
                    wrapTextNode(node, state);
                }

                return;
            }

            if (node.nodeType === Node.ELEMENT_NODE && node.tagName !== 'SVG') {
                colorize(node, state);
            }
        });
    };

    const init = () => {
        document.querySelectorAll(TITLE_SELECTOR).forEach((title) => {
            if (title.dataset.rainbowProcessed === 'true') {
                return;
            }

            title.dataset.rainbowProcessed = 'true';
            colorize(title, { index: 0 });
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
