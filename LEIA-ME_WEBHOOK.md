# Recuperação de vendas: webhook da Cakto

A Cakto avisa o site, por webhook, quando alguém:

- abandona o checkout depois de preencher nome, e-mail e celular;
- gera um Pix, boleto, PicPay ou Open Finance e não paga;
- tem o pagamento recusado;
- compra (para marcar quem foi recuperado).

O site guarda esses contatos, manda um e-mail para você e mostra tudo num painel com senha. No painel, cada contato tem um botão de WhatsApp com a mensagem pronta e o link do checkout.

| Arquivo | O que faz |
| --- | --- |
| `api/cakto-webhook.php` | Recebe os eventos da Cakto. Confere o `secret` e a assinatura HMAC. |
| `api/abandonos.php` | Painel com senha: lista, WhatsApp, "Já chamei" e CSV. |
| `api/avisar-pendentes.php` | Opcional, pelo Cron: avisa Pix e boletos que continuam sem pagamento. |
| `api/lib.php` | Funções comuns. Não abre pelo navegador. |
| `api/config.exemplo.php` | Modelo da configuração. |

Funciona com PHP 7.4 a 8.4 (hPanel → **Avançado → Configuração do PHP**). Não precisa de banco de dados.

## 1. Criar o e-mail de envio

No hPanel, abra **E-mails** e crie uma conta no seu domínio, por exemplo `avisos@seudominio.com.br`. O aviso sai por ela. Um remetente de fora do domínio costuma cair no spam ou nem chegar.

## 2. Cadastrar o webhook na Cakto

1. Abra **Painel Cakto → Apps → Webhooks** e crie um webhook.
2. Em URL, coloque `https://SEU_DOMINIO/api/cakto-webhook.php`.
3. Marque os eventos **Abandono de checkout**, **Pix gerado**, **Boleto gerado**, **PicPay gerado**, **Compra recusada** e **Compra aprovada**.
4. Escolha o produto Preço Certo.
5. Copie o **secret** que a Cakto gerar.

O webhook pode ser V1 ou V2: o receptor aceita os dois formatos.

## 3. Criar a configuração no servidor

1. No **Gerenciador de arquivos**, abra a pasta do domínio, que fica **acima** do `public_html` (`domains/SEU_DOMINIO/`).
2. Crie ali o arquivo `preco-certo-config.php` com o conteúdo de `api/config.exemplo.php`.
3. Preencha:
   - `secret`: o que a Cakto gerou;
   - `email_para`: onde você quer receber os avisos;
   - `email_de`: o e-mail criado no passo 1;
   - `painel_senha`: uma senha nova, com 8 caracteres ou mais;
   - `site`: `https://SEU_DOMINIO`.

Fora do `public_html`, a configuração fica longe do navegador, e a implantação por Git nunca apaga nem sobrescreve o arquivo. Se a Hostinger não deixar criar arquivos ali, use `public_html/api/config.php`. Ele também fica bloqueado para o navegador e fora do Git (`.gitignore`).

**Nunca coloque o arquivo preenchido no GitHub.** O repositório é público.

## 4. Testar

1. Na Cakto, use **Enviar teste** ou **Reenviar evento** no webhook.
2. Abra `https://SEU_DOMINIO/api/abandonos.php`, entre com a `painel_senha` e confira se o contato apareceu.
3. Se não aparecer, olhe a resposta do envio na Cakto:
   - `401`: o secret está diferente;
   - `500 secret não configurado`: a configuração não foi encontrada.

## Como os avisos funcionam

- **Abandono e pagamento recusado:** o e-mail sai na hora, uma vez por pessoa.
- **Pix, boleto, PicPay e Open Finance:** só avisam se continuarem sem pagamento depois de `pix_minutos` (30 por padrão), porque a maioria paga em poucos minutos. A conferência roda quando chega outro webhook e quando o painel é aberto.
- **Para avisar no horário certo**, ligue o Cron (opcional). Em **hPanel → Avançado → Cron Jobs**, crie um a cada 15 minutos com o comando:

  ```
  php /home/SEU_USUARIO/domains/SEU_DOMINIO/public_html/api/avisar-pendentes.php
  ```

- **Quando alguém da lista compra**, o contato vai para a aba **Compraram depois** com a marca "venda recuperada". O e-mail dessa compra é opcional (`avisar_recuperacao`).
- **Registro dos envios:** toda tentativa de e-mail fica no arquivo `avisos.log`, na pasta de dados. Se os avisos não chegarem, confira esse arquivo e a caixa de spam.

## Dados e LGPD

- Os contatos ficam em `domains/SEU_DOMINIO/preco-certo-dados/contatos.json`, fora do `public_html`. Se essa pasta não puder ser criada, ficam em `public_html/api/dados/`, que o navegador não acessa.
- Cada contato é apagado sozinho depois de `dias_guardar` dias (30 por padrão).
- Use os dados só para falar da compra que a pessoa começou. Não coloque esses contatos em lista de promoção sem autorização. Se alguém pedir para não receber mensagens, marque e respeite.

## Medir o abandono e anunciar de novo (Meta)

Isso não depende do webhook:

- **Taxa de abandono:** no Gerenciador de Eventos, compare quem clicou em comprar (`lp_checkout_clicked` + `quiz_checkout_clicked`), quem chegou ao checkout e quem comprou (`Purchase`). O pixel é o mesmo da Cakto. Para saber se a Cakto envia `InitiateCheckout` quando o checkout abre, use **Testar eventos** e abra o checkout, sem pagar.
- **Remarketing:** crie um público personalizado de quem visitou `pay.cakto.com.br/35dgsa8_1077031`, ou disparou `InitiateCheckout`, nos últimos 7 dias, excluindo quem disparou `Purchase`. Use esse público num anúncio próprio para quem quase comprou.
