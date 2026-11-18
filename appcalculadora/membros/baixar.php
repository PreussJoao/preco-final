<?php
require __DIR__ . '/../_membros/bootstrap.php';

$sessao = pc_exigir_sessao();
$slug = (string)($_GET['p'] ?? '');
$arquivo = (string)($_GET['f'] ?? '');

if (!pc_tem_acesso($sessao['email'], $slug)) {
    http_response_code(403);
    exit('Sem acesso a este produto.');
}
$lista = array_column(pc_arquivos($slug), 'arquivo');
if (!in_array($arquivo, $lista, true)) {      // só nomes que existem na pasta: nada de "../"
    http_response_code(404);
    exit('Arquivo não encontrado.');
}
$caminho = __DIR__ . '/../_membros/arquivos/' . $slug . '/' . $arquivo;
$tipos = ['pdf' => 'application/pdf',
          'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
pc_sem_cache();
header('Content-Type: ' . ($tipos[strtolower(pathinfo($arquivo, PATHINFO_EXTENSION))] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($caminho));
header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($arquivo));
readfile($caminho);
