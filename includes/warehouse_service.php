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
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

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

function createOrUpdateItem(PDO $pdo, string $name, string $description, int $minStock, int $maxStock): int
{
    ensureWarehouseSchema($pdo);

    $stmt = $pdo->prepare('SELECT id FROM items WHERE name = ? LIMIT 1');
    $stmt->execute([$name]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $upd = $pdo->prepare('UPDATE items SET description = ?, min_stock = ?, max_stock = ? WHERE id = ?');
        $upd->execute([$description, $minStock, $maxStock, (int)$existing['id']]);
        return (int)$existing['id'];
    }

    $ins = $pdo->prepare('INSERT INTO items (name, description, min_stock, max_stock) VALUES (?,?,?,?)');
    $ins->execute([$name, $description, $minStock, $maxStock]);
    return (int)$pdo->lastInsertId();
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

/**
 * Returns items for a warehouse.
 */
function getWarehouseItems(PDO $pdo, int $warehouseId): array
{
    $sql = "SELECT i.id, i.name, i.description, i.min_stock, i.max_stock,
            COALESCE(wis.current_stock, 0) AS current_stock,
            COALESCE(SUM(all_wis.current_stock), 0) AS total_stock
        FROM items i
        LEFT JOIN warehouse_item_stocks wis ON wis.item_id = i.id AND wis.warehouse_id = :warehouseId
        LEFT JOIN warehouse_item_stocks all_wis ON all_wis.item_id = i.id
        GROUP BY i.id, i.name, i.description, i.min_stock, i.max_stock, wis.current_stock
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