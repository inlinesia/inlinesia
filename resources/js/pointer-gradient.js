//Sorotan gradasi tema Ceria mengikuti pointer.
//
//Posisi pointer ditulis ke variabel CSS --pointer-x / --pointer-y pada <html>,
//lalu dipakai radial-gradient di ceria-theme.css (body dan hero). Gerakan
//dihaluskan dengan interpolasi pada requestAnimationFrame.

(function () {
    const root = document.documentElement;
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    let currentX = 50;
    let currentY = 50;
    let targetX = 50;
    let targetY = 50;
    let frame = 0;

    const apply = () => {
        root.style.setProperty('--pointer-x', currentX.toFixed(2) + '%');
        root.style.setProperty('--pointer-y', currentY.toFixed(2) + '%');
    };

    const animate = () => {
        currentX += (targetX - currentX) * 0.14;
        currentY += (targetY - currentY) * 0.14;

        apply();

        if (Math.abs(targetX - currentX) > 0.05 || Math.abs(targetY - currentY) > 0.05) {
            frame = window.requestAnimationFrame(animate);
        } else {
            frame = 0;
        }
    };

    const onPointerMove = (event) => {
        if (root.getAttribute('data-theme') !== 'ceria' || reduceMotion) {
            return;
        }

        const width = window.innerWidth || 1;
        const height = window.innerHeight || 1;
        targetX = Math.min(100, Math.max(0, (event.clientX / width) * 100));
        targetY = Math.min(100, Math.max(0, (event.clientY / height) * 100));

        if (!frame) {
            frame = window.requestAnimationFrame(animate);
        }
    };

    apply();

    window.addEventListener('pointermove', onPointerMove, { passive: true });
})();
