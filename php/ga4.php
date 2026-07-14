<?php
/**
 * Cliente GA4 Data API (sem Composer) — service account JWT + runReport.
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function jota_ga4_credentials_path(): string
{
    return defined('JOTA_GA_CREDENTIALS_FILE')
        ? JOTA_GA_CREDENTIALS_FILE
        : (JOTA_LEADS_DIR . '/ga-service-account.json');
}

function jota_ga4_configured(): bool
{
    $path = jota_ga4_credentials_path();
    if (!is_file($path)) {
        return false;
    }
    $json = json_decode((string) file_get_contents($path), true);
    return is_array($json)
        && !empty($json['client_email'])
        && !empty($json['private_key'])
        && (($json['type'] ?? '') === 'service_account');
}

function jota_ga4_load_credentials(): array
{
    $path = jota_ga4_credentials_path();
    if (!is_file($path)) {
        throw new RuntimeException(
            'Arquivo de credenciais GA4 ausente: dados/ga-service-account.json. '
            . 'Crie uma conta de serviço no Google Cloud, baixe o JSON e envie para essa pasta.'
        );
    }
    $json = json_decode((string) file_get_contents($path), true);
    if (!is_array($json) || empty($json['client_email']) || empty($json['private_key'])) {
        throw new RuntimeException('Credenciais GA4 inválidas em ga-service-account.json.');
    }
    return $json;
}

function jota_ga4_base64url(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function jota_ga4_access_token(bool $force = false): string
{
    jota_ensure_data_dirs();
    $cacheFile = JOTA_ANALYTICS_DIR . '/ga-token.json';
    if (!$force && is_file($cacheFile)) {
        $cached = jota_read_json($cacheFile, []);
        if (
            is_array($cached)
            && !empty($cached['access_token'])
            && (int) ($cached['expires_at'] ?? 0) > (time() + 60)
        ) {
            return (string) $cached['access_token'];
        }
    }

    $creds = jota_ga4_load_credentials();
    $now = time();
    $header = jota_ga4_base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_UNESCAPED_SLASHES) ?: '{}');
    $claim = jota_ga4_base64url(json_encode([
        'iss' => $creds['client_email'],
        'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600,
    ], JSON_UNESCAPED_SLASHES) ?: '{}');

    $unsigned = $header . '.' . $claim;
    $key = openssl_pkey_get_private((string) $creds['private_key']);
    if ($key === false) {
        throw new RuntimeException('Não foi possível ler a private_key da conta de serviço GA4.');
    }
    $signature = '';
    $ok = openssl_sign($unsigned, $signature, $key, OPENSSL_ALGO_SHA256);
    if (!$ok) {
        throw new RuntimeException('Falha ao assinar JWT da conta de serviço GA4.');
    }
    $jwt = $unsigned . '.' . jota_ga4_base64url($signature);

    $response = jota_ga4_http_post_form('https://oauth2.googleapis.com/token', [
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt,
    ]);
    $token = $response['access_token'] ?? '';
    if (!is_string($token) || $token === '') {
        $err = (string) ($response['error_description'] ?? $response['error'] ?? 'token vazio');
        throw new RuntimeException('OAuth GA4 falhou: ' . $err);
    }

    jota_write_json($cacheFile, [
        'access_token' => $token,
        'expires_at' => $now + (int) ($response['expires_in'] ?? 3600),
    ]);

    return $token;
}

function jota_ga4_http_post_form(string $url, array $fields): array
{
    $body = http_build_query($fields);
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            throw new RuntimeException('cURL OAuth: ' . $cerr);
        }
    } else {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $body,
                'timeout' => 20,
                'ignore_errors' => true,
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        $code = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', (string) $http_response_header[0], $m)) {
            $code = (int) $m[1];
        }
        if ($raw === false) {
            throw new RuntimeException('Falha HTTP no OAuth GA4.');
        }
    }

    $json = json_decode((string) $raw, true);
    if (!is_array($json)) {
        throw new RuntimeException('Resposta OAuth inválida (HTTP ' . $code . ').');
    }
    return $json;
}

function jota_ga4_http_post_json(string $url, array $payload, string $accessToken): array
{
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($body === false) {
        throw new RuntimeException('Falha ao montar JSON do relatório GA4.');
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken,
            ],
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            throw new RuntimeException('cURL GA4: ' . $cerr);
        }
    } else {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAuthorization: Bearer {$accessToken}\r\n",
                'content' => $body,
                'timeout' => 45,
                'ignore_errors' => true,
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        $code = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', (string) $http_response_header[0], $m)) {
            $code = (int) $m[1];
        }
        if ($raw === false) {
            throw new RuntimeException('Falha HTTP no relatório GA4.');
        }
    }

    $json = json_decode((string) $raw, true);
    if (!is_array($json)) {
        throw new RuntimeException('Resposta GA4 inválida (HTTP ' . $code . ').');
    }
    if ($code >= 400 || isset($json['error'])) {
        $msg = (string) ($json['error']['message'] ?? ('HTTP ' . $code));
        throw new RuntimeException('GA4 API: ' . $msg);
    }
    return $json;
}

function jota_ga4_date_range(string $period): array
{
    $end = date('Y-m-d');
    if ($period === 'today') {
        return ['startDate' => $end, 'endDate' => $end];
    }
    if ($period === 'all') {
        return ['startDate' => '2020-01-01', 'endDate' => $end];
    }
    $map = ['7' => 6, '30' => 29, '90' => 89];
    $daysBack = $map[$period] ?? 29;
    return [
        'startDate' => date('Y-m-d', strtotime("-{$daysBack} days")),
        'endDate' => $end,
    ];
}

function jota_ga4_period_label(string $period): string
{
    return [
        'today' => 'Hoje',
        '7' => '7 dias',
        '30' => '30 dias',
        '90' => '90 dias',
        'all' => 'Todo período',
    ][$period] ?? '30 dias';
}

function jota_ga4_run_report(array $body, string $accessToken): array
{
    $propertyId = preg_replace('/\D+/', '', (string) JOTA_GA_PROPERTY_ID) ?: '';
    if ($propertyId === '') {
        throw new RuntimeException('JOTA_GA_PROPERTY_ID inválido.');
    }
    $url = 'https://analyticsdata.googleapis.com/v1beta/properties/' . $propertyId . ':runReport';
    return jota_ga4_http_post_json($url, $body, $accessToken);
}

/**
 * Extrai linhas [{dims:[...], mets:[...]}] de um runReport.
 */
function jota_ga4_rows(array $report): array
{
    $out = [];
    foreach (($report['rows'] ?? []) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $dims = [];
        foreach (($row['dimensionValues'] ?? []) as $d) {
            $dims[] = (string) ($d['value'] ?? '');
        }
        $mets = [];
        foreach (($row['metricValues'] ?? []) as $m) {
            $mets[] = (string) ($m['value'] ?? '0');
        }
        $out[] = ['dims' => $dims, 'mets' => $mets];
    }
    return $out;
}

function jota_ga4_metric_total(array $report, int $index = 0): float
{
    $totals = $report['totals'][0]['metricValues'][$index]['value']
        ?? $report['rows'][0]['metricValues'][$index]['value']
        ?? null;
    if ($totals !== null) {
        return (float) $totals;
    }
    $sum = 0.0;
    foreach (jota_ga4_rows($report) as $row) {
        $sum += (float) ($row['mets'][$index] ?? 0);
    }
    return $sum;
}

function jota_ga4_cache_file(string $period): string
{
    return JOTA_ANALYTICS_DIR . '/ga-cache-' . preg_replace('/[^a-z0-9_-]/i', '', $period) . '.json';
}

function jota_ga4_read_cache(string $period): ?array
{
    $ttl = defined('JOTA_GA_CACHE_TTL') ? (int) JOTA_GA_CACHE_TTL : 900;
    $file = jota_ga4_cache_file($period);
    if (!is_file($file)) {
        return null;
    }
    $data = jota_read_json($file, []);
    if (!is_array($data) || empty($data['payload']) || !is_array($data['payload'])) {
        return null;
    }
    if ((time() - (int) ($data['cached_at'] ?? 0)) > $ttl) {
        return null;
    }
    return $data['payload'];
}

function jota_ga4_write_cache(string $period, array $payload): void
{
    jota_ensure_data_dirs();
    jota_write_json(jota_ga4_cache_file($period), [
        'cached_at' => time(),
        'payload' => $payload,
    ]);
}

function jota_ga4_channel_bucket(string $channel): string
{
    $c = strtolower(trim($channel));
    if ($c === '' || $c === '(not set)' || str_contains($c, 'direct')) {
        return 'direct';
    }
    if (str_contains($c, 'organic') || str_contains($c, 'search')) {
        return 'search';
    }
    if (str_contains($c, 'social')) {
        return 'social';
    }
    if (str_contains($c, 'paid') || str_contains($c, 'cpc') || str_contains($c, 'display')) {
        return 'other';
    }
    return 'other';
}

/**
 * Summary no mesmo formato do painel (KPIs + listas), fonte GA4.
 */
function jota_ga4_summary(string $period = '30'): array
{
    $cached = jota_ga4_read_cache($period);
    if ($cached !== null) {
        // Recarrega partes locais sempre frescas
        require_once __DIR__ . '/messages.php';
        require_once __DIR__ . '/analytics.php';
        $counts = jota_messages_counts();
        $cached['unread_messages'] = (int) ($counts['new'] ?? 0);
        $cached['messages'] = array_slice(jota_messages_all(), 0, 20);
        $cached['online'] = jota_analytics_online_count();
        $cached['from_cache'] = true;
        return $cached;
    }

    $accessToken = jota_ga4_access_token();
    $range = jota_ga4_date_range($period);
    $dateRanges = [$range];

    $overview = jota_ga4_run_report([
        'dateRanges' => $dateRanges,
        'metrics' => [
            ['name' => 'totalUsers'],
            ['name' => 'sessions'],
            ['name' => 'screenPageViews'],
            ['name' => 'averageSessionDuration'],
            ['name' => 'bounceRate'],
            ['name' => 'screenPageViewsPerSession'],
            ['name' => 'engagedSessions'],
        ],
        'metricAggregations' => ['TOTAL'],
    ], $accessToken);

    $byDate = jota_ga4_run_report([
        'dateRanges' => $dateRanges,
        'dimensions' => [['name' => 'date']],
        'metrics' => [
            ['name' => 'totalUsers'],
            ['name' => 'sessions'],
            ['name' => 'screenPageViews'],
        ],
        'orderBys' => [['dimension' => ['dimensionName' => 'date'], 'desc' => false]],
        'limit' => 400,
    ], $accessToken);

    $byHour = jota_ga4_run_report([
        'dateRanges' => $dateRanges,
        'dimensions' => [['name' => 'hour']],
        'metrics' => [['name' => 'sessions']],
        'orderBys' => [['dimension' => ['dimensionName' => 'hour'], 'desc' => false]],
        'limit' => 24,
    ], $accessToken);

    $byWeekday = jota_ga4_run_report([
        'dateRanges' => $dateRanges,
        'dimensions' => [['name' => 'dayOfWeek']],
        'metrics' => [['name' => 'sessions']],
        'limit' => 7,
    ], $accessToken);

    $byPage = jota_ga4_run_report([
        'dateRanges' => $dateRanges,
        'dimensions' => [['name' => 'pagePath']],
        'metrics' => [['name' => 'screenPageViews']],
        'orderBys' => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => true]],
        'limit' => 15,
    ], $accessToken);

    $byDevice = jota_ga4_run_report([
        'dateRanges' => $dateRanges,
        'dimensions' => [['name' => 'deviceCategory']],
        'metrics' => [['name' => 'sessions']],
        'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]],
        'limit' => 10,
    ], $accessToken);

    $byBrowser = jota_ga4_run_report([
        'dateRanges' => $dateRanges,
        'dimensions' => [['name' => 'browser']],
        'metrics' => [['name' => 'sessions']],
        'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]],
        'limit' => 10,
    ], $accessToken);

    $byOs = jota_ga4_run_report([
        'dateRanges' => $dateRanges,
        'dimensions' => [['name' => 'operatingSystem']],
        'metrics' => [['name' => 'sessions']],
        'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]],
        'limit' => 10,
    ], $accessToken);

    $byChannel = jota_ga4_run_report([
        'dateRanges' => $dateRanges,
        'dimensions' => [['name' => 'sessionDefaultChannelGroup']],
        'metrics' => [['name' => 'sessions']],
        'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]],
        'limit' => 20,
    ], $accessToken);

    $byCountry = jota_ga4_run_report([
        'dateRanges' => $dateRanges,
        'dimensions' => [['name' => 'country']],
        'metrics' => [['name' => 'sessions']],
        'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]],
        'limit' => 15,
    ], $accessToken);

    $byCity = jota_ga4_run_report([
        'dateRanges' => $dateRanges,
        'dimensions' => [['name' => 'city'], ['name' => 'country']],
        'metrics' => [
            ['name' => 'sessions'],
            ['name' => 'screenPageViews'],
        ],
        'orderBys' => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => true]],
        'limit' => 20,
    ], $accessToken);

    $landing = jota_ga4_run_report([
        'dateRanges' => $dateRanges,
        'dimensions' => [['name' => 'landingPage']],
        'metrics' => [['name' => 'sessions']],
        'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]],
        'limit' => 10,
    ], $accessToken);

    $exitPages = [];
    try {
        $exitReport = jota_ga4_run_report([
            'dateRanges' => $dateRanges,
            'dimensions' => [['name' => 'pagePath']],
            'metrics' => [['name' => 'sessions']],
            'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]],
            'limit' => 10,
            // Aprox.: páginas mais vistas no período (GA4 não expõe exitPage em todas as propriedades)
        ], $accessToken);
        foreach (jota_ga4_rows($exitReport) as $row) {
            $path = $row['dims'][0] !== '' ? $row['dims'][0] : '/';
            $exitPages[$path] = (int) round((float) ($row['mets'][0] ?? 0));
        }
    } catch (Throwable $e) {
        $exitPages = [];
    }

    $visitors = (int) round(jota_ga4_metric_total($overview, 0));
    $sessions = (int) round(jota_ga4_metric_total($overview, 1));
    $views = (int) round(jota_ga4_metric_total($overview, 2));
    $avgDuration = (int) round(jota_ga4_metric_total($overview, 3));
    $bounceRate = round(jota_ga4_metric_total($overview, 4) * 100, 1); // GA4 returns 0–1
    // Some properties return bounceRate already as percentage; clamp sanity
    if ($bounceRate > 100) {
        $bounceRate = round(jota_ga4_metric_total($overview, 4), 1);
    }
    $pagesPerSession = round(jota_ga4_metric_total($overview, 5), 1);

    $series = [];
    foreach (jota_ga4_rows($byDate) as $row) {
        $rawDate = $row['dims'][0] ?? '';
        $ts = strtotime(strlen($rawDate) === 8
            ? substr($rawDate, 0, 4) . '-' . substr($rawDate, 4, 2) . '-' . substr($rawDate, 6, 2)
            : $rawDate);
        $series[] = [
            'date' => $ts ? date('Y-m-d', $ts) : $rawDate,
            'label' => $ts ? date('d/m', $ts) : $rawDate,
            'visitors' => (int) round((float) ($row['mets'][0] ?? 0)),
            'sessions' => (int) round((float) ($row['mets'][1] ?? 0)),
            'views' => (int) round((float) ($row['mets'][2] ?? 0)),
        ];
    }

    $hours = array_fill(0, 24, 0);
    foreach (jota_ga4_rows($byHour) as $row) {
        $h = (int) ($row['dims'][0] ?? -1);
        if ($h >= 0 && $h <= 23) {
            $hours[$h] = (int) round((float) ($row['mets'][0] ?? 0));
        }
    }

    $weekdayNames = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
    $weekdays = array_fill(0, 7, 0);
    foreach (jota_ga4_rows($byWeekday) as $row) {
        // GA4 dayOfWeek: 0=Sunday .. 6=Saturday
        $d = (int) ($row['dims'][0] ?? -1);
        if ($d >= 0 && $d <= 6) {
            $weekdays[$d] = (int) round((float) ($row['mets'][0] ?? 0));
        }
    }
    $weekdaysNamed = [];
    foreach ($weekdayNames as $i => $name) {
        $weekdaysNamed[] = ['name' => $name, 'count' => (int) $weekdays[$i]];
    }

    $pages = [];
    foreach (jota_ga4_rows($byPage) as $row) {
        $path = $row['dims'][0] !== '' ? $row['dims'][0] : '/';
        $pages[$path] = (int) round((float) ($row['mets'][0] ?? 0));
    }

    $devices = [];
    $devicesList = [];
    $deviceTotal = 0;
    foreach (jota_ga4_rows($byDevice) as $row) {
        $name = $row['dims'][0] !== '' ? $row['dims'][0] : '(desconhecido)';
        $count = (int) round((float) ($row['mets'][0] ?? 0));
        $devices[strtolower($name)] = $count;
        $deviceTotal += $count;
        $devicesList[] = ['name' => ucfirst($name), 'count' => $count, 'pct' => 0.0];
    }
    $deviceTotal = $deviceTotal ?: 1;
    foreach ($devicesList as &$d) {
        $d['pct'] = round(($d['count'] / $deviceTotal) * 100, 1);
    }
    unset($d);

    $browsers = [];
    foreach (jota_ga4_rows($byBrowser) as $row) {
        $browsers[$row['dims'][0] !== '' ? $row['dims'][0] : 'Unknown'] = (int) round((float) ($row['mets'][0] ?? 0));
    }

    $os = [];
    foreach (jota_ga4_rows($byOs) as $row) {
        $os[$row['dims'][0] !== '' ? $row['dims'][0] : 'Unknown'] = (int) round((float) ($row['mets'][0] ?? 0));
    }

    $sources = ['social' => 0, 'search' => 0, 'direct' => 0, 'other' => 0];
    foreach (jota_ga4_rows($byChannel) as $row) {
        $bucket = jota_ga4_channel_bucket($row['dims'][0] ?? '');
        $sources[$bucket] = (int) ($sources[$bucket] ?? 0) + (int) round((float) ($row['mets'][0] ?? 0));
    }

    $countries = [];
    foreach (jota_ga4_rows($byCountry) as $row) {
        $countries[$row['dims'][0] !== '' ? $row['dims'][0] : '(desconhecido)'] = (int) round((float) ($row['mets'][0] ?? 0));
    }

    $citiesDetail = [];
    foreach (jota_ga4_rows($byCity) as $row) {
        $city = $row['dims'][0] !== '' ? $row['dims'][0] : '(desconhecido)';
        $country = $row['dims'][1] !== '' ? $row['dims'][1] : '';
        $key = $city . ($country !== '' ? '(' . $country . ')' : '');
        $citiesDetail[$key] = [
            'sessions' => (int) round((float) ($row['mets'][0] ?? 0)),
            'views' => (int) round((float) ($row['mets'][1] ?? 0)),
        ];
    }

    $entry = [];
    foreach (jota_ga4_rows($landing) as $row) {
        $path = $row['dims'][0] !== '' ? $row['dims'][0] : '/';
        $entry[$path] = (int) round((float) ($row['mets'][0] ?? 0));
    }

    require_once __DIR__ . '/messages.php';
    require_once __DIR__ . '/analytics.php';
    $counts = jota_messages_counts();

    // Cliques: complemento do tracking first-party (GA não tem WhatsApp por padrão)
    $clicksNamed = [
        ['name' => 'WhatsApp', 'count' => 0],
        ['name' => 'E-mail', 'count' => 0],
        ['name' => 'Links', 'count' => 0],
        ['name' => 'CTAs', 'count' => 0],
    ];
    $whatsapp = 0;
    $email = 0;
    $links = 0;
    $ctas = 0;
    $forms = 0;
    try {
        $dates = jota_analytics_period_dates($period);
        foreach ($dates as $date) {
            $day = jota_analytics_load_day($date);
            $whatsapp += (int) ($day['whatsapp_clicks'] ?? 0);
            $email += (int) ($day['email_clicks'] ?? 0);
            $links += (int) ($day['link_clicks'] ?? 0);
            $ctas += (int) ($day['cta_clicks'] ?? 0);
            $forms += (int) ($day['form_submits'] ?? 0);
        }
        $clicksNamed = [
            ['name' => 'WhatsApp', 'count' => $whatsapp],
            ['name' => 'E-mail', 'count' => $email],
            ['name' => 'Links', 'count' => $links],
            ['name' => 'CTAs', 'count' => $ctas],
        ];
    } catch (Throwable $e) {
        // ignore
    }
    $clicksTotal = $whatsapp + $email + $links + $ctas;
    $conversion = $sessions > 0 ? round(($clicksTotal / $sessions) * 100, 1) : 0.0;

    $summary = [
        'source' => 'ga4',
        'from_cache' => false,
        'period' => $period,
        'period_label' => jota_ga4_period_label($period),
        'online' => jota_analytics_online_count(),
        'views' => $views,
        'visitors' => $visitors,
        'sessions' => $sessions,
        'whatsapp_clicks' => $whatsapp,
        'email_clicks' => $email,
        'link_clicks' => $links,
        'cta_clicks' => $ctas,
        'form_submits' => $forms,
        'clicks_total' => $clicksTotal,
        'avg_duration' => $avgDuration,
        'bounce_rate' => $bounceRate,
        'pages_per_session' => $pagesPerSession,
        'conversion_rate' => $conversion,
        'views_per_visitor' => $visitors > 0 ? round($views / $visitors, 1) : 0,
        'pages' => $pages,
        'entry' => $entry,
        'exit' => $exitPages,
        'devices' => $devices,
        'devices_list' => $devicesList,
        'browsers' => $browsers,
        'os' => $os,
        'sources' => $sources,
        'sources_named' => [
            ['name' => 'Redes Sociais', 'count' => (int) $sources['social']],
            ['name' => 'Buscadores', 'count' => (int) $sources['search']],
            ['name' => 'Direto', 'count' => (int) $sources['direct']],
            ['name' => 'Outros', 'count' => (int) $sources['other']],
        ],
        'clicks_named' => $clicksNamed,
        'countries' => $countries,
        'cities' => [],
        'cities_detail' => $citiesDetail,
        'ips' => [],
        'hours' => $hours,
        'weekdays' => $weekdays,
        'weekdays_named' => $weekdaysNamed,
        'series' => $series,
        'unread_messages' => (int) ($counts['new'] ?? 0),
        'messages' => array_slice(jota_messages_all(), 0, 20),
        'admin_name' => defined('JOTA_ADMIN_DISPLAY_NAME') ? JOTA_ADMIN_DISPLAY_NAME : 'Admin',
        'ga_id' => JOTA_GA_MEASUREMENT_ID,
        'ga_property_id' => (string) JOTA_GA_PROPERTY_ID,
    ];

    jota_ga4_write_cache($period, $summary);
    return $summary;
}
