<?php
// Núcleo da área de membros Preço Certo. Todas as páginas começam com:
//   require __DIR__ . '/../_membros/bootstrap.php';
declare(strict_types=1);

const PC_MEMBROS = 1;
date_default_timezone_set('America/Sao_Paulo');

/**
 * Configuração. O arquivo real fica FORA da pasta pública e fora do Git:
 *   /home/<usuario>/domains/kustos.com.br/membros-config.php
 * (três pastas acima desta). Modelo em _membros/config.exemplo.php.
 * Em teste local, a variável de ambiente PC_MEMBROS_CONFIG aponta para outro arquivo.
 */
function pc_config(): array
{
    static $c = null;
    if ($c !== null) return $c;
    $candidatos = array_filter([getenv('PC_MEMBROS_CONFIG') ?: null, dirname(__DIR__, 3) . '/membros-config.php']);
    foreach ($candidatos as $p) {
        if (is_file($p)) { $c = require $p; break; }
    }
    if (!is_array($c) || empty($c['app_secret']) || empty($c['db']['dsn'])) {
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
        exit('Área de membros em manutenção. Tente de novo em alguns minutos.');
    }
    $c += [
        'base'          => '/',          // caminho do site (appcalculadora.kustos.com.br = "/")
        'sessao_dias'   => 365,
        'codigo_min'    => 15,
        'suporte'       => '',           // ex.: "https://wa.me/55..." ou "mailto:..."
        'cakto_secret'  => '',
        'admin_token'   => '',
        'produtos_cakto'=> [],           // id do produto/oferta na Cakto => slug do catálogo
        'checkout'      => [],           // slug => link do checkout
        'entrega'       => [],           // slug => link externo de entrega (opcional)
    ];
    $c['email'] = ($c['email'] ?? []) + [
        'modo' => 'smtp', 'host' => 'smtp.hostinger.com', 'porta' => 465,
        'usuario' => 'acesso@kustos.com.br', 'senha' => '',
        'remetente' => 'acesso@kustos.com.br', 'nome' => 'Preço Certo', 'arquivo' => '',
    ];
    return $c;
}

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function pc_url(string $caminho = '/'): string
{
    return rtrim(pc_config()['base'], '/') . '/' . ltrim($caminho, '/');
}

function pc_redirecionar(string $caminho): never
{
    header('Location: ' . (preg_match('#^https?://#', $caminho) ? $caminho : pc_url($caminho)), true, 303);
    exit;
}

/** Só aceita voltar para caminhos internos (evita redirecionamento aberto). */
function pc_volta_segura(?string $v): string
{
    $v = (string)$v;
    return ($v !== '' && $v[0] === '/' && !str_starts_with($v, '//') && !str_contains($v, '\\')) ? $v : '/membros/';
}

function pc_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function pc_sem_cache(): void
{
    header('Cache-Control: no-store, private');
    header('X-Robots-Tag: noindex, nofollow');
    header('Referrer-Policy: same-origin');
    header('X-Content-Type-Options: nosniff');
}

require __DIR__ . '/db.php';
require __DIR__ . '/produtos.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/email.php';
require __DIR__ . '/layout.php';

// carrega a configuração já no início: sem ela, a página para aqui com 503 (e não pela metade)
pc_config();
