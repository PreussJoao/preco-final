<?php
// Painel de recuperação de vendas (com senha): quem abandonou o checkout, gerou Pix/boleto sem pagar ou teve
// o pagamento recusado — com WhatsApp pronto para chamar, link do checkout e quem comprou depois.
declare(strict_types=1);
require __DIR__ . '/lib.php';

header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'");

$https = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_name('pc_painel');
session_set_cookie_params(['lifetime' => 0, 'path' => dirname($_SERVER['SCRIPT_NAME'] ?? '/api/x') . '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Strict']);
session_start();

function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$senha = (string)configuracao()['painel_senha'];
$erro = '';
$post = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';

if ($post && isset($_POST['senha'])) {
    if (strlen($senha) >= 8 && hash_equals($senha, (string)$_POST['senha'])) {
        session_regenerate_id(true);
        $_SESSION['ok'] = true;
        $_SESSION['token'] = bin2hex(random_bytes(16));
        header('Location: abandonos.php', true, 303);
        exit;
    }
    sleep(2); // freia tentativa de adivinhar a senha
    $erro = 'Senha incorreta.';
}

$logado = !empty($_SESSION['ok']);
if ($logado && $post) {
    if (!hash_equals((string)($_SESSION['token'] ?? ''), (string)($_POST['token'] ?? ''))) {
        http_response_code(400);
        exit('Sessão expirada. Recarregue a página.');
    }
    if (isset($_POST['sair'])) {
        $_SESSION = [];
        session_destroy();
        header('Location: abandonos.php', true, 303);
        exit;
    }
    if (isset($_POST['contatado'])) {
        $chave = (string)$_POST['contatado'];
        comContatos(function (array &$contatos) use ($chave) {
            if (isset($contatos[$chave])) {
                if (empty($contatos[$chave]['contatado'])) {
                    $contatos[$chave]['contatado'] = time();
                } else {
                    unset($contatos[$chave]['contatado']);
                }
            }
        });
    }
    header('Location: abandonos.php?ver=' . rawurlencode((string)($_GET['ver'] ?? 'recuperar')), true, 303);
    exit;
}

if (!$logado) {
    ?><!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Recuperação de vendas · Preço Certo</title>
<style>body{margin:0;font:16px/1.5 -apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#faf7f1;color:#251e19;display:grid;place-items:center;min-height:100vh}
form{background:#fff;border:1px solid #e6d9c7;border-radius:16px;padding:26px;width:min(340px,90vw);box-shadow:0 16px 45px #241c1612}
h1{font-size:1.2rem;margin:0 0 14px}input{width:100%;box-sizing:border-box;padding:12px;border:1px solid #d9cbb8;border-radius:10px;font-size:1rem}
button{margin-top:12px;width:100%;padding:12px;border:0;border-radius:10px;background:#c6512b;color:#fff;font-weight:800;font-size:1rem;cursor:pointer}.erro{color:#b33129;font-weight:700}</style></head>
<body><form method="post"><h1>Recuperação de vendas</h1>
<?php if (strlen($senha) < 8): ?><p class="erro">Defina <b>painel_senha</b> (8+ caracteres) na configuração. Veja LEIA-ME_WEBHOOK.md.</p>
<?php else: ?><?php if ($erro): ?><p class="erro"><?= e($erro) ?></p><?php endif; ?>
<input type="password" name="senha" placeholder="Senha" autocomplete="current-password" autofocus required><button>Entrar</button><?php endif; ?>
</form></body></html><?php
    exit;
}

// CSV
if (isset($_GET['csv'])) {
    $todos = comContatos(function (array &$c) { return $c; });
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="recuperacao-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Etapa', 'Quando', 'Nome', 'E-mail', 'Celular', 'Produto', 'Oferta', 'Valor', 'Link do checkout', 'Comprou depois', 'Contatado'], ';');
    foreach ($todos as $r) {
        fputcsv($out, [ROTULOS[$r['etapa'] ?? ''] ?? '', date('d/m/Y H:i', (int)($r['etapaEm'] ?? $r['ultimo'] ?? 0)), $r['nome'] ?? '', $r['email'] ?? '', $r['telefone'] ?? '',
            $r['produto'] ?? '', $r['oferta'] ?? '', isset($r['valor']) ? number_format((float)$r['valor'], 2, ',', '') : '', $r['checkoutUrl'] ?? '',
            !empty($r['recuperado']) ? 'sim' : 'não', !empty($r['contatado']) ? date('d/m/Y H:i', (int)$r['contatado']) : ''], ';');
    }
    exit;
}

avisarPendentes();
$contatos = comContatos(function (array &$c) { return $c; });
uasort($contatos, function ($a, $b) {
    return ((int)($b['ultimo'] ?? 0)) <=> ((int)($a['ultimo'] ?? 0));
});
$ver = (string)($_GET['ver'] ?? 'recuperar');
$filtros = [
    'recuperar' => function ($r) { return ($r['etapa'] ?? '') !== 'comprou'; },
    'recuperados' => function ($r) { return !empty($r['recuperado']); },
    'todos' => function ($r) { return true; },
];
$filtro = $filtros[$ver] ?? $filtros['recuperar'];
$lista = array_filter($contatos, $filtro);

$semana = time() - 7 * 86400;
$conta = ['abandonou' => 0, 'cobranca' => 0, 'recusado' => 0, 'recuperado' => 0, 'perdido' => 0];
foreach ($contatos as $r) {
    if ((int)($r['primeiro'] ?? 0) < $semana) {
        continue;
    }
    $teve = array_column($r['eventos'] ?? [], 'etapa');
    if (array_diff($teve, ['comprou'])) {
        $conta['perdido']++;
    }
    if (in_array('abandonou', $teve, true)) {
        $conta['abandonou']++;
    }
    if (array_intersect($teve, COBRANCAS)) {
        $conta['cobranca']++;
    }
    if (in_array('recusado', $teve, true)) {
        $conta['recusado']++;
    }
    if (!empty($r['recuperado'])) {
        $conta['recuperado']++;
    }
}
$token = (string)$_SESSION['token'];
$cores = ['abandonou' => '#c6512b', 'recusado' => '#b33129', 'pix' => '#a46a00', 'boleto' => '#a46a00', 'picpay' => '#a46a00', 'openfinance' => '#a46a00', 'comprou' => '#246644'];
?><!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Recuperação de vendas · Preço Certo</title>
<style>
:root{--ink:#251e19;--brand:#c6512b;--line:#e6d9c7;--muted:#685b4f}
*{box-sizing:border-box}body{margin:0;font:15px/1.45 -apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#faf7f1;color:var(--ink)}
.wrap{width:min(1100px,100% - 28px);margin:0 auto;padding:20px 0 50px}
header{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}h1{font-size:1.35rem;margin:0}
.btn{display:inline-block;border:1px solid var(--line);background:#fff;color:var(--ink);border-radius:9px;padding:7px 12px;font:inherit;font-weight:700;text-decoration:none;cursor:pointer}
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin:16px 0}
.card{background:#fff;border:1px solid var(--line);border-radius:12px;padding:12px 14px}.card b{display:block;font-size:1.6rem}.card span{color:var(--muted);font-size:.82rem}
nav{display:flex;gap:6px;margin:6px 0 12px;flex-wrap:wrap}nav a{padding:6px 12px;border-radius:99px;text-decoration:none;color:var(--muted);font-weight:700;border:1px solid var(--line);background:#fff}nav a.on{background:var(--ink);color:#fff;border-color:var(--ink)}
.item{background:#fff;border:1px solid var(--line);border-radius:12px;padding:12px 14px;margin:0 0 8px;display:grid;grid-template-columns:1fr auto;gap:6px 14px}
.item.feito{opacity:.62}.tag{display:inline-block;border-radius:99px;padding:2px 9px;color:#fff;font-size:.75rem;font-weight:800}
.nome{font-weight:800;font-size:1.02rem}.meta{color:var(--muted);font-size:.85rem}.acoes{display:flex;gap:6px;flex-wrap:wrap;align-items:start;justify-content:end}
.wa{background:#1f8f4e;color:#fff;border-color:#1f8f4e}.vazio{color:var(--muted);text-align:center;padding:30px}
@media(max-width:640px){.item{grid-template-columns:1fr}.acoes{justify-content:start}}
</style></head><body><div class="wrap">
<header><h1>Recuperação de vendas</h1>
<div><a class="btn" href="?csv=1">Baixar CSV</a> <form method="post" style="display:inline"><input type="hidden" name="token" value="<?= e($token) ?>"><button class="btn" name="sair" value="1">Sair</button></form></div></header>
<div class="cards">
<div class="card"><b><?= $conta['abandonou'] ?></b><span>abandonaram o checkout (7 dias)</span></div>
<div class="card"><b><?= $conta['cobranca'] ?></b><span>geraram Pix/boleto (7 dias)</span></div>
<div class="card"><b><?= $conta['recusado'] ?></b><span>tiveram pagamento recusado (7 dias)</span></div>
<div class="card"><b><?= $conta['recuperado'] ?></b><span>compraram depois (7 dias<?= $conta['perdido'] ? ' · ' . round(100 * $conta['recuperado'] / $conta['perdido']) . '%' : '' ?>)</span></div>
</div>
<nav><?php foreach (['recuperar' => 'A recuperar', 'recuperados' => 'Compraram depois', 'todos' => 'Todos'] as $k => $t): ?><a href="?ver=<?= e($k) ?>" class="<?= $ver === $k ? 'on' : '' ?>"><?= e($t) ?></a><?php endforeach; ?></nav>
<?php if (!$lista): ?><p class="vazio">Nada por aqui ainda. Os contatos aparecem quando a Cakto enviar os eventos.</p><?php endif; ?>
<?php foreach ($lista as $chave => $r):
    $etapa = (string)($r['etapa'] ?? '');
    $wa = linkWhatsapp($r);
    ?>
<div class="item<?= !empty($r['contatado']) ? ' feito' : '' ?>">
  <div>
    <span class="tag" style="background:<?= e($cores[$etapa] ?? '#685b4f') ?>"><?= e(ROTULOS[$etapa] ?? $etapa) ?></span>
    <?php if (!empty($r['recuperado'])): ?><span class="tag" style="background:#246644">venda recuperada</span><?php endif; ?>
    <div class="nome"><?= e(($r['nome'] ?? '') ?: 'Sem nome') ?></div>
    <div class="meta"><?= e(date('d/m H:i', (int)($r['etapaEm'] ?? $r['ultimo'] ?? 0))) ?> · <?= e(dinheiro($r['valor'] ?? null)) ?> · <?= e(($r['oferta'] ?? '') ?: ($r['produto'] ?? '')) ?></div>
    <div class="meta"><?= e(($r['email'] ?? '') ?: '—') ?> · <?= e(($r['telefone'] ?? '') ?: 'sem celular') ?><?= !empty($r['contatado']) ? ' · contatado em ' . e(date('d/m H:i', (int)$r['contatado'])) : '' ?></div>
  </div>
  <div class="acoes">
    <?php if ($wa && $etapa !== 'comprou'): ?><a class="btn wa" href="<?= e($wa) ?>" target="_blank" rel="noopener noreferrer">WhatsApp</a><?php endif; ?>
    <?php if (!empty($r['email'])): ?><a class="btn" href="mailto:<?= e($r['email']) ?>">E-mail</a><?php endif; ?>
    <?php if (!empty($r['checkoutUrl'])): ?><a class="btn" href="<?= e($r['checkoutUrl']) ?>" target="_blank" rel="noopener noreferrer">Checkout</a><?php endif; ?>
    <?php if ($etapa !== 'comprou'): ?><form method="post" action="?ver=<?= e($ver) ?>"><input type="hidden" name="token" value="<?= e($token) ?>"><button class="btn" name="contatado" value="<?= e($chave) ?>"><?= !empty($r['contatado']) ? 'Desfazer' : 'Já chamei' ?></button></form><?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
<p class="meta">Os registros somem sozinhos depois de <?= (int)configuracao()['dias_guardar'] ?> dias. Pix e boleto só aparecem como pendentes depois de <?= (int)configuracao()['pix_minutos'] ?> minutos sem pagamento.</p>
</div></body></html>
