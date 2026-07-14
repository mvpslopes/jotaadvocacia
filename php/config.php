<?php
/**
 * Configurações gerais do site JOTA Advocacia.
 * Ajuste os valores abaixo conforme os dados reais do escritório.
 */

// Número de WhatsApp no formato internacional (DDI+DDD+número), sem espaços ou símbolos.
define('JOTA_WHATSAPP_NUMBER', '5531982445112');

// E-mail que deve receber a notificação de novos contatos vindos do formulário.
// Deixe em branco ('') para desativar o envio de e-mail (o lead continua sendo
// salvo em arquivo normalmente).
define('JOTA_NOTIFICATION_EMAIL', '');

// E-mail utilizado como remetente nas notificações (idealmente do mesmo domínio do site).
define('JOTA_MAIL_FROM', 'contato@jotaadvocacia.com.br');

// Pasta onde leads, mensagens e analytics são registrados (protegida via .htaccess).
define('JOTA_LEADS_DIR', dirname(__DIR__) . '/dados');

// Limite de envios por IP dentro da janela de tempo abaixo (proteção simples contra spam).
define('JOTA_RATE_LIMIT_MAX', 8);
define('JOTA_RATE_LIMIT_WINDOW', 600); // segundos (10 minutos)

// ---- Painel interno (/admin) ----
// Usuário padrão. Altere a senha após o primeiro acesso regenerando o hash:
// php -r "echo password_hash('SUA_SENHA', PASSWORD_DEFAULT);"
define('JOTA_ADMIN_USER', 'admin');
define('JOTA_ADMIN_PASSWORD_HASH', '$2y$12$08fbTcmOBqx5Tqk2lZviR.7w.nLCmVdIXBA1G4MsVGdrSwHRA2iK.'); // senha: Jota@Admin2026
define('JOTA_ADMIN_DISPLAY_NAME', 'Josi');

define('JOTA_ADMIN_SESSION_NAME', 'jota_admin_sess');

// Google Analytics 4 — fluxos / leitura via Data API
define('JOTA_GA_MEASUREMENT_ID', 'G-S6CGMRRYNT');
define('JOTA_GA_PROPERTY_ID', '545459391');
define('JOTA_GA_STREAM_ID', '15251477316');
define('JOTA_GA_CREDENTIALS_FILE', JOTA_LEADS_DIR . '/ga-service-account.json');
define('JOTA_GA_CACHE_TTL', 900); // segundos (15 min)

define('JOTA_MESSAGES_FILE', JOTA_LEADS_DIR . '/messages.json');
define('JOTA_ANALYTICS_DIR', JOTA_LEADS_DIR . '/analytics');
