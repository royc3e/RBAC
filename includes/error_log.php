<?php
function log_error($message) {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) mkdir($logDir, 0777, true);
    $logFile = $logDir . '/error.log';
    $entry = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
    file_put_contents($logFile, $entry, FILE_APPEND);
}

// Global error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    log_error("PHP Error [$errno] $errstr in $errfile on line $errline");
    return false; // Let PHP handle as well
});

// Global exception handler
set_exception_handler(function($exception) {
    log_error('Uncaught Exception: ' . $exception->getMessage() . ' in ' . $exception->getFile() . ' on line ' . $exception->getLine());
}); 