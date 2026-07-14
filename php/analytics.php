<?php
/**
 * Analytics first-party completo (JSON diário).
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function jota_analytics_day_file(string $date): string
{
    return JOTA_ANALYTICS_DIR . '/day-' . $date . '.json';
}

function jota_analytics_online_file(): string
{
    return JOTA_ANALYTICS_DIR . '/online.json';
}

function jota_analytics_geo_cache_file(): string
{
    return JOTA_ANALYTICS_DIR . '/geo-cache.json';
}

function jota_analytics_empty_day(string $date = ''): array
{
    return [
        'date' => $date !== '' ? $date : date('Y-m-d'),
        'views' => 0,
        'visitors' => [],
        'session_meta' => [], // sid => [...metrics...]
        'whatsapp_clicks' => 0,
        'email_clicks' => 0,
        'link_clicks' => 0,
        'cta_clicks' => 0,
        'form_submits' => 0,
        'pages' => [],
        'entry' => [],
        'exit' => [],
        'devices' => ['desktop' => 0, 'mobile' => 0, 'tablet' => 0, 'desconhecido' => 0],
        'browsers' => [],
        'os' => [],
        'sources' => ['social' => 0, 'search' => 0, 'direct' => 0, 'other' => 0],
        'countries' => [],
        'cities' => [],
        'ips' => [],
        'hours' => array_fill(0, 24, 0),
        'weekdays' => array_fill(0, 7, 0), // 0=domingo
        'duration_sum' => 0,
        'duration_count' => 0,
        'bounce_sessions' => 0,
        'session_count' => 0,
    ];
}

function jota_analytics_parse_ua(string $ua): array
{
    $uaLower = strtolower($ua);
    $device = 'desktop';
    if (preg_match('/ipad|tablet|kindle|playbook|silk/', $uaLower)) {
        $device = 'tablet';
    } elseif (preg_match('/mobi|iphone|ipod|android.*mobile|windows phone|opera mini/', $uaLower)) {
        $device = 'mobile';
    }

    $browser = 'Unknown';
    if (preg_match('/edg\//i', $ua)) {
        $browser = 'Edge';
    } elseif (preg_match('/opr\/|opera/i', $ua)) {
        $browser = 'Opera';
    } elseif (preg_match('/chrome|crios/i', $ua) && !preg_match('/edg/i', $ua)) {
        $browser = 'Chrome';
    } elseif (preg_match('/safari/i', $ua) && !preg_match('/chrome|crios|android/i', $ua)) {
        $browser = 'Safari';
    } elseif (preg_match('/firefox|fxios/i', $ua)) {
        $browser = 'Firefox';
    } elseif (preg_match('/msie|trident/i', $ua)) {
        $browser = 'Internet Explorer';
    }

    $os = 'Unknown';
    if (preg_match('/windows nt/i', $ua)) {
        $os = 'Windows';
    } elseif (preg_match('/android/i', $ua)) {
        $os = 'Android';
    } elseif (preg_match('/iphone|ipad|ipod/i', $ua)) {
        $os = 'iOS';
    } elseif (preg_match('/mac os x|macintosh/i', $ua)) {
        $os = 'macOS';
    } elseif (preg_match('/linux/i', $ua)) {
        $os = 'Linux';
    }

    return ['device' => $device, 'browser' => $browser, 'os' => $os];
}

function jota_analytics_source(string $referrer, string $hostOwn): string
{
    $referrer = trim($referrer);
    if ($referrer === '') {
        return 'direct';
    }
    $host = parse_url($referrer, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        return 'other';
    }
    $host = strtolower(preg_replace('/^www\./', '', $host) ?: $host);
    $own = strtolower(preg_replace('/^www\./', '', $hostOwn) ?: $hostOwn);
    if ($own !== '' && $host === $own) {
        return 'direct';
    }

    $social = ['instagram.com', 'facebook.com', 'fb.com', 'm.facebook.com', 't.co', 'twitter.com', 'x.com', 'linkedin.com', 'tiktok.com', 'youtube.com', 'whatsapp.com', 'pinterest.com'];
    foreach ($social as $s) {
        if ($host === $s || str_ends_with($host, '.' . $s)) {
            return 'social';
        }
    }
    $search = ['google.', 'bing.', 'yahoo.', 'duckduckgo.', 'baidu.', 'yandex.', 'ecosia.'];
    foreach ($search as $s) {
        if (str_contains($host, $s)) {
            return 'search';
        }
    }
    return 'other';
}

function jota_analytics_normalize_path(string $path): string
{
    $path = trim($path);
    if ($path === '') {
        return '/';
    }
    if (strlen($path) > 180) {
        $path = substr($path, 0, 180);
    }
    return $path;
}

function jota_analytics_client_ip(): string
{
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? '',
    ];
    foreach ($candidates as $raw) {
        if ($raw === '') {
            continue;
        }
        // X-Forwarded-For pode ter lista
        $parts = explode(',', $raw);
        $ip = trim($parts[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    return '0.0.0.0';
}

function jota_analytics_geo_lookup(string $ip): array
{
    $fallback = ['country' => '(desconhecido)', 'city' => '(desconhecido)'];
    if ($ip === '' || $ip === '0.0.0.0' || !filter_var($ip, FILTER_VALIDATE_IP)) {
        return $fallback;
    }
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return ['country' => 'Local', 'city' => 'Rede privada'];
    }

    jota_ensure_data_dirs();
    $cache = jota_read_json(jota_analytics_geo_cache_file(), []);
    if (isset($cache[$ip]) && is_array($cache[$ip])) {
        return [
            'country' => (string) ($cache[$ip]['country'] ?? $fallback['country']),
            'city' => (string) ($cache[$ip]['city'] ?? $fallback['city']),
        ];
    }

    $url = 'http://ip-api.com/json/' . rawurlencode($ip) . '?fields=status,country,city&lang=en';
    $ctx = stream_context_create(['http' => ['timeout' => 1.2, 'ignore_errors' => true]]);
    $raw = @file_get_contents($url, false, $ctx);
    $country = $fallback['country'];
    $city = $fallback['city'];
    if (is_string($raw) && $raw !== '') {
        $json = json_decode($raw, true);
        if (is_array($json) && ($json['status'] ?? '') === 'success') {
            $country = trim((string) ($json['country'] ?? '')) ?: $fallback['country'];
            $city = trim((string) ($json['city'] ?? '')) ?: $fallback['city'];
        }
    }

    $cache[$ip] = ['country' => $country, 'city' => $city, 'at' => time()];
    if (count($cache) > 4000) {
        $cache = array_slice($cache, -3000, null, true);
    }
    jota_write_json(jota_analytics_geo_cache_file(), $cache);

    return ['country' => $country, 'city' => $city];
}

function jota_analytics_load_day(string $date): array
{
    jota_ensure_data_dirs();
    $base = jota_analytics_empty_day($date);
    $data = jota_read_json(jota_analytics_day_file($date), []);
    if (!is_array($data) || !$data) {
        return $base;
    }

    $merged = array_replace_recursive($base, $data);
    $merged['date'] = $date;

    foreach (['views', 'whatsapp_clicks', 'email_clicks', 'link_clicks', 'cta_clicks', 'form_submits', 'duration_sum', 'duration_count', 'bounce_sessions', 'session_count'] as $k) {
        $merged[$k] = (int) ($merged[$k] ?? 0);
    }
    foreach (['visitors', 'pages', 'entry', 'exit', 'browsers', 'os', 'countries', 'cities', 'ips', 'session_meta'] as $k) {
        $merged[$k] = isset($merged[$k]) && is_array($merged[$k]) ? $merged[$k] : [];
    }
    $devices = jota_analytics_empty_day()['devices'];
    if (isset($merged['devices']) && is_array($merged['devices'])) {
        foreach ($devices as $d => $_) {
            $devices[$d] = (int) ($merged['devices'][$d] ?? 0);
        }
    }
    $merged['devices'] = $devices;

    $sources = jota_analytics_empty_day()['sources'];
    if (isset($merged['sources']) && is_array($merged['sources'])) {
        foreach ($sources as $s => $_) {
            $sources[$s] = (int) ($merged['sources'][$s] ?? 0);
        }
    }
    $merged['sources'] = $sources;

    $hours = array_fill(0, 24, 0);
    if (isset($merged['hours']) && is_array($merged['hours'])) {
        foreach ($merged['hours'] as $h => $c) {
            $hi = (int) $h;
            if ($hi >= 0 && $hi <= 23) {
                $hours[$hi] = (int) $c;
            }
        }
    }
    $merged['hours'] = $hours;

    $weekdays = array_fill(0, 7, 0);
    if (isset($merged['weekdays']) && is_array($merged['weekdays'])) {
        foreach ($merged['weekdays'] as $d => $c) {
            $di = (int) $d;
            if ($di >= 0 && $di <= 6) {
                $weekdays[$di] = (int) $c;
            }
        }
    }
    $merged['weekdays'] = $weekdays;

    if (isset($merged['visitors']) && is_array($merged['visitors'])) {
        $merged['visitors'] = array_values(array_unique(array_map('strval', $merged['visitors'])));
    }

    return $merged;
}

function jota_analytics_save_day(array $data): bool
{
    $date = (string) ($data['date'] ?? date('Y-m-d'));
    if (isset($data['visitors']) && is_array($data['visitors'])) {
        $data['visitors'] = array_values(array_unique(array_map('strval', $data['visitors'])));
        if (count($data['visitors']) > 8000) {
            $data['visitors'] = array_slice($data['visitors'], -8000);
        }
    }
    if (isset($data['session_meta']) && is_array($data['session_meta']) && count($data['session_meta']) > 5000) {
        $data['session_meta'] = array_slice($data['session_meta'], -4000, null, true);
    }
    return jota_write_json(jota_analytics_day_file($date), $data);
}

function jota_analytics_touch_online(string $visitorId): void
{
    if ($visitorId === '') {
        return;
    }
    jota_ensure_data_dirs();
    $online = jota_read_json(jota_analytics_online_file(), []);
    if (!is_array($online)) {
        $online = [];
    }
    $now = time();
    $online[$visitorId] = $now;
    foreach ($online as $vid => $ts) {
        if (($now - (int) $ts) > 180) {
            unset($online[$vid]);
        }
    }
    jota_write_json(jota_analytics_online_file(), $online);
}

function jota_analytics_online_count(): int
{
    $online = jota_read_json(jota_analytics_online_file(), []);
    if (!is_array($online)) {
        return 0;
    }
    $now = time();
    $count = 0;
    foreach ($online as $ts) {
        if (($now - (int) $ts) <= 120) {
            $count++;
        }
    }
    return $count;
}

function jota_analytics_inc(array &$bucket, string $key, int $by = 1): void
{
    if ($key === '') {
        $key = '(desconhecido)';
    }
    $bucket[$key] = (int) ($bucket[$key] ?? 0) + $by;
}

function jota_analytics_track(array $event): bool
{
    $type = (string) ($event['type'] ?? 'pageview');
    $allowed = ['pageview', 'heartbeat', 'whatsapp_click', 'email_click', 'link_click', 'cta_click', 'form_submit'];
    if (!in_array($type, $allowed, true)) {
        return false;
    }

    $date = date('Y-m-d');
    $hour = (int) date('G');
    $weekday = (int) date('w'); // 0=domingo
    $data = jota_analytics_load_day($date);

    $visitorRaw = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($event['visitor_id'] ?? ''));
    $sessionRaw = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($event['session_id'] ?? ''));
    $visitorId = substr(is_string($visitorRaw) ? $visitorRaw : '', 0, 64);
    $sessionId = substr(is_string($sessionRaw) ? $sessionRaw : '', 0, 64);
    $path = jota_analytics_normalize_path((string) ($event['path'] ?? '/'));
    $referrer = (string) ($event['referrer'] ?? '');
    $ua = (string) ($event['ua'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $parsed = jota_analytics_parse_ua($ua);
    $ip = jota_analytics_client_ip();
    $geo = jota_analytics_geo_lookup($ip);
    $source = jota_analytics_source($referrer, (string) ($_SERVER['HTTP_HOST'] ?? ''));
    $duration = max(0, min(86400, (int) ($event['duration'] ?? 0)));

    if ($visitorId !== '') {
        jota_analytics_touch_online($visitorId);
        if (!in_array($visitorId, $data['visitors'], true)) {
            $data['visitors'][] = $visitorId;
        }
    }

    $isNewSession = false;
    if ($sessionId !== '') {
        if (!isset($data['session_meta'][$sessionId]) || !is_array($data['session_meta'][$sessionId])) {
            $isNewSession = true;
            $data['session_meta'][$sessionId] = [
                'visitor_id' => $visitorId,
                'pages' => 0,
                'entry' => $path,
                'exit' => $path,
                'start' => time(),
                'end' => time(),
                'duration' => 0,
                'source' => $source,
                'device' => $parsed['device'],
                'browser' => $parsed['browser'],
                'os' => $parsed['os'],
                'ip' => $ip,
                'country' => $geo['country'],
                'city' => $geo['city'],
            ];
            $data['session_count']++;
            jota_analytics_inc($data['entry'], $path);
            jota_analytics_inc($data['sources'], $source);
            jota_analytics_inc($data['devices'], $parsed['device']);
            jota_analytics_inc($data['browsers'], $parsed['browser']);
            jota_analytics_inc($data['os'], $parsed['os']);
            jota_analytics_inc($data['countries'], $geo['country']);
            $cityKey = $geo['city'] . ' (' . $geo['country'] . ')';
            jota_analytics_inc($data['cities'], $cityKey);
        }
        $sess = &$data['session_meta'][$sessionId];
        $sess['end'] = time();
        $sess['exit'] = $path;
        if ($duration > (int) ($sess['duration'] ?? 0)) {
            $sess['duration'] = $duration;
        }
        if ($type === 'pageview') {
            $sess['pages'] = (int) ($sess['pages'] ?? 0) + 1;
        }
        unset($sess);
    }

    if ($type === 'pageview') {
        $data['views']++;
        jota_analytics_inc($data['pages'], $path);
        $data['hours'][$hour] = (int) ($data['hours'][$hour] ?? 0) + 1;
        $data['weekdays'][$weekday] = (int) ($data['weekdays'][$weekday] ?? 0) + 1;

        if (!isset($data['ips'][$ip]) || !is_array($data['ips'][$ip])) {
            $data['ips'][$ip] = [
                'views' => 0,
                'sessions' => 0,
                'country' => $geo['country'],
                'city' => $geo['city'],
            ];
        }
        $data['ips'][$ip]['views']++;
        $data['ips'][$ip]['country'] = $geo['country'];
        $data['ips'][$ip]['city'] = $geo['city'];
        if ($isNewSession) {
            $data['ips'][$ip]['sessions']++;
        }
    } elseif ($type === 'heartbeat') {
        // só atualiza sessão/online
    } elseif ($type === 'whatsapp_click') {
        $data['whatsapp_clicks']++;
    } elseif ($type === 'email_click') {
        $data['email_clicks']++;
    } elseif ($type === 'link_click') {
        $data['link_clicks']++;
    } elseif ($type === 'cta_click') {
        $data['cta_clicks']++;
    } elseif ($type === 'form_submit') {
        $data['form_submits']++;
    }

    // Recalcula bounce/duração a partir das sessões do dia
    $durationSum = 0;
    $durationCount = 0;
    $bounce = 0;
    $exitPages = [];
    foreach ($data['session_meta'] as $meta) {
        if (!is_array($meta)) {
            continue;
        }
        $pages = (int) ($meta['pages'] ?? 0);
        $dur = (int) ($meta['duration'] ?? 0);
        if ($dur <= 0 && isset($meta['start'], $meta['end'])) {
            $dur = max(0, (int) $meta['end'] - (int) $meta['start']);
        }
        if ($dur > 0) {
            $durationSum += $dur;
            $durationCount++;
        }
        if ($pages <= 1) {
            $bounce++;
        }
        $exit = (string) ($meta['exit'] ?? '/');
        $exitPages[$exit] = (int) ($exitPages[$exit] ?? 0) + 1;
    }
    $data['duration_sum'] = $durationSum;
    $data['duration_count'] = $durationCount;
    $data['bounce_sessions'] = $bounce;
    $data['exit'] = $exitPages;

    return jota_analytics_save_day($data);
}

function jota_analytics_list_dates(): array
{
    jota_ensure_data_dirs();
    $files = glob(JOTA_ANALYTICS_DIR . '/day-*.json') ?: [];
    $dates = [];
    foreach ($files as $file) {
        if (preg_match('/day-(\d{4}-\d{2}-\d{2})\.json$/', $file, $m)) {
            $dates[] = $m[1];
        }
    }
    sort($dates);
    return $dates;
}

function jota_analytics_period_dates(string $period): array
{
    if ($period === 'all') {
        $all = jota_analytics_list_dates();
        return $all ?: [date('Y-m-d')];
    }
    $map = ['today' => 1, '7' => 7, '30' => 30, '90' => 90];
    $n = $map[$period] ?? 30;
    $dates = [];
    for ($i = $n - 1; $i >= 0; $i--) {
        $dates[] = date('Y-m-d', strtotime("-{$i} days"));
    }
    return $dates;
}

function jota_analytics_summary(string $period = '30'): array
{
    $dates = jota_analytics_period_dates($period);
    $weekdayNames = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];

    $summary = [
        'period' => $period,
        'period_label' => [
            'today' => 'Hoje',
            '7' => '7 dias',
            '30' => '30 dias',
            '90' => '90 dias',
            'all' => 'Todo período',
        ][$period] ?? '30 dias',
        'online' => jota_analytics_online_count(),
        'views' => 0,
        'visitors' => 0,
        'sessions' => 0,
        'whatsapp_clicks' => 0,
        'email_clicks' => 0,
        'link_clicks' => 0,
        'cta_clicks' => 0,
        'form_submits' => 0,
        'clicks_total' => 0,
        'avg_duration' => 0,
        'bounce_rate' => 0,
        'pages_per_session' => 0,
        'conversion_rate' => 0,
        'views_per_visitor' => 0,
        'pages' => [],
        'entry' => [],
        'exit' => [],
        'devices' => [],
        'browsers' => [],
        'os' => [],
        'sources' => ['social' => 0, 'search' => 0, 'direct' => 0, 'other' => 0],
        'countries' => [],
        'cities' => [],
        'ips' => [],
        'hours' => array_fill(0, 24, 0),
        'weekdays' => array_fill(0, 7, 0),
        'series' => [],
        'unread_messages' => 0,
        'messages' => [],
        'admin_name' => defined('JOTA_ADMIN_DISPLAY_NAME') ? JOTA_ADMIN_DISPLAY_NAME : 'Admin',
        'ga_id' => JOTA_GA_MEASUREMENT_ID,
    ];

    $visitorSet = [];
    $durationSum = 0;
    $durationCount = 0;
    $bounce = 0;
    $sessionCount = 0;

    foreach ($dates as $date) {
        $day = jota_analytics_load_day($date);
        $daySessions = (int) ($day['session_count'] ?? count($day['session_meta'] ?? []));
        $dayVisitors = count($day['visitors'] ?? []);

        $summary['views'] += (int) $day['views'];
        $summary['whatsapp_clicks'] += (int) $day['whatsapp_clicks'];
        $summary['email_clicks'] += (int) $day['email_clicks'];
        $summary['link_clicks'] += (int) $day['link_clicks'];
        $summary['cta_clicks'] += (int) $day['cta_clicks'];
        $summary['form_submits'] += (int) $day['form_submits'];
        $sessionCount += $daySessions;
        $bounce += (int) $day['bounce_sessions'];
        $durationSum += (int) $day['duration_sum'];
        $durationCount += (int) $day['duration_count'];

        foreach ($day['visitors'] as $vid) {
            $visitorSet[(string) $vid] = true;
        }
        foreach (['pages', 'entry', 'exit', 'browsers', 'os', 'countries', 'cities'] as $bucket) {
            foreach ((array) ($day[$bucket] ?? []) as $k => $v) {
                jota_analytics_inc($summary[$bucket], (string) $k, (int) $v);
            }
        }
        foreach ((array) ($day['devices'] ?? []) as $k => $v) {
            jota_analytics_inc($summary['devices'], (string) $k, (int) $v);
        }
        foreach ((array) ($day['sources'] ?? []) as $k => $v) {
            $summary['sources'][$k] = (int) ($summary['sources'][$k] ?? 0) + (int) $v;
        }
        foreach ((array) ($day['ips'] ?? []) as $ip => $meta) {
            if (!is_array($meta)) {
                continue;
            }
            if (!isset($summary['ips'][$ip])) {
                $summary['ips'][$ip] = [
                    'views' => 0,
                    'sessions' => 0,
                    'country' => (string) ($meta['country'] ?? ''),
                    'city' => (string) ($meta['city'] ?? ''),
                ];
            }
            $summary['ips'][$ip]['views'] += (int) ($meta['views'] ?? 0);
            $summary['ips'][$ip]['sessions'] += (int) ($meta['sessions'] ?? 0);
            $summary['ips'][$ip]['country'] = (string) ($meta['country'] ?? $summary['ips'][$ip]['country']);
            $summary['ips'][$ip]['city'] = (string) ($meta['city'] ?? $summary['ips'][$ip]['city']);
        }
        for ($h = 0; $h < 24; $h++) {
            $summary['hours'][$h] += (int) ($day['hours'][$h] ?? 0);
        }
        for ($d = 0; $d < 7; $d++) {
            $summary['weekdays'][$d] += (int) ($day['weekdays'][$d] ?? 0);
        }

        $summary['series'][] = [
            'date' => $date,
            'label' => date('d/m', strtotime($date)),
            'views' => (int) $day['views'],
            'visitors' => $dayVisitors,
        ];
    }

    $summary['visitors'] = count($visitorSet);
    $summary['sessions'] = $sessionCount;
    $summary['clicks_total'] = $summary['whatsapp_clicks'] + $summary['email_clicks'] + $summary['link_clicks'] + $summary['cta_clicks'];
    $summary['avg_duration'] = $durationCount > 0 ? (int) round($durationSum / $durationCount) : 0;
    $summary['bounce_rate'] = $sessionCount > 0 ? round(($bounce / $sessionCount) * 100, 1) : 0;
    $summary['pages_per_session'] = $sessionCount > 0 ? round($summary['views'] / $sessionCount, 1) : 0;
    $summary['conversion_rate'] = $sessionCount > 0 ? round(($summary['clicks_total'] / $sessionCount) * 100, 1) : 0;
    $summary['views_per_visitor'] = $summary['visitors'] > 0 ? round($summary['views'] / $summary['visitors'], 1) : 0;

    $deviceTotal = array_sum($summary['devices']) ?: 1;
    $devicesOut = [];
    foreach ($summary['devices'] as $name => $count) {
        $devicesOut[] = [
            'name' => ucfirst((string) $name),
            'count' => (int) $count,
            'pct' => round(((int) $count / $deviceTotal) * 100, 1),
        ];
    }
    $summary['devices_list'] = $devicesOut;

    $summary['weekdays_named'] = [];
    foreach ($weekdayNames as $i => $name) {
        $summary['weekdays_named'][] = ['name' => $name, 'count' => (int) ($summary['weekdays'][$i] ?? 0)];
    }

    arsort($summary['pages']);
    arsort($summary['entry']);
    arsort($summary['exit']);
    arsort($summary['browsers']);
    arsort($summary['os']);
    arsort($summary['countries']);
    arsort($summary['cities']);
    uasort($summary['ips'], static function ($a, $b) {
        return ((int) ($b['views'] ?? 0)) <=> ((int) ($a['views'] ?? 0));
    });

    $summary['pages'] = array_slice($summary['pages'], 0, 15, true);
    $summary['entry'] = array_slice($summary['entry'], 0, 10, true);
    $summary['exit'] = array_slice($summary['exit'], 0, 10, true);
    $summary['browsers'] = array_slice($summary['browsers'], 0, 10, true);
    $summary['os'] = array_slice($summary['os'], 0, 10, true);
    $summary['countries'] = array_slice($summary['countries'], 0, 15, true);
    $summary['cities'] = array_slice($summary['cities'], 0, 20, true);
    $summary['ips'] = array_slice($summary['ips'], 0, 50, true);

    $summary['sources_named'] = [
        ['name' => 'Redes Sociais', 'count' => (int) $summary['sources']['social']],
        ['name' => 'Buscadores', 'count' => (int) $summary['sources']['search']],
        ['name' => 'Direto', 'count' => (int) $summary['sources']['direct']],
        ['name' => 'Outros', 'count' => (int) $summary['sources']['other']],
    ];

    $summary['clicks_named'] = [
        ['name' => 'WhatsApp', 'count' => (int) $summary['whatsapp_clicks']],
        ['name' => 'E-mail', 'count' => (int) $summary['email_clicks']],
        ['name' => 'Links', 'count' => (int) $summary['link_clicks']],
        ['name' => 'CTAs', 'count' => (int) $summary['cta_clicks']],
    ];

    // Cidades enriquecidas a partir dos IPs (sessões + views)
    $cityAgg = [];
    foreach ($summary['ips'] as $meta) {
        if (!is_array($meta)) {
            continue;
        }
        $city = trim((string) ($meta['city'] ?? ''));
        $country = trim((string) ($meta['country'] ?? ''));
        $key = $city !== '' ? ($city . '(' . ($country !== '' ? $country : '?') . ')') : '(desconhecido)';
        if (!isset($cityAgg[$key])) {
            $cityAgg[$key] = ['sessions' => 0, 'views' => 0];
        }
        $cityAgg[$key]['sessions'] += (int) ($meta['sessions'] ?? 0);
        $cityAgg[$key]['views'] += (int) ($meta['views'] ?? 0);
    }
    uasort($cityAgg, static function ($a, $b) {
        return ((int) ($b['views'] ?? 0)) <=> ((int) ($a['views'] ?? 0));
    });
    $summary['cities_detail'] = array_slice($cityAgg, 0, 20, true);

    require_once __DIR__ . '/messages.php';
    $counts = jota_messages_counts();
    $summary['unread_messages'] = (int) ($counts['new'] ?? 0);
    $summary['messages'] = array_slice(jota_messages_all(), 0, 20);

    return $summary;
}
