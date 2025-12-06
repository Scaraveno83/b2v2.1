<?php
if (!function_exists('session_status') || session_status() !== PHP_SESSION_ACTIVE) {
    // Session handling is done by the caller (layout or explicit checks).
}

/**
 * Ensure that the module settings table exists.
 */
function ensureModuleSettingsTable(PDO $pdo): void
{
    static $initialized = false;
    if ($initialized) {
        return;
    }

    $initialized = true;

    $pdo->exec("CREATE TABLE IF NOT EXISTS module_settings (
        setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
        setting_value VARCHAR(20) NOT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

/**
 * Default states for all togglable modules.
 */
function getDefaultModuleSettings(): array
{
    return [
        'farming_tasks_enabled'     => true,
        'processing_tasks_enabled'  => true,
    ];
}

/**
 * Load module settings merged with defaults.
 */
function getModuleSettings(PDO $pdo): array
{
    ensureModuleSettingsTable($pdo);

    $defaults = getDefaultModuleSettings();
    $rows = $pdo->query('SELECT setting_key, setting_value FROM module_settings')->fetchAll(PDO::FETCH_KEY_PAIR);

    foreach ($rows as $key => $value) {
        $defaults[$key] = $value === '1' || $value === 1 || $value === true;
    }

    return $defaults;
}

/**
 * Persist module settings.
 */
function saveModuleSettings(PDO $pdo, array $values): array
{
    ensureModuleSettingsTable($pdo);

    $defaults = getDefaultModuleSettings();
    $normalized = [];

    foreach ($defaults as $key => $default) {
        $normalized[$key] = !empty($values[$key]);
    }

    $stmt = $pdo->prepare('REPLACE INTO module_settings (setting_key, setting_value) VALUES (:key, :value)');
    foreach ($normalized as $key => $state) {
        $stmt->execute([
            ':key'   => $key,
            ':value' => $state ? '1' : '0',
        ]);
    }

    return $normalized;
}

/**
 * Quick helper to check if a module is enabled.
 */
function isModuleEnabled(PDO $pdo, string $key): bool
{
    $settings = getModuleSettings($pdo);

    return !empty($settings[$key]);
}