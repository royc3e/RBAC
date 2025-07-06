<?php
$logFile = __DIR__ . '/../logs/error.log';
$logContent = file_exists($logFile) ? file_get_contents($logFile) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Error Log Viewer</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background: #f8f9fa; }
        .log-container { max-width: 900px; margin: 40px auto; }
        pre { background: #222; color: #eee; padding: 20px; border-radius: 8px; font-size: 1em; }
    </style>
</head>
<body>
<div class="log-container">
    <h2 class="mb-4">Error Log Viewer</h2>
    <?php if ($logContent): ?>
        <pre><?php echo htmlspecialchars($logContent); ?></pre>
    <?php else: ?>
        <div class="alert alert-success">No errors logged yet.</div>
    <?php endif; ?>
</div>
</body>
</html> 