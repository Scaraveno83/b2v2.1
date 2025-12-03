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

    $pdo->exec("CREATE TABLE IF NOT EXISTS warehouse_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        warehouse_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        description TEXT NULL,
        min_stock INT NOT NULL DEFAULT 0,
        max_stock INT NOT NULL DEFAULT 0,
        current_stock INT NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_wi_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE CASCADE
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
        CONSTRAINT fk_wl_item FOREIGN KEY (item_id) REFERENCES warehouse_items(id) ON DELETE CASCADE,
        CONSTRAINT fk_wl_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
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

/**
 * Adds or removes stock and records a log entry.
 */
function adjustWarehouseStock(PDO $pdo, int $warehouseId, int $itemId, int $delta, string $action, ?int $userId, string $note = ''): bool
{
    ensureWarehouseSchema($pdo);

    $stmt = $pdo->prepare("SELECT id, current_stock FROM warehouse_items WHERE id = ? AND warehouse_id = ?");
    $stmt->execute([$itemId, $warehouseId]);
    $item = $stmt->fetch();

    if (!$item) {
        return false;
    }

    $current = (int)$item['current_stock'];
    $newStock = max(0, $current + $delta);
    $actualChange = $newStock - $current;

    $upd = $pdo->prepare("UPDATE warehouse_items SET current_stock = ? WHERE id = ?");
    $upd->execute([$newStock, $itemId]);

    logWarehouseChange($pdo, $warehouseId, $itemId, $userId, $actualChange, $action, $note, $newStock);

    return true;
}

function logWarehouseChange(PDO $pdo, int $warehouseId, int $itemId, ?int $userId, int $changeAmount, string $action, string $note, int $resultingStock): void
{
    $stmt = $pdo->prepare("INSERT INTO warehouse_logs (warehouse_id, item_id, user_id, change_amount, resulting_stock, action, note)
        VALUES (?,?,?,?,?,?,?)");
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
    $stmt = $pdo->prepare("SELECT * FROM warehouse_items WHERE warehouse_id = ? ORDER BY name ASC");
    $stmt->execute([$warehouseId]);
    return $stmt->fetchAll();
}

/**
 * Fetch logs for warehouse overview, optionally filtered by warehouse.
 */
function getWarehouseLogEntries(PDO $pdo, ?int $warehouseId = null, int $limit = 200): array
{
    $sql = "SELECT wl.*, w.name AS warehouse_name, wi.name AS item_name, u.username FROM warehouse_logs wl
        INNER JOIN warehouses w ON wl.warehouse_id = w.id
        INNER JOIN warehouse_items wi ON wl.item_id = wi.id
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