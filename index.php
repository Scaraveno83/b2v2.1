<?php
session_start();
require_once __DIR__ . '/includes/layout.php';

renderHeader('Startseite', 'start');
?>
<div class="card">
    <?php if (isset($_SESSION['user'])): ?>
        <h2>Willkommen zurück</h2>
        <p class="muted">
            Du bist angemeldet als
            <span class="pill">
                <?= htmlspecialchars($_SESSION['user']['username']) ?>
            </span>
            &mdash;
            <span class="pill pill-role-<?= htmlspecialchars($_SESSION['user']['role']) ?>">
                <?= htmlspecialchars($_SESSION['user']['role']) ?>
            </span>
            <?php if (!empty($_SESSION['user']['rank_name'])): ?>
                &nbsp;<span class="badge-rank"><?= htmlspecialchars($_SESSION['user']['rank_name']) ?></span>
            <?php endif; ?>
        </p>
        <div class="grid grid-2">
            <div class="stat">
                <div class="stat-label">Dein Bereich</div>
                <div class="stat-value">
                    <?php
                    $role = $_SESSION['user']['role'];
                    if ($role === 'admin') {
                        echo 'Admin Dashboard';
                    } elseif ($role === 'employee') {
                        echo 'Mitarbeiterbereich';
                    } elseif ($role === 'partner') {
                        echo 'Partnerbereich';
                    } else {
                        echo ucfirst($role);
                    }
                    ?>
                </div>
                <div class="stat-sub">Nutze das Menü oben, um direkt dorthin zu springen.</div>
            </div>
            <div class="stat">
                <div class="stat-label">Support</div>
                <div class="stat-value">Ticketsystem</div>
                <div class="stat-sub">Über den Punkt „Support-Ticket“ oben kannst du jederzeit ein Ticket anlegen.</div>
            </div>
        </div>
    <?php else: ?>
        <h2>Willkommen im Ultra Neon Panel</h2>
        <p class="muted">
            Zentrale Oberfläche für Admins, Mitarbeiter, Partner und Gäste. Du kannst jederzeit ein Support-Ticket anlegen.
        </p>
        <a class="btn btn-primary" href="/ticket_create.php">Support-Ticket erstellen</a>
        <a class="btn btn-secondary" href="/login/login.php">Login</a>
    <?php endif; ?>
</div>

<div class="card news-ticker-card">
    <div class="card-head">
        <div>
            <p class="eyebrow">Live</p>
            <h3>News-Ticker (Öffentlich)</h3>
        </div>
        <a class="btn btn-secondary" href="/news/index.php">Alle News</a>
    </div>
    <div class="news-ticker" data-news-ticker data-scope="public"></div>
</div>
<?php
renderFooter();
