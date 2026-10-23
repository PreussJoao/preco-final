<?php
// Opcional, pelo Cron da Hostinger (a cada 15 min): avisa os Pix/boletos gerados que continuam sem pagamento.
// Sem o cron, o mesmo aviso sai quando chega outro webhook ou quando o painel é aberto.
// Comando no hPanel → Avançado → Cron Jobs:  php /home/SEU_USUARIO/domains/SEU_DOMINIO/public_html/api/avisar-pendentes.php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require __DIR__ . '/lib.php';
echo avisarPendentes() . " aviso(s) enviado(s)\n";
