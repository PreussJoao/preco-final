<?php
// Recebe os webhooks da Cakto: abandono de checkout, Pix/boleto gerado, pagamento recusado e compra aprovada.
// Guarda o contato para recuperar a venda, avisa por e-mail e mostra tudo em abandonos.php (com senha).
// Cadastre na Cakto: https://SEU-DOMINIO/api/cakto-webhook.php — passo a passo em LEIA-ME_WEBHOOK.md.
declare(strict_types=1);
require __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

function responder(int $status, array $corpo): void
{
    http_response_code($status);
    echo json_encode($corpo, JSON_UNESCAPED_UNICODE);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    responder(405, ['erro' => 'use POST']);
    exit;
}
if (!configurado()) {
    responder(500, ['erro' => 'secret não configurado (veja LEIA-ME_WEBHOOK.md)']);
    exit;
}
$secret = (string)configuracao()['secret'];
$bruto = (string)file_get_contents('php://input', false, null, 0, 1048576);

// assinatura HMAC (cabeçalhos X-Cakto-Timestamp e X-Cakto-Signature: v1=<hmac-sha256 de "timestamp.corpo">)
$ts = (string)($_SERVER['HTTP_X_CAKTO_TIMESTAMP'] ?? '');
$assinatura = (string)($_SERVER['HTTP_X_CAKTO_SIGNATURE'] ?? '');
if ($assinatura !== '') {
    $esperada = 'v1=' . hash_hmac('sha256', $ts . '.' . $bruto, $secret);
    if (!ctype_digit($ts) || abs(time() - (int)$ts) > 600 || !hash_equals($esperada, $assinatura)) {
        responder(401, ['erro' => 'assinatura inválida']);
        exit;
    }
}

$payload = json_decode($bruto, true);
if (!is_array($payload)) {
    responder(400, ['erro' => 'json inválido']);
    exit;
}
// o corpo também traz o secret: confere sempre
if (!hash_equals($secret, (string)($payload['secret'] ?? ''))) {
    responder(401, ['erro' => 'secret inválido']);
    exit;
}

$evento = (string)($payload['event'] ?? '');
$dados = $payload['data'] ?? [];
// webhook V1: data é um objeto; V2: lista de pedidos
$itens = is_array($dados) && array_keys($dados) === range(0, count($dados) - 1) ? $dados : [$dados];
$agora = time();

try {
    $avisar = comContatos(function (array &$contatos) use ($evento, $itens, $agora) {
        $avisar = [];
        foreach ($itens as $d) {
            if (!is_array($d)) {
                continue;
            }
            $res = registrar($contatos, $evento, $d, $agora);
            if ($res && $res[1]) {
                $avisar[] = $contatos[$res[0]];
            }
        }
        return $avisar;
    });
} catch (Throwable $e) {
    responder(500, ['erro' => 'não consegui gravar os dados']);
    exit;
}

// responde logo (a Cakto espera até 8 s) e só depois manda os e-mails
responder(200, ['ok' => true, 'evento' => $evento, 'registrados' => count($itens)]);
if (function_exists('litespeed_finish_request')) {
    litespeed_finish_request();
} elseif (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}
foreach ($avisar as $r) {
    enviarAviso($r);
}
avisarPendentes();
