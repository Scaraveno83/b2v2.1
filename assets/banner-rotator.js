(function() {
    function initRotator(root) {
        const items = Array.from(root.querySelectorAll('[data-banner-item]'));
        if (items.length <= 1) {
            return;
        }

        let index = 0;
        let timer = null;
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

        function switchTo(nextIndex) {
            if (nextIndex === index) return;

            const current = items[index];
            const next = items[nextIndex];

            clearTimeout(timer);
            clearAnimations(current);
            clearAnimations(next);

            current.classList.add('is-leaving');
            next.classList.add('is-active', 'is-entering');

            timer = setTimeout(() => {
                current.classList.remove('is-active', 'is-leaving');
                next.classList.remove('is-entering');
                index = nextIndex;
                schedule();
            }, transitionDuration);
        }

        function schedule() {
            clearTimeout(timer);
            timer = setTimeout(() => {
                const nextIndex = (index + 1) % items.length;
                switchTo(nextIndex);
            }, interval);
        }

        root.addEventListener('mouseenter', () => clearTimeout(timer));
        root.addEventListener('mouseleave', schedule);

        schedule();
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-banner-rotator]').forEach(initRotator);
    });
})();