<?php
require_once __DIR__ . '/../auth/check_role.php';
requirePermission('can_access_admin');
requirePermission('can_view_tickets');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/live_support.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo "Ticket-ID fehlt.";
    exit;
}

// Ticket laden
$stmt = $pdo->prepare("SELECT t.*, c.name AS category_name, u.username AS creator_name, g.guest_name, g.guest_email, a.username AS assignee_name
                       FROM tickets t
                       LEFT JOIN ticket_categories c ON t.category_id = c.id
                       LEFT JOIN users u ON t.created_by = u.id
                       LEFT JOIN guest_tickets g ON t.id = g.ticket_id
                       LEFT JOIN users a ON t.assigned_to = a.id
                       WHERE t.id = ?");
$stmt->execute([$id]);
$ticket = $stmt->fetch();

if (!$ticket) {
    echo "Ticket nicht gefunden.";
    exit;
}

ensureLiveSupportTables($pdo);

// Sichtbarkeits-Check: ohne Bearbeitungsrecht nur eigenes Ticket
if (!hasPermission('can_edit_tickets')) {
    if (!isset($_SESSION['user']['id']) || (int)$ticket['created_by'] !== (int)$_SESSION['user']['id']) {
        http_response_code(403);
        echo "Keine Berechtigung für dieses Ticket.";
        exit;
    }
}

// Kommentare
$comStmt = $pdo->prepare("SELECT tc.*, u.username FROM ticket_comments tc
                          LEFT JOIN users u ON tc.user_id = u.id
                          WHERE tc.ticket_id = ?
                          ORDER BY tc.created_at ASC");
$comStmt->execute([$id]);
$comments = $comStmt->fetchAll();

// Anhänge
$attStmt = $pdo->prepare("SELECT ta.*, u.username FROM ticket_attachments ta
                          LEFT JOIN users u ON ta.user_id = u.id
                          WHERE ta.ticket_id = ?
                          ORDER BY ta.created_at ASC");
$attStmt->execute([$id]);
$attachments = $attStmt->fetchAll();

// Log
$logStmt = $pdo->prepare("SELECT tl.*, u.username FROM ticket_logs tl
                          LEFT JOIN users u ON tl.user_id = u.id
                          WHERE tl.ticket_id = ?
                          ORDER BY tl.created_at ASC");
$logStmt->execute([$id]);
$logs = $logStmt->fetchAll();

$liveSupportRequest = latestLiveSupportRequest($pdo, $id);
$liveSupportHistory = liveSupportHistory($pdo, $id, 5);
$liveSupportError = "";
$liveSupportSuccess = "";

// Mitarbeiterliste für Zuweisung (Admins + Mitarbeiter)
$userStmt = $pdo->query("SELECT id, username, role FROM users WHERE role IN ('admin','employee') ORDER BY username ASC");
$assignableUsers = $userStmt->fetchAll();

// Aktionen verarbeiten
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (hasPermission('can_handle_live_support')) {
        if (isset($_POST['live_accept'])) {
            if ($liveSupportRequest) {
                $note = trim($_POST['live_note'] ?? '');
                $upd = $pdo->prepare("UPDATE live_support_requests SET status = 'accepted', assigned_to = ?, scheduled_for = NULL, note = ? WHERE id = ?");
                $upd->execute([$_SESSION['user']['id'] ?? null, $note, $liveSupportRequest['id']]);

                $details = 'Live-Co-Browsing angenommen';
                if ($note !== '') {
                    $details .= ' – Hinweis: ' . $note;
                }
                logLiveSupportAction($pdo, $id, $_SESSION['user']['id'] ?? null, $details);
                $liveSupportSuccess = 'Anfrage angenommen – bitte Live-Unterstützung starten.';
            } else {
                $liveSupportError = 'Keine Live-Co-Browsing-Anfrage für dieses Ticket vorhanden.';
            }
        } elseif (isset($_POST['live_schedule'])) {
            if ($liveSupportRequest) {
                $note = trim($_POST['live_note'] ?? '');
                $rawDate = trim($_POST['scheduled_for'] ?? '');
                $dt = $rawDate !== '' ? DateTime::createFromFormat('Y-m-d\TH:i', $rawDate) : false;
                if (!$dt) {
                    $liveSupportError = 'Bitte einen gültigen Termin auswählen.';
                } else {
                    $scheduledFor = $dt->format('Y-m-d H:i:s');
                    $upd = $pdo->prepare("UPDATE live_support_requests SET status = 'scheduled', assigned_to = ?, scheduled_for = ?, note = ? WHERE id = ?");
                    $upd->execute([$_SESSION['user']['id'] ?? null, $scheduledFor, $note, $liveSupportRequest['id']]);

                    $details = 'Live-Co-Browsing terminiert auf ' . $scheduledFor;
                    if ($note !== '') {
                        $details .= ' – Hinweis: ' . $note;
                    }
                    logLiveSupportAction($pdo, $id, $_SESSION['user']['id'] ?? null, $details);
                    $liveSupportSuccess = 'Termin gespeichert und Kunde wird im Ticket informiert.';
                }
            } else {
                $liveSupportError = 'Keine Live-Co-Browsing-Anfrage für dieses Ticket vorhanden.';
            }
        } elseif (isset($_POST['live_decline'])) {
            if ($liveSupportRequest) {
                $note = trim($_POST['live_note'] ?? '');
                $upd = $pdo->prepare("UPDATE live_support_requests SET status = 'declined', assigned_to = ?, scheduled_for = NULL, note = ? WHERE id = ?");
                $upd->execute([$_SESSION['user']['id'] ?? null, $note, $liveSupportRequest['id']]);

                $details = 'Live-Co-Browsing abgelehnt';
                if ($note !== '') {
                    $details .= ' – Hinweis: ' . $note;
                }
                logLiveSupportAction($pdo, $id, $_SESSION['user']['id'] ?? null, $details);
                $liveSupportSuccess = 'Anfrage wurde abgelehnt.';
            } else {
                $liveSupportError = 'Keine Live-Co-Browsing-Anfrage für dieses Ticket vorhanden.';
            }
        } elseif (isset($_POST['live_complete'])) {
            if ($liveSupportRequest) {
                $note = trim($_POST['live_note'] ?? '');
                $upd = $pdo->prepare("UPDATE live_support_requests SET status = 'completed', assigned_to = COALESCE(assigned_to, ?), note = ? WHERE id = ?");
                $upd->execute([$_SESSION['user']['id'] ?? null, $note, $liveSupportRequest['id']]);

                $details = 'Live-Co-Browsing abgeschlossen';
                if ($note !== '') {
                    $details .= ' – Hinweis: ' . $note;
                }
                logLiveSupportAction($pdo, $id, $_SESSION['user']['id'] ?? null, $details);
                $liveSupportSuccess = 'Live-Co-Browsing abgeschlossen. Für neue Hilfe bitte neue Anfrage starten.';
            } else {
                $liveSupportError = 'Keine Live-Co-Browsing-Anfrage für dieses Ticket vorhanden.';
            }
        }

        if ($liveSupportError === '') {
            $liveSupportRequest = latestLiveSupportRequest($pdo, $id);
            $liveSupportHistory = liveSupportHistory($pdo, $id, 5);
        }
    }

    // Statuswechsel
    if (isset($_POST['change_status']) && hasPermission('can_edit_tickets')) {
        $newStatus = $_POST['status'] ?? $ticket['status'];
        $valid = ['open','in_progress','waiting','closed'];
        if (in_array($newStatus, $valid, true) && $newStatus !== $ticket['status']) {
            $up = $pdo->prepare("UPDATE tickets SET status = ? WHERE id = ?");
            $up->execute([$newStatus, $id]);
            $log = $pdo->prepare("INSERT INTO ticket_logs (ticket_id, user_id, action, details) VALUES (?,?,?,?)");
            $log->execute([$id, $_SESSION['user']['id'] ?? null, 'status_change', 'Status: ' . $ticket['status'] . ' → ' . $newStatus]);
            $ticket['status'] = $newStatus;
        }
    }

    // Zuweisung ändern
    if (isset($_POST['assign']) && hasPermission('can_edit_tickets')) {
        $assignee = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
        $up = $pdo->prepare("UPDATE tickets SET assigned_to = ? WHERE id = ?");
        $up->execute([$assignee, $id]);

        $name = 'niemand';
        if ($assignee) {
            foreach ($assignableUsers as $u) {
                if ($u['id'] == $assignee) {
                    $name = $u['username'];
                    break;
                }
            }
        }
        $log = $pdo->prepare("INSERT INTO ticket_logs (ticket_id, user_id, action, details) VALUES (?,?,?,?)");
        $log->execute([$id, $_SESSION['user']['id'] ?? null, 'assign', 'Zugewiesen an: ' . $name]);

        $ticket['assigned_to'] = $assignee;
        $ticket['assignee_name'] = $name;
    }

    // Kommentar hinzufügen
    if (isset($_POST['add_comment']) && hasPermission('can_edit_tickets')) {
        $msg = trim($_POST['comment'] ?? '');
        if ($msg !== '') {
            $ins = $pdo->prepare("INSERT INTO ticket_comments (ticket_id, user_id, message) VALUES (?,?,?)");
            $ins->execute([$id, $_SESSION['user']['id'] ?? 0, $msg]);

            $log = $pdo->prepare("INSERT INTO ticket_logs (ticket_id, user_id, action, details) VALUES (?,?,?,?)");
            $log->execute([$id, $_SESSION['user']['id'] ?? null, 'comment', 'Kommentar hinzugefügt']);
        }
        header("Location: /admin/ticket_view.php?id=" . $id);
        exit;
    }

    // Datei hochladen (nur intern, Gäste haben keinen Zugang)
    if (isset($_POST['upload_file']) && hasPermission('can_upload_files')) {
        if (!empty($_FILES['attachment']['name'])) {
            $uploadDir = __DIR__ . '/../uploads/tickets';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0775, true);
            }
            $originalName = $_FILES['attachment']['name'];
            $ext = pathinfo($originalName, PATHINFO_EXTENSION);
            $storedName = 't' . $id . '_' . time() . '_' . bin2hex(random_bytes(4)) . ($ext ? '.' . $ext : '');
            $target = $uploadDir . '/' . $storedName;
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target)) {
                $insA = $pdo->prepare("INSERT INTO ticket_attachments (ticket_id, user_id, original_name, stored_name) VALUES (?,?,?,?)");
                $insA->execute([$id, $_SESSION['user']['id'] ?? 0, $originalName, $storedName]);

                $log = $pdo->prepare("INSERT INTO ticket_logs (ticket_id, user_id, action, details) VALUES (?,?,?,?)");
                $log->execute([$id, $_SESSION['user']['id'] ?? null, 'attachment', 'Datei hochgeladen: ' . $originalName]);
            } else {
                $error = "Upload fehlgeschlagen.";
            }
        }
    }

    // Ticket löschen
    if (isset($_POST['delete_ticket']) && hasPermission('can_delete_tickets')) {
        // zugehörige Einträge löschen
        $pdo->prepare("DELETE FROM ticket_comments WHERE ticket_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM ticket_logs WHERE ticket_id = ?")->execute([$id]);
        $attStmt = $pdo->prepare("SELECT stored_name FROM ticket_attachments WHERE ticket_id = ?");
        $attStmt->execute([$id]);
        $allA = $attStmt->fetchAll();
        $uploadDir = __DIR__ . '/../uploads/tickets';
        foreach ($allA as $a) {
            $file = $uploadDir . '/' . $a['stored_name'];
            if (is_file($file)) {
                @unlink($file);
            }
        }
        $pdo->prepare("DELETE FROM ticket_attachments WHERE ticket_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM tickets WHERE id = ?")->execute([$id]);

        header("Location: /admin/tickets.php");
        exit;
    }
}

renderHeader('Ticket anzeigen', 'admin');
?>
<div class="card">
    <h2>Ticket #<?= (int)$ticket['id'] ?> &mdash; <?= htmlspecialchars($ticket['title']) ?></h2>
    <p class="muted">
        Status:
        <span class="ticket-status-<?= htmlspecialchars($ticket['status']) ?>">
            <?= htmlspecialchars($ticket['status']) ?>
        </span>
        &nbsp;· Priorität:
        <span class="ticket-priority-<?= htmlspecialchars($ticket['priority']) ?>">
            <?= htmlspecialchars($ticket['priority']) ?>
        </span>
        &nbsp;· Kategorie:
        <?= htmlspecialchars($ticket['category_name'] ?? '–') ?>
    </p>
    <p class="muted">
        <?php
            $creator = $ticket['creator_name'] ?? '';
            if ($creator === '' || $creator === null) {
                $creator = $ticket['guest_name'] ? 'Gast: ' . $ticket['guest_name'] : 'Gast';
            }
        ?>
        Erstellt von <?= htmlspecialchars($creator) ?>
        am <?= htmlspecialchars($ticket['created_at']) ?>,
        zuletzt aktualisiert <?= htmlspecialchars($ticket['updated_at']) ?>.
    </p>

    <h3>Beschreibung</h3>
    <p><?= nl2br(htmlspecialchars($ticket['description'])) ?></p>

    <h3>Live-Co-Browsing</h3>
    <p class="muted">Live-Unterstützung ohne Fernsteuerung: Anfragen annehmen, terminieren oder abschließen.</p>
    <?php if ($liveSupportError): ?>
        <div class="error"><?= htmlspecialchars($liveSupportError) ?></div>
    <?php endif; ?>
    <?php if ($liveSupportSuccess): ?>
        <div class="success"><?= htmlspecialchars($liveSupportSuccess) ?></div>
    <?php endif; ?>
    <?php if ($liveSupportRequest): ?>
        <div style="margin:10px 0;padding:10px;border-radius:10px;border:1px solid rgba(148,163,184,0.4);background:rgba(15,23,42,0.5);">
            <strong>Status:</strong>
            <span class="ticket-status-<?= htmlspecialchars($liveSupportRequest['status']) ?>">
                <?= htmlspecialchars($liveSupportRequest['status']) ?>
            </span>
            <div class="muted" style="margin-top:4px;">
                Kunde: <?= htmlspecialchars($liveSupportRequest['requester_name'] ?? 'Unbekannt') ?>
                <?php if (!empty($liveSupportRequest['assignee_name'])): ?>
                    · Bearbeiter: <?= htmlspecialchars($liveSupportRequest['assignee_name']) ?>
                <?php endif; ?>
            </div>
            <?php if (!empty($liveSupportRequest['scheduled_for'])): ?>
                <div class="muted">Termin: <?= htmlspecialchars(date('d.m.Y H:i', strtotime($liveSupportRequest['scheduled_for']))) ?></div>
            <?php endif; ?>
            <?php if (!empty($liveSupportRequest['note'])): ?>
                <div class="muted">Hinweis: <?= nl2br(htmlspecialchars($liveSupportRequest['note'])) ?></div>
            <?php endif; ?>
            <div class="muted">Zuletzt aktualisiert: <?= htmlspecialchars($liveSupportRequest['updated_at']) ?></div>
        </div>
    <?php else: ?>
        <p class="muted">Noch keine Live-Co-Browsing-Anfrage für dieses Ticket.</p>
    <?php endif; ?>

    <?php if (hasPermission('can_handle_live_support')): ?>
        <form method="post" class="field-group" style="margin-bottom:16px;">
            <label for="scheduled_for">Termin (für Verschieben)</label>
            <input type="datetime-local" id="scheduled_for" name="scheduled_for">
            <label for="live_note">Notiz an Kunden (optional)</label>
            <textarea id="live_note" name="live_note" rows="2"></textarea>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;">
                <button class="btn btn-primary" type="submit" name="live_accept" value="1">Annehmen</button>
                <button class="btn btn-secondary" type="submit" name="live_schedule" value="1">Auf später verschieben</button>
                <button class="btn btn-danger" type="submit" name="live_decline" value="1">Ablehnen</button>
                <button class="btn btn-secondary" type="submit" name="live_complete" value="1">Support beenden</button>
            </div>
        </form>
    <?php endif; ?>

    <?php if ($liveSupportHistory): ?>
        <h4>Letzte Live-Co-Browsing-Aktivitäten</h4>
        <ul>
            <?php foreach ($liveSupportHistory as $ls): ?>
                <li class="muted" style="font-size:0.85rem;">
                    <?= htmlspecialchars($ls['updated_at']) ?> · Status <?= htmlspecialchars($ls['status']) ?>
                    <?php if (!empty($ls['assignee_name'])): ?>
                        · Bearbeiter: <?= htmlspecialchars($ls['assignee_name']) ?>
                    <?php endif; ?>
                    <?php if (!empty($ls['note'])): ?>
                        · <?= htmlspecialchars(mb_strimwidth($ls['note'], 0, 80, '…')) ?>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if (hasPermission('can_edit_tickets')): ?>
        <h3>Ticket-Einstellungen</h3>
        <form method="post" style="margin-bottom:14px;">
            <div class="field-group">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="open" <?= $ticket['status']==='open'?'selected':'' ?>>Offen</option>
                    <option value="in_progress" <?= $ticket['status']==='in_progress'?'selected':'' ?>>In Bearbeitung</option>
                    <option value="waiting" <?= $ticket['status']==='waiting'?'selected':'' ?>>Warten auf Kunde</option>
                    <option value="closed" <?= $ticket['status']==='closed'?'selected':'' ?>>Erledigt</option>
                </select>
            </div>
            <button class="btn btn-secondary" type="submit" name="change_status" value="1">Status aktualisieren</button>
        </form>

        <form method="post" style="margin-bottom:14px;">
            <div class="field-group">
                <label for="assigned_to">Zugewiesen an</label>
                <select id="assigned_to" name="assigned_to">
                    <option value="">Niemand</option>
                    <?php foreach ($assignableUsers as $u): ?>
                        <option value="<?= (int)$u['id'] ?>" <?= $ticket['assigned_to']==$u['id']?'selected':'' ?>>
                            <?= htmlspecialchars($u['username']) ?> (<?= htmlspecialchars($u['role']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-secondary" type="submit" name="assign" value="1">Zuweisung speichern</button>
        </form>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (hasPermission('can_upload_files')): ?>
        <h3>Dateianhänge</h3>
        <ul>
            <?php foreach ($attachments as $a): ?>
                <li>
                    <?= htmlspecialchars($a['original_name']) ?>
                    <span class="muted">
                        (von <?= htmlspecialchars($a['username'] ?? '–') ?>,
                        <?= htmlspecialchars($a['created_at']) ?>)
                    </span>
                    <?php
                        $url = '/uploads/tickets/' . rawurlencode($a['stored_name']);
                    ?>
                    &mdash; <a class="btn-link" href="<?= $url ?>" target="_blank">Download</a>
                </li>
            <?php endforeach; ?>
            <?php if (!$attachments): ?>
                <li class="muted">Keine Anhänge vorhanden.</li>
            <?php endif; ?>
        </ul>
        <form method="post" enctype="multipart/form-data">
            <div class="field-group">
                <label for="attachment">Neue Datei hochladen</label>
                <input type="file" id="attachment" name="attachment">
            </div>
            <button class="btn btn-secondary" type="submit" name="upload_file" value="1">Upload</button>
        </form>
    <?php endif; ?>

    <h3>Kommentare</h3>
    <div>
        <?php foreach ($comments as $c): ?>
            <div style="margin-bottom:10px;padding:8px;border-radius:12px;border:1px solid rgba(148,163,184,0.5);background:rgba(15,23,42,0.8);">
                <div style="font-size:0.8rem;" class="muted">
                    <?= htmlspecialchars($c['username'] ?? '–') ?> · <?= htmlspecialchars($c['created_at']) ?>
                </div>
                <div><?= nl2br(htmlspecialchars($c['message'])) ?></div>
            </div>
        <?php endforeach; ?>
        <?php if (!$comments): ?>
            <p class="muted">Noch keine Kommentare.</p>
        <?php endif; ?>
    </div>

    <?php if (hasPermission('can_edit_tickets')): ?>
        <form method="post">
            <div class="field-group">
                <label for="comment">Kommentar hinzufügen</label>
                <textarea id="comment" name="comment" rows="3"></textarea>
            </div>
            <button class="btn btn-primary" type="submit" name="add_comment" value="1">Kommentar speichern</button>
        </form>
    <?php endif; ?>

    <?php if (hasPermission('can_delete_tickets')): ?>
        <form method="post" onsubmit="return confirm('Ticket wirklich löschen?');" style="margin-top:18px;">
            <button class="btn btn-danger" type="submit" name="delete_ticket" value="1">Ticket löschen</button>
        </form>
    <?php endif; ?>

    <h3>Aktivitätsprotokoll</h3>
    <ul>
        <?php foreach ($logs as $l): ?>
            <li class="muted" style="font-size:0.8rem;">
                <?= htmlspecialchars($l['created_at']) ?> ·
                <?= htmlspecialchars($l['username'] ?? 'System') ?> ·
                <?= htmlspecialchars($l['action']) ?>:
                <?= htmlspecialchars($l['details']) ?>
            </li>
        <?php endforeach; ?>
        <?php if (!$logs): ?>
            <li class="muted">Noch keine Aktivitäten protokolliert.</li>
        <?php endif; ?>
    </ul>

    <p style="margin-top:14px;">
        <a class="btn btn-secondary" href="/admin/tickets.php">Zurück zur Übersicht</a>
    </p>
</div>
<?php
renderFooter();
