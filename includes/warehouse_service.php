<?php
if (!function_exists('session_status') || session_status() !== PHP_SESSION_ACTIVE) {
    // Do not start session automatically here; the caller handles it via layout or check_role.
}

/**
 * Ensure that the warehouse-related tables are present.
 */
function ensureWarehouseSchema(PDO $pdo): void
{
    static $initialized = false;
    if ($initialized) {
        return;
    }

    $initialized = true;

    $pdo->exec("CREATE TABLE IF NOT EXISTS warehouses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS warehouse_ranks (
        warehouse_id INT NOT NULL,
        rank_id INT NOT NULL,
        PRIMARY KEY (warehouse_id, rank_id),
        CONSTRAINT fk_wr_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE CASCADE,
        CONSTRAINT fk_wr_rank FOREIGN KEY (rank_id) REFERENCES ranks(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT NULL,
        min_stock INT NOT NULL DEFAULT 0,
        max_stock INT NOT NULL DEFAULT 0,
        farmable TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    ensureFarmableColumn($pdo);

    $pdo->exec("CREATE TABLE IF NOT EXISTS warehouse_item_stocks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        warehouse_id INT NOT NULL,
        item_id INT NOT NULL,
        current_stock INT NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_wis (warehouse_id, item_id),
        CONSTRAINT fk_wis_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE CASCADE,
        CONSTRAINT fk_wis_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS warehouse_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        warehouse_id INT NOT NULL,
        item_id INT NOT NULL,
        user_id INT NULL,
        change_amount INT NOT NULL,
        resulting_stock INT NOT NULL,
        action VARCHAR(20) NOT NULL,
        note TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_wl_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE CASCADE,
        CONSTRAINT fk_wl_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
        CONSTRAINT fk_wl_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS farming_tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_id INT NOT NULL,
        warehouse_id INT NULL,
        required_amount INT NOT NULL DEFAULT 0,
        status ENUM('open','done','cancelled') NOT NULL DEFAULT 'open',
        note TEXT NULL,
        done_by INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        completed_at DATETIME NULL,
        CONSTRAINT fk_ft_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
        CONSTRAINT fk_ft_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE SET NULL,
        CONSTRAINT fk_ft_user FOREIGN KEY (done_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    migrateLegacyWarehouseItems($pdo);
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function dropForeignKey(PDO $pdo, string $table, string $foreignKey): void
{
    try {
        $pdo->exec(sprintf('ALTER TABLE %s DROP FOREIGN KEY %s', $table, $foreignKey));
    } catch (Throwable $e) {
        // Ignore if the constraint does not exist.
    }
}

function ensureFarmableColumn(PDO $pdo): void
{
    $stmt = $pdo->query("SHOW COLUMNS FROM items LIKE 'farmable'");
    if ($stmt->fetch()) {
        return;
    }

    $pdo->exec("ALTER TABLE items ADD COLUMN farmable TINYINT(1) NOT NULL DEFAULT 0 AFTER max_stock");
}

function migrateLegacyWarehouseItems(PDO $pdo): void
{
    if (!tableExists($pdo, 'warehouse_items')) {
        return;
    }

    // Ensure foreign key from logs does not block migration.
    if (tableExists($pdo, 'warehouse_logs')) {
        dropForeignKey($pdo, 'warehouse_logs', 'fk_wl_item');
    }

    $legacyItems = $pdo->query('SELECT * FROM warehouse_items')->fetchAll(PDO::FETCH_ASSOC);
    $legacyItemMap = [];

    foreach ($legacyItems as $legacy) {
        $name = $legacy['name'];
        $description = $legacy['description'];
        $minStock = (int)$legacy['min_stock'];
        $maxStock = (int)$legacy['max_stock'];

        $stmt = $pdo->prepare('SELECT id, min_stock, max_stock FROM items WHERE name = ? LIMIT 1');
        $stmt->execute([$name]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $itemId = (int)$existing['id'];
            $newMin = max((int)$existing['min_stock'], $minStock);
            $newMax = max((int)$existing['max_stock'], $maxStock);
            $upd = $pdo->prepare('UPDATE items SET description = ?, min_stock = ?, max_stock = ? WHERE id = ?');
            $upd->execute([$description, $newMin, $newMax, $itemId]);
        } else {
            $ins = $pdo->prepare('INSERT INTO items (name, description, min_stock, max_stock) VALUES (?,?,?,?)');
            $ins->execute([$name, $description, $minStock, $maxStock]);
            $itemId = (int)$pdo->lastInsertId();
        }

        $legacyItemMap[(int)$legacy['id']] = $itemId;

        $stockIns = $pdo->prepare('INSERT INTO warehouse_item_stocks (warehouse_id, item_id, current_stock) VALUES (?,?,?)
            ON DUPLICATE KEY UPDATE current_stock = VALUES(current_stock)');
        $stockIns->execute([(int)$legacy['warehouse_id'], $itemId, (int)$legacy['current_stock']]);
    }

    if (tableExists($pdo, 'warehouse_logs')) {
        $logs = $pdo->query('SELECT id, item_id FROM warehouse_logs')->fetchAll(PDO::FETCH_ASSOC);
        $upd = $pdo->prepare('UPDATE warehouse_logs SET item_id = ? WHERE id = ?');
        foreach ($logs as $log) {
            $legacyId = (int)$log['item_id'];
            if (!isset($legacyItemMap[$legacyId])) {
                continue;
            }
            $upd->execute([$legacyItemMap[$legacyId], (int)$log['id']]);
        }

        try {
            $pdo->exec('ALTER TABLE warehouse_logs ADD CONSTRAINT fk_wl_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE');
        } catch (Throwable $e) {
            // Ignore if constraint already exists.
        }
    }

    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $pdo->exec('DROP TABLE warehouse_items');
    } finally {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }
}

/**
 * Returns warehouses accessible to the current user.
 */
function getAccessibleWarehouses(PDO $pdo, array $user): array
{
    ensureWarehouseSchema($pdo);

    if (!empty($user['permissions']['can_manage_warehouses'])) {
        $stmt = $pdo->query("SELECT * FROM warehouses ORDER BY name ASC");
        return $stmt->fetchAll();
    }

    if (empty($user['rank_id'])) {
        return [];
    }

    $stmt = $pdo->prepare("SELECT w.* FROM warehouses w
        INNER JOIN warehouse_ranks wr ON wr.warehouse_id = w.id
        WHERE wr.rank_id = ?
        ORDER BY w.name ASC");
    $stmt->execute([(int)$user['rank_id']]);
    return $stmt->fetchAll();
}

/**
 * Retrieves all ranks that may be associated with a warehouse.
 */
function getAllRanks(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT id, name FROM ranks ORDER BY name ASC");
    return $stmt->fetchAll();
}

/**
 * Returns rank ids linked to a warehouse.
 */
function getWarehouseRankIds(PDO $pdo, int $warehouseId): array
{
    $stmt = $pdo->prepare("SELECT rank_id FROM warehouse_ranks WHERE warehouse_id = ?");
    $stmt->execute([$warehouseId]);
    return array_map('intval', array_column($stmt->fetchAll(), 'rank_id'));
}

function createOrUpdateItem(PDO $pdo, string $name, string $description, int $minStock, int $maxStock, bool $farmable): int
{
    ensureWarehouseSchema($pdo);

    $stmt = $pdo->prepare('SELECT id FROM items WHERE name = ? LIMIT 1');
    $stmt->execute([$name]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $upd = $pdo->prepare('UPDATE items SET description = ?, min_stock = ?, max_stock = ?, farmable = ? WHERE id = ?');
        $upd->execute([$description, $minStock, $maxStock, $farmable ? 1 : 0, (int)$existing['id']]);
        syncFarmingTasksForItem($pdo, (int)$existing['id']);
        return (int)$existing['id'];
    }

    $ins = $pdo->prepare('INSERT INTO items (name, description, min_stock, max_stock, farmable) VALUES (?,?,?,?,?)');
    $ins->execute([$name, $description, $minStock, $maxStock, $farmable ? 1 : 0]);
    $itemId = (int)$pdo->lastInsertId();
    syncFarmingTasksForItem($pdo, $itemId);
    return $itemId;
}

function ensureWarehouseItemLink(PDO $pdo, int $warehouseId, int $itemId): void
{
    $stmt = $pdo->prepare('INSERT IGNORE INTO warehouse_item_stocks (warehouse_id, item_id, current_stock) VALUES (?,?,0)');
    $stmt->execute([$warehouseId, $itemId]);
}

function getWarehouseStock(PDO $pdo, int $warehouseId, int $itemId): int
{
    $stmt = $pdo->prepare('SELECT current_stock FROM warehouse_item_stocks WHERE warehouse_id = ? AND item_id = ?');
    $stmt->execute([$warehouseId, $itemId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (int)$row['current_stock'] : 0;
}

function deleteItemCompletely(PDO $pdo, int $itemId): void
{
    $stmt = $pdo->prepare('DELETE FROM items WHERE id = ?');
    $stmt->execute([$itemId]);
}

/**
 * Adds or removes stock and records a log entry.
 */
function adjustWarehouseStock(PDO $pdo, int $warehouseId, int $itemId, int $delta, string $action, ?int $userId, string $note = ''): bool
{
    ensureWarehouseSchema($pdo);

    $stmt = $pdo->prepare('SELECT id FROM items WHERE id = ?');
    $stmt->execute([$itemId]);
    if (!$stmt->fetch()) {
        return false;
    }

    $stockStmt = $pdo->prepare('SELECT id, current_stock FROM warehouse_item_stocks WHERE item_id = ? AND warehouse_id = ?');
    $stockStmt->execute([$itemId, $warehouseId]);
    $stockRow = $stockStmt->fetch(PDO::FETCH_ASSOC);

    if (!$stockRow) {
        $insert = $pdo->prepare('INSERT INTO warehouse_item_stocks (warehouse_id, item_id, current_stock) VALUES (?,?,0)');
        $insert->execute([$warehouseId, $itemId]);
        $stockId = (int)$pdo->lastInsertId();
        $current = 0;
    } else {
        $stockId = (int)$stockRow['id'];
        $current = (int)$stockRow['current_stock'];
    }

    $newStock = max(0, $current + $delta);
    $actualChange = $newStock - $current;

    $upd = $pdo->prepare('UPDATE warehouse_item_stocks SET current_stock = ? WHERE id = ?');
    $upd->execute([$newStock, $stockId]);

    logWarehouseChange($pdo, $warehouseId, $itemId, $userId, $actualChange, $action, $note, $newStock);

    syncFarmingTasksForItem($pdo, $itemId);

    return true;
}

function logWarehouseChange(PDO $pdo, int $warehouseId, int $itemId, ?int $userId, int $changeAmount, string $action, string $note, int $resultingStock): void
{
    $stmt = $pdo->prepare('INSERT INTO warehouse_logs (warehouse_id, item_id, user_id, change_amount, resulting_stock, action, note)
        VALUES (?,?,?,?,?,?,?)');
    $stmt->execute([
        $warehouseId,
        $itemId,
        $userId,
        $changeAmount,
        $resultingStock,
        $action,
        $note,
    ]);
}

function getTotalStockForItem(PDO $pdo, int $itemId): int
{
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(current_stock), 0) FROM warehouse_item_stocks WHERE item_id = ?');
    $stmt->execute([$itemId]);
    return (int)$stmt->fetchColumn();
}

function syncFarmingTasksForItem(PDO $pdo, int $itemId): void
{
    ensureWarehouseSchema($pdo);

    $stmt = $pdo->prepare('SELECT id, min_stock, farmable FROM items WHERE id = ?');
    $stmt->execute([$itemId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$item) {
        return;
    }

    $minStock = (int)$item['min_stock'];
    $isFarmable = (int)$item['farmable'] === 1;
    $totalStock = getTotalStockForItem($pdo, $itemId);

    if (!$isFarmable || $minStock <= 0) {
        $status = $isFarmable ? 'done' : 'cancelled';
        $closeStmt = $pdo->prepare("UPDATE farming_tasks SET status = ?, completed_at = NOW() WHERE item_id = ? AND status = 'open'");
        $closeStmt->execute([$status, $itemId]);
        return;
    }

    $requiredAmount = max(0, $minStock - $totalStock);

    $warehouseStmt = $pdo->prepare('SELECT warehouse_id FROM warehouse_item_stocks WHERE item_id = ? ORDER BY current_stock ASC LIMIT 1');
    $warehouseStmt->execute([$itemId]);
    $targetWarehouseId = $warehouseStmt->fetchColumn();
    $targetWarehouseId = $targetWarehouseId !== false ? (int)$targetWarehouseId : null;

    if ($requiredAmount > 0) {
        $openStmt = $pdo->prepare("SELECT id FROM farming_tasks WHERE item_id = ? AND status = 'open' LIMIT 1");
        $openStmt->execute([$itemId]);
        $openTask = $openStmt->fetch(PDO::FETCH_ASSOC);

        $note = sprintf('Fehlbestand %d Stück (Min: %d, Bestand: %d)', $requiredAmount, $minStock, $totalStock);

        if ($openTask) {
            $upd = $pdo->prepare('UPDATE farming_tasks SET required_amount = ?, warehouse_id = ?, note = ?, updated_at = NOW() WHERE id = ?');
            $upd->execute([$requiredAmount, $targetWarehouseId, $note, (int)$openTask['id']]);
        } else {
            $ins = $pdo->prepare('INSERT INTO farming_tasks (item_id, warehouse_id, required_amount, note) VALUES (?,?,?,?)');
            $ins->execute([$itemId, $targetWarehouseId, $requiredAmount, $note]);
        }

        return;
    }

    $doneStmt = $pdo->prepare("UPDATE farming_tasks SET status = 'done', completed_at = NOW() WHERE item_id = ? AND status = 'open'");
    $doneStmt->execute([$itemId]);
}

function syncAllFarmingTasks(PDO $pdo): void
{
    ensureWarehouseSchema($pdo);
    $stmt = $pdo->query('SELECT id FROM items');
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $itemId) {
        syncFarmingTasksForItem($pdo, (int)$itemId);
    }
}

function getOpenFarmingTasks(PDO $pdo, ?array $warehouseIds = null): array
{
    ensureWarehouseSchema($pdo);

    $sql = "SELECT ft.*, i.name AS item_name, i.min_stock, i.max_stock, i.farmable, COALESCE(SUM(wis.current_stock), 0) AS total_stock, w.name AS warehouse_name
        FROM farming_tasks ft
        INNER JOIN items i ON ft.item_id = i.id
        LEFT JOIN warehouse_item_stocks wis ON wis.item_id = i.id
        LEFT JOIN warehouses w ON ft.warehouse_id = w.id
        WHERE ft.status = 'open'";

    $params = [];
    if ($warehouseIds !== null) {
        if ($warehouseIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($warehouseIds), '?'));
        $sql .= " AND (ft.warehouse_id IS NULL OR ft.warehouse_id IN ($placeholders))";
        foreach ($warehouseIds as $wid) {
            $params[] = (int)$wid;
        }
    }

    $sql .= " GROUP BY ft.id, i.id, w.id ORDER BY ft.created_at ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function markFarmingTaskDone(PDO $pdo, int $taskId, ?int $userId, ?array $warehouseIds = null): bool
{
    ensureWarehouseSchema($pdo);

    $stmt = $pdo->prepare('SELECT id, item_id, warehouse_id, status FROM farming_tasks WHERE id = ?');
    $stmt->execute([$taskId]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$task || $task['status'] !== 'open') {
        return false;
    }

    if ($warehouseIds !== null && $task['warehouse_id'] !== null && !in_array((int)$task['warehouse_id'], $warehouseIds, true)) {
        return false;
    }

    $update = $pdo->prepare("UPDATE farming_tasks SET status = 'done', done_by = ?, completed_at = NOW() WHERE id = ? AND status = 'open'");
    $update->execute([$userId, $taskId]);

    syncFarmingTasksForItem($pdo, (int)$task['item_id']);

    return $update->rowCount() > 0;
}

/**
 * Returns items for a warehouse.
 */
function getWarehouseItems(PDO $pdo, int $warehouseId): array
{
    $sql = "SELECT i.id, i.name, i.description, i.min_stock, i.max_stock, i.farmable,
            COALESCE(wis.current_stock, 0) AS current_stock,
            COALESCE(SUM(all_wis.current_stock), 0) AS total_stock
        FROM items i
        LEFT JOIN warehouse_item_stocks wis ON wis.item_id = i.id AND wis.warehouse_id = :warehouseId
        LEFT JOIN warehouse_item_stocks all_wis ON all_wis.item_id = i.id
        GROUP BY i.id, i.name, i.description, i.min_stock, i.max_stock, i.farmable, wis.current_stock
        ORDER BY i.name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['warehouseId' => $warehouseId]);
    return $stmt->fetchAll();
}

function getItemDistribution(PDO $pdo, int $itemId): array
{
    $stmt = $pdo->prepare('SELECT w.id, w.name, COALESCE(wis.current_stock, 0) AS current_stock
        FROM warehouses w
        LEFT JOIN warehouse_item_stocks wis ON wis.warehouse_id = w.id AND wis.item_id = ?
        ORDER BY w.name ASC');
    $stmt->execute([$itemId]);
    return $stmt->fetchAll();
}

/**
 * Fetch logs for warehouse overview, optionally filtered by warehouse.
 */
function getWarehouseLogEntries(PDO $pdo, ?int $warehouseId = null, int $limit = 200): array
{
    $sql = "SELECT wl.*, w.name AS warehouse_name, i.name AS item_name, u.username FROM warehouse_logs wl
        INNER JOIN warehouses w ON wl.warehouse_id = w.id
        INNER JOIN items i ON wl.item_id = i.id
        LEFT JOIN users u ON wl.user_id = u.id";

    $params = [];
    if ($warehouseId !== null) {
        $sql .= " WHERE wl.warehouse_id = ?";
        $params[] = $warehouseId;
    }

    $sql .= " ORDER BY wl.created_at DESC LIMIT " . (int)$limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}