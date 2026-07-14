<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

register_shutdown_function(static function (): void {
    $err = error_get_last();
    if (!$err) {
        return;
    }
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($err['type'], $fatalTypes, true)) {
        return;
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<pre style="padding:24px;font:14px/1.5 Consolas,monospace;background:#111;color:#f8d7da">';
    echo "Erro fatal no painel:\n";
    echo htmlspecialchars($err['message'] . "\n" . $err['file'] . ':' . $err['line'], ENT_QUOTES, 'UTF-8');
    echo '</pre>';
});

try {
    require_once dirname(__DIR__) . '/php/helpers.php';
    jota_admin_require_login();

    require_once dirname(__DIR__) . '/php/analytics.php';
    require_once dirname(__DIR__) . '/php/messages.php';
    require_once dirname(__DIR__) . '/php/ga4.php';

    $pageTitle = 'Estatísticas';
    $activeNav = 'dashboard';

    $period = (string) ($_GET['period'] ?? '30');
    if (!in_array($period, ['today', '7', '30', '90', 'all'], true)) {
        $period = '30';
    }

    $periods = [
        'today' => 'Hoje',
        '7' => '7 dias',
        '30' => '30 dias',
        '90' => '90 dias',
        'all' => 'Todo período',
    ];

    $statsError = '';
    $statsNotice = '';
    $statsSource = 'local';

    try {
        if (jota_ga4_configured()) {
            $stats = jota_ga4_summary($period);
            $statsSource = 'ga4';
            if (!empty($stats['from_cache'])) {
                $statsNotice = 'Dados do Google Analytics (cache ~15 min).';
            } else {
                $statsNotice = 'Dados do Google Analytics 4 em tempo quase real.';
            }
        } else {
            $stats = jota_analytics_summary($period);
            $statsSource = 'local';
            $statsNotice = 'Ainda sem credenciais GA4 — mostrando tracking interno. Envie dados/ga-service-account.json para usar o Analytics.';
        }
    } catch (Throwable $e) {
        $statsError = $e->getMessage();
        try {
            $stats = jota_analytics_summary($period);
            $statsSource = 'local';
            $statsNotice = 'Falha ao ler o GA4; usando tracking interno como fallback.';
        } catch (Throwable $e2) {
            $stats = [
                'period' => $period,
                'period_label' => $periods[$period] ?? '30 dias',
                'online' => 0,
                'views' => 0,
                'visitors' => 0,
                'sessions' => 0,
                'clicks_total' => 0,
                'avg_duration' => 0,
                'bounce_rate' => 0,
                'pages_per_session' => 0,
                'conversion_rate' => 0,
                'views_per_visitor' => 0,
                'unread_messages' => 0,
                'hours' => array_fill(0, 24, 0),
                'weekdays_named' => [],
                'series' => [],
                'pages' => [],
                'clicks_named' => [],
                'devices_list' => [],
                'browsers' => [],
                'os' => [],
                'sources_named' => [],
                'entry' => [],
                'exit' => [],
                'countries' => [],
                'cities' => [],
                'cities_detail' => [],
                'ips' => [],
                'messages' => [],
                'admin_name' => defined('JOTA_ADMIN_DISPLAY_NAME') ? JOTA_ADMIN_DISPLAY_NAME : 'Admin',
                'ga_id' => JOTA_GA_MEASUREMENT_ID,
                'ga_property_id' => (string) JOTA_GA_PROPERTY_ID,
            ];
        }
    }

    $fmt = static function ($n, int $decimals = 0): string {
        return number_format((float) $n, $decimals, ',', '.');
    };

    $fmtDuration = static function (int $seconds): string {
        if ($seconds <= 0) {
            return '0s';
        }
        if ($seconds < 60) {
            return $seconds . 's';
        }
        $m = intdiv($seconds, 60);
        $s = $seconds % 60;
        if ($m < 60) {
            return $m . 'min' . ($s > 0 ? ' ' . $s . 's' : '');
        }
        $h = intdiv($m, 60);
        $m = $m % 60;
        return $h . 'h' . ($m > 0 ? ' ' . $m . 'min' : '');
    };

    $maxHour = max(1, ...array_map('intval', $stats['hours'] ?? [0]));
    $maxWeekday = 1;
    foreach ($stats['weekdays_named'] ?? [] as $wd) {
        $maxWeekday = max($maxWeekday, (int) ($wd['count'] ?? 0));
    }
    $maxSeries = 1;
    foreach ($stats['series'] ?? [] as $row) {
        $maxSeries = max($maxSeries, (int) ($row['visitors'] ?? $row['views'] ?? 0));
    }

    $online = (int) ($stats['online'] ?? 0);
    $adminName = (string) ($stats['admin_name'] ?? 'Admin');
    $gaId = (string) ($stats['ga_id'] ?? JOTA_GA_MEASUREMENT_ID);
    $gaPropertyId = (string) ($stats['ga_property_id'] ?? JOTA_GA_PROPERTY_ID);

    require __DIR__ . '/inc/header.php';
} catch (Throwable $e) {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<pre style="padding:24px;font:14px/1.5 Consolas,monospace;background:#111;color:#f8d7da">';
    echo "Erro no painel:\n";
    echo htmlspecialchars($e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine(), ENT_QUOTES, 'UTF-8');
    echo '</pre>';
    exit;
}
?>

<?php if ($statsError !== ''): ?>
  <div class="admin-alert admin-alert--error" style="margin-bottom:16px">
    Erro Google Analytics: <?= jota_h($statsError) ?>
  </div>
<?php endif; ?>

<?php if ($statsNotice !== ''): ?>
  <div class="admin-alert <?= $statsSource === 'ga4' ? 'admin-alert--ok' : 'admin-alert--warn' ?>" style="margin-bottom:16px">
    <?= jota_h($statsNotice) ?>
    <?php if ($statsSource === 'ga4'): ?>
      <span> · Propriedade <?= jota_h($gaPropertyId) ?> · <?= jota_h($gaId) ?></span>
    <?php endif; ?>
  </div>
<?php endif; ?>

<section class="admin-welcome">
  <div>
    <p class="admin-period-label">Estatísticas · <?= jota_h((string) ($stats['period_label'] ?? $periods[$period])) ?>
      <?php if ($statsSource === 'ga4'): ?> · fonte GA4<?php endif; ?>
    </p>
    <h2 class="admin-welcome-title">Bem-vindo(a) de volta, <?= jota_h($adminName) ?></h2>
    <p class="admin-online">
      <span class="admin-online-dot" aria-hidden="true"></span>
      <?= $online === 1 ? '1 visitante online agora' : $fmt($online) . ' visitantes online agora' ?>
      <span class="admin-muted" style="font-weight:500"> (tempo real interno)</span>
    </p>
  </div>
</section>

<section class="admin-toolbar">
  <div class="admin-tabs">
    <?php foreach ($periods as $key => $label): ?>
      <?php $periodKey = (string) $key; ?>
      <a class="<?= $period === $periodKey ? 'is-active' : '' ?>" href="?period=<?= rawurlencode($periodKey) ?>"><?= jota_h($label) ?></a>
    <?php endforeach; ?>
  </div>
</section>

<section class="admin-kpis admin-kpis--wide">
  <article class="admin-kpi">
    <span>Visitantes únicos</span>
    <strong><?= $fmt((int) $stats['visitors']) ?></strong>
    <small>Total de sessões: <?= $fmt((int) $stats['sessions']) ?></small>
  </article>
  <article class="admin-kpi">
    <span>Visualizações</span>
    <strong><?= $fmt((int) $stats['views']) ?></strong>
    <small>Média por visitante: <?= $fmt((float) $stats['views_per_visitor'], 1) ?></small>
  </article>
  <article class="admin-kpi">
    <span>Total de cliques</span>
    <strong><?= $fmt((int) $stats['clicks_total']) ?></strong>
    <small>WhatsApp, e-mail e links</small>
  </article>
  <article class="admin-kpi">
    <span>Tempo médio</span>
    <strong><?= jota_h($fmtDuration((int) $stats['avg_duration'])) ?></strong>
    <small>Duração média da sessão (<?= $fmt((int) $stats['avg_duration']) ?>s)</small>
  </article>
  <article class="admin-kpi">
    <span>Taxa de saída</span>
    <strong><?= $fmt((float) $stats['bounce_rate'], 1) ?>%</strong>
    <small>Sessões com 1 página</small>
  </article>
  <article class="admin-kpi">
    <span>Páginas / sessão</span>
    <strong><?= $fmt((float) $stats['pages_per_session'], 1) ?></strong>
    <small>Média de páginas visitadas</small>
  </article>
  <article class="admin-kpi">
    <span>Taxa de conversão</span>
    <strong><?= $fmt((float) $stats['conversion_rate'], 1) ?>%</strong>
    <small>Cliques por sessão</small>
  </article>
  <article class="admin-kpi">
    <span>Mensagens novas</span>
    <strong><?= $fmt((int) $stats['unread_messages']) ?></strong>
    <small><a href="mensagens.php?status=new">Ver formulário ↓</a></small>
  </article>
</section>

<section class="admin-grid-2">
  <article class="admin-card">
    <h2>Horários de pico</h2>
    <div class="admin-hours" role="img" aria-label="Atividade por hora">
      <?php for ($h = 0; $h < 24; $h++): ?>
        <?php
          $count = (int) ($stats['hours'][$h] ?? 0);
          $height = max(3, (int) round(($count / $maxHour) * 100));
        ?>
        <div class="admin-hour" title="<?= sprintf('%02d:00 — %d', $h, $count) ?>">
          <span style="height: <?= $height ?>%"></span>
          <em><?= sprintf('%02d', $h) ?></em>
        </div>
      <?php endfor; ?>
    </div>
  </article>

  <article class="admin-card">
    <h2>Atividade por dia da semana</h2>
    <ul class="admin-weekday-list">
      <?php foreach ($stats['weekdays_named'] ?? [] as $wd): ?>
        <?php
          $c = (int) ($wd['count'] ?? 0);
          $pct = max(4, (int) round(($c / $maxWeekday) * 100));
        ?>
        <li>
          <span><?= jota_h((string) ($wd['name'] ?? '')) ?></span>
          <div class="admin-weekday-bar"><i style="width: <?= $pct ?>%"></i></div>
          <strong><?= $fmt($c) ?></strong>
        </li>
      <?php endforeach; ?>
    </ul>
  </article>
</section>

<section class="admin-card" style="margin-bottom:14px">
  <h2>Visitantes ao longo do tempo</h2>
  <div class="admin-bars admin-bars--series" role="img" aria-label="Visitantes por dia">
    <?php if (empty($stats['series'])): ?>
      <p class="admin-muted">Sem dados neste período.</p>
    <?php endif; ?>
    <?php foreach ($stats['series'] ?? [] as $row): ?>
      <?php
        $val = (int) ($row['visitors'] ?? $row['views'] ?? 0);
        $h = max(4, (int) round(($val / $maxSeries) * 100));
      ?>
      <div class="admin-bar" title="<?= jota_h(($row['label'] ?? '') . ': ' . $val . ' visitantes') ?>">
        <span style="height: <?= $h ?>%"></span>
        <em><?= jota_h((string) ($row['label'] ?? '')) ?></em>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="admin-grid-3">
  <article class="admin-card">
    <h2>Páginas mais visitadas</h2>
    <ul class="admin-list">
      <?php if (empty($stats['pages'])): ?>
        <li class="admin-muted">Sem dados ainda.</li>
      <?php endif; ?>
      <?php foreach ($stats['pages'] as $page => $count): ?>
        <li>
          <span><?= jota_h((string) $page) ?></span>
          <strong><?= $fmt((int) $count) ?> views</strong>
        </li>
      <?php endforeach; ?>
    </ul>
  </article>

  <article class="admin-card">
    <h2>Cliques registrados</h2>
    <ul class="admin-list">
      <?php foreach ($stats['clicks_named'] ?? [] as $click): ?>
        <li>
          <span><?= jota_h((string) ($click['name'] ?? '')) ?></span>
          <strong><?= $fmt((int) ($click['count'] ?? 0)) ?></strong>
        </li>
      <?php endforeach; ?>
    </ul>
  </article>

  <article class="admin-card">
    <h2>Dispositivos</h2>
    <ul class="admin-list">
      <?php if (empty($stats['devices_list'])): ?>
        <li class="admin-muted">Sem dados ainda.</li>
      <?php endif; ?>
      <?php foreach ($stats['devices_list'] ?? [] as $device): ?>
        <?php if ((int) ($device['count'] ?? 0) <= 0) {
            continue;
        } ?>
        <li>
          <span><?= jota_h((string) ($device['name'] ?? '')) ?></span>
          <strong><?= $fmt((int) $device['count']) ?> (<?= $fmt((float) $device['pct'], 1) ?>%)</strong>
        </li>
      <?php endforeach; ?>
    </ul>
  </article>
</section>

<section class="admin-grid-3" style="margin-top:14px">
  <article class="admin-card">
    <h2>Navegadores</h2>
    <ul class="admin-list">
      <?php if (empty($stats['browsers'])): ?>
        <li class="admin-muted">Sem dados ainda.</li>
      <?php endif; ?>
      <?php foreach ($stats['browsers'] as $name => $count): ?>
        <li>
          <span><?= jota_h((string) $name) ?></span>
          <strong><?= $fmt((int) $count) ?></strong>
        </li>
      <?php endforeach; ?>
    </ul>
  </article>

  <article class="admin-card">
    <h2>Sistemas operacionais</h2>
    <ul class="admin-list">
      <?php if (empty($stats['os'])): ?>
        <li class="admin-muted">Sem dados ainda.</li>
      <?php endif; ?>
      <?php foreach ($stats['os'] as $name => $count): ?>
        <li>
          <span><?= jota_h((string) $name) ?></span>
          <strong><?= $fmt((int) $count) ?></strong>
        </li>
      <?php endforeach; ?>
    </ul>
  </article>

  <article class="admin-card">
    <h2>Origem do tráfego</h2>
    <ul class="admin-list">
      <?php foreach ($stats['sources_named'] ?? [] as $src): ?>
        <li>
          <span><?= jota_h((string) ($src['name'] ?? '')) ?></span>
          <strong><?= $fmt((int) ($src['count'] ?? 0)) ?></strong>
        </li>
      <?php endforeach; ?>
    </ul>
  </article>
</section>

<section class="admin-grid-2" style="margin-top:14px">
  <article class="admin-card">
    <h2>Páginas de entrada / saída</h2>
    <div class="admin-split">
      <div>
        <h3>Entrada</h3>
        <ul class="admin-list">
          <?php if (empty($stats['entry'])): ?>
            <li class="admin-muted">—</li>
          <?php endif; ?>
          <?php foreach ($stats['entry'] as $page => $count): ?>
            <li>
              <span><?= jota_h((string) $page) ?></span>
              <strong><?= $fmt((int) $count) ?></strong>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div>
        <h3>Saída</h3>
        <ul class="admin-list">
          <?php if (empty($stats['exit'])): ?>
            <li class="admin-muted">—</li>
          <?php endif; ?>
          <?php foreach ($stats['exit'] as $page => $count): ?>
            <li>
              <span><?= jota_h((string) $page) ?></span>
              <strong><?= $fmt((int) $count) ?></strong>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </article>

  <article class="admin-card">
    <h2>Acessos por país</h2>
    <ul class="admin-list">
      <?php if (empty($stats['countries'])): ?>
        <li class="admin-muted">Sem dados ainda.</li>
      <?php endif; ?>
      <?php foreach ($stats['countries'] as $country => $count): ?>
        <li>
          <span><?= jota_h((string) $country) ?></span>
          <strong><?= $fmt((int) $count) ?></strong>
        </li>
      <?php endforeach; ?>
    </ul>
  </article>
</section>

<section class="admin-card" style="margin-top:14px;margin-bottom:14px">
  <h2>Acessos por cidade</h2>
  <ul class="admin-list admin-list--dense">
    <?php
      $citiesDetail = $stats['cities_detail'] ?? [];
      if (!$citiesDetail && !empty($stats['cities'])) {
          foreach ($stats['cities'] as $city => $count) {
              $citiesDetail[$city] = ['sessions' => (int) $count, 'views' => (int) $count];
          }
      }
    ?>
    <?php if (!$citiesDetail): ?>
      <li class="admin-muted">Sem dados ainda.</li>
    <?php endif; ?>
    <?php foreach ($citiesDetail as $city => $meta): ?>
      <li>
        <span><?= jota_h((string) $city) ?></span>
        <strong><?= $fmt((int) ($meta['sessions'] ?? 0)) ?> sess. · <?= $fmt((int) ($meta['views'] ?? 0)) ?> views</strong>
      </li>
    <?php endforeach; ?>
  </ul>
</section>

<section class="admin-card" style="margin-bottom:14px">
  <h2>Localização dos IPs de acesso</h2>
  <?php if ($statsSource === 'ga4'): ?>
    <p class="admin-muted">O Google Analytics 4 não disponibiliza IPs individuais. Esta lista aparece só no tracking interno do site.</p>
  <?php elseif (empty($stats['ips'])): ?>
    <p class="admin-muted">Sem dados ainda.</p>
  <?php else: ?>
  <p class="admin-muted" style="margin-top:-6px;margin-bottom:14px">Visão detalhada — uso interno restrito.</p>
  <ul class="admin-ip-list">
    <?php foreach ($stats['ips'] as $ip => $meta): ?>
      <?php
        $city = (string) ($meta['city'] ?? '');
        $country = (string) ($meta['country'] ?? '');
        $loc = trim($city . ($city && $country ? ', ' : '') . $country);
        $mapsQ = rawurlencode($loc !== '' ? $loc : (string) $ip);
        $maps = 'https://www.google.com/maps/search/?api=1&query=' . $mapsQ;
      ?>
      <li>
        <div>
          <code><?= jota_h((string) $ip) ?></code>
          <span>
            <?= jota_h($loc !== '' ? $loc : '(desconhecido)') ?>
            · <?= $fmt((int) ($meta['sessions'] ?? 0)) ?> sessão(ões)
            · <?= $fmt((int) ($meta['views'] ?? 0)) ?> views
          </span>
        </div>
        <a href="<?= jota_h($maps) ?>" target="_blank" rel="noopener">Ver no Maps</a>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</section>

<section class="admin-card" id="mensagens-formulario" style="margin-bottom:14px">
  <h2>Mensagens do formulário</h2>
  <?php if (empty($stats['messages'])): ?>
    <p class="admin-muted">Nenhuma mensagem ainda.</p>
  <?php else: ?>
    <div class="admin-msg-preview">
      <?php foreach (array_slice($stats['messages'], 0, 8) as $msg): ?>
        <?php
          $created = !empty($msg['created_at']) ? date('d/m/Y H:i', strtotime((string) $msg['created_at'])) : '';
          $phone = (string) ($msg['phone'] ?? '');
          $email = (string) ($msg['email'] ?? '');
          $wa = jota_whatsapp_link($phone, 'Olá! Retornando o contato pelo site JOTA Advocacia.');
        ?>
        <article class="admin-msg-item">
          <header>
            <strong><?= jota_h((string) ($msg['name'] ?? 'Sem nome')) ?></strong>
            <span><?= jota_h($created) ?> · <?= jota_h((string) ($msg['subject'] ?? '')) ?></span>
          </header>
          <p class="admin-msg-contacts">
            <?php if ($email !== ''): ?>
              <a href="mailto:<?= jota_h($email) ?>"><?= jota_h($email) ?></a>
              <span>·</span>
            <?php endif; ?>
            <?php if ($phone !== ''): ?>
              <span><?= jota_h($phone) ?></span>
            <?php endif; ?>
            <?php if ($phone !== ''): ?>
              <a class="admin-btn admin-btn--gold admin-btn--sm" href="<?= jota_h($wa) ?>" target="_blank" rel="noopener">Enviar WhatsApp</a>
            <?php endif; ?>
          </p>
          <p class="admin-msg-body"><?= nl2br(jota_h((string) ($msg['message'] ?? ''))) ?></p>
        </article>
      <?php endforeach; ?>
    </div>
    <p style="margin-top:14px">
      <a class="admin-btn admin-btn--outline" href="mensagens.php">Abrir caixa de mensagens</a>
    </p>
  <?php endif; ?>
</section>

<section class="admin-card admin-ga-card">
  <h2>Google Analytics 4</h2>
  <p class="admin-muted">
    Measurement ID: <code><?= jota_h($gaId) ?></code>
    · Property ID: <code><?= jota_h($gaPropertyId) ?></code>
    <?php if ($statsSource === 'ga4'): ?>
      — este painel está lendo os dados diretamente da API.
    <?php else: ?>
      — falta o arquivo <code>dados/ga-service-account.json</code> (conta de serviço com acesso de Visualizador).
    <?php endif; ?>
  </p>
  <a class="admin-btn admin-btn--gold" href="https://analytics.google.com/analytics/web/#/p<?= rawurlencode($gaPropertyId) ?>/" target="_blank" rel="noopener">
    Abrir Google Analytics
  </a>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>
