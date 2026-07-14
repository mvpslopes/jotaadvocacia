<?php
/**
 * Endpoint de tracking first-party.
 */

declare(strict_types=1);

require __DIR__ . '/analytics.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '', true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$type = (string) ($payload['type'] ?? '');
$allowed = ['pageview', 'heartbeat', 'whatsapp_click', 'email_click', 'link_click', 'cta_click', 'form_submit'];
if (!in_array($type, $allowed, true)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'tipo inválido']);
    exit;
}

$ok = jota_analytics_track([
    'type' => $type,
    'path' => (string) ($payload['path'] ?? '/'),
    'referrer' => (string) ($payload['referrer'] ?? ($_SERVER['HTTP_REFERER'] ?? '')),
    'visitor_id' => (string) ($payload['visitor_id'] ?? ''),
    'session_id' => (string) ($payload['session_id'] ?? ''),
    'duration' => (int) ($payload['duration'] ?? 0),
    'ua' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
]);

echo json_encode(['ok' => $ok]);
