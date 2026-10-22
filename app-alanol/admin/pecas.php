<?php
// Peças das bombas de poço: cada peça e cada modelo com título, referência, notas, desenhos e fotos.
// Na lista larga-se um desenho e uma foto por peça; mais do que um junta-se na ficha.
// Dados em data/pecas/ (includes/pecas_lib.php); gravações por pecas_api.php.
require_once __DIR__ . '/../auth.php'; requer_auth('admin');
require_once __DIR__ . '/../includes/pecas_lib.php';
pc_preparar();
$v = @filemtime(__DIR__ . '/../assets/pecas.js') . '-' . @filemtime(__DIR__ . '/../assets/pecas.css');
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Peças — Alanol</title>
<link rel="stylesheet" href="../assets/style.css?v=<?= @filemtime(__DIR__.'/../assets/style.css') ?>">
<link rel="stylesheet" href="../assets/pecas.css?v=<?= $v ?>">
<?php require_once __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body>
<?php require_once __DIR__ . '/../includes/header.php'; ?>
<main>
  <a class="back-link" href="index.php">← Regressar ao painel</a>
  <div id="pecas">
    <div class="pc-barra">
      <h1>Peças</h1>
      <div class="separadores" role="tablist">
        <button role="tab" id="tab-pecas" aria-selected="true">Peças</button>
        <button role="tab" id="tab-modelos" aria-selected="false">Modelos</button>
      </div>
      <span class="estado-db" id="estado-db">A carregar…</span>
      <div class="pc-barra__fim">
        <button class="btn" id="m-exportar" type="button">Exportar CSV</button>
        <button class="btn" id="m-importar" type="button">Importar listagem…</button>
      </div>
    </div>

    <div class="corpo">
      <section class="lista-col" aria-label="Lista">
        <div class="filtros">
          <input class="procura" id="procura" type="search" placeholder="Procurar código, descrição, título, referência…" autocomplete="off">
          <div class="filtros__linha">
            <select id="f-familia" aria-label="Família"><option value="">Todas as famílias</option></select>
            <button class="chip" id="f-sem-foto" aria-pressed="false" type="button">Sem foto</button>
            <button class="chip" id="f-sem-desenho" aria-pressed="false" type="button">Sem desenho</button>
          </div>
          <div class="contagem" id="contagem"></div>
        </div>
        <div id="lista"></div>
      </section>
      <section class="ficha-col" aria-label="Ficha" id="ficha"></section>
    </div>

    <div class="visor" id="visor" hidden>
      <div class="visor__cab"><span id="visor-nome"></span><button id="visor-fechar" type="button">Fechar ✕</button></div>
      <div class="visor__img"><img id="visor-img" alt=""></div>
      <div class="visor__nav"><button id="visor-ant" type="button">‹ Anterior</button><span id="visor-pos"></span><button id="visor-seg" type="button">Seguinte ›</button></div>
    </div>
    <input type="file" id="ficheiro" multiple hidden>
    <div class="toast" id="toast" role="status" hidden></div>
  </div>
  <dialog class="pc-janela" id="janela"></dialog>
</main>
<script>window.PA = <?= json_encode(['token' => pc_token(), 'user' => pc_quem(), 'admin' => true, 'api' => 'pecas_api.php', 'ficheiro' => 'pecas_ficheiro.php']) ?>;</script>
<script src="../assets/pecas.js?v=<?= $v ?>"></script>
<?php require_once __DIR__ . '/../includes/bottom_nav.php'; ?>
</body>
</html>
