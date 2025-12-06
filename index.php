<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/banner_service.php';
require_once __DIR__ . '/includes/layout.php';

ensureBannerSchema($pdo);
$banners = fetchHomepageBanners($pdo, 12);

renderHeader('Startseite', 'start');
?>
<?php if ($banners): ?>
    <aside class="banner-beacon" data-banner-rotator>
        <div class="banner-beacon__glow"></div>
        <div class="banner-beacon__badge">Spotlight</div>
        <div class="banner-beacon__viewport" data-banner-viewport>
            <?php foreach ($banners as $index => $banner): ?>
                <figure class="banner-beacon__item<?= $index === 0 ? ' is-active' : '' ?>" data-banner-item>
                    <img src="<?= htmlspecialchars($banner['image_path']) ?>" alt="<?= htmlspecialchars($banner['title'] ?? 'Partner-Banner') ?>">
                    <figcaption><?= htmlspecialchars($banner['title'] ?? 'Partner-Banner') ?></figcaption>
                </figure>
            <?php endforeach; ?>
            <div class="banner-beacon__progress" aria-hidden="true">
                <div class="banner-beacon__progress-bar" data-banner-progress></div>
            </div>
        </div>
    </aside>
<?php endif; ?>
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
