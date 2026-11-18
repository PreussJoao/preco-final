<?php
// Recebe os avisos da Cakto. Cadastre na Cakto com os eventos:
// purchase_approved, refund, chargeback (e refund_requested, só para registro).
require __DIR__ . '/../_membros/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
pc_sem_cache();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('{"ok":false}'); }

$cru = file_get_contents('php://input') ?: '';
$segredo = (string)pc_config()['cakto_secret'];
if ($segredo === '') { http_response_code(503); exit('{"ok":false,"erro":"sem segredo"}'); }

// 1) Assinatura no header (preferida): HMAC-SHA256 de "timestamp.corpo", até 5 min de diferença.
$ts  = (string)($_SERVER['HTTP_X_CAKTO_TIMESTAMP'] ?? '');
$sig = (string)($_SERVER['HTTP_X_CAKTO_SIGNATURE'] ?? '');
$valido = false;
if ($ts !== '' && $sig !== '' && ctype_digit($ts) && abs(time() - (int)$ts) <= 300) {
    $esperado = 'v1=' . hash_hmac('sha256', $ts . '.' . $cru, $segredo);
    foreach (explode(',', $sig) as $parte) {
        if (hash_equals($esperado, trim($parte))) { $valido = true; break; }
    }
}
$corpo = json_decode($cru, true);
// 2) Sem header: compara o "secret" que vem no corpo.
if (!$valido && is_array($corpo) && is_string($corpo['secret'] ?? null)) {
    $valido = hash_equals($segredo, $corpo['secret']);
}
if (!$valido || !is_array($corpo)) { http_response_code(401); exit('{"ok":false}'); }

$evento = (string)($corpo['event'] ?? '');
$dados = $corpo['data'] ?? [];
$pedidos = array_is_list($dados) ? $dados : [$dados];   // webhook V2 manda lista

$statusPorEvento = [
    'purchase_approved' => 'ativa',
    'refund'            => 'reembolsada',
    'chargeback'        => 'chargeback',
];

$db = pc_db();
$resultados = [];
foreach ($pedidos as $d) {
    if (!is_array($d)) continue;
    $pedido = (string)($d['id'] ?? '');
    $chave = substr($evento . ':' . ($pedido !== '' ? $pedido : hash('sha256', json_encode($d))), 0, 190);

    // já processado? (a Cakto pode reenviar)
    $q = $db->prepare('SELECT 1 FROM eventos WHERE chave = ?');
    $q->execute([$chave]);
    if ($q->fetchColumn()) { $resultados[] = 'repetido'; continue; }

    $resultado = 'ignorado';
    $produto = pc_produto_da_venda($d);
    $email = pc_norm_email((string)($d['customer']['email'] ?? ''));

    if (isset($statusPorEvento[$evento]) && $pedido !== '' && $email !== '') {
        if ($produto === null) {
            $resultado = 'produto não mapeado: ' . ($d['product']['id'] ?? '?') . ' / oferta ' . ($d['offer']['id'] ?? '?');
        } elseif ($evento === 'purchase_approved' && ($d['status'] ?? '') !== 'paid') {
            $resultado = 'aprovado sem status paid: ' . ($d['status'] ?? '?');
        } else {
            pc_gravar_compra([
                'pedido'  => $pedido,
                'email'   => $email,
                'nome'    => $d['customer']['name'] ?? null,
                'produto' => $produto,
                'oferta'  => $d['offer']['id'] ?? null,
                'valor'   => isset($d['amount']) ? (float)$d['amount'] : null,
                'status'  => $statusPorEvento[$evento],
                'pago_em' => !empty($d['paidAt']) ? strtotime($d['paidAt']) : time(),
            ]);
            $resultado = $statusPorEvento[$evento] . ' → ' . $produto;
        }
    }

    // produto ainda não mapeado: não trava o pedido, para um reenvio (depois de mapear) funcionar
    if (str_starts_with($resultado, 'produto não mapeado')) $chave = substr($chave, 0, 170) . ':pendente:' . bin2hex(random_bytes(4));

    // guarda o aviso sem o segredo
    $copia = $corpo; unset($copia['secret']); $copia['data'] = $d;
    $db->prepare('INSERT INTO eventos (chave, evento, pedido, recebido_em, resultado, payload) VALUES (?, ?, ?, ?, ?, ?)')
       ->execute([$chave, substr($evento, 0, 60), $pedido ?: null, time(), substr($resultado, 0, 190),
                  json_encode($copia, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    $resultados[] = $resultado;
}

echo json_encode(['ok' => true, 'resultados' => $resultados], JSON_UNESCAPED_UNICODE);
