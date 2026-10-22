<?php
// Peças Alanol — tudo o que é comum: dados em JSON, contas, sessão, ficheiros.
declare(strict_types=1);

const PA_VERSAO = '2';                 // subir ao mudar app.css / app.js
const PA_PAINEL = '/privado/';         // endereço do painel da Alanol (botão «‹ Painel»)
const PA_DADOS = __DIR__ . '/dados';   // fora do Git; criada sozinha
const PA_FICHEIROS = PA_DADOS . '/ficheiros';   // nomes aleatórios; servidos só por ficheiro.php
const PA_MAX_FICHEIRO = 20 * 1024 * 1024;
const PA_PASS_MIN = 10;
const PA_TIPOS = [                     // tipos aceites => extensão
    'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp', 'application/pdf' => 'pdf',
];

// ---------- Pasta de dados ----------
function pa_preparar(): void {
    foreach ([PA_DADOS, PA_FICHEIROS] as $d) {
        if (!is_dir($d)) mkdir($d, 0775, true);
    }
    $ht = PA_DADOS . '/.htaccess';
    if (!is_file($ht)) file_put_contents($ht, "Require all denied\nDeny from all\n");
}

// ---------- JSON com tranca ----------
// Cada ficheiro começa por um exit: mesmo num servidor que não respeite o .htaccess, não se lê pelo browser
function pa_ler(string $nome): array {
    $f = PA_DADOS . "/$nome.php";
    if (!is_file($f)) return [];
    $txt = (string) file_get_contents($f);
    $p = strpos($txt, "\n");
    $j = json_decode($p === false ? '' : substr($txt, $p + 1), true);
    return is_array($j) ? $j : [];
}
function pa_gravar(string $nome, array $dados): void {
    $f = PA_DADOS . "/$nome.php";
    $tmp = $f . '.' . bin2hex(random_bytes(4)) . '.tmp';
    file_put_contents($tmp, "<?php exit; ?>\n" . json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    rename($tmp, $f);
}
// Corre $fn com a base trancada (uma gravação de cada vez)
function pa_trancado(callable $fn) {
    $h = fopen(PA_DADOS . '/.tranca', 'c');
    flock($h, LOCK_EX);
    try { return $fn(); } finally { flock($h, LOCK_UN); fclose($h); }
}
function pa_versao(): string {
    $f = PA_DADOS . '/versao.txt';
    return is_file($f) ? trim((string) file_get_contents($f)) : '0';
}
function pa_mexeu(): void { file_put_contents(PA_DADOS . '/versao.txt', (string) (microtime(true) * 1000)); }

// ---------- Contas ----------
function pa_contas(): array { return pa_ler('contas'); }
function pa_gravar_contas(array $c): void { pa_gravar('contas', $c); }
function pa_user_valido(string $u): bool { return (bool) preg_match('/^[a-z0-9._-]{2,30}$/', $u); }

// ---------- Sessão ----------
function pa_sessao(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('pecas_alanol');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
    session_start();
    if (empty($_SESSION['token'])) $_SESSION['token'] = bin2hex(random_bytes(16));
}
function pa_eu(): ?array {
    pa_sessao();
    $u = $_SESSION['u'] ?? null;
    if (!$u) return null;
    $c = pa_contas();
    return isset($c[$u]) ? ['user' => $u] + $c[$u] : null;
}
// Tentativas falhadas por IP (10 em 15 min)
function pa_limite(bool $falhou = false): bool {
    $l = pa_ler('limites');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '?';
    $agora = time();
    $l = array_filter($l, fn($v) => ($v['t'] ?? 0) > $agora - 900);
    if ($falhou) { $l[$ip] = ['n' => ($l[$ip]['n'] ?? 0) + 1, 't' => $agora]; pa_gravar('limites', $l); }
    return ($l[$ip]['n'] ?? 0) < 10;
}

function e(?string $s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

// ---------- Fichas ----------
function pa_colecao(string $tipo): string {
    if ($tipo === 'peca') return 'pecas';
    if ($tipo === 'modelo') return 'modelos';
    throw new RuntimeException('Tipo desconhecido');
}
function pa_campo_media(string $c): string {
    if ($c !== 'desenhos' && $c !== 'fotos') throw new RuntimeException('Campo desconhecido');
    return $c;
}
// Ficheiro usado por alguma ficha?
function pa_ficheiro_usado(string $fid, array $pecas, array $modelos): bool {
    foreach ([$pecas, $modelos] as $col) foreach ($col as $d) foreach (['desenhos', 'fotos'] as $c)
        foreach ($d[$c] ?? [] as $m) if (($m['id'] ?? '') === $fid) return true;
    return false;
}
function pa_id_ficheiro_valido(string $id): bool { return (bool) preg_match('/^[a-f0-9]{24}\.(png|jpg|gif|webp|pdf)$/', $id); }
