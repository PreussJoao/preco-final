<?php
defined('PC_MEMBROS') || exit;

function pc_db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    $c = pc_config()['db'];
    $pdo = new PDO($c['dsn'], $c['usuario'] ?? null, $c['senha'] ?? null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    if (pc_driver($pdo) === 'sqlite') $pdo->exec('PRAGMA busy_timeout = 5000');
    pc_migrar($pdo);
    return $pdo;
}

function pc_driver(PDO $pdo): string { return (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME); }

/** Cria as tabelas na primeira vez (MySQL na Hostinger, SQLite no teste local). */
function pc_migrar(PDO $pdo): void
{
    $mysql = pc_driver($pdo) === 'mysql';
    $marca = $mysql
        ? "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'eventos'"
        : "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'eventos'";
    if ((int)$pdo->query($marca)->fetchColumn() > 0) return;

    $id  = $mysql ? 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $fim = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
    $txt = $mysql ? 'MEDIUMTEXT' : 'TEXT';
    $idx = fn(string $nome, string $col) => $mysql ? ", INDEX $nome ($col)" : '';

    $sql = [
        "CREATE TABLE IF NOT EXISTS clientes (
            id $id, email VARCHAR(190) NOT NULL UNIQUE, nome VARCHAR(190) NULL, criado_em INT NOT NULL)$fim",
        // status: ativa | reembolsada | chargeback | cancelada. expira_em NULL = acesso vitalício (pronto para assinatura).
        "CREATE TABLE IF NOT EXISTS compras (
            id $id, pedido VARCHAR(80) NOT NULL UNIQUE, email VARCHAR(190) NOT NULL, produto VARCHAR(40) NOT NULL,
            oferta VARCHAR(80) NULL, valor DECIMAL(10,2) NULL, status VARCHAR(20) NOT NULL, origem VARCHAR(20) NOT NULL,
            pago_em INT NULL, expira_em INT NULL, atualizado_em INT NOT NULL" . $idx('idx_compras_email', 'email') . ")$fim",
        "CREATE TABLE IF NOT EXISTS codigos (
            id $id, email VARCHAR(190) NOT NULL, hash CHAR(64) NOT NULL, ip VARCHAR(64) NOT NULL, criado_em INT NOT NULL,
            expira_em INT NOT NULL, tentativas INT NOT NULL DEFAULT 0, usado INT NOT NULL DEFAULT 0"
            . $idx('idx_codigos_email', 'email') . $idx('idx_codigos_ip', 'ip, criado_em') . ")$fim",
        "CREATE TABLE IF NOT EXISTS sessoes (
            id $id, token CHAR(64) NOT NULL UNIQUE, email VARCHAR(190) NOT NULL, criado_em INT NOT NULL,
            expira_em INT NOT NULL, ultimo_uso INT NOT NULL)$fim",
        "CREATE TABLE IF NOT EXISTS eventos (
            id $id, chave VARCHAR(190) NOT NULL UNIQUE, evento VARCHAR(60) NOT NULL, pedido VARCHAR(80) NULL,
            recebido_em INT NOT NULL, resultado VARCHAR(190) NULL, payload $txt NULL)$fim",
    ];
    if (!$mysql) {
        $sql[] = 'CREATE INDEX IF NOT EXISTS idx_compras_email ON compras (email)';
        $sql[] = 'CREATE INDEX IF NOT EXISTS idx_codigos_email ON codigos (email)';
        $sql[] = 'CREATE INDEX IF NOT EXISTS idx_codigos_ip ON codigos (ip, criado_em)';
    }
    // "eventos" é criada por último: ela marca que a migração terminou.
    foreach ($sql as $q) $pdo->exec($q);
}

/** Insere ou atualiza uma compra pelo número do pedido (idempotente). */
function pc_gravar_compra(array $c): void
{
    $db = pc_db();
    $agora = time();
    $email = pc_norm_email($c['email']);

    $cli = $db->prepare('SELECT id FROM clientes WHERE email = ?');
    $cli->execute([$email]);
    if (!$cli->fetchColumn()) {
        $db->prepare('INSERT INTO clientes (email, nome, criado_em) VALUES (?, ?, ?)')
           ->execute([$email, $c['nome'] ?? null, $agora]);
    } elseif (!empty($c['nome'])) {
        $db->prepare('UPDATE clientes SET nome = COALESCE(nome, ?) WHERE email = ?')->execute([$c['nome'], $email]);
    }

    $q = $db->prepare('SELECT id FROM compras WHERE pedido = ?');
    $q->execute([$c['pedido']]);
    if ($q->fetchColumn()) {
        $db->prepare('UPDATE compras SET status = ?, atualizado_em = ? WHERE pedido = ?')
           ->execute([$c['status'], $agora, $c['pedido']]);
    } else {
        $db->prepare('INSERT INTO compras (pedido, email, produto, oferta, valor, status, origem, pago_em, expira_em, atualizado_em)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
           ->execute([$c['pedido'], $email, $c['produto'], $c['oferta'] ?? null, $c['valor'] ?? null,
                      $c['status'], $c['origem'] ?? 'cakto', $c['pago_em'] ?? $agora, $c['expira_em'] ?? null, $agora]);
    }
}
