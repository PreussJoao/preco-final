<?php
require __DIR__ . '/../_membros/bootstrap.php';

$sessao = pc_exigir_sessao();
$liberados = pc_produtos_liberados($sessao['email']);
$catalogo = pc_catalogo();

$meus = array_intersect_key($catalogo, $liberados);
$outros = array_diff_key($catalogo, $liberados);
$temCalc = isset($liberados['calculadora']);

pc_topo('Meus produtos', $meus ? 'Seus produtos' : 'Nenhum produto ativo',
    $meus ? 'Conectado como ' . $sessao['email'] . '.' : 'Não achamos compra ativa para ' . $sessao['email'] . '.',
    $meus ? 'Acesso liberado' : '');
?>
<?php if (!$meus): ?>
<div class="aviso erro">Se você comprou com outro e-mail, saia e entre com ele. Se a compra foi reembolsada, o acesso é encerrado.</div>
<?php endif; ?>

<?php if ($meus): ?>
<div class="rotulo">Liberados para você</div>
<div class="produtos">
<?php foreach ($meus as $slug => $p): $arquivos = pc_arquivos($slug); ?>
  <article class="produto">
    <div class="ic"><?= pc_icone($p['icone']) ?></div>
    <div><h2><?= h($p['nome']) ?></h2><p><?= h($p['desc']) ?></p></div>
    <div class="acoes">
      <?php if (!empty($p['abrir'])): ?>
        <a class="btn" href="<?= h($p['abrir']) ?>">Abrir <?= h($p['nome']) ?> <?= pc_icone('seta', 18) ?></a>
      <?php endif; ?>
      <?php if ($arquivos): ?>
      <ul class="arquivos">
        <?php foreach ($arquivos as $a): ?>
        <li><a href="<?= h(pc_url('/membros/baixar.php?p=' . rawurlencode($slug) . '&f=' . rawurlencode($a['arquivo']))) ?>">
          <span><?= h($a['nome']) ?></span><small><?= pc_icone('baixar', 16) ?></small></a></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
      <?php if (empty($p['abrir']) && !$arquivos): ?>
        <p class="ajuda">O acesso a este material foi enviado para o seu e-mail pela Cakto.</p>
      <?php endif; ?>
    </div>
  </article>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($outros): ?>
<div class="rotulo">Para o seu negócio</div>
<div class="produtos">
<?php foreach ($outros as $slug => $p): ?>
  <article class="produto bloqueado">
    <div class="ic"><?= pc_icone(empty($p['em_breve']) ? 'lock' : $p['icone']) ?></div>
    <div>
      <?php if (empty($p['em_breve'])): ?><span class="cadeado"><?= pc_icone('lock', 12) ?> Bloqueado</span><?php endif; ?>
      <h2><?= h($p['nome']) ?></h2><p><?= h($p['desc']) ?></p>
      <?php if (!empty($p['em_breve']) || empty($p['checkout'])): ?><span class="breve">Em breve</span><?php endif; ?>
    </div>
    <?php if (empty($p['em_breve']) && !empty($p['checkout'])): ?>
    <div class="acoes">
      <a class="btn claro" href="<?= h($p['checkout']) ?>" target="_blank" rel="noopener">
        Quero o <?= h($p['nome']) ?><?= !empty($p['preco']) ? ' · ' . h($p['preco']) : '' ?></a>
    </div>
    <?php endif; ?>
  </article>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($temCalc): ?>
<div class="rotulo">Primeiros passos</div>
<section class="passo">
  <div class="num">1</div>
  <div>
    <h3>Deixe o atalho no seu celular</h3>
    <p>Abra a calculadora e salve na tela inicial. Ela passa a abrir como um aplicativo.</p>
    <div class="seg" role="tablist" aria-label="Seu celular">
      <button type="button" role="tab" id="t-and" aria-controls="p-and" aria-selected="true">Android</button>
      <button type="button" role="tab" id="t-ios" aria-controls="p-ios" aria-selected="false">iPhone</button>
    </div>
    <ol class="como" id="p-and" role="tabpanel" aria-labelledby="t-and">
      <li>No Chrome, toque nos <b>três pontinhos</b> no canto de cima.</li>
      <li>Toque em <b>Adicionar à tela inicial</b> (ou <b>Instalar app</b>).</li>
      <li>Confirme em <b>Adicionar</b>.</li>
    </ol>
    <ol class="como" id="p-ios" role="tabpanel" aria-labelledby="t-ios" hidden>
      <li>Abra a calculadora no <b>Safari</b>.</li>
      <li>Toque em <b>Compartilhar</b> na barra de baixo.</li>
      <li>Role e toque em <b>Adicionar à Tela de Início</b>.</li>
    </ol>
  </div>
</section>
<section class="passo">
  <div class="num">2</div>
  <div>
    <h3>Veja o tutorial rápido</h3>
    <p>A calculadora em uso, do começo ao preço final.</p>
    <div class="video">
      <video controls preload="none" playsinline poster="<?= h(pc_url('/videos/tutorial-capa.jpg')) ?>">
        <source src="<?= h(pc_url('/v2/videos/tutorial-leve.mp4')) ?>" type="video/mp4">
        <source src="<?= h(pc_url('/videos/tutorial.mp4')) ?>" type="video/mp4">
      </video>
    </div>
  </div>
</section>
<section class="passo">
  <div class="num">3</div>
  <div>
    <h3>Suas marmitas ficam no celular</h3>
    <p>O que você salva na calculadora fica guardado no próprio aparelho. Use sempre o mesmo celular e o mesmo navegador para ver tudo de novo.</p>
  </div>
</section>
<script>
(function () {
  var tabs = document.querySelectorAll('.seg [role="tab"]');
  function escolher(id) {
    tabs.forEach(function (t) {
      var on = t.id === id;
      t.setAttribute('aria-selected', on ? 'true' : 'false');
      document.getElementById(t.getAttribute('aria-controls')).hidden = !on;
    });
  }
  tabs.forEach(function (t) { t.addEventListener('click', function () { escolher(t.id); }); });
  if (/iPhone|iPad|iPod/i.test(navigator.userAgent)) escolher('t-ios');
})();
</script>
<?php endif; ?>
<?php pc_rodape();
