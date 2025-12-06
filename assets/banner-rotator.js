(function() {
    function initRotator(root) {
        const items = Array.from(root.querySelectorAll('[data-banner-item]'));
        if (items.length <= 1) {
            return;
        }

        let index = 0;
        const animations = ['slide', 'tilt', 'zoom', 'blur'];

        items.forEach((item, i) => {
            if (i !== 0) {
                item.classList.remove('is-active');
            }
        });

        function switchTo(nextIndex) {
            if (nextIndex === index) return;
            const current = items[index];
            const next = items[nextIndex];
            const animation = animations[Math.floor(Math.random() * animations.length)];

            next.classList.add('is-active', 'is-entering', `animate-${animation}`);
            current.classList.add('is-leaving', `animate-${animation}`);

            setTimeout(() => {
                current.classList.remove('is-active', 'is-leaving', `animate-${animation}`);
            }, 520);

            setTimeout(() => {
                next.classList.remove('is-entering', `animate-${animation}`);
            }, 640);

            index = nextIndex;
        }

        setInterval(() => {
            const nextIndex = (index + 1) % items.length;
            switchTo(nextIndex);
        }, 4800);
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-banner-rotator]').forEach(initRotator);
    });
})();