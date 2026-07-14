<?php
/**
 * Recebe os dados do formulário de contato da landing page, valida,
 * registra o lead e (opcionalmente) notifica por e-mail.
 * O redirecionamento final para o WhatsApp é feito pelo JavaScript do site,
 * então este script sempre responde em JSON.
 */

declare(strict_types=1);

require __DIR__ . '/config.php';
require_once __DIR__ . '/messages.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function respond(bool $success, string $message, int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Método não permitido.', 405);
}

/* ---------------------------------------------------------------------
   1. Proteção anti-spam: honeypot + limite de envios por IP
   --------------------------------------------------------------------- */
$honeypot = trim((string) ($_POST['site'] ?? ''));
if ($honeypot !== '') {
    // Bot detectado: responde "sucesso" para não revelar a proteção,
    // mas não salva nem envia nada.
    respond(true, 'Recebido.');
}

$leadsDir = JOTA_LEADS_DIR;
if (!is_dir($leadsDir)) {
    @mkdir($leadsDir, 0755, true);
}

// Protege a pasta de dados com .htaccess, caso algum dia fique acessível via web.
$htaccessPath = $leadsDir . '/.htaccess';
if (is_dir($leadsDir) && !file_exists($htaccessPath)) {
    @file_put_contents($htaccessPath, "Require all denied\n");
}

$ip = filter_var($_SERVER['REMOTE_ADDR'] ?? 'desconhecido', FILTER_VALIDATE_IP) ?: 'desconhecido';

function passaLimiteDeEnvio(string $ip, string $leadsDir): bool
{
    $arquivo = $leadsDir . '/.rate-limit.json';
    $agora = time();
    $registros = [];

    if (file_exists($arquivo)) {
        $conteudo = @file_get_contents($arquivo);
        $decodificado = $conteudo ? json_decode($conteudo, true) : null;
        if (is_array($decodificado)) {
            $registros = $decodificado;
        }
    }

    // Remove registros antigos (fora da janela de tempo) para não crescer indefinidamente.
    foreach ($registros as $chave => $envios) {
        $registros[$chave] = array_values(array_filter($envios, function ($timestamp) use ($agora) {
            return ($agora - $timestamp) < JOTA_RATE_LIMIT_WINDOW;
        }));
        if (empty($registros[$chave])) {
            unset($registros[$chave]);
        }
    }

    $enviosDoIp = $registros[$ip] ?? [];
    if (count($enviosDoIp) >= JOTA_RATE_LIMIT_MAX) {
        return false;
    }

    $enviosDoIp[] = $agora;
    $registros[$ip] = $enviosDoIp;
    @file_put_contents($arquivo, json_encode($registros), LOCK_EX);

    return true;
}

if (!passaLimiteDeEnvio($ip, $leadsDir)) {
    respond(false, 'Muitas tentativas em pouco tempo. Aguarde alguns minutos ou fale direto pelo WhatsApp.', 429);
}

/* ---------------------------------------------------------------------
   2. Validação e sanitização dos campos
   --------------------------------------------------------------------- */
function sanitizarTexto(string $valor, int $maxLength = 500): string
{
    $valor = trim($valor);
    $valor = strip_tags($valor);
    $valor = preg_replace('/[\r\n]+/', ' ', $valor) ?? $valor;
    if (mb_strlen($valor) > $maxLength) {
        $valor = mb_substr($valor, 0, $maxLength);
    }
    return $valor;
}

$nome = sanitizarTexto((string) ($_POST['nome'] ?? ''), 120);
$telefone = sanitizarTexto((string) ($_POST['telefone'] ?? ''), 30);
$email = sanitizarTexto((string) ($_POST['email'] ?? ''), 160);
$assunto = sanitizarTexto((string) ($_POST['assunto'] ?? ''), 120);
$mensagem = sanitizarTexto((string) ($_POST['mensagem'] ?? ''), 1000);

$erros = [];

if (mb_strlen($nome) < 2) {
    $erros[] = 'Informe seu nome completo.';
}

$telefoneDigitos = preg_replace('/\D/', '', $telefone) ?? '';
if (mb_strlen($telefoneDigitos) < 10 || mb_strlen($telefoneDigitos) > 13) {
    $erros[] = 'Informe um número de WhatsApp válido, com DDD.';
}

if ($assunto === '') {
    $erros[] = 'Selecione o assunto do seu caso.';
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $erros[] = 'Informe um e-mail válido.';
}

if (!empty($erros)) {
    respond(false, implode(' ', $erros), 422);
}

/* ---------------------------------------------------------------------
   3. Registro do lead em arquivo CSV (fora da pasta pública)
   --------------------------------------------------------------------- */
$arquivoLeads = $leadsDir . '/leads.csv';
$novoArquivo = !file_exists($arquivoLeads);

$fp = @fopen($arquivoLeads, 'a');
if ($fp) {
    if ($novoArquivo) {
        fputcsv($fp, ['data_hora', 'nome', 'telefone', 'assunto', 'mensagem', 'ip']);
    }
    fputcsv($fp, [
        date('Y-m-d H:i:s'),
        $nome,
        $telefone,
        $email,
        $assunto,
        $mensagem,
        $ip,
    ]);
    fclose($fp);
}

/* Também grava no inbox JSON do painel /admin */
jota_messages_add([
    'name' => $nome,
    'email' => $email,
    'phone' => $telefone,
    'subject' => $assunto,
    'message' => $mensagem,
    'ip' => $ip,
    'ua' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
]);

/* ---------------------------------------------------------------------
   4. Notificação por e-mail (opcional — depende de JOTA_NOTIFICATION_EMAIL
      e de o servidor ter o envio de e-mail configurado)
   --------------------------------------------------------------------- */
if (JOTA_NOTIFICATION_EMAIL !== '') {
    $assuntoEmail = 'Novo contato pelo site — JOTA Advocacia';
    $corpo = "Novo lead recebido pelo site da JOTA Advocacia:\n\n"
        . "Nome: {$nome}\n"
        . "WhatsApp: {$telefone}\n"
        . "Assunto: {$assunto}\n"
        . "Mensagem: {$mensagem}\n"
        . "IP: {$ip}\n"
        . 'Data/hora: ' . date('d/m/Y H:i:s') . "\n";

    $headers = 'From: JOTA Advocacia <' . JOTA_MAIL_FROM . ">\r\n"
        . 'Reply-To: ' . JOTA_MAIL_FROM . "\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n";

    // Suprime falhas de mail() (comuns em ambientes locais sem SMTP) para não
    // impedir o fluxo principal do usuário rumo ao WhatsApp.
    @mail(JOTA_NOTIFICATION_EMAIL, $assuntoEmail, $corpo, $headers);
}

respond(true, 'Lead recebido com sucesso.');
