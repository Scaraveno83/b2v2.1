<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/theme_settings.php';

$includeDraft = isset($_GET['includeDraft']);
$format = $_GET['format'] ?? 'json';
$tokens = getThemeTokens($pdo, $includeDraft);

if ($format === 'css') {
    header('Content-Type: text/css; charset=utf-8');
    echo ":root {\n";
    foreach ($tokens['active_css_variables'] as $key => $value) {
        $safeValue = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '    --' . $key . ': ' . $safeValue . ";\n";
    }
    echo "}\n";
    if ($includeDraft && $tokens['draft_css_variables']) {
        echo "\n/* Draft Preview */\n:root.draft-preview {\n";
        foreach ($tokens['draft_css_variables'] as $key => $value) {
            $safeValue = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            echo '    --' . $key . ': ' . $safeValue . ";\n";
        }
        echo "}\n";
    }
    exit;
}

header('Content-Type: application/json; charset=utf-8');
$response = [
    'tokens' => $tokens,
    'generated_at' => gmdate('c'),
];
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);