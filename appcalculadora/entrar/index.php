<?php
require __DIR__ . '/../_membros/bootstrap.php';

$volta = pc_volta_segura($_GET['volta'] ?? $_POST['volta'] ?? null);
if (pc_sessao()) pc_redirecionar($volta);

$etapa = 'email';
$email = '';
$erro = '';
$info = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = pc_norm_email((string)($_POST['email'] ?? ''));
    if (!pc_csrf_ok()) {
        $erro = 'A página ficou aberta muito tempo. Tente de novo.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Confira o e-mail: ele parece incompleto.';
    } elseif (($_POST['acao'] ?? '') === 'email') {
        // Mesma resposta para quem comprou e para quem não comprou (ninguém descobre quem é cliente).
        if (pc_produtos_liberados($email)) {
            $codigo = pc_criar_codigo($email);
            if ($codigo !== null) {
                try {
                    pc_enviar_codigo($email, $codigo);
                } catch (Throwable $e) {
                    error_log('[membros] falha ao enviar código: ' . $e->getMessage());
                }
            }
        }
        $etapa = 'codigo';
        $info = 'Se este e-mail tem uma compra, enviamos um código de 6 números para ele. Pode levar 1 ou 2 minutos.';
    } elseif (($_POST['acao'] ?? '') === 'codigo') {
        if (pc_verificar_codigo($email, (string)($_POST['codigo'] ?? ''))) {
            pc_iniciar_sessao($email);
            pc_redirecionar($volta);
        }
        $etapa = 'codigo';
        $erro = 'Código errado ou vencido. Confira o último e-mail que chegou ou peça um código novo.';
    }
}

$csrf = pc_csrf();
pc_topo('Entrar', $etapa === 'email' ? 'Entre na sua área' : 'Digite o código',
    $etapa === 'email'
        ? 'Use o mesmo e-mail que você colocou na compra. Vamos mandar um código para entrar, sem senha.'
        : 'Enviamos para ' . $email . '.');
?>
<?php if ($erro): ?><div class="aviso erro" role="alert"><?= h($erro) ?></div><?php endif; ?>
<?php if ($info && !$erro): ?><div class="aviso info" role="status"><?= h($info) ?></div><?php endif; ?>

<?php if ($etapa === 'email'): ?>
<form method="post" action="<?= h(pc_url('/entrar/')) ?>" novalidate>
  <input type="hidden" name="acao" value="email">
  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
  <input type="hidden" name="volta" value="<?= h($volta) ?>">
  <label for="email">E-mail da compra</label>
  <input class="campo" id="email" name="email" type="email" inputmode="email" autocomplete="email" autocapitalize="off" spellcheck="false"
         placeholder="seuemail@exemplo.com" value="<?= h($email) ?>" required autofocus>
  <button class="btn" type="submit">Receber código <?= pc_icone('seta', 18) ?></button>
  <p class="ajuda">Não lembra qual e-mail usou? Procure na sua caixa de entrada o e-mail de "compra aprovada" da Cakto.</p>
</form>
<?php else: ?>
<form method="post" action="<?= h(pc_url('/entrar/')) ?>" novalidate>
  <input type="hidden" name="acao" value="codigo">
  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
  <input type="hidden" name="volta" value="<?= h($volta) ?>">
  <input type="hidden" name="email" value="<?= h($email) ?>">
  <label for="codigo">Código de 6 números</label>
  <input class="campo codigo" id="codigo" name="codigo" type="text" inputmode="numeric" autocomplete="one-time-code"
         pattern="[0-9]*" maxlength="6" placeholder="••••••" required autofocus>
  <button class="btn" type="submit">Entrar <?= pc_icone('seta', 18) ?></button>
  <p class="ajuda">Não chegou? Olhe também no spam e na aba Promoções. O código vale por <?= (int)pc_config()['codigo_min'] ?> minutos.</p>
</form>
<form method="post" action="<?= h(pc_url('/entrar/')) ?>">
  <input type="hidden" name="acao" value="email">
  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
  <input type="hidden" name="volta" value="<?= h($volta) ?>">
  <input type="hidden" name="email" value="<?= h($email) ?>">
  <button class="link" type="submit">Mandar um código novo</button>
  <a class="link" href="<?= h(pc_url('/entrar/?volta=' . rawurlencode($volta))) ?>" style="margin-left:14px">Usar outro e-mail</a>
</form>
<?php endif; ?>
<?php pc_rodape();
