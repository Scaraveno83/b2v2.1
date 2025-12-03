<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/auth/check_role.php';
$isGuest = !isset($_SESSION['user']);
require_once __DIR__ . '/includes/layout.php';

if (!$isGuest) {
    requireAbsenceAccess('tickets');
}

// Tabellen sicherstellen (falls Adminbereich noch nicht aufgerufen wurde)
$pdo->exec("CREATE TABLE IF NOT EXISTS ticket_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$pdo->exec("CREATE TABLE IF NOT EXISTS tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('open','in_progress','waiting','closed') NOT NULL DEFAULT 'open',
    priority ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
    category_id INT NULL,
    created_by INT NOT NULL,
    assigned_to INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$pdo->exec("CREATE TABLE IF NOT EXISTS guest_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    guest_name VARCHAR(255) NOT NULL,
    guest_email VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ticket (ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Standardkategorien
$catCount = (int)$pdo->query("SELECT COUNT(*) FROM ticket_categories")->fetchColumn();
if ($catCount === 0) {
    $pdo->exec("INSERT INTO ticket_categories (name) VALUES
        ('Allgemein'),
        ('Technik'),
        ('Verkauf'),
        ('Abrechnung')");
}

// Kategorien laden
$catStmt = $pdo->query("SELECT id, name FROM ticket_categories ORDER BY name ASC");
$categories = $catStmt->fetchAll();

$error = "";
$successTicket = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $guestName  = trim($_POST['guest_name'] ?? '');
    $guestEmail = trim($_POST['guest_email'] ?? '');
    $title      = trim($_POST['title'] ?? '');
    $description= trim($_POST['description'] ?? '');
    $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $priority   = $_POST['priority'] ?? 'medium';
    $userId     = $_SESSION['user']['id'] ?? 0;

    if ($userId === 0) {
        if ($guestName === '' || $guestEmail === '' || $title === '') {
            $error = "Name, E-Mail und Betreff müssen ausgefüllt werden.";
        } elseif (!filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) {
            $error = "Bitte eine gültige E-Mail-Adresse angeben.";
        }
    } elseif ($title === '') {
        $error = "Betreff darf nicht leer sein.";
    }

    if ($error === '') {
        $stmt = $pdo->prepare("INSERT INTO tickets (title, description, priority, category_id, created_by) VALUES (?,?,?,?,?)");
        $stmt->execute([
            $title,
            $description,
            $priority,
            $categoryId,
            $userId
        ]);
        $ticketId = (int)$pdo->lastInsertId();

        if ($userId === 0) {
            // Gast-Verknüpfung speichern
            $g = $pdo->prepare("INSERT INTO guest_tickets (ticket_id, guest_name, guest_email) VALUES (?,?,?)");
            $g->execute([$ticketId, $guestName, $guestEmail]);

        // Log-Eintrag
            $log = $pdo->prepare("INSERT INTO ticket_logs (ticket_id, user_id, action, details) VALUES (?,?,?,?)");
            $log->execute([$ticketId, null, 'create_guest', 'Ticket durch Gast erstellt']);
        } else {
            // Log-Eintrag für angemeldete Nutzer
            $log = $pdo->prepare("INSERT INTO ticket_logs (ticket_id, user_id, action, details) VALUES (?,?,?,?)");
            $log->execute([$ticketId, $userId, 'create', 'Ticket erstellt']);
        }

        $successTicket = [
            'id' => $ticketId,
            'title' => $title,
            'category_name' => null,
            'priority' => $priority
        ];
        if ($categoryId) {
            foreach ($categories as $cat) {
                if ((int)$cat['id'] === $categoryId) {
                    $successTicket['category_name'] = $cat['name'];
                    break;
                }
            }
        }
    }
}

renderHeader('Support-Ticket', 'ticket_public');
?>
<div class="card">
    <?php if ($successTicket): ?>
        <h2>🎉 Ticket erfolgreich erstellt!</h2>
        <p class="muted">
            Vielen Dank, dein Anliegen wurde als Ticket erfasst.
            Unser Team wird sich so schnell wie möglich bei dir melden.
        </p>
        <p>
            <strong>Ticket-ID:</strong> #<?= (int)$successTicket['id'] ?><br>
            <strong>Betreff:</strong> <?= htmlspecialchars($successTicket['title']) ?><br>
            <strong>Kategorie:</strong> <?= htmlspecialchars($successTicket['category_name'] ?? 'Allgemein') ?><br>
            <strong>Priorität:</strong>
            <span class="ticket-priority-<?= htmlspecialchars($successTicket['priority']) ?>">
                <?= htmlspecialchars($successTicket['priority']) ?>
            </span>
        </p>
        <p class="countdown">
            Du wirst in <span id="cd">5</span> Sekunden automatisch zur Startseite weitergeleitet…
        </p>
        <script>
            (function(){
                var s = 5;
                var el = document.getElementById('cd');
                function tick() {
                    s--;
                    if (s <= 0) {
                        window.location.href = '/index.php';
                    } else if (el) {
                        el.textContent = s;
                        setTimeout(tick, 1000);
                    }
                }
                setTimeout(tick, 1000);
            })();
        </script>
    <?php else: ?>
        <div class="card-header-row">
            <h2>Support-Ticket erstellen</h2>
            <?php if (!$isGuest): ?>
                <div class="card-header-actions">
                    <a class="btn btn-secondary" href="/my_tickets.php">Meine Tickets</a>
                </div>
            <?php endif; ?>
        </div>
        <p class="muted">
            Du kannst hier ohne Login ein Ticket erstellen. Bitte gib eine gültige E-Mail-Adresse an,
            damit wir dich kontaktieren können.
        </p>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="field-group">
                <label for="guest_name">Dein Name</label>
                <input id="guest_name" name="guest_name" <?= $isGuest ? 'required' : '' ?> value="<?= $isGuest ? '' : htmlspecialchars($_SESSION['user']['username'] ?? '') ?>">
            </div>
            <div class="field-group">
                <label for="guest_email">Deine E-Mail</label>
                <input id="guest_email" name="guest_email" type="email" <?= $isGuest ? 'required' : '' ?>>
            </div>
            <div class="field-group">
                <label for="title">Betreff</label>
                <input id="title" name="title" required>
            </div>
            <div class="field-group">
                <label for="description">Beschreibung</label>
                <textarea id="description" name="description" rows="5"></textarea>
            </div>
            <div class="field-group">
                <label for="category_id">Kategorie</label>
                <select id="category_id" name="category_id">
                    <option value="">Allgemein</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int)$cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field-group">
                <label for="priority">Priorität</label>
                <select id="priority" name="priority">
                    <option value="low">Niedrig</option>
                    <option value="medium" selected>Mittel</option>
                    <option value="high">Hoch</option>
                    <option value="urgent">Dringend</option>
                </select>
            </div>
            <button class="btn btn-primary" type="submit">Ticket absenden</button>
            <a class="btn btn-secondary" href="/index.php">Abbrechen</a>
        </form>
    <?php endif; ?>
</div>
<?php
renderFooter();
