<?php
// Peças Alanol — entrada: login, primeira conta, e a app.
declare(strict_types=1);
require __DIR__ . '/_lib.php';
pa_preparar();
pa_sessao();
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

$msg = '';
$contas = pa_contas();
$primeira = !$contas;

// Sair
if (isset($_GET['sair'])) {
    $_SESSION = []; session_regenerate_id(true);
    header('Location: ./'); exit;
}

// Entrar, ou criar a primeira conta (administrador)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['token'], (string) ($_POST['token'] ?? ''))) {
        $msg = 'A página ficou desatualizada. Tenta outra vez.';
    } elseif (!pa_limite()) {
        $msg = 'Demasiadas tentativas. Espera 15 minutos.';
    } else {
        $u = strtolower(trim((string) ($_POST['user'] ?? '')));
        $pass = (string) ($_POST['pass'] ?? '');
        if ($primeira) {
            if (!pa_user_valido($u)) $msg = 'Utilizador: 2 a 30 letras minúsculas, números, ponto, hífen ou _.';
            elseif (mb_strlen($pass) < PA_PASS_MIN) $msg = 'A password tem de ter pelo menos ' . PA_PASS_MIN . ' caracteres.';
            elseif ($pass !== (string) ($_POST['pass2'] ?? '')) $msg = 'As duas passwords não são iguais.';
            else {
                pa_trancado(function () use ($u, $pass) {
                    if (pa_contas()) return; // alguém criou entretanto
                    pa_gravar_contas([$u => ['nome' => '', 'hash' => password_hash($pass, PASSWORD_DEFAULT), 'admin' => true, 'criado' => gmdate('c')]]);
                });
                session_regenerate_id(true); $_SESSION['u'] = $u;
                header('Location: ./'); exit;
            }
        } else {
            $c = $contas[$u] ?? null;
            if ($c && password_verify($pass, $c['hash'] ?? '')) {
                session_regenerate_id(true); $_SESSION['u'] = $u;
                header('Location: ./'); exit;
            }
            pa_limite(true);
            $msg = 'Utilizador ou password errados.';
        }
    }
}

$eu = pa_eu();
$v = PA_VERSAO;
?><!doctype html>
<html lang="pt-PT">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#0d5a8a">
<title>Peças Alanol</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@75..100,500..800&family=Public+Sans:wght@400;500;600&family=JetBrains+Mono:wght@400;600&display=swap">
<link rel="stylesheet" href="app.css?v=<?= e($v) ?>">
</head>
<body>
<?php if (!$eu): ?>
<main class="entrar">
  <form class="entrar__caixa" method="post" autocomplete="on">
    <div class="marca">Peças Alanol<small>Bombas de poço</small></div>
    <?php if ($primeira): ?>
      <p class="aviso">Primeira vez: cria a conta de administrador.</p>
    <?php endif; ?>
    <input type="hidden" name="token" value="<?= e($_SESSION['token']) ?>">
    <div class="campo"><label for="user">Utilizador</label>
      <input id="user" name="user" required autocapitalize="none" autocomplete="username" value="<?= e($_POST['user'] ?? '') ?>"></div>
    <div class="campo"><label for="pass">Password</label>
      <input id="pass" name="pass" type="password" required autocomplete="<?= $primeira ? 'new-password' : 'current-password' ?>"<?= $primeira ? ' minlength="' . PA_PASS_MIN . '"' : '' ?>></div>
    <?php if ($primeira): ?>
      <div class="campo"><label for="pass2">Repetir a password</label>
        <input id="pass2" name="pass2" type="password" required autocomplete="new-password" minlength="<?= PA_PASS_MIN ?>"></div>
    <?php endif; ?>
    <?php if ($msg): ?><p class="aviso aviso--erro"><?= e($msg) ?></p><?php endif; ?>
    <button class="btn btn--pri" type="submit"><?= $primeira ? 'Criar conta e entrar' : 'Entrar' ?></button>
  </form>
</main>
<?php else: ?>
<header class="topo">
  <div class="topo__linha">
    <div class="marca">Peças Alanol<small>Bombas de poço</small></div>
    <div class="separadores" role="tablist">
      <button role="tab" id="tab-pecas" aria-selected="true">Peças</button>
      <button role="tab" id="tab-modelos" aria-selected="false">Modelos</button>
    </div>
    <div class="topo__fim">
      <span class="estado-db" id="estado-db">A carregar…</span>
      <details class="menu">
        <summary class="btn">☰ <?= e($eu['user']) ?></summary>
        <div class="menu__painel">
          <button class="menu__item" id="m-exportar">Exportar CSV</button>
          <?php if (!empty($eu['admin'])): ?>
            <button class="menu__item" id="m-importar">Importar listagem…</button>
            <button class="menu__item" id="m-contas">Contas</button>
          <?php endif; ?>
          <button class="menu__item" id="m-password">Mudar a password</button>
          <a class="menu__item" href="?sair=1">Sair</a>
        </div>
      </details>
    </div>
  </div>
</header>

<main class="corpo">
  <section class="lista-col" aria-label="Lista">
    <div class="filtros">
      <input class="procura" id="procura" type="search" placeholder="Procurar código, descrição, título, referência…" autocomplete="off">
      <div class="filtros__linha">
        <select id="f-familia" aria-label="Família"><option value="">Todas as famílias</option></select>
        <button class="chip" id="f-sem-foto" aria-pressed="false">Sem foto</button>
        <button class="chip" id="f-sem-desenho" aria-pressed="false">Sem desenho</button>
      </div>
      <div class="contagem" id="contagem"></div>
    </div>
    <div id="lista"></div>
  </section>
  <section class="ficha-col" aria-label="Ficha" id="ficha"></section>
</main>

<div class="visor" id="visor" hidden>
  <div class="visor__cab"><span id="visor-nome"></span><button id="visor-fechar">Fechar ✕</button></div>
  <div class="visor__img"><img id="visor-img" alt=""></div>
  <div class="visor__nav"><button id="visor-ant">‹ Anterior</button><span id="visor-pos"></span><button id="visor-seg">Seguinte ›</button></div>
</div>

<dialog class="janela" id="janela"></dialog>
<input type="file" id="ficheiro" multiple hidden>
<div class="toast" id="toast" role="status" hidden></div>
<script>window.PA = <?= json_encode(['token' => $_SESSION['token'], 'user' => $eu['user'], 'admin' => !empty($eu['admin'])]) ?>;</script>
<script src="app.js?v=<?= e($v) ?>"></script>
<?php endif; ?>
</body>
</html>
