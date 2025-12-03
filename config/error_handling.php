<?php
// Zentrales Fehler-Handling für das Panel.
// Aktivieren Sie APP_DEBUG=1 in der Umgebung, um Fehlermeldungen direkt im Browser anzuzeigen.

if (!defined('APP_ERROR_HANDLING_INIT')) {
    define('APP_ERROR_HANDLING_INIT', true);

    $debugEnabled = in_array(getenv('APP_DEBUG'), ['1', 'true', 'TRUE'], true);

    error_reporting(E_ALL);
    ini_set('display_errors', $debugEnabled ? '1' : '0');
    ini_set('log_errors', '1');

    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0777, true);
    }
    $logFile = $logDir . '/php_errors.log';
    ini_set('error_log', $logFile);

    set_error_handler(function ($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    set_exception_handler(function (Throwable $e) use ($debugEnabled, $logFile) {
        $errorMessage = sprintf(
            '[%s] %s in %s on line %d',
            date('c'),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        );
        error_log($errorMessage . "\n" . $e->getTraceAsString());

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, $errorMessage . PHP_EOL);
            return;
        }

        http_response_code(500);

        if ($debugEnabled) {
            echo '<h1>Fehler</h1>';
            echo '<p>Aktiviere APP_DEBUG nur kurzfristig in Produktionsumgebungen.</p>';
            echo '<pre>' . htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') . "\n";
            echo htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8') . '</pre>';
        } else {
            echo 'Es ist ein unerwarteter Fehler aufgetreten. Bitte versuche es später erneut.';
        }
    });

    register_shutdown_function(function () use ($debugEnabled) {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            $message = sprintf(
                '[%s] Fatal error: %s in %s on line %d',
                date('c'),
                $error['message'],
                $error['file'],
                $error['line']
            );
            error_log($message);

            if (PHP_SAPI !== 'cli') {
                http_response_code(500);
                echo $debugEnabled
                    ? '<pre>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</pre>'
                    : 'Es ist ein unerwarteter Fehler aufgetreten. Bitte versuche es später erneut.';
            }
        }
    });
}