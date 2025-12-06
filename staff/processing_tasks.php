<?php
require_once __DIR__ . '/../auth/check_role.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/warehouse_service.php';

checkRole(['employee', 'admin']);
requireAbsenceAccess('staff');
requireAbsenceAccess('warehouses');
requirePermission('can_use_warehouses');
ensureWarehouseSchema($pdo);

$user = $_SESSION['user'];
$message = '';

$warehouses = getAccessibleWarehouses($pdo, $user);
$warehouseIds = array_column($warehouses, 'id');
$hasManageOverride = !empty($user['permissions']['can_manage_warehouses']);
$scopeWarehouseIds = $hasManageOverride ? null : $warehouseIds;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_done') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        if (markProcessingTaskDone($pdo, $taskId, $user['id'] ?? null, $scopeWarehouseIds)) {
            $message = 'Aufgabe wurde als erledigt markiert.';
        } else {
            $message = 'Aufgabe konnte nicht aktualisiert werden.';
        }
    }
}

syncAllProcessingTasks($pdo);
$tasks = getOpenProcessingTasks($pdo);

renderHeader('Herstellungs-Aufgaben', 'staff');
?>
<div class="card">
    <h2>Herstellungs-Aufgaben</h2>
    <p class="muted">Artikel, die als herstellbar markiert sind und den Mindestbestand unterschreiten, erscheinen hier automatisch.</p>

    <?php if ($message): ?>
        <div class="notice" style="margin-bottom:12px;"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if (!$tasks): ?>
        <div class="muted">Aktuell liegen keine offenen Herstellungs-Aufgaben vor.</div>
    <?php else: ?>
        <div class="item-grid" style="display:grid; gap:12px; grid-template-columns:repeat(auto-fit, minmax(340px, 1fr));">
            <?php foreach ($tasks as $task): ?>
                <?php
                    $canComplete = $hasManageOverride
                        || $task['warehouse_id'] === null
                        || in_array((int)$task['warehouse_id'], $warehouseIds, true);
                    $plan = calculateProcessingNeeds($pdo, (int)$task['item_id'], (int)$task['required_amount']);
                ?>
                <div class="item-card" style="border:1px solid rgba(255,255,255,0.1); border-radius:10px; padding:12px; background:rgba(33,150,243,0.05);">
                    <header style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px; margin-bottom:8px;">
                        <div>
                            <div><strong><?= htmlspecialchars($task['item_name']) ?></strong></div>
                            <div class="muted" style="font-size:12px; margin-top:2px;">
                                Mindestbestand <?= (int)$task['min_stock'] ?> · Gesamtbestand <?= (int)$task['total_stock'] ?>
                            </div>
                        </div>
                        <div class="badge" style="background:rgba(255,193,7,0.15); color:#ffe082;">Fehlen: <?= (int)$task['required_amount'] ?></div>
                    </header>

                    <div class="muted" style="margin-bottom:10px; font-size:13px;">
                        <?= htmlspecialchars($task['note'] ?? 'Herstellungs-Aufgabe erstellt.') ?>
                    </div>

                    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:10px;">
                        <?php if (!empty($task['warehouse_name'])): ?>
                            <span class="badge">Lager: <?= htmlspecialchars($task['warehouse_name']) ?></span>
                        <?php else: ?>
                            <span class="badge">Lager: beliebig</span>
                        <?php endif; ?>
                        <span class="badge">Durchläufe: <?= (int)$task['batches'] ?></span>
                        <span class="badge">Aufgabe seit: <?= date('d.m.y H:i', strtotime($task['created_at'])) ?></span>
                    </div>

                    <?php if ($plan && $plan['ingredients']): ?>
                        <div class="table" role="table" aria-label="Zutatenbedarf">
                            <div class="table-row" role="row" style="display:grid; grid-template-columns: 1.3fr 0.7fr 0.7fr; gap:8px; font-weight:700;">
                                <div>Zutat</div>
                                <div>Gesamtbedarf</div>
                                <div>Bestand gesamt</div>
                            </div>
                            <?php foreach ($plan['ingredients'] as $ing): ?>
                                <div class="table-row" role="row" style="display:grid; grid-template-columns: 1.3fr 0.7fr 0.7fr; gap:8px; align-items:center;">
                                    <div><?= htmlspecialchars($ing['name']) ?></div>
                                    <div><?= (int)$ing['total_needed'] ?></div>
                                    <div><?= (int)$ing['available_stock'] ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($canComplete): ?>
                        <form method="post" style="margin-top:10px;">
                            <input type="hidden" name="action" value="mark_done">
                            <input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>">
                            <button class="btn btn-primary" type="submit">
                                Als erledigt markieren
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="muted" style="margin-top:6px; font-size:13px;">
                            Keine Berechtigung für dieses Lager.
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php
renderFooter();