(function() {
    'use strict';

    function collectNavOptions() {
        const links = Array.from(document.querySelectorAll('nav .nav-link'));
        const options = [];
        const seen = new Set();

        links.forEach(link => {
            const label = (link.textContent || '').trim();
            const href = link.getAttribute('href');

            if (!href || href === '/login/logout.php') {
                return;
            }

            const absoluteHref = link.href;
            if (!absoluteHref || seen.has(absoluteHref)) {
                return;
            }

            seen.add(absoluteHref);
            options.push({ label, href: absoluteHref });
        });

        // Add current page as quick option if it is not already present
        const currentHref = window.location.href;
        if (currentHref && !seen.has(currentHref)) {
            options.unshift({ label: 'Aktuelle Seite', href: currentHref });
        }

        return options;
    }

    function populateSelect(select, options) {
        select.innerHTML = '';

        options.forEach(opt => {
            const option = document.createElement('option');
            option.value = opt.href;
            option.textContent = opt.label;
            select.appendChild(option);
        });
    }

    function openOverlay(overlay, toggleButton) {
        overlay.hidden = false;
        overlay.classList.add('visible');
        document.body.classList.add('split-mode-open');
        if (toggleButton) {
            toggleButton.setAttribute('aria-expanded', 'true');
        }
    }

    function closeOverlay(overlay, toggleButton) {
        overlay.classList.remove('visible');
        overlay.hidden = true;
        document.body.classList.remove('split-mode-open');
        if (toggleButton) {
            toggleButton.setAttribute('aria-expanded', 'false');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const overlay = document.querySelector('[data-split-overlay]');
        const toggleButton = document.querySelector('[data-split-toggle]');
        const closeButton = overlay ? overlay.querySelector('[data-split-close]') : null;
        const selects = overlay ? overlay.querySelectorAll('[data-split-select]') : [];

        if (!overlay || !toggleButton || selects.length === 0) {
            return;
        }

        // Ensure the overlay starts inactive to avoid blocking the UI if markup states were altered
        overlay.hidden = true;
        overlay.classList.remove('visible');
        document.body.classList.remove('split-mode-open');

        const frames = {
            left: overlay.querySelector('[data-split-frame="left"]'),
            right: overlay.querySelector('[data-split-frame="right"]'),
        };

        const options = collectNavOptions();
        selects.forEach(select => populateSelect(select, options));

        function syncFrame(select) {
            const target = select.getAttribute('data-split-target');
            const frame = frames[target];
            if (frame) {
                frame.src = select.value;
            }
        }

        // Preload defaults
        const left = overlay.querySelector('[data-split-target="left"]');
        const right = overlay.querySelector('[data-split-target="right"]');
        if (left && left.options.length > 0) {
            left.selectedIndex = 0;
            syncFrame(left);
        }
        if (right && right.options.length > 1) {
            right.selectedIndex = 1;
            syncFrame(right);
        } else if (right && right.options.length > 0) {
            right.selectedIndex = 0;
            syncFrame(right);
        }

        selects.forEach(select => {
            select.addEventListener('change', () => syncFrame(select));
        });

        toggleButton.addEventListener('click', () => {
            if (overlay.hidden) {
                openOverlay(overlay, toggleButton);
            } else {
                closeOverlay(overlay, toggleButton);
            }
        });

        if (closeButton) {
            closeButton.addEventListener('click', () => closeOverlay(overlay, toggleButton));
        }

        overlay.addEventListener('click', (event) => {
            if (event.target === overlay) {
                closeOverlay(overlay, toggleButton);
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !overlay.hidden) {
                closeOverlay(overlay, toggleButton);
            }
        });
    });
})();