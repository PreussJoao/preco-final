<?php
// Importa quem comprou antes da área de membros existir.
// Uso (uma vez): POST com o cabeçalho "Authorization: Bearer <admin_token>" e o corpo em CSV:
//   email,pedido,produto
//   maria@gmail.com,4852F91,calculadora
// "pedido" pode ser o refId ou o id da Cakto; "produto" é o slug do catálogo.
require __DIR__ . '/../_membros/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
pc_sem_cache();

$token = (string)pc_config()['admin_token'];
$auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || strlen($token) < 32 || !hash_equals('Bearer ' . $token, $auth)) {
    http_response_code(401);
    exit('{"ok":false}');
}

$catalogo = pc_catalogo();
$linhas = preg_split('/\r\n|\r|\n/', (string)file_get_contents('php://input'));
$ok = 0; $erros = [];
foreach ($linhas as $n => $linha) {
    if (trim($linha) === '' || stripos($linha, 'email') === 0) continue;
    [$email, $pedido, $produto] = array_map('trim', str_getcsv($linha) + ['', '', '']);
    $email = pc_norm_email($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $pedido === '' || !isset($catalogo[$produto])) {
        $erros[] = 'linha ' . ($n + 1) . ': ' . $linha;
        continue;
    }
    pc_gravar_compra(['pedido' => $pedido, 'email' => $email, 'produto' => $produto,
                      'status' => 'ativa', 'origem' => 'importacao']);
    $ok++;
}
echo json_encode(['ok' => true, 'importados' => $ok, 'erros' => $erros], JSON_UNESCAPED_UNICODE);
