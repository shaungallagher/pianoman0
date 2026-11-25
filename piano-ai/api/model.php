<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/PianoModel.php';
require_once __DIR__ . '/../lib/SharedModel.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    SharedModel::sendJson(405, ['error' => 'Method not allowed.']);
}

try {
    $database = SharedModel::database();
    $record = SharedModel::readRecord($database);
    $model = $record === null ? PianoModel::create() : $record['model'];
    SharedModel::sendJson(200, SharedModel::publicModel($model));
} catch (Throwable $error) {
    error_log('Could not load the shared piano model: ' . $error->getMessage());
    SharedModel::sendJson(503, ['error' => 'Shared learning is unavailable.']);
}
