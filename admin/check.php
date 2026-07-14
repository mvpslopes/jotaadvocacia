<?php
/**
 * Diagnóstico rápido do painel (sem dados sensíveis).
 * Acesse: /admin/check.php
 */
header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex');

echo "JOTA Admin check\n";
echo 'PHP: ' . PHP_VERSION . "\n";
echo 'SAPI: ' . PHP_SAPI . "\n\n";

$files = [
    '../php/config.php',
    '../php/helpers.php',
    '../php/analytics.php',
    '../php/messages.php',
    '../css/admin.css',
    'login.php',
    'index.php',
    'inc/header.php',
    'inc/footer.php',
];

foreach ($files as $rel) {
    $path = __DIR__ . '/' . $rel;
    echo $rel . ' => ' . (is_file($path) ? 'OK' : 'AUSENTE') . "\n";
}

echo "\n--- Testes ---\n";
try {
    require_once dirname(__DIR__) . '/php/helpers.php';
    echo "helpers OK\n";
    jota_ensure_data_dirs();
    echo "dirs OK\n";
    jota_admin_boot_session();
    echo "session OK\n";

    require_once dirname(__DIR__) . '/php/analytics.php';
    echo "analytics load OK\n";
    $stats = jota_analytics_summary(7);
    echo 'summary OK views=' . (int) $stats['views'] . ' series=' . count($stats['series']) . "\n";

    require_once dirname(__DIR__) . '/php/messages.php';
    $counts = jota_messages_counts();
    echo 'messages OK new=' . (int) $counts['new'] . "\n";

    echo "\nTudo certo. Acesse /admin/login.php\n";
} catch (Throwable $e) {
    echo 'ERRO: ' . $e->getMessage() . "\n";
    echo $e->getFile() . ':' . $e->getLine() . "\n";
}
