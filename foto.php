<?php
if(session_status()===PHP_SESSION_NONE) session_start();
require_once 'config.php';
checkLogin();
$pdo = getConnection();
$id = (int)($_GET['id'] ?? 0);
if(!$id){ http_response_code(404); exit(); }

$s = $pdo->prepare("SELECT dados FROM fotos WHERE participante_id=? LIMIT 1");
$s->execute([$id]);
$row = $s->fetch();

if(!$row || empty($row['dados'])){
    // Retorna imagem placeholder transparente 1x1
    header('Content-Type: image/png');
    echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    exit();
}

$data = $row['dados'];
// Extrair mime type do data URI
if(preg_match('/^data:(image\/[a-zA-Z+]+);base64,/', $data, $m)){
    header('Content-Type: ' . $m[1]);
    header('Cache-Control: private, max-age=3600');
    echo base64_decode(substr($data, strpos($data, ',')+1));
} else {
    http_response_code(400); exit();
}
?>