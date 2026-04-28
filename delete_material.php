<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'config.php';
checkLogin();
header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['id'])) throw new Exception('ID não fornecido');
    
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT arquivo FROM materiais WHERE id=?"); $stmt->execute([$input['id']]);
    $mat = $stmt->fetch();
    if (!$mat) throw new Exception('Material não encontrado');
    
    if (file_exists($mat['arquivo'])) unlink($mat['arquivo']);
    $pdo->prepare("DELETE FROM materiais WHERE id=?")->execute([$input['id']]);
    
    echo json_encode(['success'=>true,'message'=>'Excluído!']);
} catch (Exception $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
?>
