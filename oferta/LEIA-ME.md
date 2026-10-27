# Oferta de R$ 19,99 (para quem curtiu os Reels, link enviado na DM)

- **Página:** `oferta/index.html`. Só imagens reais do produto (telas da calculadora e prints de compradores), sem vídeo.
- **Checkout:** `https://pay.cakto.com.br/3ckby5v` (oferta "PARAVOCE" na Cakto: R$ 19,00 + R$ 0,99 de taxa = R$ 19,99, com os mesmos order bumps).
- **Endereços:**
  - `https://kustos.com.br/oferta/` funciona assim que o deploy da Hostinger roda.
  - `https://oferta.kustos.com.br/` funciona depois de criar o subdomínio no hPanel apontando para a pasta `public_html/oferta`.
- **Fora do Google** (`noindex`): a página principal continua em R$ 29,99.
- **Rastreamento:**
  - Meta Pixel 1121235693689365 com `PageView` e `ViewContent` (19,99 BRL).
  - `oferta_checkout_clicked` (`position`: hero, telas, oferta, final, barra).
  - `oferta_scroll` (50% e 90%). Os eventos personalizados levam `funnel: oferta_dm`.
  - UTMify e as UTMs do link passam para o checkout.
- **Prévia do link** (Instagram/WhatsApp): `assets/og-oferta.jpg`.

## Criar o subdomínio na Hostinger

1. No hPanel, entre em **Domínios → Subdomínios** (ou "Sites → kustos.com.br → Domínios → Subdomínios").
2. Em **Subdomínio**, coloque `oferta`.
3. Marque a opção de **pasta personalizada** e use `public_html/oferta`. É a pasta que o deploy do Git já cria.
4. Clique em **Criar** e espere o SSL ficar ativo (alguns minutos). Ele fica em "Segurança → SSL", e o `https://` só vale depois disso.

## Link para mandar na DM

```
https://oferta.kustos.com.br/?utm_source=instagram&utm_medium=dm&utm_campaign=oferta-19
```

Enquanto o subdomínio não estiver pronto, use `https://kustos.com.br/oferta/?utm_source=instagram&utm_medium=dm&utm_campaign=oferta-19`.
