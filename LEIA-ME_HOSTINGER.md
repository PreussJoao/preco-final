# Publicar a LP na Hostinger

Este repositório ([PreussJoao/preco-final](https://github.com/PreussJoao/preco-final)) já está no formato que a Hostinger espera: `index.html`, `.htaccess`, `assets/` e `quiz/` na raiz. Ele contém a LP otimizada, o quiz, as mídias, a fonte local e o `.htaccess` com regras de cache e compressão condicionais. A aplicação é estática e não exige instalação de WordPress, PHP, banco de dados ou Node.js.

## 1. Publicar

### Opção A: Git (recomendado)

Com o Git, cada atualização vira um push no GitHub e um clique na Hostinger.

1. No hPanel, abra **Sites → Painel do domínio → Avançado → Git**. O nome exato do menu pode variar.
2. Em **Criar um novo repositório**, preencha:
   - **Repositório:** `https://github.com/PreussJoao/preco-final.git`
   - **Branch:** `main`
   - **Diretório:** deixe em branco para publicar direto em `public_html`.
3. A Hostinger só implanta em pasta vazia. Se o `public_html` já tiver arquivos (como a página padrão de boas-vindas), faça backup e esvazie a pasta antes.
4. Clique em **Criar** e depois em **Implantar** (Deploy).
5. Opcional: ative a **implantação automática**, copie a URL do webhook que a Hostinger mostrar e cadastre-a no GitHub em **Settings → Webhooks → Add webhook**. Daí em diante, cada push no `main` atualiza o site sozinho.

O repositório é público, então a Hostinger não precisa de chave SSH para baixá-lo. A implantação por Git também copia a pasta `.git` e os arquivos `.md` e `.mjs` para o `public_html`; o `.htaccess` bloqueia o acesso a eles pelo navegador.

### Opção B: Gerenciador de arquivos

No hPanel, abra **Sites → Painel do domínio → Gerenciador de arquivos** e acesse os arquivos desse domínio. Coloque os arquivos da LP em `public_html`, conforme o [guia de arquivos da Hostinger](https://support.hostinger.com/en/articles/4548688-basic-actions-in-the-file-manager).

Envie o ZIP e extraia. Se a extração criar uma pasta intermediária (como `preco-final-main` no download do GitHub em **Code → Download ZIP**), mova seu conteúdo para o destino da LP, incluindo `.htaccess`. A estrutura final deve ser:

```text
public_html/
  index.html
  .htaccess
  assets/
  quiz/
    index.html
```

Se o domínio já contém outro site, use uma pasta ou subdomínio destinado à LP. Faça backup dos arquivos substituídos e integre as regras do `.htaccess` existente quando necessário. Não apague configurações de outras páginas.

Ao atualizar a LP, envie os assets novos antes dos HTMLs e mantenha os assets antigos durante a transição. Os nomes com hash evitam reutilizar versões antigas dos arquivos modificados.

## 2. Ativar o cache do servidor

No painel do domínio, procure **Cache Manager / Gerenciador de cache** e ative **Automatic cache / Cache automático**. Após publicar alterações, use a opção de limpar o cache. A Hostinger documenta suporte a sites HTML e atualização automática a cada 30 minutos. Esse cache do LiteSpeed é separado do cache da CDN. [Guia oficial do Cache Manager](https://www.hostinger.com/support/6215624-how-to-use-cache-manager-at-hostinger/).

## 3. Conferir a CDN

Abra **Desempenho → CDN** e confira o status. Se estiver inativa e o domínio atender aos requisitos, ative-a. Se já estiver ativa, mantenha-a e limpe o cache após a publicação. [Instruções oficiais da CDN](https://www.hostinger.com/support/7935917-hostinger-cdn-website-optimization/).

A CDN da Hostinger exige que o domínio use os nameservers da Hostinger. Se ele já usa Cloudflare com proxy, mantenha uma única CDN e avalie a configuração existente antes de trocar o DNS. Não foi feita alteração de DNS nesta tarefa. [Requisitos e disponibilidade](https://www.hostinger.com/support/which-plans-include-hostinger-cdn/).

O pacote envia cabeçalhos que permitem cache compartilhado do HTML por 5 minutos e cache de um ano dos assets com hash. Segundo a Hostinger, sua CDN pode armazenar HTML marcado com `Cache-Control: public` e um prazo de `max-age` ou `s-maxage`. A aplicação efetiva deve ser conferida no domínio publicado. [Regras de cache da CDN](https://www.hostinger.com/support/how-hostinger-cdn-caching-works/).

O prazo solicitado pelo `.htaccess` não substitui a necessidade de limpar as outras camadas de cache do provedor. Depois de alterar a página, limpe tanto o Cache Manager quanto a CDN.

## 4. Conferir a publicação

- Abra a página principal e o quiz no celular.
- Confira o vídeo, as cinco abas e os links de compra.
- Abra a LP com parâmetros de campanha e confira se chegam ao quiz e ao checkout.
- Confirme a coleta real do Pixel e da UTMify no domínio publicado; siga [o roteiro dos eventos do quiz e do vídeo](RASTREAMENTO.md).
- Verifique os cabeçalhos de cache, a compressão HTTP e a reprodução do vídeo com suporte a requisições parciais.

A versão otimizada passou por verificações locais de navegação, quiz, imagens, vídeo e parâmetros de campanha. A configuração de cache e a coleta real dos eventos ainda precisam ser verificadas no domínio publicado; não foram executadas na sua conta durante os testes locais.

## Manutenção

Se editar somente o texto dos HTMLs, publique-os e limpe os caches. Pelo Git, publicar é fazer o push no `main` e clicar em **Implantar** (ou esperar a implantação automática). Se editar CSS, JavaScript ou mídias, execute localmente `node atualizar-cache.mjs` antes do upload. Esse utilitário atualiza os hashes e referências; não precisa rodar na hospedagem.

A LP não executa cálculos no servidor: cada visitante usa o próprio navegador. Mesmo assim, a capacidade de acessos simultâneos depende dos recursos do plano, da CDN, da banda e dos serviços externos. O vídeo continua consumindo banda durante a reprodução. Não foi realizado teste de carga no ambiente publicado.
