<?php
// MODELO. Copie para FORA da pasta pública, com o nome membros-config.php:
//   /home/<usuario>/domains/kustos.com.br/membros-config.php
// (no Gerenciador de Arquivos da Hostinger: a pasta acima de public_html).
// Nunca coloque o arquivo preenchido no Git.
return [
    // gere com: php -r "echo bin2hex(random_bytes(32));"   (ou qualquer texto aleatório de 64 caracteres)
    'app_secret' => 'TROQUE-POR-64-CARACTERES-ALEATORIOS',

    // hPanel > Bancos de dados > MySQL: nome do banco, usuário e senha
    'db' => [
        'dsn'     => 'mysql:host=localhost;dbname=u000000000_membros;charset=utf8mb4',
        'usuario' => 'u000000000_membros',
        'senha'   => '',
    ],

    // hPanel > E-mails: a caixa acesso@kustos.com.br
    'email' => [
        'modo'      => 'smtp',
        'host'      => 'smtp.hostinger.com',
        'porta'     => 465,
        'usuario'   => 'acesso@kustos.com.br',
        'senha'     => '',
        'remetente' => 'acesso@kustos.com.br',
        'nome'      => 'Preço Certo',
    ],

    // Cakto > Integrações > Webhooks: o "secret" gerado ao criar o webhook
    'cakto_secret' => '',

    // token para a importação dos compradores antigos (64 caracteres aleatórios; pode apagar depois)
    'admin_token' => '',

    // id do produto OU da oferta na Cakto => produto da vitrine
    'produtos_cakto' => [
        // 'id-do-produto-calculadora' => 'calculadora',
        // 'id-da-oferta-downsell'     => 'calculadora',
        // 'id-do-produto-contrato'    => 'contrato-certo',
    ],

    // botão "Quero" dos produtos bloqueados
    'checkout' => [
        'contrato-certo' => '',
    ],

    // link de suporte no rodapé (WhatsApp ou e-mail)
    'suporte' => '',

    // true = a calculadora só abre logada (ligar só depois de importar os compradores)
    'proteger_calculadora' => false,
];
