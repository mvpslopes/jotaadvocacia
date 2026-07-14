<?php
/**
 * Helpers de mensagens do formulário de contato (JSON).
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function jota_messages_all(): array
{
    jota_ensure_data_dirs();
    $list = jota_read_json(JOTA_MESSAGES_FILE, []);
    if (!is_array($list)) {
        return [];
    }
    usort($list, static function ($a, $b) {
        return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
    });
    return $list;
}

function jota_messages_save(array $list): bool
{
    jota_ensure_data_dirs();
    return jota_write_json(JOTA_MESSAGES_FILE, array_values($list));
}

function jota_messages_add(array $payload): array
{
    $list = jota_messages_all();
    $item = [
        'id' => bin2hex(random_bytes(8)),
        'created_at' => date('c'),
        'name' => (string) ($payload['name'] ?? ''),
        'email' => (string) ($payload['email'] ?? ''),
        'phone' => (string) ($payload['phone'] ?? ''),
        'subject' => (string) ($payload['subject'] ?? ''),
        'message' => (string) ($payload['message'] ?? ''),
        'ip' => (string) ($payload['ip'] ?? ''),
        'status' => 'new', // new | read | archived
        'ua' => (string) ($payload['ua'] ?? ''),
    ];
    array_unshift($list, $item);
    // Mantém no máximo 2000 mensagens
    if (count($list) > 2000) {
        $list = array_slice($list, 0, 2000);
    }
    jota_messages_save($list);
    return $item;
}

function jota_messages_find(string $id): ?array
{
    foreach (jota_messages_all() as $item) {
        if (($item['id'] ?? '') === $id) {
            return $item;
        }
    }
    return null;
}

function jota_messages_update_status(string $id, string $status): bool
{
    $allowed = ['new', 'read', 'archived'];
    if (!in_array($status, $allowed, true)) {
        return false;
    }
    $list = jota_messages_all();
    $changed = false;
    foreach ($list as &$item) {
        if (($item['id'] ?? '') === $id) {
            $item['status'] = $status;
            $changed = true;
            break;
        }
    }
    unset($item);
    return $changed ? jota_messages_save($list) : false;
}

function jota_messages_counts(): array
{
    $counts = ['all' => 0, 'new' => 0, 'read' => 0, 'archived' => 0];
    foreach (jota_messages_all() as $item) {
        $counts['all']++;
        $status = (string) ($item['status'] ?? 'new');
        if (isset($counts[$status])) {
            $counts[$status]++;
        }
    }
    return $counts;
}
