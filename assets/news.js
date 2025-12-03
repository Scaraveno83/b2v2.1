(() => {
    const pollIntervalMs = 20000;
    const userRole = document.body?.dataset?.userRole || 'guest';
    const userId = document.body?.dataset?.userId || 'anon';
    const statusScope = document.body?.dataset?.newsScope || 'auto';
    const seenKey = `news:lastSeen:${statusScope}:${userRole}:${userId}`;
    const mentionEndpoint = '/news/mentions.php';
    let mentionOptionsPromise = null;
    const emojiPalette = ['😀', '😁', '😂', '🙂', '😊', '😍', '🤩', '🤔', '😎', '🙌', '👍', '🔥', '🚀', '💡', '✅'];

    function loadMentionOptions() {
        if (!mentionOptionsPromise) {
            mentionOptionsPromise = fetch(mentionEndpoint, { credentials: 'same-origin' })
                .then((res) => res.ok ? res.json() : { ok: false })
                .then((json) => json && json.ok ? json : { ranks: [], partners: [], employees: [] })
                .catch(() => ({ ranks: [], partners: [], employees: [] }));
        }
        return mentionOptionsPromise;
    }

    function insertAtCursor(textarea, text) {
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const before = textarea.value.slice(0, start);
        const after = textarea.value.slice(end);
        textarea.value = `${before}${text}${after}`;
        const pos = start + text.length;
        textarea.setSelectionRange(pos, pos);
        textarea.focus();
    }

    function getToastContainer() {
        let container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            document.body.appendChild(container);
        }
        return container;
    }

    function showNewsToast(payload) {
        if (!payload || !payload.latest_title) {
            return;
        }
        const container = getToastContainer();
        const toast = document.createElement('div');
        toast.className = 'toast';

        const title = document.createElement('div');
        title.className = 'toast__title';
        title.textContent = 'Neue News verfügbar';

        const subject = document.createElement('div');
        subject.className = 'toast__subject';
        subject.textContent = payload.latest_title;

        const meta = document.createElement('div');
        meta.className = 'toast__meta';
        meta.textContent = 'Live-Ticker Update';

        toast.appendChild(title);
        toast.appendChild(subject);
        toast.appendChild(meta);

        container.appendChild(toast);
        requestAnimationFrame(() => toast.classList.add('is-visible'));
        setTimeout(() => {
            toast.classList.remove('is-visible');
            setTimeout(() => toast.remove(), 240);
        }, 4200);
    }

    async function pollNewsStatus() {
        try {
            const response = await fetch(`/news/news_status.php?scope=${encodeURIComponent(statusScope)}`, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
            });
            if (!response.ok) {
                return;
            }
            const payload = await response.json();
            const latestId = payload.latest_news_id ? Number.parseInt(payload.latest_news_id, 10) : null;
            if (latestId === null) {
                return;
            }

            const seenRaw = localStorage.getItem(seenKey);
            const seenId = seenRaw ? Number.parseInt(seenRaw, 10) : null;
            if (seenId === null) {
                localStorage.setItem(seenKey, String(latestId));
                return;
            }

            if (latestId > seenId) {
                showNewsToast(payload);
                localStorage.setItem(seenKey, String(latestId));
            }
        } catch (e) {
            // ignore network errors silently
        } finally {
            setTimeout(pollNewsStatus, pollIntervalMs);
        }
    }

    function bindReactions() {
        const roots = document.querySelectorAll('[data-news-reaction-root]');
        roots.forEach((root) => {
            const newsId = root.closest('[data-news-id]')?.dataset?.newsId;
            if (!newsId) return;

            root.querySelectorAll('[data-news-reaction]').forEach((button) => {
                button.addEventListener('click', async () => {
                    if (button.disabled) return;
                    const emoji = button.dataset.emoji;
                    const form = new FormData();
                    form.append('news_id', newsId);
                    form.append('emoji', emoji);

                    try {
                        const response = await fetch('/news/react.php', {
                            method: 'POST',
                            body: form,
                            credentials: 'same-origin',
                        });
                        if (!response.ok) {
                            return;
                        }
                        const data = await response.json();
                        if (!data.ok) {
                            return;
                        }
                        updateReactionUi(root, data);
                    } catch (e) {
                        // ignore
                    }
                });
            });
        });
    }

    function updateReactionUi(root, payload) {
        if (!payload || !payload.counts) return;
        const active = new Set(payload.user || []);
        root.querySelectorAll('[data-news-reaction]').forEach((button) => {
            const emoji = button.dataset.emoji;
            const count = payload.counts[emoji] ?? 0;
            const counter = button.querySelector('.reaction-count');
            if (counter) {
                counter.textContent = count;
            }
            if (active.has(emoji)) {
                button.classList.add('is-active');
            } else {
                button.classList.remove('is-active');
            }
        });
    }

    function initTickers() {
        document.querySelectorAll('[data-news-ticker]').forEach(loadTicker);
    }

    async function loadTicker(node) {
        const scope = node.dataset.scope || 'auto';
        node.classList.add('news-ticker');
        node.innerHTML = '<div class="muted">Live-Ticker wird geladen...</div>';
        try {
            const response = await fetch(`/news/ticker.php?scope=${encodeURIComponent(scope)}`, {
                credentials: 'same-origin',
            });
            if (!response.ok) {
                node.innerHTML = '<div class="muted">Ticker derzeit nicht verfügbar.</div>';
                return;
            }
            const payload = await response.json();
            renderTicker(node, payload.items || []);
        } catch (e) {
            node.innerHTML = '<div class="muted">Ticker derzeit nicht verfügbar.</div>';
        }
    }

    function renderTicker(node, items) {
        if (!items || items.length === 0) {
            node.innerHTML = '<div class="muted">Keine News im Ticker.</div>';
            return;
        }

        node.innerHTML = '';
        const stage = document.createElement('div');
        stage.className = 'news-ticker__stage';
        node.appendChild(stage);

        items.forEach((item, index) => {
            const slide = document.createElement('div');
            slide.className = 'news-ticker__item';
            if (index === 0) {
                slide.classList.add('is-active');
            }
            slide.innerHTML = `
                <div class="news-ticker__meta">
                    <span class="badge badge-live">Live</span>
                    <span class="badge badge-ghost">${item.audience_label || 'News'}</span>
                    <span class="muted">${new Date(item.created_at).toLocaleString('de-DE')}</span>
                </div>
                <div class="news-ticker__title">${item.title}</div>
            `;
            stage.appendChild(slide);
        });

        let idx = 0;
        setInterval(() => {
            const slides = stage.querySelectorAll('.news-ticker__item');
            if (slides.length === 0) return;
            slides.forEach((el) => el.classList.remove('is-active'));
            idx = (idx + 1) % slides.length;
            slides[idx].classList.add('is-active');
        }, 4800);
    }

    function normalizeMentionOptions(payload) {
        const ranks = (payload.ranks || []).map((name) => ({ label: name, type: 'rank', labelLower: name.toLowerCase() }));
        const partners = (payload.partners || []).map((name) => ({ label: name, type: 'partner', labelLower: name.toLowerCase() }));
        const employees = (payload.employees || []).map((name) => ({ label: name, type: 'employee', labelLower: name.toLowerCase() }));
        return [...ranks, ...partners, ...employees];
    }

    function buildMentionListMarkup(options, container) {
        container.innerHTML = '';
        const groups = options.reduce((acc, item) => {
            if (!acc[item.type]) acc[item.type] = [];
            acc[item.type].push(item);
            return acc;
        }, {});
        const order = ['rank', 'employee', 'partner'];
        const labels = { rank: 'Ränge', employee: 'Mitarbeiter', partner: 'Partner' };

        const header = document.createElement('div');
        header.className = 'mention-suggestions__header';
        header.innerHTML = `
            <div class="mention-suggestions__title">Erwähnungen</div>
            <div class="mention-suggestions__count">${options.length} Treffer</div>
        `;
        container.appendChild(header);

        order.forEach((key) => {
            if (!groups[key] || groups[key].length === 0) return;
            const group = document.createElement('div');
            group.className = 'mention-option__group';

            const heading = document.createElement('div');
            heading.className = 'mention-option__group-title';
            heading.textContent = labels[key];
            group.appendChild(heading);

            groups[key].forEach((item) => {
                const option = document.createElement('div');
                option.className = 'mention-option';
                option.dataset.label = item.label;
                option.dataset.type = item.type;
                option.innerHTML = `
                    <div class="mention-avatar mention-avatar--${item.type}" aria-hidden="true">${getInitials(item.label)}</div>
                    <div class="mention-option__body">
                        <div class="mention-option__title">${item.label}</div>
                        <div class="mention-option__meta">${labels[item.type]}</div>
                    </div>
                    <div class="mention-option__tag">@${item.label}</div>
                `;
                group.appendChild(option);
            });
            container.appendChild(group);
        });

        const footer = document.createElement('div');
        footer.className = 'mention-suggestions__footer';
        footer.textContent = `${options.length} Ergebnis${options.length === 1 ? '' : 'se'} angezeigt`;
        container.appendChild(footer);
    }

    function getInitials(label) {
        if (!label) return '';
        const parts = label.split(/\s+/).filter(Boolean);
        if (parts.length === 1) {
            return parts[0].slice(0, 2).toUpperCase();
        }
        return `${parts[0][0] ?? ''}${parts[1][0] ?? ''}`.toUpperCase();
    }

    function bindMentionInputs() {
        document.querySelectorAll('textarea[data-mentionable]').forEach((textarea) => {
            setupMentionInput(textarea);
        });
    }

    function setupMentionInput(textarea) {
        const suggestionBox = textarea.parentElement.querySelector('[data-mention-list]');
        if (!suggestionBox) return;

        let flatOptions = [];
        let visibleOptions = [];
        let activeIndex = -1;

        suggestionBox.addEventListener('mousedown', (event) => {
            const target = event.target.closest('.mention-option');
            if (!target) return;
            event.preventDefault();
            insertMention(target.dataset.label);
        });

        function insertMention(label) {
            if (!label) return;
            const cursor = textarea.selectionStart;
            const fullText = textarea.value;
            const before = fullText.slice(0, cursor);
            const after = fullText.slice(cursor);
            const match = before.match(/@([\p{L}\d _\-]{0,48})$/u);
            const replacement = `@${label} `;

            const start = match ? match.index : before.length;
            const newBefore = `${before.slice(0, start)}${replacement}`;
            textarea.value = `${newBefore}${after}`;

            const pos = newBefore.length;
            textarea.setSelectionRange(pos, pos);
            textarea.focus();
            hideSuggestions();
        }

        function hideSuggestions() {
            suggestionBox.classList.remove('is-visible');
            suggestionBox.classList.remove('is-above');
            suggestionBox.hidden = true;
            visibleOptions = [];
            activeIndex = -1;
        }

        function highlightActive() {
            suggestionBox.querySelectorAll('.mention-option').forEach((node, idx) => {
                node.classList.toggle('is-active', idx === activeIndex);
            });
        }

        function positionSuggestions() {
            const margin = 12;
            suggestionBox.classList.remove('is-above');
            suggestionBox.style.maxHeight = '';

            const triggerRect = textarea.getBoundingClientRect();
            const viewportHeight = window.innerHeight;
            const spaceBelow = Math.max(0, viewportHeight - triggerRect.bottom);
            const spaceAbove = Math.max(0, triggerRect.top);

            const shouldAnchorBelow = spaceBelow >= spaceAbove;
            const availableSpace = shouldAnchorBelow ? spaceBelow : spaceAbove;
            const availableHeight = Math.max(0, availableSpace - margin);

            let maxHeight = Math.min(320, availableHeight);
            if (maxHeight < 140) {
                maxHeight = Math.min(availableHeight, 140);
            }

            if (!shouldAnchorBelow) {
                suggestionBox.classList.add('is-above');
            }
            suggestionBox.style.maxHeight = `${maxHeight}px`;
            suggestionBox.scrollTop = 0;
        }

        function renderSuggestions(query) {
            const filter = (query || '').toLowerCase();
            visibleOptions = flatOptions.filter((opt) => !filter || opt.labelLower.includes(filter));
            if (visibleOptions.length === 0) {
                hideSuggestions();
                return;
            }
            buildMentionListMarkup(visibleOptions, suggestionBox);
            suggestionBox.classList.add('is-visible');
            suggestionBox.hidden = false;
            positionSuggestions();
            activeIndex = 0;
            highlightActive();
        }

        textarea.addEventListener('input', async () => {
            const cursor = textarea.selectionStart;
            const textBefore = textarea.value.slice(0, cursor);
            const match = textBefore.match(/@([\p{L}\d _\-]{0,48})$/u);
            if (!match) {
                hideSuggestions();
                return;
            }
            if (flatOptions.length === 0) {
                const payload = await loadMentionOptions();
                flatOptions = normalizeMentionOptions(payload);
            }
            renderSuggestions(match[1]);
        });

        textarea.addEventListener('keydown', (event) => {
            if (!suggestionBox.classList.contains('is-visible')) return;
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                activeIndex = (activeIndex + 1) % visibleOptions.length;
                highlightActive();
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                activeIndex = (activeIndex - 1 + visibleOptions.length) % visibleOptions.length;
                highlightActive();
            } else if (event.key === 'Enter') {
                if (activeIndex >= 0 && visibleOptions[activeIndex]) {
                    event.preventDefault();
                    insertMention(visibleOptions[activeIndex].label);
                }
            } else if (event.key === 'Escape') {
                hideSuggestions();
            }
        });

        textarea.addEventListener('blur', () => {
            setTimeout(hideSuggestions, 120);
        });
    }

    function bindEmojiPickers() {
        document.querySelectorAll('textarea[data-emoji-picker]').forEach((textarea) => {
            const toolbar = textarea.parentElement.querySelector('[data-emoji-toolbar]');
            if (!toolbar) return;
            toolbar.innerHTML = '';
            emojiPalette.forEach((emoji) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = emoji;
                btn.addEventListener('click', (event) => {
                    event.preventDefault();
                    insertAtCursor(textarea, `${emoji} `);
                });
                toolbar.appendChild(btn);
            });
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        bindReactions();
        initTickers();
        bindMentionInputs();
        bindEmojiPickers();
        pollNewsStatus();
    });
})();