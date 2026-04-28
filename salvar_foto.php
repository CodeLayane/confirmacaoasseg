<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'config.php';
checkLogin();
header('Content-Type: application/json');

$participante_id = (int)($_POST['membro_id'] ?? $_POST['participante_id'] ?? 0);
$foto = $_POST['foto'] ?? '';

if (!$participante_id || empty($foto) || strpos($foto, 'data:image') !== 0) {
    echo json_encode(['ok'=>false,'msg'=>'Dados inválidos']); exit();
}

try {
    $pdo = getConnection();
    $chk = $pdo->prepare("SELECT id FROM fotos WHERE participante_id=?"); $chk->execute([$participante_id]);
    if ($chk->fetch()) {
        $pdo->prepare("UPDATE fotos SET dados=?,updated_at=NOW() WHERE participante_id=?")->execute([$foto, $participante_id]);
    } else {
        $pdo->prepare("INSERT INTO fotos (participante_id,dados) VALUES (?,?)")->execute([$participante_id, $foto]);
    }
    echo json_encode(['ok'=>true]);
} catch (Exception $e) {
    echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
?>
