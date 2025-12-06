<?php
require_once __DIR__ . '/../auth/check_role.php';
require_once __DIR__ . '/../includes/layout.php';
checkRole(['employee','admin']);
requireAbsenceAccess('staff');

renderHeader('Mitarbeiterbereich', 'staff');
?>
<div class="card">
    <h2>Mitarbeiterbereich</h2>
    <p class="muted">Interner Bereich für Mitarbeiter &amp; Admins.</p>
    <p>Hier können später Module wie Aufgabenlisten, Dienstpläne oder interne Dokumente eingebunden werden.</p>
</div>

<div class="card news-ticker-card">
    <div class="card-header">
        <div>
            <p class="eyebrow">Live</p>
            <h3>News für Mitarbeiter &amp; Partner</h3>
        </div>
        <a class="btn btn-secondary" href="/news/index.php">News öffnen</a>
    </div>
    <div class="news-ticker" data-news-ticker data-scope="auto"></div>
</div>
<?php if (hasPermission('can_use_warehouses')): ?>
<div class="card">
    <div class="card-header">Lager</div>
    <p class="muted">Greife auf dein zugewiesenes Lager zu, um Bestände zu prüfen und Artikel zu verbuchen.</p>
    <a class="btn btn-primary" href="/staff/warehouses.php">Zum Lagersystem</a>
</div>
<div class="card">
    <div class="card-header">Farming-Aufgaben</div>
    <p class="muted">Sieh dir automatisch generierte Farming-Aufgaben an, wenn farmbare Artikel unter den Mindestbestand fallen.</p>
    <a class="btn btn-primary" href="/staff/farming.php">Farming-Aufgaben öffnen</a>
</div>
<div class="card">
    <div class="card-header">Herstellungs-Aufgaben</div>
    <p class="muted">Automatisch generierte Aufträge, wenn herstellbare Artikel unter den Mindestbestand fallen.</p>
    <a class="btn btn-primary" href="/staff/processing_tasks.php">Herstellungs-Aufgaben öffnen</a>
</div>
<div class="card">
    <div class="card-header">Weiterverarbeitung</div>
    <p class="muted">Berechne den Materialbedarf für Aufträge, z.&nbsp;B. für Repair Kits.</p>
    <a class="btn btn-primary" href="/staff/processing.php">Weiterverarbeitung planen</a>
</div>
<?php endif; ?>
<?php if (hasPermission('can_log_partner_services')): ?>
<div class="card">
    <div class="card-header">Partner-Services</div>
    <p class="muted">Leistungen für Partner mit Wochenabrechnung erfassen.</p>
    <a class="btn btn-primary" href="/staff/partner_services.php">Service erfassen</a>
</div>
<?php endif; ?>
<?php
renderFooter();
