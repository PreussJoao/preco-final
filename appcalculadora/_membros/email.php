<?php
defined('PC_MEMBROS') || exit;

/** Envia o código de acesso. Lança exceção se o envio falhar. */
function pc_enviar_codigo(string $para, string $codigo): void
{
    $min = pc_config()['codigo_min'];
    $assunto = "Seu código de acesso: $codigo";
    $texto = "Seu código de acesso ao Preço Certo é: $codigo\n\n"
           . "Ele vale por $min minutos. Se não foi você que pediu, ignore este e-mail.\n";
    $html = '<!doctype html><html><body style="margin:0;background:#F8F2E8;font-family:Arial,Helvetica,sans-serif;color:#2A211B">'
          . '<div style="max-width:440px;margin:0 auto;padding:28px 16px">'
          . '<div style="background:#1E1712;border-radius:16px 16px 0 0;padding:22px 24px;color:#fff">'
          . '<div style="font-family:Georgia,serif;font-size:20px;font-weight:bold">Preço Certo</div>'
          . '<div style="font-size:13px;color:#cbbfb2;margin-top:2px">área do cliente</div></div>'
          . '<div style="background:#FFFDF9;border:1px solid #E9DFD0;border-top:0;border-radius:0 0 16px 16px;padding:24px">'
          . '<p style="margin:0 0 14px;font-size:15px">Use este código para entrar:</p>'
          . '<div style="font-size:34px;letter-spacing:8px;font-weight:bold;font-family:Georgia,serif;background:#FBEDE6;'
          . 'border-radius:12px;padding:14px 0;text-align:center;color:#9F3D1F">' . h($codigo) . '</div>'
          . '<p style="margin:16px 0 0;font-size:13px;color:#6D5F53">Vale por ' . $min . ' minutos. '
          . 'Se não foi você que pediu, ignore este e-mail.</p></div></div></body></html>';
    pc_enviar_email($para, $assunto, $html, $texto);
}

function pc_enviar_email(string $para, string $assunto, string $html, string $texto): void
{
    $c = pc_config()['email'];
    $para = pc_norm_email($para);
    if (!filter_var($para, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('E-mail inválido');

    $limite = 'pc' . bin2hex(random_bytes(8));
    $dominio = substr(strrchr($c['remetente'], '@') ?: '@kustos.com.br', 1);
    $enc = fn(string $s) => '=?UTF-8?B?' . base64_encode($s) . '?=';
    $cab = [
        'Date: ' . date(DATE_RFC2822),
        'From: ' . $enc($c['nome']) . ' <' . $c['remetente'] . '>',
        'To: <' . $para . '>',
        'Subject: ' . $enc($assunto),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $dominio . '>',
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $limite . '"',
    ];
    $corpo = "--$limite\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
           . chunk_split(base64_encode($texto))
           . "--$limite\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
           . chunk_split(base64_encode($html))
           . "--$limite--\r\n";
    $mensagem = implode("\r\n", $cab) . "\r\n\r\n" . $corpo;

    if ($c['modo'] === 'arquivo') {   // teste local: grava em vez de enviar
        file_put_contents($c['arquivo'], "PARA: $para\nASSUNTO: $assunto\n$texto\n-----\n", FILE_APPEND | LOCK_EX);
        return;
    }
    pc_smtp($c, $para, $mensagem);
}

/** Cliente SMTP mínimo com SSL (porta 465 da Hostinger). */
function pc_smtp(array $c, string $para, string $mensagem): void
{
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $fp = @stream_socket_client('ssl://' . $c['host'] . ':' . (int)$c['porta'], $errno, $erro, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) throw new RuntimeException("SMTP sem conexão: $erro");
    stream_set_timeout($fp, 20);

    $cmd = function (?string $linha, array $esperado) use ($fp): void {
        if ($linha !== null) fwrite($fp, $linha . "\r\n");
        $resp = '';
        while (($l = fgets($fp, 1024)) !== false) {
            $resp .= $l;
            if (strlen($l) < 4 || $l[3] === ' ') break;
        }
        if (!in_array((int)substr($resp, 0, 3), $esperado, true)) {
            throw new RuntimeException('SMTP: ' . trim($resp));
        }
    };
    try {
        $dominio = substr(strrchr($c['remetente'], '@') ?: '@localhost', 1);
        $cmd(null, [220]);
        $cmd('EHLO ' . $dominio, [250]);
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode($c['usuario']), [334]);
        $cmd(base64_encode($c['senha']), [235]);
        $cmd('MAIL FROM:<' . $c['remetente'] . '>', [250]);
        $cmd('RCPT TO:<' . $para . '>', [250, 251]);
        $cmd('DATA', [354]);
        $cmd(preg_replace('/^\./m', '..', $mensagem) . "\r\n.", [250]);
        $cmd('QUIT', [221]);
    } finally {
        fclose($fp);
    }
}
