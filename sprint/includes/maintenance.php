<?php
http_response_code(503);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Maintenance</title>
    <style>
        body{font-family:Inter, system-ui, -apple-system, "Segoe UI", Roboto, Arial; background:#f7f7fa;color:#111;display:flex;align-items:center;justify-content:center;height:100vh;margin:0}
        .box{max-width:720px;padding:24px;border-radius:12px;background:#fff;box-shadow:0 8px 24px rgba(0,0,0,0.08);text-align:center}
        h1{margin-bottom:8px}
        p{color:#333}
    </style>
</head>
<body>
    <div class="box">
        <h1>We'll be back soon</h1>
        <?php if (!empty($maintenance_message)): ?>
            <p><?= htmlspecialchars($maintenance_message) ?></p>
        <?php else: ?>
            <p>The site is currently undergoing maintenance. Please check back later.</p>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
exit;
