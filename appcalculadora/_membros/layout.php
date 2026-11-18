<?php
defined('PC_MEMBROS') || exit;

function pc_topo(string $titulo, string $tituloTopo, string $subtitulo = '', string $selo = ''): void
{
    pc_sem_cache();
    header('Content-Type: text/html; charset=utf-8');
    ?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= h($titulo) ?> · Preço Certo</title>
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#1A1410">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bitter:wght@600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
  --cream: #F8F2E8; --paper: #FFFDF9; --ink: #2A211B; --ink-soft: #6D5F53;
  --brand: #C1502E; --brand-dark: #9F3D1F; --brand-soft: #FBEDE6;
  --accent: #3E6B4F; --accent-soft: #EAF1EB; --gold-lit: #F2B24C; --danger: #9A2B1A; --danger-soft: #F9E9E4;
  --line: #E9DFD0;
  --brand-grad: linear-gradient(180deg, #CF5B36 0%, #B8482A 100%);
  --ink-grad: radial-gradient(120% 90% at 100% 0%, rgba(193,80,46,0.24) 0%, rgba(193,80,46,0) 55%),
              radial-gradient(90% 70% at 0% 100%, rgba(242,178,76,0.10) 0%, rgba(242,178,76,0) 60%),
              linear-gradient(165deg, #2E241D 0%, #18120E 100%);
  --sans: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
  --serif: "Bitter", Georgia, serif;
  --shadow-1: 0 1px 2px rgba(42,33,27,0.05), 0 2px 6px rgba(42,33,27,0.04);
  --shadow-2: 0 1px 2px rgba(42,33,27,0.06), 0 10px 30px -8px rgba(42,33,27,0.16);
  --shadow: 0 2px 4px rgba(42,33,27,0.04), 0 24px 60px -20px rgba(42,33,27,0.28);
  --ease: cubic-bezier(.2,.7,.2,1);
  color-scheme: light;
}
* { box-sizing: border-box; }
html, body { margin: 0; background: var(--cream); color: var(--ink); }
body { font-family: var(--sans); -webkit-font-smoothing: antialiased; letter-spacing: -0.005em;
  background: radial-gradient(80% 40% at 50% 0%, #FFFFFF 0%, rgba(255,255,255,0) 70%), var(--cream);
  padding: 24px 12px max(32px, env(safe-area-inset-bottom)); min-height: 100vh; }
.shell { max-width: 460px; margin: 0 auto; background: var(--paper); border: 1px solid var(--line); border-radius: 22px; overflow: hidden; box-shadow: var(--shadow); }
.topo { background: var(--ink-grad); color: #fff; padding: 24px 22px 26px; position: relative; }
.topo::after { content: ""; position: absolute; left: 22px; right: 22px; bottom: 0; height: 1px;
  background: linear-gradient(90deg, rgba(242,178,76,0), rgba(242,178,76,0.5), rgba(242,178,76,0)); }
.marca { display: flex; align-items: center; gap: 11px; margin-bottom: 20px; }
.marca .selo { width: 40px; height: 40px; border-radius: 12px; background: var(--brand-grad); display: flex; align-items: center; justify-content: center;
  box-shadow: inset 0 1px 0 rgba(255,255,255,0.25), 0 6px 16px -4px rgba(193,80,46,0.6); flex: 0 0 auto; }
.marca strong { font-family: var(--serif); font-size: 1.15rem; letter-spacing: -0.015em; display: block; }
.marca span { font-size: 0.76rem; color: rgba(246,239,230,0.6); }
.marca .sair { margin-left: auto; color: rgba(246,239,230,0.7); font-size: 0.8rem; font-weight: 600; text-decoration: none;
  padding: 10px 12px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.12); min-height: 40px; display: inline-flex; align-items: center; }
.marca .sair:hover { background: rgba(255,255,255,0.07); color: #fff; }
.ok { display: inline-flex; align-items: center; gap: 7px; padding: 6px 11px 6px 8px; border-radius: 999px;
  background: rgba(76,181,113,0.14); border: 1px solid rgba(76,181,113,0.35); color: #9BE0B3; font-size: 0.76rem; font-weight: 600; }
.ok i { width: 18px; height: 18px; border-radius: 50%; background: #4CB571; display: inline-flex; align-items: center; justify-content: center; color: #10301C; }
.topo h1 { font-family: var(--serif); font-weight: 700; font-size: 1.7rem; line-height: 1.14; letter-spacing: -0.025em; margin: 12px 0 8px; }
.topo p { margin: 0; font-size: 0.9rem; line-height: 1.5; color: rgba(246,239,230,0.75); }
.corpo { padding: 24px 22px 26px; }

label { display: block; font-size: 0.86rem; font-weight: 600; margin-bottom: 7px; }
.campo { width: 100%; min-height: 52px; padding: 12px 14px; border-radius: 12px; border: 1px solid var(--line); background: #fff;
  font: 500 1.02rem var(--sans); color: var(--ink); box-shadow: inset 0 1px 2px rgba(42,33,27,0.04); transition: border-color .18s var(--ease), box-shadow .18s var(--ease); }
.campo:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 4px rgba(193,80,46,0.12); }
.campo.codigo { font: 700 1.6rem var(--serif); letter-spacing: 0.4em; text-align: center; padding-left: 0.4em; }
.ajuda { font-size: 0.8rem; color: var(--ink-soft); margin: 8px 0 0; line-height: 1.5; }
.btn { display: flex; align-items: center; justify-content: center; gap: 9px; width: 100%; min-height: 54px; margin-top: 18px;
  border-radius: 14px; border: 0; cursor: pointer; text-decoration: none; font: 700 1rem var(--sans); color: #fff; background: var(--brand-grad);
  box-shadow: inset 0 1px 0 rgba(255,255,255,0.22), 0 3px 0 var(--brand-dark), 0 12px 26px -8px rgba(193,80,46,0.6);
  transition: transform .1s var(--ease), box-shadow .1s var(--ease), filter .18s var(--ease); }
.btn:hover { filter: brightness(1.05); transform: translateY(-1px); }
.btn:active { transform: translateY(2px); box-shadow: inset 0 1px 0 rgba(255,255,255,0.18), 0 1px 0 var(--brand-dark); filter: none; }
.btn.claro { background: var(--paper); color: var(--ink); border: 1px solid var(--line); box-shadow: var(--shadow-1); }
.btn.claro:hover { background: #fff; }
.btn:focus-visible, .link:focus-visible, a:focus-visible, button:focus-visible { outline: 2px solid var(--brand); outline-offset: 3px; }
.link { background: none; border: 0; padding: 12px 0; min-height: 44px; font: 600 0.88rem var(--sans); color: var(--brand-dark); cursor: pointer; text-decoration: underline; text-underline-offset: 3px; }
.aviso { padding: 12px 14px; border-radius: 12px; font-size: 0.86rem; line-height: 1.5; margin: 0 0 16px; }
.aviso.erro { background: var(--danger-soft); border: 1px solid #EFCFC5; color: var(--danger); }
.aviso.info { background: var(--accent-soft); border: 1px solid #CBDDD0; color: #2E4A38; }

.rotulo { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.09em; font-weight: 700; color: var(--brand-dark);
  display: flex; align-items: center; gap: 8px; margin: 4px 0 12px; }
.rotulo::after { content: ""; flex: 1; height: 1px; background: var(--line); }
.produtos { display: grid; gap: 12px; margin-bottom: 26px; }
.produto { display: grid; grid-template-columns: 48px 1fr; gap: 14px; padding: 16px; border-radius: 16px; background: var(--paper);
  border: 1px solid var(--line); box-shadow: var(--shadow-1); }
.produto .ic { width: 48px; height: 48px; border-radius: 13px; display: flex; align-items: center; justify-content: center; background: var(--brand-grad); color: #fff;
  box-shadow: inset 0 1px 0 rgba(255,255,255,0.22), 0 6px 14px -6px rgba(193,80,46,0.6); }
.produto h2 { font-family: var(--serif); font-size: 1.08rem; letter-spacing: -0.015em; margin: 2px 0 4px; }
.produto p { margin: 0; font-size: 0.85rem; line-height: 1.5; color: var(--ink-soft); }
.produto .acoes { grid-column: 1 / -1; }
.produto .acoes .btn { margin-top: 2px; min-height: 48px; }
.produto.bloqueado { background: var(--cream); box-shadow: none; }
.produto.bloqueado .ic { background: #E7DDCF; color: #8A7A6B; box-shadow: none; }
.produto .cadeado { display: inline-flex; align-items: center; gap: 5px; font-size: 0.72rem; font-weight: 700; color: #8A7A6B;
  text-transform: uppercase; letter-spacing: 0.06em; }
.produto .breve { display: inline-block; font-size: 0.72rem; font-weight: 700; color: var(--accent); background: var(--accent-soft);
  padding: 4px 9px; border-radius: 999px; margin-top: 10px; }
.arquivos { list-style: none; margin: 4px 0 0; padding: 0; display: grid; gap: 6px; }
.arquivos a { display: flex; justify-content: space-between; gap: 10px; align-items: center; min-height: 46px; padding: 10px 12px; border-radius: 11px;
  background: #fff; border: 1px solid var(--line); color: var(--ink); text-decoration: none; font-size: 0.84rem; font-weight: 600; }
.arquivos a:hover { border-color: #D9CBB6; box-shadow: var(--shadow-1); }
.arquivos small { color: var(--ink-soft); font-weight: 500; white-space: nowrap; }

.passo { display: grid; grid-template-columns: 34px 1fr; gap: 14px; padding: 0 0 24px; position: relative; }
.passo:not(:last-of-type)::before { content: ""; position: absolute; left: 16.5px; top: 38px; bottom: 6px; width: 1px; background: var(--line); }
.num { width: 34px; height: 34px; border-radius: 50%; background: var(--brand-soft); color: var(--brand-dark); display: flex; align-items: center; justify-content: center;
  font: 700 0.9rem var(--serif); border: 1px solid rgba(193,80,46,0.22); }
.passo h3 { font-family: var(--serif); font-size: 1.05rem; letter-spacing: -0.015em; margin: 5px 0 6px; }
.passo p { margin: 0; font-size: 0.88rem; line-height: 1.55; color: var(--ink-soft); }
.seg { display: flex; gap: 3px; padding: 3px; margin: 12px 0 10px; border: 1px solid var(--line); background: var(--cream); border-radius: 12px; }
.seg button { flex: 1; min-height: 40px; border: 0; border-radius: 9px; background: transparent; font: 600 0.84rem var(--sans); color: var(--ink-soft); cursor: pointer; }
.seg button[aria-selected="true"] { background: var(--paper); color: var(--ink); box-shadow: 0 1px 2px rgba(42,33,27,0.10), 0 2px 8px -2px rgba(42,33,27,0.18); }
ol.como { margin: 0; padding: 14px 16px 14px 34px; background: var(--paper); border: 1px solid var(--line); border-radius: 14px; box-shadow: var(--shadow-1); font-size: 0.86rem; line-height: 1.6; }
.video { margin-top: 12px; border-radius: 16px; overflow: hidden; background: #000; aspect-ratio: 9 / 16; max-height: 70vh; width: 100%; }
.video video { width: 100%; height: 100%; display: block; object-fit: contain; background: #000; }
.rodape { text-align: center; font-size: 0.76rem; color: rgba(42,33,27,0.55); margin: 18px 0 0; line-height: 1.6; }
.rodape a { color: var(--brand-dark); font-weight: 600; }
@media (max-width: 380px) { .topo, .corpo { padding-left: 16px; padding-right: 16px; } .topo h1 { font-size: 1.5rem; } }
@media (prefers-reduced-motion: reduce) { * { transition: none !important; } }
</style>
</head>
<body>
<main class="shell">
  <header class="topo">
    <div class="marca">
      <div class="selo" aria-hidden="true"><?= pc_icone('calc', 20) ?></div>
      <div><strong>Preço Certo</strong><span>área do cliente</span></div>
      <?php if (pc_sessao()): ?><a class="sair" href="<?= h(pc_url('/sair/')) ?>">Sair</a><?php endif; ?>
    </div>
    <?php if ($selo): ?><span class="ok"><i aria-hidden="true"><?= pc_icone('check', 11) ?></i><?= h($selo) ?></span><?php endif; ?>
    <h1><?= h($tituloTopo) ?></h1>
    <?php if ($subtitulo): ?><p><?= h($subtitulo) ?></p><?php endif; ?>
  </header>
  <div class="corpo">
<?php
}

function pc_rodape(): void
{
    $sup = pc_config()['suporte'];
    ?>
  </div>
</main>
<p class="rodape">
  <?php if ($sup): ?>Precisa de ajuda? <a href="<?= h($sup) ?>" target="_blank" rel="noopener">Fale com o suporte</a><br><?php endif; ?>
  Preço Certo · Kustos
</p>
</body>
</html>
<?php
}

function pc_icone(string $nome, int $t = 22): string
{
    $p = [
        'calc'   => '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M8 6h8M8 10h.01M12 10h.01M16 10h.01M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h4"/>',
        'doc'    => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/>',
        'bolo'   => '<path d="M20 21v-8a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8"/><path d="M4 16s1.5-2 4-2 4 2 4 2 1.5-2 4-2 4 2 4 2M2 21h20M7 8v3M12 8v3M17 8v3M7 4h.01M12 4h.01M17 4h.01"/>',
        'check'  => '<path d="M20 6 9 17l-5-5"/>',
        'lock'   => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        'seta'   => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'baixar' => '<path d="M12 3v12M7 10l5 5 5-5M5 21h14"/>',
    ][$nome] ?? '';
    $sw = $nome === 'check' ? 3.5 : 2;
    return '<svg width="' . $t . '" height="' . $t . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="' . $sw
         . '" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}
