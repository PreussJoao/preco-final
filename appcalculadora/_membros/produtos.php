<?php
defined('PC_MEMBROS') || exit;

/**
 * Vitrine. Para criar um produto novo: acrescente aqui, ponha o id dele da Cakto em
 * "produtos_cakto" no config e, se tiver arquivos, coloque-os em _membros/arquivos/<slug>/.
 */
function pc_catalogo(): array
{
    $c = pc_config();
    return [
        'calculadora' => [
            'nome'     => 'Calculadora de marmita',
            'desc'     => 'Descubra quanto cobrar por cada marmita: ingredientes, embalagem, seu tempo e as contas da casa.',
            'abrir'    => pc_url('/'),
            'checkout' => $c['checkout']['calculadora'] ?? '',
            'icone'    => 'calc',
        ],
        '15-marmitas' => [
            'nome'     => '15 Marmitas que Dão Lucro',
            'desc'     => '15 receitas com ingredientes acessíveis e cara de prato caro, com o lote para 10 marmitas e as quantidades prontas para a calculadora.',
            'abrir'    => $c['entrega']['15-marmitas'] ?? '',
            'checkout' => $c['checkout']['15-marmitas'] ?? '',
            'preco'    => 'R$ 14,99',
            'icone'    => 'doc',
        ],
        'medida-certa' => [
            'nome'     => 'Medida Certa',
            'desc'     => 'Porções P, M e G para a marmita sair sempre igual: quantidades de referência, preço por tamanho e checklist de montagem.',
            'abrir'    => $c['entrega']['medida-certa'] ?? '',
            'checkout' => $c['checkout']['medida-certa'] ?? '',
            'preco'    => 'R$ 11,99',
            'icone'    => 'doc',
        ],
        'seu-restaurante' => [
            'nome'     => 'Seu Restaurante no Google',
            'desc'     => 'Passo a passo para o seu negócio aparecer no Google e no mapa para quem procura onde comer.',
            'abrir'    => $c['entrega']['seu-restaurante'] ?? '',
            'checkout' => $c['checkout']['seu-restaurante'] ?? '',
            'preco'    => 'R$ 29,99',
            'icone'    => 'doc',
        ],
        'doce-preco' => [
            'nome'     => 'Doce Preço',
            'desc'     => 'Calculadora de preço para bolos, doces e encomendas de confeitaria.',
            'em_breve' => true,
            'icone'    => 'bolo',
        ],
    ];
}

/** Arquivos para baixar de um produto (pasta protegida _membros/arquivos/<slug>/). */
function pc_arquivos(string $slug): array
{
    $dir = __DIR__ . '/arquivos/' . $slug;
    if (!preg_match('/^[a-z0-9-]+$/', $slug) || !is_dir($dir)) return [];
    $lista = [];
    foreach (scandir($dir) as $f) {
        if ($f[0] === '.' || !is_file("$dir/$f")) continue;
        $lista[] = ['arquivo' => $f, 'nome' => pc_nome_arquivo($f), 'tamanho' => filesize("$dir/$f")];
    }
    return $lista;
}

function pc_nome_arquivo(string $f): string
{
    $conhecidos = [
        '15-marmitas-que-dao-lucro.pdf' => '15 Marmitas que Dão Lucro (PDF)',
        'medida-certa.pdf'              => 'Medida Certa: guia de porções (PDF)',
    ];
    if (isset($conhecidos[$f])) return $conhecidos[$f];
    $n = pathinfo($f, PATHINFO_FILENAME);
    $n = preg_replace('/^\d+_/', '', $n);
    $n = str_replace(['_', '-'], ' ', $n);
    $n = mb_convert_case(mb_strtolower($n), MB_CASE_TITLE, 'UTF-8');
    return $n . ' (' . strtoupper(pathinfo($f, PATHINFO_EXTENSION)) . ')';
}

/** Descobre qual produto do catálogo uma venda da Cakto libera. */
function pc_produto_da_venda(array $d): ?string
{
    $mapa = pc_config()['produtos_cakto'];
    foreach ([$d['offer']['id'] ?? null, $d['product']['id'] ?? null, $d['product']['short_id'] ?? null] as $chave) {
        if ($chave !== null && isset($mapa[$chave])) return $mapa[$chave];
    }
    return null;
}
