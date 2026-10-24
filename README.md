# Preço Certo

Landing page com diagnóstico de quatro etapas, otimizada para publicação como site estático na Hostinger.

## Arquivos

- `index.html`: landing page.
- `quiz/index.html`: diagnóstico interativo.
- `assets/`: imagens responsivas, vídeo, fonte local, CSS e JavaScript.
- `.htaccess`: regras de cache e compressão para Apache/LiteSpeed.
- `vendas.json`: número de compras aprovadas mostrado nos avisos da LP. Atualize à mão.
- `api/`: webhook da Cakto e painel de recuperação de vendas (abandono de checkout, Pix não pago, pagamento recusado). Precisa de PHP, que a Hostinger já tem; configuração em [LEIA-ME_WEBHOOK.md](LEIA-ME_WEBHOOK.md).
- `LEIA-ME_HOSTINGER.md`: instruções completas de publicação.
- `RASTREAMENTO.md`: eventos do quiz e vídeo e roteiro de validação na Meta.
- `atualizar-cache.mjs`: utilitário local para atualizar os nomes com hash após editar assets.

## Publicação

A forma recomendada é ligar este repositório ao Git da Hostinger (branch `main`, publicando em `public_html`). Também é possível enviar os arquivos para a pasta pública destinada à LP, preservando a estrutura e incluindo `.htaccess`. O site não precisa de build, banco de dados ou Node.js em produção. Consulte [as instruções para Hostinger](LEIA-ME_HOSTINGER.md) para configurar cache e CDN no hPanel.

## Desenvolvimento local

Com Python instalado, execute na pasta do projeto:

```sh
python -m http.server 8080 --bind 127.0.0.1
```

Abra `http://127.0.0.1:8080/`. Esse servidor serve apenas para pré-visualização local; não aplica as regras de `.htaccess`.

## Atualização de arquivos

Após alterar CSS, JavaScript ou mídias, execute localmente com Node.js:

```sh
node atualizar-cache.mjs
```

Publique os novos assets antes dos HTMLs e preserve as versões anteriores durante a troca. Edições somente no texto dos HTMLs não exigem esse comando.

## Validação e limites

A otimização inicial passou em 71 verificações locais de navegação, quiz, imagens, vídeo e parâmetros de campanha. Nessa versão, antes da adição do rastreamento de vídeo, os arquivos próprios inicialmente carregados passaram de 302.956 para 93.675 bytes na simulação mobile. O rastreamento acrescenta aproximadamente 2 KB de JavaScript sem nova biblioteca. Serviços externos de marketing são simulados nos testes; sua coleta deve ser validada no domínio publicado.

O quiz já emite eventos de início, etapas, conclusão e clique. O vídeo agora emite início, cobertura de 25/50/75/95% e conclusão, sem contar trechos pulados ou repetidos como conteúdo adicional assistido. Consulte [o guia de rastreamento](RASTREAMENTO.md).

A atualização de rastreamento passou em 86 verificações no navegador e 12 testes determinísticos da contagem. Foram verificados reprodução real, pausa/retomada, replay, salto ao fim, duração inválida e Pixel indisponível. O carregamento inicial mobile medido nesta edição foi de 95.315 bytes próprios (+1.640 bytes frente à otimização inicial), em ambiente local sem limitação de rede ou CPU. O recebimento real dos eventos na Meta ainda precisa ser confirmado após a publicação.

A capacidade real de acessos simultâneos depende da hospedagem, banda e CDN. Não foi feito teste de carga na Hostinger.

Os avisos da LP (6 s e depois a cada 19 s) e o teste A/B estão descritos em [RASTREAMENTO.md](RASTREAMENTO.md#avisos-da-lp-e-teste-ab). Os arquivos `site.eb97c2b9b40f.js`, `quiz.d47567f18de1.js`, `styles.7018b431f3e8.css` e `demonstracao-capa.f1cd92f2515e.webp` são da versão anterior, mantidos para quem ainda tem o HTML antigo em cache; podem ser apagados na próxima atualização.

A licença da fonte Bitter está em `assets/Bitter-OFL.txt`.
