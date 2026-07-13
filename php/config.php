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

// Pasta onde os leads recebidos pelo formulário são registrados (protegida via .htaccess).
define('JOTA_LEADS_DIR', dirname(__DIR__) . '/dados');

// Limite de envios por IP dentro da janela de tempo abaixo (proteção simples contra spam).
define('JOTA_RATE_LIMIT_MAX', 8);
define('JOTA_RATE_LIMIT_WINDOW', 600); // segundos (10 minutos)
