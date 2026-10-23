<?php
// Modelo de configuração do webhook da Cakto e do painel de recuperação.
// No servidor (Hostinger), copie este arquivo para UM destes lugares e preencha:
//   1. domains/SEU_DOMINIO/preco-certo-config.php  (recomendado: fora do public_html, a implantação por Git nunca mexe)
//   2. public_html/api/config.php
// Nunca coloque o arquivo preenchido no GitHub (o repositório é público). O .gitignore já ignora api/config.php.
return [
    // "secret" que a Cakto gera ao cadastrar o webhook (Painel Cakto → Apps → Webhooks)
    'secret' => 'COLE_AQUI_O_SECRET_DO_WEBHOOK',

    // e-mail que recebe os avisos e o remetente (use um e-mail do seu domínio criado na Hostinger, senão cai no spam)
    'email_para' => 'voce@exemplo.com',
    'email_de' => 'avisos@SEU_DOMINIO',

    // senha do painel https://SEU_DOMINIO/api/abandonos.php (mínimo 8 caracteres; não reaproveite outra senha)
    'painel_senha' => '',

    // endereço do site, usado nos links do e-mail e na mensagem do WhatsApp
    'site' => 'https://SEU_DOMINIO',

    // mensagem pronta do botão WhatsApp ({nome} = primeiro nome, {link} = link do checkout)
    'mensagem_whatsapp' => 'Oi, {nome}! Vi que você começou a garantir o Preço Certo e não finalizou. Ficou alguma dúvida? Se quiser terminar, o link é este: {link}',

    // o que gera e-mail: abandonou, recusado, pix, boleto, picpay, openfinance
    'avisar' => ['abandonou', 'recusado', 'pix', 'boleto', 'picpay', 'openfinance'],
    // Pix/boleto só avisam se continuarem sem pagamento depois destes minutos (a maioria paga logo)
    'pix_minutos' => 30,
    // também mandar e-mail quando alguém da lista compra depois
    'avisar_recuperacao' => false,

    // por quantos dias os contatos ficam guardados (LGPD: o mínimo necessário)
    'dias_guardar' => 30,

    // opcional: só registra estes produtos (id ou short_id da Cakto). Vazio = todos os do webhook.
    'produtos' => [],
];
