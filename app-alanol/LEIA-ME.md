# Peças dentro da app da Alanol

Os ficheiros desta pasta juntam-se à app que está em `alanol.pt/app/` (mesmos caminhos).
Usam o login, o cabeçalho e a pasta `data/` da app; a página só abre a quem entra como
administração. O botão «Peças» vai nas Ferramentas de `app/admin/index.php`
(uma linha: `<a class="card" href="pecas.php"><?= ico('porca') ?><span class="label">Peças</span></a>`),
que não está aqui porque é código da app.

Os dados (`app/data/pecas/`) vivem no servidor; a 1.ª carga faz-se com o ficheiro
gerado por `ferramentas/excel_para_json.py` (botão «Importar listagem…» na página).
