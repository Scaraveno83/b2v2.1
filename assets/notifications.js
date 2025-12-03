(() => {
    const navBadge = document.getElementById('nav-message-badge');
    if (!navBadge) {
        return;
    }

    const pollIntervalMs = 15000;
    let lastKnownMessageId = null;
    let hasInitialSnapshot = false;

    function updateBadge(unreadCount) {
        const safeCount = Number.isFinite(unreadCount) ? unreadCount : 0;
        if (safeCount > 0) {
            navBadge.textContent = safeCount > 99 ? '99+' : String(safeCount);
            navBadge.classList.add('is-visible');
        } else {
            navBadge.textContent = '';
            navBadge.classList.remove('is-visible');
        }
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

    function showToast(statusPayload) {
        if (!statusPayload || !statusPayload.latest_subject) {
            return;
        }

        const container = getToastContainer();
        const toast = document.createElement('div');
        toast.className = 'toast';

        const title = document.createElement('div');
        title.className = 'toast__title';
        title.textContent = 'Neue Nachricht';

        const meta = document.createElement('div');
        meta.className = 'toast__meta';
        meta.textContent = statusPayload.latest_sender ? `von ${statusPayload.latest_sender}` : 'Direkt im Posteingang';

        const subject = document.createElement('div');
        subject.className = 'toast__subject';
        subject.textContent = statusPayload.latest_subject;

        toast.appendChild(title);
        toast.appendChild(subject);
        toast.appendChild(meta);

        container.appendChild(toast);

        requestAnimationFrame(() => {
            toast.classList.add('is-visible');
        });

        setTimeout(() => {
            toast.classList.remove('is-visible');
            setTimeout(() => toast.remove(), 240);
        }, 4200);
    }

    async function pollStatus() {
        try {
            const response = await fetch('/messages_status.php', {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                return;
            }

            const payload = await response.json();
            const unread = Number.parseInt(payload.unread_count, 10) || 0;
            const latestId = payload.latest_message_id ? Number.parseInt(payload.latest_message_id, 10) : null;

            updateBadge(unread);

            if (!hasInitialSnapshot) {
                hasInitialSnapshot = true;
                lastKnownMessageId = latestId;
                return;
            }

            if (latestId !== null && (lastKnownMessageId === null || latestId > lastKnownMessageId)) {
                showToast(payload);
                lastKnownMessageId = latestId;
            }
        } catch (e) {
            // Silently ignore network errors
        } finally {
            setTimeout(pollStatus, pollIntervalMs);
        }
    }

    document.addEventListener('DOMContentLoaded', pollStatus);
})();