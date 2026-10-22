# Rastreamento do quiz e do vídeo

Os eventos usam o Meta Pixel **1121235693689365**, já instalado na LP e no quiz. Não é necessário adicionar outro Pixel, Tag Manager ou plugin. Os eventos abaixo são personalizados (`trackCustom`).

## Eventos disponíveis

| Ação | Evento | Parâmetros específicos |
| --- | --- | --- |
| Iniciar o quiz | `quiz_started` | — |
| Avançar uma etapa válida | `quiz_question_answered` | `step`: etapa |
| Concluir o quiz | `quiz_completed` | `profile`: perfil calculado |
| Exibir o resultado | `quiz_result_viewed` | `profile`: perfil calculado |
| Clicar na compra pelo quiz | `quiz_checkout_clicked` | `position`: posição do botão |
| Reiniciar o quiz | `quiz_restart` | — |
| Iniciar a reprodução do vídeo | `lp_video_started` | `video_id`: `demonstracao` |
| Reproduzir 25%, 50%, 75% ou 95% do conteúdo | `lp_video_progress` | `video_id`, `percent`, `duration_seconds`, `watched_seconds` |
| Terminar a reprodução com ao menos 99% de cobertura | `lp_video_completed` | `video_id`, `percent`, `duration_seconds`, `watched_seconds` |
| Clicar na compra pela LP | `lp_checkout_clicked` | `position` |
| Abrir o quiz pela LP | `lp_quiz_clicked` | `position` |
| Selecionar uma aba da demonstração | `lp_demo_step` | `step` |
| Ver um aviso da LP | `lp_popup_shown` | `kind` (`vendas` ou `dica`), `variant` (`vendas_1` a `vendas_3`, `dica_1` a `dica_3`) e, nos avisos de vendas, `count` |
| Fechar um aviso | `lp_popup_closed` | `variant` |

Todos incluem `funnel`: `lp_v3` ou `quiz_v3`. Esses nomes foram preservados para manter a continuidade dos eventos existentes; não representam a versão do pacote publicado. Os eventos personalizados do quiz enviam etapa e perfil, sem os valores de preço e custo digitados.

Todos incluem também `popup_group`: `com_popup` ou `sem_popup`, o grupo do teste A/B dos avisos (veja abaixo).

## Avisos da LP e teste A/B

Na LP, o primeiro aviso aparece 6 s depois de abrir a página e os seguintes a cada 19 s, no máximo seis por visita. A ordem é sempre: vendas 1, dica 1, vendas 2, dica 2, vendas 3, dica 3.

- **Avisos de vendas:** mostram o número de `vendas.json` (ex.: "4 pessoas já começaram a calcular o preço certo da marmita"). Esse número é atualizado à mão: troque `compras` pelo total de compras aprovadas na Cakto, sem reembolsos, e publique. Com `0`, só aparecem as dicas.
- **Dicas:** perguntas e lembretes sem números sobre outras pessoas.
- Quem fecha um aviso não vê os outros naquela sessão. Com a aba em segundo plano, nada aparece.

Metade das visitas vê os avisos (`com_popup`) e metade não (`sem_popup`). O grupo fica guardado no navegador, então a mesma pessoa continua no mesmo grupo ao voltar e no quiz. Para conferir, abra a LP com `?popup=1` (força os avisos) ou `?popup=0` (força sem avisos); esse parâmetro não é repassado ao checkout. Para mudar a proporção, altere `SHARE_WITH_POPUP` em `site.*.js` e `quiz.*.js` e rode `node atualizar-cache.mjs`.

Para comparar os grupos, crie no Gerenciador de Eventos conversões personalizadas de `lp_checkout_clicked` e `quiz_checkout_clicked` filtradas por `popup_group`. A compra (`Purchase`) é disparada pela Cakto e não leva o grupo; compare as vendas pelo período do teste.

## Como o vídeo é medido

O início é registrado quando o navegador informa que a reprodução começou. Apenas clicar no botão, sem iniciar a reprodução, não basta.

A porcentagem representa a soma dos trechos únicos reproduzidos, usando [`HTMLMediaElement.played`](https://developer.mozilla.org/en-US/docs/Web/API/HTMLMediaElement/played). Pular diretamente para 75% da barra não significa ter assistido 75%. Rever um mesmo trecho também não aumenta a cobertura. O cálculo mede conteúdo reproduzido, inclusive em velocidades diferentes; não mede atenção, visibilidade da aba ou tempo real olhando para a tela.

Cada marco é emitido uma vez por carregamento da página. Pausar, retomar e rever o vídeo não duplicam eventos. Recarregar a página inicia uma nova medição. São no máximo seis eventos: início, quatro marcos e conclusão.

A conclusão exige chegar ao fim e ter reproduzido ao menos 99% do conteúdo. A tolerância de 1% cobre pequenas diferenças de precisão do player. No evento de progresso, `percent` é o marco atingido; em conclusão, é a cobertura efetivamente medida. Os tempos são segundos de conteúdo, arredondados para duas casas decimais.

A medição usa eventos nativos, sem biblioteca nova, temporizador contínuo, banco de dados ou chamadas à hospedagem. O vídeo mantém `preload="none"`. O Pixel existente faz a transmissão à Meta; bloqueios de rastreamento ou falhas de rede podem impedir a coleta, sem interromper o player. Não há garantia de entrega nem reenvio se o Pixel estiver indisponível.

## Publicar e validar

1. Publique os novos arquivos de `assets/` antes de `index.html`, seguindo [o guia da Hostinger](LEIA-ME_HOSTINGER.md). Preserve os assets antigos durante a transição e limpe os caches do site e da CDN.
2. Abra o Gerenciador de Eventos da Meta e selecione a fonte de dados de ID **1121235693689365**. Use a área **Testar eventos** para abrir o domínio publicado e acompanhar as ações. O nome exato das opções pode variar conforme a interface da conta.
3. Inicie e conclua o quiz. Confira `quiz_started`, as etapas, `quiz_completed` e `quiz_result_viewed`. Reinicie e confirme `quiz_restart`.
4. Reproduza o vídeo inteiro. Confira `lp_video_started`, quatro `lp_video_progress` com `percent` 25, 50, 75 e 95, e um `lp_video_completed`.
5. Pause, retome e reveja o vídeo na mesma página: os eventos não devem se repetir. Recarregue a página, dê play e pule para o fim: não deve aparecer conclusão sem a cobertura mínima.
6. Confira os eventos de clique nos botões de compra, sem realizar uma compra. Esses cliques não são eventos de pagamento aprovado.

O código foi validado localmente com os serviços externos simulados para evitar poluir os dados reais. A confirmação de recebimento depende do domínio publicado e do acesso ao Pixel correto na conta Meta. Não foi configurado um painel de relatórios ou uma conversão personalizada na conta.

## Leitura dos resultados

- Compare conclusões do quiz com inícios no mesmo período para acompanhar a conclusão das tentativas.
- Use `quiz_question_answered` com `step` para identificar etapas com abandono; voltar e avançar novamente pode repetir esse evento.
- Compare os marcos do vídeo com `lp_video_started` para acompanhar quanto conteúdo foi reproduzido.
- O quiz pode ser reiniciado e a LP pode ser recarregada. Contagens de eventos não equivalem automaticamente a pessoas únicas.
- Para acompanhar vendas, valide separadamente a integração de pagamento da Cakto com o mesmo Pixel. `Purchase` deve representar uma compra confirmada; clicar no checkout não comprova venda.
