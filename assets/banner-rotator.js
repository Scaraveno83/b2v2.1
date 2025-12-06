(function() {
    function initRotator(root) {
        const items = Array.from(root.querySelectorAll('[data-banner-item]'));
        const progress = root.querySelector('[data-banner-progress]');

        if (items.length <= 1) {
            if (progress) {
                progress.style.display = 'none';
            }
            return;
        }

        let index = 0;
        let rotationTimer = null;
        let transitionTimer = null;
        let startTime = 0;
        let remaining = 0;
        const transitionDuration = 820;
        const interval = 5200;

        items.forEach((item, i) => {
            if (i === 0) {
                item.classList.add('is-active');
            } else {
                item.classList.remove('is-active');
            }
        });

        function clearAnimations(element) {
            element.classList.remove('is-entering', 'is-leaving');
        }

        function animateProgress(duration) {
            if (!progress) return;

            const elapsedPercent = Math.min(100, ((interval - duration) / interval) * 100);
            progress.style.transition = 'none';
            progress.style.width = `${elapsedPercent}%`;

            requestAnimationFrame(() => {
                progress.style.transition = `width ${duration}ms linear`;
                progress.style.width = '100%';
            });
        }

        function freezeProgress() {
            if (!progress) return;

            const elapsed = performance.now() - startTime;
            const percent = Math.min(100, (elapsed / interval) * 100);
            progress.style.transition = 'none';
            progress.style.width = `${percent}%`;
        }

        function switchTo(nextIndex) {
            if (nextIndex === index) return;

            const current = items[index];
            const next = items[nextIndex];

            clearTimeout(rotationTimer);
            rotationTimer = null;
            clearTimeout(transitionTimer);
            transitionTimer = null;
            clearAnimations(current);
            clearAnimations(next);

            current.classList.add('is-leaving');
            next.classList.add('is-active', 'is-entering');

            transitionTimer = setTimeout(() => {
                current.classList.remove('is-active', 'is-leaving');
                next.classList.remove('is-entering');
                index = nextIndex;
                transitionTimer = null;
                schedule();
            }, transitionDuration);
        }

        function schedule(duration = interval) {
            clearTimeout(rotationTimer);
            rotationTimer = null;
            startTime = performance.now();
            remaining = duration;
            animateProgress(duration);

            rotationTimer = setTimeout(() => {
                rotationTimer = null;
                const nextIndex = (index + 1) % items.length;
                switchTo(nextIndex);
            }, duration);
        }

        function pause() {
            if (!rotationTimer) return;
            clearTimeout(rotationTimer);
            rotationTimer = null;
            const elapsed = performance.now() - startTime;
            remaining = Math.max(0, interval - elapsed);
            freezeProgress();
        }

        function resume() {
            if (rotationTimer || remaining <= 0) return;
            schedule(remaining);
        }

        root.addEventListener('mouseenter', pause);
        root.addEventListener('mouseleave', resume);

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                pause();
            } else {
                resume();
            }
        });

        schedule();
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-banner-rotator]').forEach(initRotator);
    });
})();