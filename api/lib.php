<?php
// Funções comuns do webhook da Cakto (cakto-webhook.php), do painel (abandonos.php) e do aviso de pendentes
// (avisar-pendentes.php). Não responde pelo navegador: ver api/.htaccess.
//
// O que fica guardado: quem abandonou o checkout, gerou Pix/boleto sem pagar ou teve o pagamento recusado
// (nome, e-mail, celular, produto, valor, link do checkout) e se depois comprou. Os registros somem
// sozinhos depois de "dias_guardar" dias (LGPD: só o necessário para recuperar a venda).
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

// evento da Cakto → etapa guardada
const ETAPAS = [
    'checkout_abandonment' => 'abandonou',
    'purchase_refused' => 'recusado',
    'pix_gerado' => 'pix',
    'boleto_gerado' => 'boleto',
    'picpay_gerado' => 'picpay',
    'openfinance_nubank_gerado' => 'openfinance',
    'purchase_approved' => 'comprou',
];
const ROTULOS = [
    'abandonou' => 'Abandonou o checkout',
    'recusado' => 'Pagamento recusado',
    'pix' => 'Pix gerado, não pago',
    'boleto' => 'Boleto gerado, não pago',
    'picpay' => 'PicPay gerado, não pago',
    'openfinance' => 'Open Finance gerado, não pago',
    'comprou' => 'Comprou',
];
// cobranças geradas: só viram aviso se continuarem sem pagamento depois de "pix_minutos"
const COBRANCAS = ['pix', 'boleto', 'picpay', 'openfinance'];

function configuracao(): array
{
    static $c = null;
    if ($c !== null) {
        return $c;
    }
    $c = [];
    // preferência: fora do public_html (a implantação por Git nunca mexe), senão api/config.php
    foreach ([dirname(__DIR__, 2) . '/preco-certo-config.php', __DIR__ . '/config.php'] as $f) {
        if (@is_file($f)) {
            $lido = require $f;
            $c = is_array($lido) ? $lido : [];
            break;
        }
    }
    $c += [
        'secret' => '',
        'email_para' => '',
        'email_de' => '',
        'painel_senha' => '',
        'site' => '',
        'produtos' => [],
        'avisar' => ['abandonou', 'recusado', 'pix', 'boleto', 'picpay', 'openfinance'],
        'avisar_recuperacao' => false,
        'pix_minutos' => 30,
        'dias_guardar' => 30,
        'pasta_dados' => '',
        'mensagem_whatsapp' => 'Oi, {nome}! Vi que você começou a garantir o Preço Certo e não finalizou. Ficou alguma dúvida? Se quiser terminar, o link é este: {link}',
    ];
    return $c;
}

function configurado(): bool
{
    $s = (string)configuracao()['secret'];
    return strlen($s) >= 8 && strpos($s, 'COLE_AQUI') !== 0;
}

// pasta dos dados: fora do public_html quando der; senão api/dados (bloqueada por .htaccess)
function pastaDados(): string
{
    static $p = null;
    if ($p !== null) {
        return $p;
    }
    $opcoes = array_filter([(string)configuracao()['pasta_dados'], dirname(__DIR__, 2) . '/preco-certo-dados', __DIR__ . '/dados']);
    foreach ($opcoes as $d) {
        if ((@is_dir($d) || @mkdir($d, 0700, true)) && @is_writable($d)) {
            return $p = $d;
        }
    }
    throw new RuntimeException('não consegui criar a pasta de dados');
}

// lê e grava contatos.json com trava (dois webhooks ao mesmo tempo não se atropelam)
function comContatos(callable $f)
{
    $fp = fopen(pastaDados() . '/contatos.json', 'c+');
    if ($fp === false || !flock($fp, LOCK_EX)) {
        throw new RuntimeException('não consegui abrir contatos.json');
    }
    $dados = json_decode((string)stream_get_contents($fp), true);
    $dados = is_array($dados) ? $dados : [];
    $antes = json_encode($dados);
    $ret = $f($dados);
    // LGPD: apaga o que passou do prazo
    $limite = time() - 86400 * max(1, (int)configuracao()['dias_guardar']);
    $dados = array_filter($dados, function ($r) use ($limite) {
        return (int)($r['ultimo'] ?? 0) >= $limite;
    });
    $depois = json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($depois !== $antes) {
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, (string)$depois);
        fflush($fp);
    }
    flock($fp, LOCK_UN);
    fclose($fp);
    return $ret;
}

function texto($v, int $max = 300): string
{
    return is_scalar($v) ? substr(trim((string)$v), 0, $max) : '';
}

function soDigitos(string $s): string
{
    return (string)preg_replace('/\D+/', '', $s);
}

// celular brasileiro → 55DDD9XXXXXXXX (para o link do WhatsApp)
function telefoneWhatsapp(string $tel): string
{
    $d = soDigitos($tel);
    if (strlen($d) === 10 || strlen($d) === 11) {
        $d = '55' . $d;
    }
    return strlen($d) >= 12 ? $d : '';
}

function quando($v): int
{
    if (is_numeric($v)) {
        $n = (int)$v;
        return $n > 20000000000 ? intdiv($n, 1000) : $n;
    }
    try {
        return (new DateTimeImmutable(texto($v)))->getTimestamp();
    } catch (Exception $e) {
        return time();
    }
}

// um item de "data" (pedido ou abandono) → campos que interessam
function extrair(string $evento, array $d): array
{
    $cli = is_array($d['customer'] ?? null) ? $d['customer'] : [];
    $oferta = is_array($d['offer'] ?? null) ? $d['offer'] : [];
    $produto = is_array($d['product'] ?? null) ? $d['product'] : [];
    $pix = is_array($d['pix'] ?? null) ? $d['pix'] : [];
    $boleto = is_array($d['boleto'] ?? null) ? $d['boleto'] : [];
    $valor = $d['amount'] ?? ($d['baseAmount'] ?? ($oferta['price'] ?? null));
    return [
        'nome' => texto($d['customerName'] ?? ($cli['name'] ?? ''), 120),
        'email' => strtolower(texto($d['customerEmail'] ?? ($cli['email'] ?? ''), 160)),
        'telefone' => texto($d['customerCellphone'] ?? ($cli['phone'] ?? ''), 40),
        'produto' => texto($produto['name'] ?? '', 120),
        'produtoIds' => [texto($produto['id'] ?? ''), texto($produto['short_id'] ?? '')],
        'oferta' => texto($oferta['name'] ?? '', 120),
        'ofertaId' => texto($oferta['id'] ?? ($produto['id'] ?? ''), 80),
        'valor' => is_numeric($valor) ? round((float)$valor, 2) : null,
        'checkoutUrl' => texto($d['checkoutUrl'] ?? '', 500),
        'boletoUrl' => texto($boleto['url'] ?? ($boleto['boletoUrl'] ?? ''), 500),
        'expira' => isset($pix['expirationDate']) ? quando($pix['expirationDate']) : null,
        'em' => quando($d['createdAt'] ?? ($d['paidAt'] ?? time())),
        'idEvento' => $evento . '|' . texto($d['id'] ?? '', 80) . '|' . texto($d['createdAt'] ?? '', 40) . '|' . strtolower(texto($d['customerEmail'] ?? '', 160)),
    ];
}

// grava um evento no contato (cria o contato se for novo). Devolve [chave, aviso imediato?] ou null se ignorado.
function registrar(array &$contatos, string $evento, array $d, int $agora): ?array
{
    $etapa = ETAPAS[$evento] ?? null;
    if ($etapa === null) {
        return null;
    }
    $x = extrair($evento, $d);
    $aceitos = array_filter((array)configuracao()['produtos']);
    if ($aceitos && !array_intersect(array_filter($x['produtoIds']), $aceitos)) {
        return null;
    }
    $quem = $x['email'] !== '' ? $x['email'] : soDigitos($x['telefone']);
    if ($quem === '') {
        return null;
    }
    $chave = substr(hash('sha256', $quem . '|' . $x['ofertaId']), 0, 20);
    $r = $contatos[$chave] ?? ['primeiro' => $agora, 'eventos' => [], 'vistos' => [], 'avisos' => []];
    $idEv = substr(sha1($x['idEvento']), 0, 16);
    if (in_array($idEv, $r['vistos'], true)) {
        return [$chave, false]; // reenvio da Cakto: já registrado
    }
    $r['vistos'] = array_slice(array_merge($r['vistos'], [$idEv]), -40);
    foreach (['nome', 'email', 'telefone', 'produto', 'oferta', 'checkoutUrl', 'boletoUrl'] as $k) {
        if ($x[$k] !== '') {
            $r[$k] = $x[$k];
        }
    }
    if ($x['valor'] !== null) {
        $r['valor'] = $x['valor'];
    }
    if ($x['expira'] !== null) {
        $r['expira'] = $x['expira'];
    }
    $anterior = $r['etapa'] ?? null;
    $r['eventos'] = array_slice(array_merge($r['eventos'], [['etapa' => $etapa, 't' => $agora]]), -20);
    $r['ultimo'] = $agora;
    $aviso = false;
    if ($etapa === 'comprou') {
        $r['etapa'] = 'comprou';
        $r['comprouEm'] = $agora;
        $r['recuperado'] = $anterior !== null && $anterior !== 'comprou';
        $aviso = $r['recuperado'] && configuracao()['avisar_recuperacao'];
    } elseif (($r['etapa'] ?? '') !== 'comprou') { // depois de comprar, um abandono atrasado não desfaz a compra
        $r['etapa'] = $etapa;
        $r['etapaEm'] = $agora;
        // abandono e recusa avisam na hora; Pix/boleto só se continuarem sem pagamento (avisarPendentes)
        $aviso = !in_array($etapa, COBRANCAS, true) && in_array($etapa, (array)configuracao()['avisar'], true) && !isset($r['avisos'][$etapa]);
    }
    if ($aviso) {
        $r['avisos'][$etapa] = $agora;
    }
    $contatos[$chave] = $r;
    return [$chave, $aviso];
}

// Pix/boleto gerado e ainda sem pagamento depois de "pix_minutos": marca e devolve as chaves para avisar
function marcarPendentes(array &$contatos, int $agora): array
{
    $c = configuracao();
    $espera = 60 * max(0, (int)$c['pix_minutos']);
    $saida = [];
    foreach ($contatos as $chave => &$r) {
        $e = $r['etapa'] ?? '';
        if (in_array($e, COBRANCAS, true) && in_array($e, (array)$c['avisar'], true) && !isset($r['avisos'][$e]) && $agora - (int)($r['etapaEm'] ?? $agora) >= $espera) {
            $r['avisos'][$e] = $agora;
            $saida[] = $chave;
        }
    }
    unset($r);
    return $saida;
}

function urlPainel(): string
{
    $site = rtrim((string)configuracao()['site'], '/');
    return $site !== '' ? $site . '/api/abandonos.php' : '';
}

function linkWhatsapp(array $r): string
{
    $tel = telefoneWhatsapp((string)($r['telefone'] ?? ''));
    if ($tel === '') {
        return '';
    }
    $primeiro = explode(' ', trim((string)($r['nome'] ?? '')))[0] ?? '';
    $link = (string)($r['checkoutUrl'] ?? '') ?: (string)($r['boletoUrl'] ?? '') ?: (string)configuracao()['site'];
    $msg = strtr((string)configuracao()['mensagem_whatsapp'], ['{nome}' => $primeiro !== '' ? $primeiro : 'tudo bem', '{link}' => $link]);
    return 'https://wa.me/' . $tel . '?text=' . rawurlencode($msg);
}

function dinheiro($v): string
{
    return $v === null ? '—' : 'R$ ' . number_format((float)$v, 2, ',', '.');
}

// e-mail de aviso (texto simples). Toda tentativa fica registrada em avisos.log na pasta de dados.
function enviarAviso(array $r): bool
{
    $c = configuracao();
    $etapa = (string)($r['etapa'] ?? '');
    $rotulo = ($r['recuperado'] ?? false) ? 'Recuperou a venda' : (ROTULOS[$etapa] ?? $etapa);
    $nome = (string)($r['nome'] ?? '') ?: 'Sem nome';
    $linhas = [
        $rotulo . ' — Preço Certo',
        '',
        'Nome: ' . $nome,
        'WhatsApp: ' . (($r['telefone'] ?? '') ?: '—') . (linkWhatsapp($r) ? '  →  ' . linkWhatsapp($r) : ''),
        'E-mail: ' . (($r['email'] ?? '') ?: '—'),
        'Produto: ' . trim(($r['produto'] ?? '') . ' ' . (($r['oferta'] ?? '') ? '(' . $r['oferta'] . ')' : '')),
        'Valor: ' . dinheiro($r['valor'] ?? null),
    ];
    if (!empty($r['checkoutUrl'])) {
        $linhas[] = 'Link do checkout: ' . $r['checkoutUrl'];
    }
    if (!empty($r['boletoUrl'])) {
        $linhas[] = 'Boleto: ' . $r['boletoUrl'];
    }
    $linhas[] = 'Quando: ' . date('d/m/Y H:i', (int)($r['etapaEm'] ?? $r['ultimo'] ?? time()));
    if (urlPainel()) {
        $linhas[] = '';
        $linhas[] = 'Todos os contatos: ' . urlPainel();
    }
    $assunto = $rotulo . ': ' . $nome;
    $ok = false;
    $para = (string)$c['email_para'];
    if ($para !== '' && function_exists('mail')) {
        $de = (string)$c['email_de'] ?: $para;
        $cab = "From: Preço Certo <$de>\r\nReply-To: $de\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit";
        $ok = @mail($para, '=?UTF-8?B?' . base64_encode($assunto) . '?=', implode("\r\n", $linhas), $cab);
    }
    @file_put_contents(pastaDados() . '/avisos.log', date('c') . ' ' . ($ok ? 'enviado' : 'NAO enviado') . ' · ' . $assunto . "\n", FILE_APPEND | LOCK_EX);
    return $ok;
}

// confere e avisa os Pix/boletos pendentes (roda a cada webhook, ao abrir o painel e pelo cron opcional)
function avisarPendentes(): int
{
    $agora = time();
    $lista = comContatos(function (array &$contatos) use ($agora) {
        $saida = [];
        foreach (marcarPendentes($contatos, $agora) as $k) {
            $saida[] = $contatos[$k];
        }
        return $saida;
    });
    foreach ($lista as $r) {
        enviarAviso($r);
    }
    return count($lista);
}
