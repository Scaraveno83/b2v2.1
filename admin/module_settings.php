<?php
require_once __DIR__ . '/../auth/check_role.php';
requirePermission('can_access_admin');
requirePermission('can_manage_warehouses');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/module_settings.php';

$message = '';
$settings = getModuleSettings($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = saveModuleSettings($pdo, [
        'farming_tasks_enabled'    => isset($_POST['farming_tasks_enabled']) ? 1 : 0,
        'processing_tasks_enabled' => isset($_POST['processing_tasks_enabled']) ? 1 : 0,
        'processing_planner_enabled'=> isset($_POST['processing_planner_enabled']) ? 1 : 0,
        'partner_services_enabled'  => isset($_POST['partner_services_enabled']) ? 1 : 0,
    ]);
    $message = 'Einstellungen gespeichert.';
}

renderHeader('Moduleinstellungen', 'admin');
?>
<div class="card">
    <h2>Module für Farming &amp; Herstellung</h2>
    <p class="muted">Aktiviere oder deaktiviere die Aufgabensysteme für Lager-Produktion nach Bedarf.</p>

    <?php if ($message): ?>
        <div class="notice"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="post" class="grid grid-2" style="align-items:start; gap:16px;">
        <label class="checkbox">
            <input type="checkbox" name="farming_tasks_enabled" value="1" <?= !empty($settings['farming_tasks_enabled']) ? 'checked' : '' ?>>
            <div>
                <div><strong>Farming-Aufgaben</strong></div>
                <div class="muted">Generiert Aufgaben, wenn farmbare Artikel unter dem Mindestbestand liegen.</div>
            </div>
        </label>

        <label class="checkbox">
            <input type="checkbox" name="processing_tasks_enabled" value="1" <?= !empty($settings['processing_tasks_enabled']) ? 'checked' : '' ?>>
            <div>
                <div><strong>Herstellungs-Aufgaben</strong></div>
                <div class="muted">Erstellt Aufträge für herstellbare Artikel, sobald der Mindestbestand unterschritten wird.</div>
            </div>
        </label>

        <label class="checkbox">
            <input type="checkbox" name="processing_planner_enabled" value="1" <?= !empty($settings['processing_planner_enabled']) ? 'checked' : '' ?>>
            <div>
                <div><strong>Weiterverarbeitung planen</strong></div>
                <div class="muted">Materialbedarf kalkulieren und Zutaten direkt aus Lagern ausbuchen.</div>
            </div>
        </label>

        <label class="checkbox">
            <input type="checkbox" name="partner_services_enabled" value="1" <?= !empty($settings['partner_services_enabled']) ? 'checked' : '' ?>>
            <div>
                <div><strong>Partner-Services erfassen</strong></div>
                <div class="muted">Erfassung von Leistungen für Partner mit Wochenabrechnung aktivieren oder ausblenden.</div>
            </div>
        </label>

        <div style="grid-column:1 / -1;">
            <button class="btn btn-primary" type="submit">Speichern</button>
        </div>
    </form>
</div>
<?php
renderFooter();