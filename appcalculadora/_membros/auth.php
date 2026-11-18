<?php
defined('PC_MEMBROS') || exit;

const PC_COOKIE = 'pc_sessao';
const PC_MAX_TENTATIVAS = 5;      // erros por código
const PC_MAX_CODIGOS_EMAIL = 3;   // códigos por e-mail a cada janela
const PC_MAX_CODIGOS_IP = 10;     // códigos por IP a cada janela
const PC_JANELA = 900;            // 15 minutos

function pc_norm_email(string $e): string { return mb_strtolower(trim($e), 'UTF-8'); }

function pc_ip(): string { return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64); }

function pc_hash(string $valor): string { return hash_hmac('sha256', $valor, pc_config()['app_secret']); }

/** Produtos com acesso ativo para o e-mail: [slug => true]. */
function pc_produtos_liberados(string $email): array
{
    $q = pc_db()->prepare("SELECT DISTINCT produto FROM compras
                           WHERE email = ? AND status = 'ativa' AND (expira_em IS NULL OR expira_em > ?)");
    $q->execute([pc_norm_email($email), time()]);
    return array_fill_keys($q->fetchAll(PDO::FETCH_COLUMN), true);
}

function pc_tem_acesso(string $email, string $produto): bool
{
    return isset(pc_produtos_liberados($email)[$produto]);
}

/**
 * Gera um código de 6 dígitos. Devolve null quando o limite de envios foi atingido.
 * Quem chama decide se envia (só envia se o e-mail tiver compra).
 */
function pc_criar_codigo(string $email): ?string
{
    $db = pc_db();
    $email = pc_norm_email($email);
    $desde = time() - PC_JANELA;

    $q = $db->prepare('SELECT COUNT(*) FROM codigos WHERE email = ? AND criado_em > ?');
    $q->execute([$email, $desde]);
    if ((int)$q->fetchColumn() >= PC_MAX_CODIGOS_EMAIL) return null;

    $q = $db->prepare('SELECT COUNT(*) FROM codigos WHERE ip = ? AND criado_em > ?');
    $q->execute([pc_ip(), $desde]);
    if ((int)$q->fetchColumn() >= PC_MAX_CODIGOS_IP) return null;

    $codigo = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    // um código novo invalida os anteriores
    $db->prepare('UPDATE codigos SET usado = 1 WHERE email = ? AND usado = 0')->execute([$email]);
    $db->prepare('INSERT INTO codigos (email, hash, ip, criado_em, expira_em) VALUES (?, ?, ?, ?, ?)')
       ->execute([$email, pc_hash($email . '|' . $codigo), pc_ip(), time(), time() + pc_config()['codigo_min'] * 60]);
    return $codigo;
}

/** Confere o código. Cada erro conta uma tentativa; na 5ª o código morre. */
function pc_verificar_codigo(string $email, string $codigo): bool
{
    $db = pc_db();
    $email = pc_norm_email($email);
    $codigo = preg_replace('/\D/', '', $codigo);

    $q = $db->prepare('SELECT id, hash, tentativas FROM codigos
                       WHERE email = ? AND usado = 0 AND expira_em > ? ORDER BY id DESC LIMIT 1');
    $q->execute([$email, time()]);
    $linha = $q->fetch();
    if (!$linha || $linha['tentativas'] >= PC_MAX_TENTATIVAS) return false;

    if (strlen($codigo) === 6 && hash_equals($linha['hash'], pc_hash($email . '|' . $codigo))) {
        $db->prepare('UPDATE codigos SET usado = 1 WHERE id = ?')->execute([$linha['id']]);
        return true;
    }
    $db->prepare('UPDATE codigos SET tentativas = tentativas + 1 WHERE id = ?')->execute([$linha['id']]);
    return false;
}

function pc_iniciar_sessao(string $email): void
{
    $token = bin2hex(random_bytes(32));
    $expira = time() + pc_config()['sessao_dias'] * 86400;
    pc_db()->prepare('INSERT INTO sessoes (token, email, criado_em, expira_em, ultimo_uso) VALUES (?, ?, ?, ?, ?)')
           ->execute([hash('sha256', $token), pc_norm_email($email), time(), $expira, time()]);
    pc_cookie($token, $expira);
}

function pc_cookie(string $valor, int $expira): void
{
    setcookie(PC_COOKIE, $valor, [
        'expires'  => $expira,
        'path'     => '/',
        'secure'   => pc_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/** Sessão atual: ['email' => ...] ou null. */
function pc_sessao(): ?array
{
    static $cache = false;
    if ($cache !== false) return $cache;
    $token = $_COOKIE[PC_COOKIE] ?? '';
    if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) return $cache = null;

    $q = pc_db()->prepare('SELECT id, email, ultimo_uso FROM sessoes WHERE token = ? AND expira_em > ?');
    $q->execute([hash('sha256', $token), time()]);
    $s = $q->fetch();
    if (!$s) return $cache = null;
    if (time() - (int)$s['ultimo_uso'] > 86400) {
        pc_db()->prepare('UPDATE sessoes SET ultimo_uso = ? WHERE id = ?')->execute([time(), $s['id']]);
    }
    return $cache = ['email' => $s['email']];
}

function pc_exigir_sessao(): array
{
    $s = pc_sessao();
    if (!$s) pc_redirecionar('/entrar/?volta=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/membros/'));
    return $s;
}

function pc_encerrar_sessao(): void
{
    $token = $_COOKIE[PC_COOKIE] ?? '';
    if (is_string($token) && $token !== '') {
        pc_db()->prepare('DELETE FROM sessoes WHERE token = ?')->execute([hash('sha256', $token)]);
    }
    pc_cookie('', time() - 3600);
}

/** Token simples contra envio de formulário por outro site. */
function pc_csrf(): string
{
    if (empty($_COOKIE['pc_csrf']) || !preg_match('/^[a-f0-9]{32}$/', (string)$_COOKIE['pc_csrf'])) {
        $t = bin2hex(random_bytes(16));
        setcookie('pc_csrf', $t, ['path' => '/', 'secure' => pc_https(), 'httponly' => true, 'samesite' => 'Strict']);
        $_COOKIE['pc_csrf'] = $t;
    }
    return $_COOKIE['pc_csrf'];
}

function pc_csrf_ok(): bool
{
    $c = (string)($_COOKIE['pc_csrf'] ?? '');
    return $c !== '' && hash_equals($c, (string)($_POST['csrf'] ?? ''));
}
