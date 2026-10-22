<?php
// Peças — serve um desenho ou uma foto de data/pecas/ficheiros, só à administração com sessão.
declare(strict_types=1);
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/pecas_lib.php';
if (empty($_SESSION['app_email']) || ($_SESSION['app_nivel'] ?? '') !== 'admin') { http_response_code(401); exit; }
session_write_close();
$id = (string) ($_GET['f'] ?? '');
$f = PC_FICHEIROS . '/' . $id;
if (!pc_id_ficheiro_valido($id) || !is_file($f)) { http_response_code(404); exit; }
$tipo = array_search(pathinfo($id, PATHINFO_EXTENSION), PC_TIPOS, true) ?: 'application/octet-stream';
header('Content-Type: ' . $tipo);
header('Content-Length: ' . filesize($f));
header('Cache-Control: private, max-age=31536000, immutable');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline; filename="' . $id . '"');
readfile($f);
