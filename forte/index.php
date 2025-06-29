<?php
require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$slackToken = $_ENV['SLACK_BOT_TOKEN'];
$slackSigningSecret = $_ENV['SLACK_SIGNING_SECRET'];

$requestBody = file_get_contents('php://input');
$data = json_decode($requestBody, true);

if (isset($data['type']) && $data['type'] === 'url_verification') {
    header('Content-Type: application/json');
    echo json_encode(['challenge' => $data['challenge']]);
    exit;
}

function isValidSlackRequest($slackSigningSecret, $headers, $body) {
    if (!isset($headers['X-Slack-Request-Timestamp']) || !isset($headers['X-Slack-Signature'])) {
        return false;
    }
    $timestamp = $headers['X-Slack-Request-Timestamp'];
    $sig_basestring = 'v0:' . $timestamp . ':' . $body;
    $my_signature = 'v0=' . hash_hmac('sha256', $sig_basestring, $slackSigningSecret);
    return hash_equals($my_signature, $headers['X-Slack-Signature']);
}

function getAllHeadersLower() {
    $headers = [];
    foreach ($_SERVER as $key => $value) {
        if (substr($key, 0, 5) === 'HTTP_') {
            $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
            $headers[$header] = $value;
        }
    }
    return $headers;
}

$headers = [];
foreach (getAllHeadersLower() as $k => $v) {
    $headers[$k] = $v;
}
if (!isValidSlackRequest($slackSigningSecret, [
    'X-Slack-Request-Timestamp' => $_SERVER['HTTP_X_SLACK_REQUEST_TIMESTAMP'] ?? '',
    'X-Slack-Signature' => $_SERVER['HTTP_X_SLACK_SIGNATURE'] ?? ''
], $requestBody)) {
    http_response_code(401);
    echo 'Invalid request signature';
    exit;
}

if (isset($data['event']) && $data['event']['type'] === 'app_mention') {
    $user = $data['event']['user'];
    $channel = $data['event']['channel'];
    $text = "Hello, <@$user>!";

    $payload = [
        'channel' => $channel,
        'text' => $text,
    ];

    $ch = curl_init('https://slack.com/api/chat.postMessage');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $slackToken,
        'Content-Type: application/json; charset=utf-8',
    ]);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $result = curl_exec($ch);
    curl_close($ch);
}

echo 'ok';