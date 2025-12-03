<?php
require_once __DIR__ . '/../auth/check_role.php';
requirePermission('can_access_admin');
requirePermission('can_send_messages');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/message_service.php';
require_once __DIR__ . '/../includes/layout.php';

ensureMessageTables($pdo);

$feedback = '';
$error = '';
$ranks = $pdo->query("SELECT id, name FROM ranks ORDER BY name ASC")->fetchAll();
$users = $pdo->query("SELECT id, username FROM users ORDER BY username ASC")->fetchAll();
$stats = messageStats($pdo);
$currentUser = $_SESSION['user'] ?? [];
$canBroadcastTargets = hasPermission('can_broadcast_messages') && !empty($currentUser['rank_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
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
                createMessage($pdo, (int)$_SESSION['user']['id'], $targetType, $targetValue, $subject, $body);
                $feedback = 'Nachricht wurde gesendet.';
                $stats = messageStats($pdo);
            }
        }
    }

    if ($action === 'delete' && hasPermission('can_moderate_messages')) {
        $messageId = (int)($_POST['message_id'] ?? 0);
        if ($messageId > 0) {
            deleteMessage($pdo, $messageId);
            $feedback = 'Nachricht wurde entfernt.';
            $stats = messageStats($pdo);
        }
    }

    if ($action === 'moderate' && hasPermission('can_moderate_messages')) {
        $messageId = (int)($_POST['message_id'] ?? 0);
        $flag = isset($_POST['flag']) && $_POST['flag'] === '1';
        if ($messageId > 0) {
            moderateMessage($pdo, $messageId, $flag);
            $feedback = $flag ? 'Nachricht wurde markiert.' : 'Moderationsflag wurde entfernt.';
        }
    }
}

$messages = fetchAllMessages($pdo);

renderHeader('Nachrichtensystem', 'admin');
?>
<div class="card">
    <h2>Nachrichtensystem</h2>
    <p class="muted">Rollen- und rangbasierte Mitteilungen mit Moderation und Zielgruppensteuerung.</p>

    <div class="grid grid-3" style="margin:14px 0;">
        <div class="stat">
            <div class="stat-label">Gesendete Nachrichten</div>
            <div class="stat-value"><?= (int)$stats['total'] ?></div>
            <div class="stat-sub">Aktive Einträge</div>
        </div>
        <div class="stat">
            <div class="stat-label">Letzter Betreff</div>
            <div class="stat-value" style="font-size:1rem;"><?= $stats['last_subject'] ? htmlspecialchars($stats['last_subject']) : '—' ?></div>
        </div>
        <div class="stat">
            <div class="stat-label">Zuletzt aktualisiert</div>
            <div class="stat-value" style="font-size:1rem;"><?= $stats['last_created_at'] ? date('d.m.Y H:i', strtotime($stats['last_created_at'])) : '—' ?></div>
        </div>
    </div>

    <?php if ($feedback): ?>
        <div class="success"><?= htmlspecialchars($feedback) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form class="card" method="post" style="margin-top:12px;">
        <input type="hidden" name="action" value="create">
        <h3>Neue Nachricht verfassen</h3>
        <div class="grid grid-2">
            <div class="field-group">
                <label for="subject">Betreff</label>
                <input id="subject" name="subject" required>
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
            <textarea id="body" name="body" rows="4" required></textarea>
        </div>
        <button class="btn btn-primary" type="submit">Senden</button>
    </form>

    <div class="card" style="margin-top:16px;">
        <h3>Alle Nachrichten</h3>
        <?php if (empty($messages)): ?>
            <p class="muted">Noch keine Einträge vorhanden.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Betreff</th>
                        <th>Ziel</th>
                        <th>Absender</th>
                        <th>Datum</th>
                        <th>Moderation</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($messages as $msg): ?>
                        <tr>
                            <td><?= (int)$msg['id'] ?></td>
                            <td><?= htmlspecialchars($msg['subject']) ?></td>
                            <td><?= htmlspecialchars(messageTargetLabel($msg)) ?></td>
                            <td><?= htmlspecialchars($msg['sender_name'] ?? 'System') ?></td>
                            <td><?= date('d.m.Y H:i', strtotime($msg['created_at'])) ?></td>
                            <td><?= !empty($msg['moderated']) ? 'Markiert' : 'OK' ?></td>
                            <td>
                                <?php if (hasPermission('can_moderate_messages')): ?>
                                    <form method="post" style="display:inline-block;">
                                        <input type="hidden" name="action" value="moderate">
                                        <input type="hidden" name="message_id" value="<?= (int)$msg['id'] ?>">
                                        <input type="hidden" name="flag" value="<?= !empty($msg['moderated']) ? '0' : '1' ?>">
                                        <button class="btn btn-secondary" type="submit"><?= !empty($msg['moderated']) ? 'Moderation entfernen' : 'Markieren' ?></button>
                                    </form>
                                    <form method="post" style="display:inline-block; margin-left:6px;" onsubmit="return confirm('Nachricht wirklich löschen?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="message_id" value="<?= (int)$msg['id'] ?>">
                                        <button class="btn btn-danger" type="submit">Löschen</button>
                                    </form>
                                <?php else: ?>
                                    <span class="muted">Keine Rechte</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleTargetFields(value) {
    document.getElementById('target_role_field').style.display = value === 'role' ? 'block' : 'none';
    document.getElementById('target_rank_field').style.display = value === 'rank' ? 'block' : 'none';
    document.getElementById('target_user_field').style.display = value === 'user' ? 'block' : 'none';
}

document.addEventListener('DOMContentLoaded', function() {
    const select = document.getElementById('target_type');
    toggleTargetFields(select.value);
});
</script>
<?php
renderFooter();
