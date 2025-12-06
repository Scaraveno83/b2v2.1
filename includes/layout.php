<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/../auth/check_role.php';
require_once __DIR__ . '/theme_settings.php';

function renderHeader($title, $currentPage) {
    global $pdo;
    $themeSettings = loadThemeSettings($pdo);
    $brandName = $themeSettings['brand_name'] ?? 'ULTRA NEON PANEL';
    $brandLogo = $themeSettings['brand_logo'] ?? '';
    $brandStyle = $themeSettings['brand_font_style'] ?? 'neon-depth';
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= htmlspecialchars($title) ?></title>
        <link rel="stylesheet" href="/assets/style.css">
        <?php renderThemeStyles($pdo); ?>
        <script src="/assets/theme.js" defer></script>
        <script src="/assets/notifications.js" defer></script>
        <script src="/assets/news.js" defer></script>
        <script src="/assets/split.js" defer></script>
    </head>
    <body data-user-role="<?= isset($_SESSION['user']['role']) ? htmlspecialchars($_SESSION['user']['role']) : 'guest' ?>"
          data-user-id="<?= isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 'anon' ?>"
          data-news-scope="auto"
          data-layout-variant="<?= htmlspecialchars($themeSettings['layout_variant'] ?? 'standard') ?>">
    <header>
        <div class="logo">
            <?php if (!empty($brandLogo)): ?>
                <img class="logo-image" src="<?= htmlspecialchars($brandLogo) ?>" alt="<?= htmlspecialchars($brandName) ?>">
            <?php else: ?>
                <div class="logo-mark"></div>
            <?php endif; ?>
            <div class="logo-text brand-style-<?= htmlspecialchars($brandStyle) ?>" data-brand-text="<?= htmlspecialchars($brandName) ?>"><?= htmlspecialchars($brandName) ?></div>
        </div>
        <div class="user-area">
            <?php if (isset($_SESSION['user'])): ?>
                <a class="user-label" href="/profile.php">
                   <div class="avatar-circle">
                        <?php if (!empty($_SESSION['user']['avatar_path'])): ?>
                            <img class="avatar-image" src="<?= htmlspecialchars($_SESSION['user']['avatar_path']) ?>" alt="Avatar">
                        <?php else: ?>
                            <?= strtoupper(substr($_SESSION['user']['username'], 0, 2)) ?>
                        <?php endif; ?>
                    </div>
                    <div class="user-meta">
                        <span class="user-name">
                            Hallo, <?= htmlspecialchars($_SESSION['user']['username']) ?>
                        </span>
                        <span class="user-role">
                            <?= strtoupper($_SESSION['user']['role']) ?>
                            <?php if (!empty($_SESSION['user']['rank_name'])): ?>
                                · <span class="badge-rank"><?= htmlspecialchars($_SESSION['user']['rank_name']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($_SESSION['user']['absence']['active']) && !empty($_SESSION['user']['absence']['entry']['end_at'])): ?>
                                <span class="badge-absence">Abgemeldet bis <?= date('d.m. H:i', strtotime($_SESSION['user']['absence']['entry']['end_at'])) ?></span>
                            <?php endif; ?>
                        </span>
                    </div>
                </a>
            <?php endif; ?>
            <button class="split-toggle" type="button" data-split-toggle aria-expanded="false">
                🪟 Multi-Window
            </button>
            <button class="theme-toggle" type="button" onclick="toggleThemeMode()">
                <span>🌗</span> Mode
            </button>
        </div>
    </header>
    <nav>
        <a class="nav-link<?= ($currentPage === 'start') ? ' active' : '' ?>" href="/"><span>Start</span></a>

        <a class="nav-link<?= ($currentPage === 'news') ? ' active' : '' ?>" href="/news/index.php">
            <span>News</span>
        </a>

        <?php if (isset($_SESSION['user'])): ?>
            <a class="nav-link<?= ($currentPage === 'calendar') ? ' active' : '' ?>" href="/calendar/index.php">
                <span>Kalender</span>
            </a>
        <?php endif; ?>

        <a class="nav-link<?= ($currentPage === 'services') ? ' active' : '' ?>" href="/services.php">
            <span>Services & Preise</span>
        </a>

        <a class="nav-link<?= ($currentPage === 'ticket_public') ? ' active' : '' ?>" href="/ticket_create.php">
            <span>Support-Ticket</span>
        </a>

        <?php if (isset($_SESSION['user']) && !isAbsentRestrictedArea('messages')): ?>
            <a class="nav-link<?= ($currentPage === 'messages') ? ' active' : '' ?>" href="/messages.php">
                <span>Nachrichten</span>
                <span class="nav-badge" id="nav-message-badge" aria-label="Ungelesene Nachrichten"></span>
            </a>
            <?php if (hasPermission('can_view_forum') && !isAbsentRestrictedArea('forum')): ?>
                <a class="nav-link<?= ($currentPage === 'forum') ? ' active' : '' ?>" href="/forum/index.php">
                    <span>Forum</span>
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (!isset($_SESSION['user'])): ?>
            <a class="nav-link<?= ($currentPage === 'login') ? ' active' : '' ?>" href="/login/login.php"><span>Login</span></a>
        <?php else: ?>
            <a class="nav-link" href="/login/logout.php"><span>Logout</span></a>
        <?php endif; ?>

        <?php if (isset($_SESSION['user']) && in_array($_SESSION['user']['role'], ['employee','admin'], true) && !isAbsentRestrictedArea('staff')): ?>
            <a class="nav-link<?= ($currentPage === 'staff') ? ' active' : '' ?>" href="/staff/index.php"><span>Mitarbeiter</span></a>
        <?php endif; ?>

        <?php if (isset($_SESSION['user']) && in_array($_SESSION['user']['role'], ['partner','admin'], true) && !isAbsentRestrictedArea('partner')): ?>
            <a class="nav-link<?= ($currentPage === 'partner') ? ' active' : '' ?>" href="/partner/index.php"><span>Partner</span></a>
        <?php endif; ?>

        <?php if (isset($_SESSION['user']) && hasPermission('can_access_admin') && !isAbsentRestrictedArea('admin')): ?>
            <a class="nav-link<?= ($currentPage === 'admin') ? ' active' : '' ?>" href="/admin/index.php"><span>Admin</span></a>
        <?php endif; ?>
    </nav>
    <div class="container">
    <?php
}

function renderFooter() {
    ?>
    </div>
    <div class="split-overlay" data-split-overlay hidden>
        <div class="split-overlay-panel">
            <div class="split-overlay-head">
                <div>
                    <p class="eyebrow">Experimentell</p>
                    <h3>Multi-Window Modus</h3>
                    <p class="muted">Wähle zwei Bereiche aus, um sie nebeneinander im Split-Screen zu sehen.</p>
                </div>
                <button class="split-close" type="button" data-split-close aria-label="Schließen">×</button>
            </div>
            <div class="split-controls">
                <label class="split-control">
                    <span>Fenster A</span>
                    <select data-split-select data-split-target="left"></select>
                </label>
                <label class="split-control">
                    <span>Fenster B</span>
                    <select data-split-select data-split-target="right"></select>
                </label>
            </div>
            <div class="split-frames">
                <div class="split-frame" aria-label="Linkes Fenster">
                    <iframe data-split-frame="left" title="Linkes Fenster"></iframe>
                </div>
                <div class="split-frame" aria-label="Rechtes Fenster">
                    <iframe data-split-frame="right" title="Rechtes Fenster"></iframe>
                </div>
            </div>
        </div>
    </div>
    <footer>Ultra Neon Panel — Rollen, Rechte & Ticketsystem</footer>
    </body>
    </html>
    <?php
}
