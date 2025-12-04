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
$warehouses = getAccessibleWarehouses($pdo, $user);
$warehouseIds = array_map('intval', array_column($warehouses, 'id'));

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_done') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        if (markFarmingTaskDone($pdo, $taskId, $user['id'] ?? null, $warehouseIds)) {
            $message = 'Aufgabe wurde als erledigt markiert.';
        } else {
            $message = 'Aufgabe konnte nicht aktualisiert werden.';
        }
    }
}

syncAllFarmingTasks($pdo);
$tasks = getOpenFarmingTasks($pdo, $warehouseIds);

renderHeader('Farming-Aufgaben', 'staff');
?>
<div class="card">
    <h2>Farming-Aufgaben</h2>
    <p class="muted">Artikel, die als farmbar markiert sind und den Mindestbestand unterschreiten, erscheinen hier automatisch.</p>

    <?php if ($message): ?>
        <div class="notice" style="margin-bottom:12px;"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if (!$tasks): ?>
        <div class="muted">Aktuell liegen keine offenen Farming-Aufgaben vor.</div>
    <?php else: ?>
        <div class="item-grid" style="display:grid; gap:12px; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));">
            <?php foreach ($tasks as $task): ?>
                <div class="item-card" style="border:1px solid rgba(255,255,255,0.1); border-radius:10px; padding:12px; background:rgba(76,175,80,0.05);">
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
                        <?= htmlspecialchars($task['note'] ?? 'Farming-Aufgabe erstellt.') ?>
                    </div>

                    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:10px;">
                        <?php if (!empty($task['warehouse_name'])): ?>
                            <span class="badge">Lager: <?= htmlspecialchars($task['warehouse_name']) ?></span>
                        <?php else: ?>
                            <span class="badge">Lager: beliebig</span>
                        <?php endif; ?>
                        <span class="badge">Aufgabe seit: <?= date('d.m.y H:i', strtotime($task['created_at'])) ?></span>
                    </div>

                    <form method="post" style="margin-top:6px;">
                        <input type="hidden" name="action" value="mark_done">
                        <input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>">
                        <button class="btn btn-primary" type="submit">Als erledigt markieren</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php
renderFooter();