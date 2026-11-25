<?php
declare(strict_types=1);

final class SharedModel
{
    public const MAX_EXAMPLES_PER_BATCH = 64;
    public const MAX_EXAMPLES_PER_DAY = 50000;
    public const MAX_REQUEST_IDS = 1600;
    public const NOTE_COUNT = 88;
    public const CONTEXT_LENGTH = 4;

    private const MAX_REQUEST_BYTES = 131072;
    private const REQUEST_ID_PATTERN = '/\A[A-Za-z0-9_-]{1,80}\z/';

    public static function database(): PDO
    {
        $configuredPath = getenv('PIANO_DB_PATH');
        if ($configuredPath !== false && $configuredPath !== '') {
            $databasePath = $configuredPath;
        } else {
            $dataDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR .
                'piano-ai-data-' . substr(hash('sha256', __DIR__), 0, 12);
            $databasePath = $dataDirectory . DIRECTORY_SEPARATOR . 'shared-model.sqlite';
        }

        $directory = dirname($databasePath);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Could not create the SQLite data directory.');
        }

        $database = new PDO('sqlite:' . $databasePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $database->exec('PRAGMA busy_timeout = 5000');
        $database->exec(
            'CREATE TABLE IF NOT EXISTS shared_model (' .
            'id INTEGER PRIMARY KEY CHECK (id = 1), record_json TEXT NOT NULL)'
        );
        return $database;
    }

    public static function createRecord(?string $day = null): array
    {
        return [
            'model' => PianoModel::create(),
            'contributionDate' => $day ?? gmdate('Y-m-d'),
            'dailyExamples' => 0,
            'recentRequestIds' => [],
        ];
    }

    public static function utcTimestamp(): string
    {
        $time = microtime(true);
        return gmdate('Y-m-d\TH:i:s', (int) $time) .
            sprintf('.%03dZ', (int) (($time - floor($time)) * 1000));
    }

    public static function readRecord(PDO $database): ?array
    {
        $statement = $database->query('SELECT record_json FROM shared_model WHERE id = 1');
        $row = $statement->fetch();
        if ($row === false) {
            return null;
        }

        $record = json_decode($row['record_json'], true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($record) || !self::isValidRecord($record)) {
            throw new RuntimeException('Stored shared model is invalid.');
        }

        return $record;
    }

    public static function saveRecord(PDO $database, array $record): void
    {
        $statement = $database->prepare(
            'INSERT INTO shared_model (id, record_json) VALUES (1, :record) ' .
            'ON CONFLICT(id) DO UPDATE SET record_json = excluded.record_json'
        );
        $statement->execute([
            ':record' => json_encode(
                $record,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
            ),
        ]);
    }

    public static function publicModel(array $model): array
    {
        return [
            'version' => 3,
            'weights' => $model['weights'],
            'examples' => $model['examples'],
            'history' => [],
            'trainedAt' => $model['trainedAt'] ?? null,
        ];
    }

    public static function sendJson(int $status, array $body): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function readJsonRequest(): ?stdClass
    {
        $contentLength = $_SERVER['CONTENT_LENGTH'] ?? null;
        if (is_string($contentLength) && ctype_digit($contentLength) &&
            (int) $contentLength > self::MAX_REQUEST_BYTES) {
            return null;
        }

        $body = file_get_contents('php://input', false, null, 0, self::MAX_REQUEST_BYTES + 1);
        if ($body === false || strlen($body) > self::MAX_REQUEST_BYTES) {
            return null;
        }

        try {
            $decoded = json_decode($body, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            return null;
        }

        return $decoded instanceof stdClass ? $decoded : null;
    }

    public static function parseExamples(?stdClass $body): ?array
    {
        if ($body === null || !isset($body->examples) ||
            !is_array($body->examples) || count($body->examples) === 0 ||
            count($body->examples) > self::MAX_EXAMPLES_PER_BATCH) {
            return null;
        }

        $examples = [];
        foreach ($body->examples as $example) {
            $targetNotes = $example instanceof stdClass && isset($example->notes)
                ? $example->notes
                : ($example instanceof stdClass && isset($example->note)
                    ? [$example->note]
                    : null);
            if (!$example instanceof stdClass ||
                !isset($example->context) || !is_array($example->context) ||
                count($example->context) > self::CONTEXT_LENGTH ||
                !is_array($targetNotes) || count($targetNotes) === 0 ||
                count($targetNotes) > self::NOTE_COUNT) {
                return null;
            }

            $context = [];
            foreach ($example->context as $note) {
                if (!PianoModel::isInteger($note) || $note < 0 || $note >= self::NOTE_COUNT) {
                    return null;
                }
                $context[] = (int) $note;
            }
            $notes = [];
            foreach ($targetNotes as $note) {
                if (!PianoModel::isInteger($note) || $note < 0 || $note >= self::NOTE_COUNT) {
                    return null;
                }
                $notes[] = (int) $note;
            }
            $examples[] = ['context' => $context, 'notes' => array_values(array_unique($notes))];
        }

        return $examples;
    }

    public static function isValidRequestId($requestId): bool
    {
        return is_string($requestId) && preg_match(self::REQUEST_ID_PATTERN, $requestId) === 1;
    }

    public static function isValidRecord(array $record): bool
    {
        if (!isset($record['model']) || !is_array($record['model']) ||
            !PianoModel::isValid($record['model']) ||
            !isset($record['contributionDate']) ||
            !is_string($record['contributionDate']) ||
            preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $record['contributionDate']) !== 1 ||
            !PianoModel::isInteger($record['dailyExamples'] ?? null) ||
            $record['dailyExamples'] < 0 ||
            $record['dailyExamples'] > self::MAX_EXAMPLES_PER_DAY ||
            !isset($record['recentRequestIds']) ||
            !is_array($record['recentRequestIds']) ||
            !self::isList($record['recentRequestIds']) ||
            count($record['recentRequestIds']) > self::MAX_REQUEST_IDS) {
            return false;
        }

        foreach ($record['recentRequestIds'] as $requestId) {
            if (!self::isValidRequestId($requestId)) {
                return false;
            }
        }

        return true;
    }

    private static function isList(array $values): bool
    {
        $index = 0;
        foreach ($values as $key => $_) {
            if ($key !== $index) {
                return false;
            }
            $index++;
        }
        return true;
    }
}
