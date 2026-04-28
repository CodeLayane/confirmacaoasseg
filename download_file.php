<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'config.php';
checkLogin();

if (!isset($_GET['id'])) die('Arquivo não especificado');

$pdo = getConnection();
$stmt = $pdo->prepare("SELECT * FROM materiais WHERE id=?"); $stmt->execute([$_GET['id']]); $mat = $stmt->fetch();
if (!$mat) die('Não encontrado');
if (!file_exists($mat['arquivo'])) die('Arquivo ausente no servidor');

$nome = basename($mat['arquivo']);
$tipo = mime_content_type($mat['arquivo']);
$tam = filesize($mat['arquivo']);

header('Content-Type: ' . $tipo);
header('Content-Disposition: attachment; filename="' . $nome . '"');
header('Content-Length: ' . $tam);
header('Cache-Control: no-cache');
readfile($mat['arquivo']);
exit();
?>
