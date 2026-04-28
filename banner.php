<?php
if(session_status()===PHP_SESSION_NONE) session_start();
require_once 'config.php';
checkLogin();
$pdo = getConnection();
$id  = (int)($_GET['id'] ?? 0);
$mob = isset($_GET['mob']);
if(!$id){ http_response_code(404); exit(); }

$col = $mob ? 'banner_mobile_base64' : 'banner_base64';
$s   = $pdo->prepare("SELECT $col FROM eventos WHERE id=? LIMIT 1");
$s->execute([$id]);
$row = $s->fetch();
$data = $row[$col] ?? '';

// Fallback para desktop se mobile vazio
if($mob && empty($data)){
    $s2 = $pdo->prepare("SELECT banner_base64 FROM eventos WHERE id=? LIMIT 1");
    $s2->execute([$id]);
    $data = $s2->fetch()['banner_base64'] ?? '';
}

if(empty($data)){ http_response_code(404); exit(); }

if(preg_match('/^data:(image\/[a-zA-Z+]+);base64,/', $data, $m)){
    header('Content-Type: '.$m[1]);
    header('Cache-Control: private, max-age=3600');
    echo base64_decode(substr($data, strpos($data,',')+1));
} else {
    http_response_code(400);
}
