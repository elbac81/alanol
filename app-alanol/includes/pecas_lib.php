<?php
// Peças das bombas de poço (admin/pecas.php): dados em data/pecas/ (JSON), desenhos e fotos
// em data/pecas/ficheiros/. A pasta data/ já está fechada ao browser (data/.htaccess).

const PC_DADOS = __DIR__ . '/../data/pecas';
const PC_FICHEIROS = PC_DADOS . '/ficheiros';
const PC_MAX_FICHEIRO = 20 * 1024 * 1024;
const PC_TIPOS = [   // tipos aceites => extensão
    'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp', 'application/pdf' => 'pdf',
];

function pc_preparar(): void {
    foreach ([PC_DADOS, PC_FICHEIROS] as $d) if (!is_dir($d)) mkdir($d, 0775, true);
    if (!is_file(PC_DADOS . '/.htaccess')) file_put_contents(PC_DADOS . '/.htaccess', "Deny from all\n");
}

// ---------- JSON com tranca ----------
function pc_ler(string $nome): array {
    $f = PC_DADOS . "/$nome.json";
    if (!is_file($f)) return [];
    $j = json_decode((string) file_get_contents($f), true);
    return is_array($j) ? $j : [];
}
function pc_gravar(string $nome, array $dados): void {
    $f = PC_DADOS . "/$nome.json";
    $tmp = $f . '.' . bin2hex(random_bytes(4)) . '.tmp';
    file_put_contents($tmp, json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    rename($tmp, $f);
}
// Corre $fn com a base trancada (uma gravação de cada vez)
function pc_trancado(callable $fn) {
    $h = fopen(PC_DADOS . '/.tranca', 'c');
    flock($h, LOCK_EX);
    try { return $fn(); } finally { flock($h, LOCK_UN); fclose($h); }
}
function pc_versao(): string {
    $f = PC_DADOS . '/versao.txt';
    return is_file($f) ? trim((string) file_get_contents($f)) : '0';
}
function pc_mexeu(): void { file_put_contents(PC_DADOS . '/versao.txt', (string) round(microtime(true) * 1000)); }

// ---------- Fichas ----------
function pc_colecao(string $tipo): string {
    if ($tipo === 'peca') return 'pecas';
    if ($tipo === 'modelo') return 'modelos';
    throw new RuntimeException('Tipo desconhecido.');
}
function pc_campo_media(string $c): string {
    if ($c !== 'desenhos' && $c !== 'fotos') throw new RuntimeException('Campo desconhecido.');
    return $c;
}
function pc_id_ficheiro_valido(string $id): bool { return (bool) preg_match('/^[a-f0-9]{24}\.(png|jpg|gif|webp|pdf)$/', $id); }
// Ficheiro usado por alguma ficha?
function pc_ficheiro_usado(string $fid, array $pecas, array $modelos): bool {
    foreach ([$pecas, $modelos] as $col) foreach ($col as $d) foreach (['desenhos', 'fotos'] as $c)
        foreach ($d[$c] ?? [] as $m) if (($m['id'] ?? '') === $fid) return true;
    return false;
}
// Quem está a mexer (nome da sessão da app)
function pc_quem(): string { return (string) ($_SESSION['app_nome'] ?? $_SESSION['app_email'] ?? ''); }
function pc_token(): string {
    if (empty($_SESSION['pecas_token'])) $_SESSION['pecas_token'] = bin2hex(random_bytes(16));
    return $_SESSION['pecas_token'];
}
