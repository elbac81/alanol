<?php
// Peças — leituras e gravações da página admin/pecas.php (JSON). Gravações por POST com o token no cabeçalho X-Token.
declare(strict_types=1);
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/pecas_lib.php';
pc_preparar();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function responder(array $r, int $codigo = 200): void { http_response_code($codigo); echo json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }
function erro(string $msg, int $codigo = 400): void { responder(['erro' => $msg], $codigo); }

if (empty($_SESSION['app_email'])) erro('Sessão terminada. Entra outra vez.', 401);
if (($_SESSION['app_nivel'] ?? '') !== 'admin') erro('Só a administração mexe nas peças.', 403);
$eu = ['user' => pc_quem(), 'admin' => true];
$acao = $_GET['acao'] ?? '';

// ---------- Leituras ----------
if ($acao === 'estado') {
    responder(['pecas' => (object) pc_ler('pecas'), 'modelos' => (object) pc_ler('modelos'), 'versao' => pc_versao(),
        'eu' => ['user' => $eu['user']]]);
}
if ($acao === 'versao') responder(['versao' => pc_versao()]);

// ---------- Gravações ----------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') erro('Pedido inválido.', 405);
if (!hash_equals(pc_token(), $_SERVER['HTTP_X_TOKEN'] ?? '')) erro('A página ficou desatualizada. Recarrega-a.', 403);
$corpo = json_decode((string) file_get_contents('php://input'), true) ?: [];
$p = fn(string $k) => $_POST[$k] ?? $corpo[$k] ?? null;
$agora = gmdate('c');

try {
    switch ($acao) {

    // Título, referência e notas de uma peça ou modelo
    case 'guardar':
        $col = pc_colecao((string) $p('tipo')); $id = (string) $p('id');
        $campos = is_array($p('campos')) ? $p('campos') : [];
        $doc = pc_trancado(function () use ($col, $id, $campos, $eu, $agora) {
            $d = pc_ler($col);
            if (!isset($d[$id])) throw new RuntimeException('Ficha não encontrada.');
            foreach (['titulo' => 200, 'referencia' => 200, 'notas' => 20000] as $k => $max) {
                if (array_key_exists($k, $campos)) $d[$id][$k] = mb_substr(trim((string) $campos[$k]), 0, $max);
            }
            $d[$id]['atualizado'] = $agora; $d[$id]['por'] = $eu['user'];
            pc_gravar($col, $d); pc_mexeu();
            return $d[$id];
        });
        responder(['doc' => $doc]);

    // Juntar um desenho ou uma foto (um ficheiro por pedido)
    case 'juntar':
        $col = pc_colecao((string) $p('tipo')); $id = (string) $p('id'); $campo = pc_campo_media((string) $p('campo'));
        $soVazio = !empty($p('so_se_vazio'));
        $f = $_FILES['ficheiro'] ?? null;
        if (!$f || ($f['error'] ?? 1) !== UPLOAD_ERR_OK) {
            erro(($f['error'] ?? 0) === UPLOAD_ERR_INI_SIZE || ($f['error'] ?? 0) === UPLOAD_ERR_FORM_SIZE ? 'O ficheiro é grande demais para o servidor.' : 'O ficheiro não chegou.');
        }
        if ($f['size'] > PC_MAX_FICHEIRO) erro('Ficheiro grande demais (máx. 20 MB).');
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: '';
        if (!isset(PC_TIPOS[$mime])) erro('Tipo de ficheiro não aceite. Usa imagem (PNG, JPG, WebP, GIF) ou PDF.');
        if ($campo === 'fotos' && $mime === 'application/pdf') erro('A foto tem de ser uma imagem.');
        $fid = bin2hex(random_bytes(12)) . '.' . PC_TIPOS[$mime];
        if (!move_uploaded_file($f['tmp_name'], PC_FICHEIROS . '/' . $fid)) erro('Não foi possível guardar o ficheiro.', 500);
        $nome = mb_substr(preg_replace('/[\x00-\x1f]/u', '', basename((string) $f['name'])), 0, 150);
        try {
            $doc = pc_trancado(function () use ($col, $id, $campo, $soVazio, $fid, $nome, $mime, $eu, $agora) {
                $d = pc_ler($col);
                if (!isset($d[$id])) throw new RuntimeException('Ficha não encontrada.');
                $lista = $d[$id][$campo] ?? [];
                if ($soVazio && $lista) throw new RuntimeException($campo === 'fotos' ? 'Já tem foto. Para juntar mais, abre a ficha.' : 'Já tem desenho. Para juntar mais, abre a ficha.');
                $lista[] = ['id' => $fid, 'nome' => $nome, 'tipo' => $mime, 'em' => $agora, 'por' => $eu['user']];
                $d[$id][$campo] = $lista; $d[$id]['atualizado'] = $agora; $d[$id]['por'] = $eu['user'];
                pc_gravar($col, $d); pc_mexeu();
                return $d[$id];
            });
        } catch (Throwable $t) { @unlink(PC_FICHEIROS . '/' . $fid); throw $t; }
        responder(['doc' => $doc]);

    // Tirar um desenho ou uma foto (o ficheiro só se apaga se mais nenhuma ficha o usar)
    case 'tirar':
        $col = pc_colecao((string) $p('tipo')); $id = (string) $p('id'); $campo = pc_campo_media((string) $p('campo'));
        $fid = (string) $p('fid');
        $doc = pc_trancado(function () use ($col, $id, $campo, $fid, $eu, $agora) {
            $d = pc_ler($col);
            if (!isset($d[$id])) throw new RuntimeException('Ficha não encontrada.');
            $d[$id][$campo] = array_values(array_filter($d[$id][$campo] ?? [], fn($m) => ($m['id'] ?? '') !== $fid));
            $d[$id]['atualizado'] = $agora; $d[$id]['por'] = $eu['user'];
            pc_gravar($col, $d); pc_mexeu();
            $pecas = $col === 'pecas' ? $d : pc_ler('pecas');
            $modelos = $col === 'modelos' ? $d : pc_ler('modelos');
            if (pc_id_ficheiro_valido($fid) && !pc_ficheiro_usado($fid, $pecas, $modelos)) @unlink(PC_FICHEIROS . '/' . $fid);
            return $d[$id];
        });
        responder(['doc' => $doc]);

    // Importar a listagem (ficheiro JSON). Só junta o que ainda não existe: nada do que já foi preenchido se perde.
    case 'importar':
        $f = $_FILES['ficheiro'] ?? null;
        if (!$f || ($f['error'] ?? 1) !== UPLOAD_ERR_OK) erro('O ficheiro não chegou.');
        $j = json_decode((string) file_get_contents($f['tmp_name']), true);
        if (!is_array($j) || !isset($j['pecas'], $j['modelos'])) erro('Este ficheiro não é uma listagem de peças.');
        $r = pc_trancado(function () use ($j) {
            // imagens que vêm dentro do ficheiro (fotos dos modelos)
            $imgs = [];
            foreach (($j['imagens'] ?? []) as $chave => $b64) {
                $bin = base64_decode((string) $b64, true);
                if ($bin === false) continue;
                $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bin) ?: '';
                if (!isset(PC_TIPOS[$mime]) || $mime === 'application/pdf') continue;
                $fid = substr(hash('sha256', $bin), 0, 24) . '.' . PC_TIPOS[$mime];
                if (!is_file(PC_FICHEIROS . '/' . $fid)) file_put_contents(PC_FICHEIROS . '/' . $fid, $bin);
                $imgs[$chave] = ['id' => $fid, 'tipo' => $mime];
            }
            $novos = ['pecas' => 0, 'modelos' => 0];
            foreach (['pecas', 'modelos'] as $col) {
                $d = pc_ler($col);
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
                pc_gravar($col, $d);
            }
            pc_mexeu();
            return $novos;
        });
        responder(['novos' => $r]);

    }
} catch (RuntimeException $t) {
    erro($t->getMessage());
}
erro('Ação desconhecida.', 404);
