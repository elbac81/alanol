<?php
// Peças Alanol — serve um desenho ou uma foto, só a quem tem sessão.
declare(strict_types=1);
require __DIR__ . '/_lib.php';
if (!pa_eu()) { http_response_code(401); exit; }
session_write_close();
$id = (string) ($_GET['f'] ?? '');
$f = PA_FICHEIROS . '/' . $id;
if (!pa_id_ficheiro_valido($id) || !is_file($f)) { http_response_code(404); exit; }
$tipo = array_search(pathinfo($id, PATHINFO_EXTENSION), PA_TIPOS, true) ?: 'application/octet-stream';
header('Content-Type: ' . $tipo);
header('Content-Length: ' . filesize($f));
header('Cache-Control: private, max-age=31536000, immutable');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline; filename="' . $id . '"');
readfile($f);
