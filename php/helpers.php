<?php
/**
 * Funções utilitárias de armazenamento JSON e autenticação do painel.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function jota_ensure_data_dirs(): void
{
    if (!is_dir(JOTA_LEADS_DIR)) {
        @mkdir(JOTA_LEADS_DIR, 0755, true);
    }
    if (!is_dir(JOTA_ANALYTICS_DIR)) {
        @mkdir(JOTA_ANALYTICS_DIR, 0755, true);
    }

    $htaccess = JOTA_LEADS_DIR . '/.htaccess';
    if (!file_exists($htaccess)) {
        @file_put_contents($htaccess, "Require all denied\n");
    }
}

/**
 * @param mixed $default
 * @return mixed
 */
function jota_read_json(string $path, $default = [])
{
    if (!file_exists($path)) {
        return $default;
    }
    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
        return $default;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : $default;
}

/**
 * @param mixed $data
 */
function jota_write_json(string $path, $data): bool
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }
    return @file_put_contents($path, $json . "\n", LOCK_EX) !== false;
}

function jota_admin_boot_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    if (session_status() === PHP_SESSION_DISABLED) {
        return;
    }

    session_name(JOTA_ADMIN_SESSION_NAME);

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params(0, '/', '', $secure, true);
    }

    @session_start();
}

function jota_admin_logged_in(): bool
{
    jota_admin_boot_session();
    return !empty($_SESSION['jota_admin_ok']) && (isset($_SESSION['jota_admin_user']) && $_SESSION['jota_admin_user'] === JOTA_ADMIN_USER);
}

function jota_admin_require_login(): void
{
    if (!jota_admin_logged_in()) {
        if (!headers_sent()) {
            header('Location: login.php');
        }
        exit;
    }
}

function jota_admin_attempt_login(string $user, string $password): bool
{
    jota_admin_boot_session();
    if ($user !== JOTA_ADMIN_USER) {
        return false;
    }
    if (!password_verify($password, JOTA_ADMIN_PASSWORD_HASH)) {
        return false;
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    $_SESSION['jota_admin_ok'] = true;
    $_SESSION['jota_admin_user'] = JOTA_ADMIN_USER;
    $_SESSION['jota_admin_login_at'] = time();
    return true;
}

function jota_admin_logout(): void
{
    jota_admin_boot_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'] ?? '/',
            $params['domain'] ?? '',
            !empty($params['secure']),
            !empty($params['httponly'])
        );
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function jota_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function jota_whatsapp_link(string $phone, string $message = ''): string
{
    $digits = preg_replace('/\D+/', '', $phone);
    if (!is_string($digits)) {
        $digits = '';
    }
    if ($digits !== '' && strpos($digits, '55') !== 0 && strlen($digits) <= 11) {
        $digits = '55' . $digits;
    }
    $url = 'https://wa.me/' . $digits;
    if ($message !== '') {
        $url .= '?text=' . rawurlencode($message);
    }
    return $url;
}
