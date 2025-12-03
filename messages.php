<?php
require_once __DIR__ . '/auth/check_role.php';
checkRole(['admin', 'employee', 'partner']);
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/message_service.php';
require_once __DIR__ . '/includes/layout.php';
requireAbsenceAccess('messages');

ensureMessageTables($pdo);

$user = $_SESSION['user'];
$feedback = '';
$error = '';

function buildQuotedBody(array $message): string
{
    $author = $message['sender_name'] ?? 'System';
    $timestamp = date('d.m.Y H:i', strtotime($message['created_at']));
    $body = trim((string)$message['body']);

    if ($body === '') {
        return '';
    }

    $quoted = preg_replace('/^/m', '> ', $body);
    return "— {$author} am {$timestamp} —\n{$quoted}";
}

function renderMessageBody(string $body): string
{
    $lines = preg_split('/\r?\n/', $body);
    $html = '';

    foreach ($lines as $line) {
        if (preg_match('/^(>+)(.*)$/', $line, $matches)) {
            $depth = strlen($matches[1]);
            $content = trim($matches[2]);
            $html .= '<div class="quote-line" style="--depth:' . $depth . '"><span class="quote-bar"></span>'
                . ($content === '' ? '&nbsp;' : htmlspecialchars($content)) . '</div>';
        } else {
            $safeLine = $line === '' ? '&nbsp;' : htmlspecialchars($line);
            $html .= '<div class="plain-line">' . $safeLine . '</div>';
        }
    }

    return $html;
}

// Dropdown-Daten
$ranks = $pdo->query("SELECT id, name FROM ranks ORDER BY name ASC")->fetchAll();
$users = $pdo->query("SELECT id, username FROM users ORDER BY username ASC")->fetchAll();
$canBroadcastTargets = hasPermission('can_broadcast_messages') && !empty($user['rank_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' && hasPermission('can_send_messages')) {
        $targetType = $_POST['target_type'] ?? ($canBroadcastTargets ? 'all' : 'user');
        $targetValue = null;
        $subject = trim($_POST['subject'] ?? '');
        $body = trim($_POST['body'] ?? '');

        if ($subject === '' || $body === '') {
            $error = 'Betreff und Inhalt dürfen nicht leer sein.';
        } else {
            if (!$canBroadcastTargets && in_array($targetType, ['all', 'role', 'rank'], true)) {
                $error = 'Broadcast-Ziele sind nur mit zugewiesenem Rang und Berechtigung möglich.';
            }

            if ($targetType === 'role') {
                $allowedRoles = ['admin', 'employee', 'partner'];
                $roleValue = $_POST['target_role'] ?? '';
                if (!in_array($roleValue, $allowedRoles, true)) {
                    $error = 'Ungültige Ziel-Rolle.';
                } else {
                    $targetValue = $roleValue;
                }
            } elseif ($targetType === 'rank') {
                $rankId = (int)($_POST['target_rank'] ?? 0);
                $targetValue = $rankId > 0 ? (string)$rankId : null;
                if (!$targetValue) {
                    $error = 'Bitte einen Rang auswählen.';
                }
            } elseif ($targetType === 'user') {
                $userId = (int)($_POST['target_user'] ?? 0);
                $targetValue = $userId > 0 ? (string)$userId : null;
                if (!$targetValue) {
                    $error = 'Bitte einen Benutzer auswählen.';
                }
            }

            if ($error === '') {
                createMessage($pdo, (int)$user['id'], $targetType, $targetValue, $subject, $body);
                $feedback = 'Nachricht wurde gesendet.';
            }
        }
    }

    if ($action === 'reply') {
        $messageId = (int)($_POST['message_id'] ?? 0);
        $subject = trim($_POST['subject'] ?? '');
        $body = trim($_POST['body'] ?? '');

        if ($messageId <= 0 || $subject === '' || $body === '') {
            $error = 'Bitte Betreff und Inhalt ausfüllen.';
        } else {
            $message = fetchMessageForUser($pdo, $user, $messageId);
            if (!$message) {
                $error = 'Nachricht nicht gefunden oder kein Zugriff.';
            } else {
                $targetType = null;
                $targetValue = null;

                if (!empty($message['sender_id']) && (int)$message['sender_id'] !== (int)$user['id']) {
                    $targetType = 'user';
                    $targetValue = (string)$message['sender_id'];
                } elseif ($message['target_type'] === 'user' && !empty($message['target_value'])) {
                    $targetType = 'user';
                    $targetValue = (string)$message['target_value'];
                }

                if ($targetType === 'user' && $targetValue !== null) {
                    createMessage($pdo, (int)$user['id'], $targetType, $targetValue, $subject, $body);
                    $feedback = 'Antwort wurde gesendet.';
                } else {
                    $error = 'Antworten sind nur auf direkte Nachrichten möglich.';
                }
            }
        }
    }

    if ($action === 'mark_read') {
        $messageId = (int)($_POST['message_id'] ?? 0);
        if ($messageId > 0) {
            $rankId = !empty($user['rank_id']) ? (int)$user['rank_id'] : -1;
            $check = $pdo->prepare("SELECT 1 FROM messages m WHERE m.id = :messageId AND m.deleted_at IS NULL AND (
                m.target_type = 'all'
                OR (m.target_type = 'role' AND m.target_value = :role)
                OR (m.target_type = 'rank' AND m.target_value = :rankId)
                OR (m.target_type = 'user' AND m.target_value = :userId)
            ) LIMIT 1");
            $check->execute([
                ':messageId' => $messageId,
                ':role' => $user['role'],
                ':rankId' => $rankId,
                ':userId' => $user['id'],
            ]);

            if ($check->fetchColumn()) {
                markMessageRead($pdo, $messageId, (int)$user['id']);
            }
        }
    }

    if ($action === 'delete_inbox') {
        $messageId = (int)($_POST['message_id'] ?? 0);
        if ($messageId > 0) {
            $message = fetchMessageForUser($pdo, $user, $messageId);
            if (!$message) {
                $error = 'Nachricht nicht gefunden oder kein Zugriff.';
            } else {
                $rankId = !empty($user['rank_id']) ? (int)$user['rank_id'] : -1;
                $isRecipient = $message['target_type'] === 'all'
                    || ($message['target_type'] === 'role' && $message['target_value'] === $user['role'])
                    || ($message['target_type'] === 'rank' && (int)$message['target_value'] === $rankId)
                    || ($message['target_type'] === 'user' && (int)$message['target_value'] === (int)$user['id']);

                if ($isRecipient) {
                    deleteMessageForUser($pdo, $messageId, (int)$user['id'], 'inbox');
                    $feedback = 'Nachricht wurde aus dem Posteingang entfernt.';
                } else {
                    $error = 'Nur empfangene Nachrichten können entfernt werden.';
                }
            }
        }
    }

    if ($action === 'delete_sent') {
        $messageId = (int)($_POST['message_id'] ?? 0);
        if ($messageId > 0) {
            $message = fetchMessageForUser($pdo, $user, $messageId);
            if (!$message) {
                $error = 'Nachricht nicht gefunden oder kein Zugriff.';
            } elseif ((int)$message['sender_id'] !== (int)$user['id']) {
                $error = 'Nur eigene gesendete Nachrichten können entfernt werden.';
            } else {
                deleteMessageForUser($pdo, $messageId, (int)$user['id'], 'sent');
                $feedback = 'Nachricht wurde aus dem Postausgang entfernt.';
            }
        }
    }
}

$messages = fetchInboxMessages($pdo, $user);
$readMap = fetchReadMap($pdo, (int)$user['id']);
$sentMessages = fetchSentMessages($pdo, (int)$user['id']);

renderHeader('Nachrichten', 'messages');
?>
<div class="page-header">
    <div>
        <p class="eyebrow">Nachrichten</p>
        <h2 style="margin: 4px 0 6px;">Deine Posteingänge im Blick</h2>
        <p class="muted">Rollen- und rangbasierte Ankündigungen sowie direkte Nachrichten an dein Konto.</p>
    </div>
    <div class="pill-row">
        <span class="pill">📥 Posteingang</span>
        <span class="pill">📤 Postausgang</span>
        <span class="pill">💬 Antworten</span>
    </div>
</div>

<?php if ($feedback): ?>
    <div class="banner success">
        <span class="banner-icon">✅</span>
        <div>
            <strong>Erfolg:</strong> <?= htmlspecialchars($feedback) ?>
        </div>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="banner error">
        <span class="banner-icon">⚠️</span>
        <div>
            <strong>Hinweis:</strong> <?= htmlspecialchars($error) ?>
        </div>
    </div>
<?php endif; ?>

<div class="message-layout">
    <?php if (hasPermission('can_send_messages')): ?>
        <form class="panel" method="post">
            <input type="hidden" name="action" value="create">
            <div class="panel__header">
                <div>
                    <p class="eyebrow">Neue Nachricht</p>
                    <h3>Verfassen & senden</h3>
                </div>
                <div class="chip">✉️</div>
            </div>
            <div class="grid grid-2">
                <div class="field-group">
                    <label for="subject">Betreff</label>
                    <input id="subject" name="subject" required placeholder="Worum geht es?">
                </div>
                <div class="field-group">
                    <label for="target_type">Ziel</label>
                    <select id="target_type" name="target_type" onchange="toggleTargetFields(this.value)">
                        <?php if ($canBroadcastTargets): ?>
                            <option value="all">Alle Benutzer</option>
                            <option value="role">Bestimmte Rolle</option>
                            <option value="rank">Bestimmter Rang</option>
                        <?php endif; ?>
                        <option value="user" <?= $canBroadcastTargets ? '' : 'selected' ?>>Direkte Nachricht</option>
                    </select>
                </div>
            </div>
            <div class="field-group" id="target_role_field" style="display:none;">
                <label for="target_role">Rolle wählen</label>
                <select id="target_role" name="target_role">
                    <option value="admin">Admin</option>
                    <option value="employee">Mitarbeiter</option>
                    <option value="partner">Partner</option>
                </select>
            </div>
            <div class="field-group" id="target_rank_field" style="display:none;">
                <label for="target_rank">Rang wählen</label>
                <select id="target_rank" name="target_rank">
                    <option value="">-- Rang wählen --</option>
                    <?php foreach ($ranks as $r): ?>
                        <option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field-group" id="target_user_field" style="display:none;">
                <label for="target_user">Benutzer wählen</label>
                <select id="target_user" name="target_user">
                    <option value="">-- Benutzer wählen --</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['username']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field-group">
                <label for="body">Nachricht</label>
                <div class="input-toolbar">
                    <button type="button" class="chip" onclick="toggleEmojiPalette('body')">😊 Emoji</button>
                    <span class="muted">HTML wird automatisch sicher formatiert.</span>
                </div>
                <textarea id="body" name="body" rows="4" required placeholder="Schreibe deine Nachricht..."></textarea>
                <div id="emoji-palette-body" class="emoji-palette">
                    <?php foreach (['😀','😊','👍','🎉','🚀','✅','❗','💡','🙌','🔥','🙏','👀'] as $emoji): ?>
                        <button type="button" onclick="addEmoji('body', '<?= $emoji ?>')"><?= $emoji ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="panel__footer">
                <div class="pill muted">Broadcast-Optionen erscheinen nur mit Rang & Recht.</div>
                <button class="btn btn-primary" type="submit">Senden</button>
            </div>
        </form>
    <?php endif; ?>

    <div class="stacked-panels">
        <div class="panel">
            <div class="panel__header">
                <div>
                    <p class="eyebrow">Posteingang</p>
                    <h3>Neueste Nachrichten</h3>
                </div>
                <div class="chip">📥</div>
            </div>
            <?php if (empty($messages)): ?>
                <p class="muted">Keine Nachrichten gefunden.</p>
            <?php else: ?>
                <div class="message-list">
                    <?php foreach ($messages as $message): ?>
                        <?php
                            $messageId = (int)$message['id'];
                            $replyId = 'reply-form-' . $messageId;
                            $replyBodyId = 'reply-body-' . $messageId;
                            $replySubject = strpos($message['subject'], 'Re: ') === 0 ? $message['subject'] : 'Re: ' . $message['subject'];
                            $quoted = buildQuotedBody($message);
                        ?>
                        <article id="message-card-<?= $messageId ?>" class="message-card <?= isset($readMap[$message['id']]) ? 'is-read' : 'is-unread' ?>" data-message-id="<?= $messageId ?>">
                            <button class="message-card__summary" type="button" onclick="toggleMessageCard(<?= $messageId ?>, true)" aria-expanded="false">
                                <div class="summary-main">
                                    <div class="eyebrow">von <?= htmlspecialchars($message['sender_name'] ?? 'System') ?></div>
                                    <h4><?= htmlspecialchars($message['subject']) ?></h4>
                                    <div class="message-meta">
                                        <span class="pill"><?= date('d.m.Y H:i', strtotime($message['created_at'])) ?></span>
                                        <span class="pill"><?= htmlspecialchars(messageTargetLabel($message)) ?></span>
                                    </div>
                                </div>
                                <div class="summary-status">
                                    <?php if (!isset($readMap[$message['id']])): ?>
                                        <span class="status-dot unread"></span>
                                        <span class="message-status-label">Neu</span>
                                    <?php else: ?>
                                        <span class="status-dot read"></span>
                                        <span class="message-status-label">Gelesen</span>
                                    <?php endif; ?>
                                    <span class="chevron">▾</span>
                                </div>
                            </button>
                            <div class="message-card__content" id="message-content-<?= $messageId ?>">
                                <div class="message-body"><?= renderMessageBody($message['body']) ?></div>
                                <div class="message-actions">
                                    <form method="post" onsubmit="return confirm('Nachricht aus dem Posteingang entfernen?');">
                                        <input type="hidden" name="action" value="delete_inbox">
                                        <input type="hidden" name="message_id" value="<?= $messageId ?>">
                                        <button class="btn btn-ghost" type="submit">Löschen</button>
                                    </form>
                                    <?php if (!empty($message['sender_id'])): ?>
                                        <button class="btn btn-ghost" type="button" onclick="toggleReply('<?= $replyId ?>')">Antworten</button>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($message['sender_id'])): ?>
                                    <form id="<?= $replyId ?>" class="reply-form" method="post" style="display:none;">
                                        <input type="hidden" name="action" value="reply">
                                        <input type="hidden" name="message_id" value="<?= $messageId ?>">
                                        <div class="field-group">
                                            <label>Betreff</label>
                                            <input name="subject" value="<?= htmlspecialchars($replySubject) ?>" required>
                                        </div>
        
                                        <div class="field-group">
                                            <label>Antwort</label>
                                            <div class="input-toolbar">
                                                <button type="button" class="chip" onclick="toggleEmojiPalette('<?= $replyBodyId ?>')">😊 Emoji</button>
                                                <span class="muted">Direkt an Absender antworten.</span>
                                            </div>
                                            <textarea id="<?= $replyBodyId ?>" name="body" rows="4" required placeholder="Schreibe deine Antwort..."><?= htmlspecialchars($quoted ? "\n\n{$quoted}" : '') ?></textarea>
                                            <div id="emoji-palette-<?= $replyBodyId ?>" class="emoji-palette">
                                                <?php foreach (['😀','😊','👍','🎉','🚀','✅','❗','💡','🙌','🔥','🙏','👀'] as $emoji): ?>
                                                    <button type="button" onclick="addEmoji('<?= $replyBodyId ?>', '<?= $emoji ?>')"><?= $emoji ?></button>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <div class="panel__footer">
                                            <span class="pill muted">Antwort geht an <?= htmlspecialchars($message['sender_name'] ?? 'den Absender') ?></span>
                                            <button class="btn btn-primary" type="submit">Antwort senden</button>
                                        </div>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="panel">
            <div class="panel__header">
                <div>
                    <p class="eyebrow">Postausgang</p>
                    <h3>Gesendete Nachrichten</h3>
                </div>
                <div class="chip">📤</div>
            </div>
            <?php if (empty($sentMessages)): ?>
                <p class="muted">Keine gesendeten Nachrichten.</p>
            <?php else: ?>
                <div class="message-list">
                    <?php foreach ($sentMessages as $message): ?>
                        <?php
                            $messageId = (int)$message['id'];
                            $replyId = 'reply-form-sent-' . $messageId;
                            $replySubject = strpos($message['subject'], 'Re: ') === 0 ? $message['subject'] : 'Re: ' . $message['subject'];
                            $replyBodyId = 'reply-body-sent-' . $messageId;
                            $quoted = buildQuotedBody($message);
                        ?>
                        <article id="message-card-sent-<?= $messageId ?>" class="message-card is-read" data-message-id="<?= $messageId ?>">
                            <button class="message-card__summary" type="button" onclick="toggleMessageCard(<?= $messageId ?>, false)" aria-expanded="false">
                                <div class="summary-main">
                                    <div class="eyebrow">gesendet</div>
                                    <h4><?= htmlspecialchars($message['subject']) ?></h4>
                                    <div class="message-meta">
                                        <span class="pill">gesendet am <?= date('d.m.Y H:i', strtotime($message['created_at'])) ?></span>
                                        <span class="pill"><?= htmlspecialchars(messageTargetLabel($message)) ?></span>
                                    </div>
                                </div>
                                <div class="summary-status">
                                    <span class="status-dot sent"></span>
                                    <span class="message-status-label">Gesendet</span>
                                    <span class="chevron">▾</span>
                                </div>
                            </button>
                            <div class="message-card__content" id="message-content-<?= $messageId ?>-sent">
                                <div class="message-body"><?= renderMessageBody($message['body']) ?></div>
                                <div class="message-actions">
                                    <form method="post" onsubmit="return confirm('Nachricht aus dem Postausgang entfernen?');">
                                        <input type="hidden" name="action" value="delete_sent">
                                        <input type="hidden" name="message_id" value="<?= $messageId ?>">
                                        <button class="btn btn-ghost" type="submit">Löschen</button>
                                    </form>
                                    <?php if ($message['target_type'] === 'user' && !empty($message['target_value'])): ?>
                                        <button class="btn btn-ghost" type="button" onclick="toggleReply('<?= $replyId ?>')">Antworten</button>
                                    <?php endif; ?>
                                </div>
                                <?php if ($message['target_type'] === 'user' && !empty($message['target_value'])): ?>
                                    <form id="<?= $replyId ?>" class="reply-form" method="post" style="display:none;">
                                        <input type="hidden" name="action" value="reply">
                                        <input type="hidden" name="message_id" value="<?= $messageId ?>">
                                        <div class="field-group">
                                            <label>Betreff</label>
                                            <input name="subject" value="<?= htmlspecialchars($replySubject) ?>" required>
                                        </div>
                                        <div class="field-group">
                                            <label>Antwort</label>
                                            <div class="input-toolbar">
                                                <button type="button" class="chip" onclick="toggleEmojiPalette('<?= $replyBodyId ?>')">😊 Emoji</button>
                                                <span class="muted">Direkte Antwort an den Empfänger.</span>
                                            </div>
                                            <textarea id="<?= $replyBodyId ?>" name="body" rows="4" required placeholder="Schreibe deine Antwort..."><?= htmlspecialchars($quoted ? "\n\n{$quoted}" : '') ?></textarea>
                                            <div id="emoji-palette-<?= $replyBodyId ?>" class="emoji-palette">
                                                <?php foreach (['😀','😊','👍','🎉','🚀','✅','❗','💡','🙌','🔥','🙏','👀'] as $emoji): ?>
                                                    <button type="button" onclick="addEmoji('<?= $replyBodyId ?>', '<?= $emoji ?>')"><?= $emoji ?></button>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <div class="panel__footer">
                                            <span class="pill muted">Antwort bleibt im selben Thread.</span>
                                            <button class="btn btn-primary" type="submit">Antwort senden</button>
                                        </div>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const messageReads = new Set(<?= json_encode(array_map('intval', array_keys($readMap))) ?>);

function toggleTargetFields(value) {
    document.getElementById('target_role_field').style.display = value === 'role' ? 'block' : 'none';
    document.getElementById('target_rank_field').style.display = value === 'rank' ? 'block' : 'none';
    document.getElementById('target_user_field').style.display = value === 'user' ? 'block' : 'none';
}

function toggleMessageCard(id, isInbox) {
    const card = document.getElementById(`message-card-${id}`) || document.getElementById(`message-card-sent-${id}`);
    const content = document.getElementById(`message-content-${id}`) || document.getElementById(`message-content-${id}-sent`);
    if (!card || !content) return;

    const willOpen = !card.classList.contains('open');
    card.classList.toggle('open');
    content.style.maxHeight = willOpen ? content.scrollHeight + 'px' : null;
    const summary = card.querySelector('.message-card__summary');
    if (summary) {
        summary.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    }

    if (willOpen && isInbox && !messageReads.has(id)) {
        markMessageAsRead(id, card);
    }
}

function markMessageAsRead(id, card) {
    const formData = new FormData();
    formData.append('action', 'mark_read');
    formData.append('message_id', id);

    fetch('messages.php', { method: 'POST', body: formData })
        .then(() => {
            messageReads.add(id);
            card.classList.remove('is-unread');
            card.classList.add('is-read');
            const label = card.querySelector('.message-status-label');
            if (label) {
                label.textContent = 'Gelesen';
            }
            const dot = card.querySelector('.status-dot');
            if (dot) {
                dot.classList.remove('unread');
                dot.classList.add('read');
            }
        })
        .catch(() => {});
}

function toggleReply(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.style.display = el.style.display === 'none' || el.style.display === '' ? 'block' : 'none';
}

function toggleEmojiPalette(fieldId) {
    const palette = document.getElementById('emoji-palette-' + fieldId);
    if (!palette) return;
    palette.classList.toggle('open');
}

function addEmoji(fieldId, emoji) {
    const textarea = document.getElementById(fieldId);
    if (!textarea) return;

    const start = textarea.selectionStart ?? textarea.value.length;
    const end = textarea.selectionEnd ?? textarea.value.length;
    const before = textarea.value.slice(0, start);
    const after = textarea.value.slice(end);
    textarea.value = before + emoji + after;
    const cursor = start + emoji.length;
    textarea.focus();
    textarea.setSelectionRange(cursor, cursor);
}

document.addEventListener('DOMContentLoaded', function() {
    const select = document.getElementById('target_type');
    if (select) {
        toggleTargetFields(select.value);
    }
});
</script>
<?php
renderFooter();