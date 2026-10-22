<?php
// Peças Alanol — todas as leituras e gravações (JSON). Gravações por POST com o token no cabeçalho X-Token.
declare(strict_types=1);
require __DIR__ . '/_lib.php';
pa_preparar();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function responder(array $r, int $codigo = 200): void { http_response_code($codigo); echo json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }
function erro(string $msg, int $codigo = 400): void { responder(['erro' => $msg], $codigo); }

$eu = pa_eu();
if (!$eu) erro('Sessão terminada. Entra outra vez.', 401);
$acao = $_GET['acao'] ?? '';

// ---------- Leituras ----------
if ($acao === 'estado') {
    responder(['pecas' => (object) pa_ler('pecas'), 'modelos' => (object) pa_ler('modelos'), 'versao' => pa_versao(),
        'eu' => ['user' => $eu['user'], 'admin' => !empty($eu['admin'])]]);
}
if ($acao === 'versao') responder(['versao' => pa_versao()]);

// ---------- Gravações ----------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') erro('Pedido inválido.', 405);
if (!hash_equals($_SESSION['token'], $_SERVER['HTTP_X_TOKEN'] ?? '')) erro('A página ficou desatualizada. Recarrega-a.', 403);
$corpo = json_decode((string) file_get_contents('php://input'), true) ?: [];
$p = fn(string $k) => $_POST[$k] ?? $corpo[$k] ?? null;
$agora = gmdate('c');

try {
    switch ($acao) {

    // Título, referência e notas de uma peça ou modelo
    case 'guardar':
        $col = pa_colecao((string) $p('tipo')); $id = (string) $p('id');
        $campos = is_array($p('campos')) ? $p('campos') : [];
        $doc = pa_trancado(function () use ($col, $id, $campos, $eu, $agora) {
            $d = pa_ler($col);
            if (!isset($d[$id])) throw new RuntimeException('Ficha não encontrada.');
            foreach (['titulo' => 200, 'referencia' => 200, 'notas' => 20000] as $k => $max) {
                if (array_key_exists($k, $campos)) $d[$id][$k] = mb_substr(trim((string) $campos[$k]), 0, $max);
            }
            $d[$id]['atualizado'] = $agora; $d[$id]['por'] = $eu['user'];
            pa_gravar($col, $d); pa_mexeu();
            return $d[$id];
        });
        responder(['doc' => $doc]);

    // Juntar um desenho ou uma foto (um ficheiro por pedido)
    case 'juntar':
        $col = pa_colecao((string) $p('tipo')); $id = (string) $p('id'); $campo = pa_campo_media((string) $p('campo'));
        $soVazio = !empty($p('so_se_vazio'));
        $f = $_FILES['ficheiro'] ?? null;
        if (!$f || ($f['error'] ?? 1) !== UPLOAD_ERR_OK) {
            erro(($f['error'] ?? 0) === UPLOAD_ERR_INI_SIZE || ($f['error'] ?? 0) === UPLOAD_ERR_FORM_SIZE ? 'O ficheiro é grande demais para o servidor.' : 'O ficheiro não chegou.');
        }
        if ($f['size'] > PA_MAX_FICHEIRO) erro('Ficheiro grande demais (máx. 20 MB).');
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: '';
        if (!isset(PA_TIPOS[$mime])) erro('Tipo de ficheiro não aceite. Usa imagem (PNG, JPG, WebP, GIF) ou PDF.');
        if ($campo === 'fotos' && $mime === 'application/pdf') erro('A foto tem de ser uma imagem.');
        $fid = bin2hex(random_bytes(12)) . '.' . PA_TIPOS[$mime];
        if (!move_uploaded_file($f['tmp_name'], PA_FICHEIROS . '/' . $fid)) erro('Não foi possível guardar o ficheiro.', 500);
        $nome = mb_substr(preg_replace('/[\x00-\x1f]/u', '', basename((string) $f['name'])), 0, 150);
        try {
            $doc = pa_trancado(function () use ($col, $id, $campo, $soVazio, $fid, $nome, $mime, $eu, $agora) {
                $d = pa_ler($col);
                if (!isset($d[$id])) throw new RuntimeException('Ficha não encontrada.');
                $lista = $d[$id][$campo] ?? [];
                if ($soVazio && $lista) throw new RuntimeException($campo === 'fotos' ? 'Já tem foto. Para juntar mais, abre a ficha.' : 'Já tem desenho. Para juntar mais, abre a ficha.');
                $lista[] = ['id' => $fid, 'nome' => $nome, 'tipo' => $mime, 'em' => $agora, 'por' => $eu['user']];
                $d[$id][$campo] = $lista; $d[$id]['atualizado'] = $agora; $d[$id]['por'] = $eu['user'];
                pa_gravar($col, $d); pa_mexeu();
                return $d[$id];
            });
        } catch (Throwable $t) { @unlink(PA_FICHEIROS . '/' . $fid); throw $t; }
        responder(['doc' => $doc]);

    // Tirar um desenho ou uma foto (o ficheiro só se apaga se mais nenhuma ficha o usar)
    case 'tirar':
        $col = pa_colecao((string) $p('tipo')); $id = (string) $p('id'); $campo = pa_campo_media((string) $p('campo'));
        $fid = (string) $p('fid');
        $doc = pa_trancado(function () use ($col, $id, $campo, $fid, $eu, $agora) {
            $d = pa_ler($col);
            if (!isset($d[$id])) throw new RuntimeException('Ficha não encontrada.');
            $d[$id][$campo] = array_values(array_filter($d[$id][$campo] ?? [], fn($m) => ($m['id'] ?? '') !== $fid));
            $d[$id]['atualizado'] = $agora; $d[$id]['por'] = $eu['user'];
            pa_gravar($col, $d); pa_mexeu();
            $pecas = $col === 'pecas' ? $d : pa_ler('pecas');
            $modelos = $col === 'modelos' ? $d : pa_ler('modelos');
            if (pa_id_ficheiro_valido($fid) && !pa_ficheiro_usado($fid, $pecas, $modelos)) @unlink(PA_FICHEIROS . '/' . $fid);
            return $d[$id];
        });
        responder(['doc' => $doc]);

    // Importar a listagem (ficheiro JSON). Só junta o que ainda não existe: nada do que já foi preenchido se perde.
    case 'importar':
        if (empty($eu['admin'])) erro('Só um administrador importa dados.', 403);
        $f = $_FILES['ficheiro'] ?? null;
        if (!$f || ($f['error'] ?? 1) !== UPLOAD_ERR_OK) erro('O ficheiro não chegou.');
        $j = json_decode((string) file_get_contents($f['tmp_name']), true);
        if (!is_array($j) || !isset($j['pecas'], $j['modelos'])) erro('Este ficheiro não é uma listagem de peças.');
        $r = pa_trancado(function () use ($j) {
            // imagens que vêm dentro do ficheiro (fotos dos modelos)
            $imgs = [];
            foreach (($j['imagens'] ?? []) as $chave => $b64) {
                $bin = base64_decode((string) $b64, true);
                if ($bin === false) continue;
                $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bin) ?: '';
                if (!isset(PA_TIPOS[$mime]) || $mime === 'application/pdf') continue;
                $fid = substr(hash('sha256', $bin), 0, 24) . '.' . PA_TIPOS[$mime];
                if (!is_file(PA_FICHEIROS . '/' . $fid)) file_put_contents(PA_FICHEIROS . '/' . $fid, $bin);
                $imgs[$chave] = ['id' => $fid, 'tipo' => $mime];
            }
            $novos = ['pecas' => 0, 'modelos' => 0];
            foreach (['pecas', 'modelos'] as $col) {
                $d = pa_ler($col);
                foreach ($j[$col] as $doc) {
                    $id = (string) ($doc['codigo'] ?? '');
                    if ($id === '' || isset($d[$id])) continue;
                    foreach (['desenhos', 'fotos'] as $c) {
                        $doc[$c] = array_values(array_filter(array_map(function ($m) use ($imgs) {
                            $k = $m['imagem'] ?? null;
                            if ($k === null) return null;
                            return isset($imgs[$k]) ? ['id' => $imgs[$k]['id'], 'nome' => $m['nome'] ?? 'Imagem', 'tipo' => $imgs[$k]['tipo'], 'em' => gmdate('c')] : null;
                        }, $doc[$c] ?? [])));
                    }
                    $doc += ['titulo' => '', 'referencia' => '', 'notas' => ''];
                    $d[$id] = $doc; $novos[$col]++;
                }
                pa_gravar($col, $d);
            }
            pa_mexeu();
            return $novos;
        });
        responder(['novos' => $r]);

    // Contas: listar, criar, apagar (administrador); mudar a própria password (todos)
    case 'contas':
        if (empty($eu['admin'])) erro('Só um administrador gere contas.', 403);
        $c = pa_contas(); $lista = [];
        foreach ($c as $u => $x) $lista[] = ['user' => $u, 'nome' => $x['nome'] ?? '', 'admin' => !empty($x['admin'])];
        responder(['contas' => $lista]);

    case 'conta_nova':
        if (empty($eu['admin'])) erro('Só um administrador cria contas.', 403);
        $u = strtolower(trim((string) $p('user'))); $pass = (string) $p('pass');
        if (!pa_user_valido($u)) erro('Utilizador: 2 a 30 letras minúsculas, números, ponto, hífen ou _.');
        if (mb_strlen($pass) < PA_PASS_MIN) erro('A password tem de ter pelo menos ' . PA_PASS_MIN . ' caracteres.');
        pa_trancado(function () use ($u, $pass, $p) {
            $c = pa_contas();
            if (isset($c[$u])) throw new RuntimeException('Esse utilizador já existe.');
            $c[$u] = ['nome' => mb_substr(trim((string) $p('nome')), 0, 80), 'hash' => password_hash($pass, PASSWORD_DEFAULT), 'admin' => !empty($p('admin')), 'criado' => gmdate('c')];
            pa_gravar_contas($c);
        });
        responder(['ok' => true]);

    case 'conta_apagar':
        if (empty($eu['admin'])) erro('Só um administrador apaga contas.', 403);
        $u = (string) $p('user');
        if ($u === $eu['user']) erro('Não podes apagar a tua própria conta.');
        pa_trancado(function () use ($u) { $c = pa_contas(); unset($c[$u]); pa_gravar_contas($c); });
        responder(['ok' => true]);

    case 'password':
        $atual = (string) $p('atual'); $nova = (string) $p('nova');
        if (mb_strlen($nova) < PA_PASS_MIN) erro('A password nova tem de ter pelo menos ' . PA_PASS_MIN . ' caracteres.');
        pa_trancado(function () use ($eu, $atual, $nova) {
            $c = pa_contas();
            if (!password_verify($atual, $c[$eu['user']]['hash'] ?? '')) throw new RuntimeException('A password atual não está certa.');
            $c[$eu['user']]['hash'] = password_hash($nova, PASSWORD_DEFAULT);
            pa_gravar_contas($c);
        });
        responder(['ok' => true]);
    }
} catch (RuntimeException $t) {
    erro($t->getMessage());
}
erro('Ação desconhecida.', 404);
