<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/PianoModel.php';
require_once __DIR__ . '/../lib/SharedModel.php';

header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    SharedModel::sendJson(405, ['error' => 'Method not allowed.']);
}

$body = SharedModel::readJsonRequest();
$examples = SharedModel::parseExamples($body);
$requestId = $body->requestId ?? null;
if ($examples === null || !SharedModel::isValidRequestId($requestId)) {
    SharedModel::sendJson(400, ['error' => 'Invalid note examples.']);
}

$database = null;
try {
    $database = SharedModel::database();
    $database->exec('BEGIN IMMEDIATE');

    $record = SharedModel::readRecord($database);
    if ($record === null) {
        $record = SharedModel::createRecord();
    }
    $record['model']['version'] = 3;

    if (in_array($requestId, $record['recentRequestIds'], true)) {
        $database->exec('COMMIT');
        SharedModel::sendJson(202, [
            'accepted' => count($examples),
            'duplicate' => true,
        ]);
    }

    $day = gmdate('Y-m-d');
    $dailyExamples = $record['contributionDate'] === $day
        ? (int) $record['dailyExamples']
        : 0;
    if ($dailyExamples + count($examples) > SharedModel::MAX_EXAMPLES_PER_DAY) {
        $database->exec('COMMIT');
        SharedModel::sendJson(429, ['error' => 'Daily contribution limit reached.']);
    }

    foreach ($examples as $example) {
        PianoModel::learnFromContext(
            $record['model'],
            $example['context'],
            $example['notes']
        );
    }
    $record['model']['trainedAt'] = SharedModel::utcTimestamp();
    $record['contributionDate'] = $day;
    $record['dailyExamples'] = $dailyExamples + count($examples);
    $record['recentRequestIds'][] = $requestId;
    if (count($record['recentRequestIds']) > SharedModel::MAX_REQUEST_IDS) {
        array_shift($record['recentRequestIds']);
    }

    SharedModel::saveRecord($database, $record);
    $database->exec('COMMIT');
    SharedModel::sendJson(202, ['accepted' => count($examples)]);
} catch (Throwable $error) {
    if ($database instanceof PDO && $database->inTransaction()) {
        $database->rollBack();
    }
    error_log('Could not save shared piano examples: ' . $error->getMessage());
    SharedModel::sendJson(503, ['error' => 'Shared learning is unavailable.']);
}
