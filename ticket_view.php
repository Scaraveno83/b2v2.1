<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/auth/check_role.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/live_support.php';

checkRole(['admin','employee','partner']);
requireAbsenceAccess('tickets');

// Tabellen sicherstellen (falls Adminbereich noch nicht aufgerufen wurde)
$pdo->exec("CREATE TABLE IF NOT EXISTS ticket_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ticket (ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$pdo->exec("CREATE TABLE IF NOT EXISTS ticket_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT NULL,
    action VARCHAR(50) NOT NULL,
    details TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ticket (ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

ensureLiveSupportTables($pdo);

$ticketId = (int)($_GET['id'] ?? 0);
if ($ticketId <= 0) {
    echo "Ticket-ID fehlt.";
    exit;
}

// Ticket laden und Besitz prüfen
$stmt = $pdo->prepare("SELECT t.*, c.name AS category_name, a.username AS assignee_name
                       FROM tickets t
                       LEFT JOIN ticket_categories c ON t.category_id = c.id
                       LEFT JOIN users a ON t.assigned_to = a.id
                       WHERE t.id = ?");
$stmt->execute([$ticketId]);
$ticket = $stmt->fetch();

if (!$ticket) {
    echo "Ticket nicht gefunden.";
    exit;
}

if ((int)($ticket['created_by'] ?? 0) !== (int)($_SESSION['user']['id'] ?? -1)) {
    http_response_code(403);
    echo "Keine Berechtigung für dieses Ticket.";
    exit;
}

// Kommentare laden
$comStmt = $pdo->prepare("SELECT tc.*, u.username FROM ticket_comments tc
                          LEFT JOIN users u ON tc.user_id = u.id
                          WHERE tc.ticket_id = ?
                          ORDER BY tc.created_at ASC");
$comStmt->execute([$ticketId]);
$comments = $comStmt->fetchAll();

$liveSupportRequest = latestLiveSupportRequest($pdo, $ticketId);
$liveSupportError = "";
$liveSupportSuccess = "";

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['request_live_support'])) {
        if (isLiveSupportActive($liveSupportRequest)) {
            $liveSupportError = "Es läuft bereits eine Live-Co-Browsing-Anfrage zu diesem Ticket.";
        } else {
            $ins = $pdo->prepare("INSERT INTO live_support_requests (ticket_id, requested_by, status) VALUES (?,?, 'pending')");
            $ins->execute([$ticketId, $_SESSION['user']['id'] ?? 0]);

            logLiveSupportAction($pdo, $ticketId, $_SESSION['user']['id'] ?? null, 'Live-Co-Browsing angefragt');
            $liveSupportRequest = latestLiveSupportRequest($pdo, $ticketId);
            $liveSupportSuccess = "Deine Anfrage wurde übermittelt. Ein Support-Mitarbeiter meldet sich gleich.";
        }
    }

    if (isset($_POST['add_comment'])) {
        $msg = trim($_POST['comment'] ?? '');
        if ($msg === '') {
            $error = "Kommentar darf nicht leer sein.";
        } else {
            $ins = $pdo->prepare("INSERT INTO ticket_comments (ticket_id, user_id, message) VALUES (?,?,?)");
            $ins->execute([$ticketId, $_SESSION['user']['id'] ?? 0, $msg]);

            $pdo->prepare("UPDATE tickets SET updated_at = NOW() WHERE id = ?")->execute([$ticketId]);

            $log = $pdo->prepare("INSERT INTO ticket_logs (ticket_id, user_id, action, details) VALUES (?,?,?,?)");
            $log->execute([$ticketId, $_SESSION['user']['id'] ?? null, 'comment_user', 'Kommentar vom Ticket-Ersteller']);

            header("Location: /ticket_view.php?id=" . $ticketId);
            exit;
        }
    }
}

renderHeader('Ticket ansehen', 'my_tickets');
?>
<div class="card">
    <h2>Ticket #<?= (int)$ticket['id'] ?> — <?= htmlspecialchars($ticket['title']) ?></h2>
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
        Erstellt am <?= htmlspecialchars($ticket['created_at']) ?>,
        zuletzt aktualisiert <?= htmlspecialchars($ticket['updated_at']) ?>.
        <?php if ($ticket['assignee_name']): ?>
            · Bearbeiter: <?= htmlspecialchars($ticket['assignee_name']) ?>
        <?php endif; ?>
    </p>

    <h3>Beschreibung</h3>
    <p><?= nl2br(htmlspecialchars($ticket['description'])) ?></p>

    <h3>Live-Co-Browsing</h3>
    <p class="muted">
        Lass dir live helfen: Wir sehen deine Klicks, übernehmen aber nicht die Steuerung.
        Du kannst jederzeit eine neue Session anfragen, wenn keine aktive Verbindung besteht.
    </p>
    <?php if ($liveSupportError): ?>
        <div class="error"><?= htmlspecialchars($liveSupportError) ?></div>
    <?php endif; ?>
    <?php if ($liveSupportSuccess): ?>
        <div class="success"><?= htmlspecialchars($liveSupportSuccess) ?></div>
    <?php endif; ?>
    <?php if ($liveSupportRequest): ?>
        <?php
            $statusLabels = [
                'pending'   => 'wartet auf Support',
                'accepted'  => 'angenommen – bitte auf die Live-Unterstützung warten',
                'scheduled' => 'terminiert',
                'declined'  => 'abgelehnt',
                'completed' => 'abgeschlossen',
            ];
            $statusText = $statusLabels[$liveSupportRequest['status']] ?? $liveSupportRequest['status'];
        ?>
        <div style="margin:10px 0;padding:10px;border-radius:10px;border:1px solid rgba(148,163,184,0.4);background:rgba(15,23,42,0.5);">
            <strong>Status:</strong>
            <span class="ticket-status-<?= htmlspecialchars($liveSupportRequest['status']) ?>">
                <?= htmlspecialchars($liveSupportRequest['status']) ?>
            </span>
            <div class="muted" style="margin-top:4px;">
                <?= htmlspecialchars($statusText) ?>
                <?php if (!empty($liveSupportRequest['assignee_name'])): ?>
                    · Betreuer: <?= htmlspecialchars($liveSupportRequest['assignee_name']) ?>
                <?php endif; ?>
            </div>
            <?php if (!empty($liveSupportRequest['scheduled_for'])): ?>
                <div class="muted">Termin: <?= htmlspecialchars(date('d.m.Y H:i', strtotime($liveSupportRequest['scheduled_for']))) ?></div>
            <?php endif; ?>
            <?php if (!empty($liveSupportRequest['note'])): ?>
                <div class="muted">Hinweis: <?= nl2br(htmlspecialchars($liveSupportRequest['note'])) ?></div>
            <?php endif; ?>
            <?php if (!isLiveSupportActive($liveSupportRequest)): ?>
                <div class="muted" style="margin-top:6px;">Sobald du wieder Hilfe benötigst, kannst du eine neue Live-Co-Browsing-Anfrage senden.</div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if (!$liveSupportRequest || !isLiveSupportActive($liveSupportRequest)): ?>
        <form method="post" style="margin:8px 0 14px 0;">
            <button class="btn btn-primary" type="submit" name="request_live_support" value="1">
                Live-Co-Browsing anfragen
            </button>
        </form>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
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

    <form method="post">
        <div class="field-group">
            <label for="comment">Antwort hinzufügen</label>
            <textarea id="comment" name="comment" rows="3" required></textarea>
        </div>
        <button class="btn btn-primary" type="submit" name="add_comment" value="1">Kommentar speichern</button>
    </form>

    <p style="margin-top:14px;">
        <a class="btn btn-secondary" href="/my_tickets.php">Zurück zur Übersicht</a>
    </p>
</div>
<?php
renderFooter();