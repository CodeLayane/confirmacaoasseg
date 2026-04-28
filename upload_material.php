<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'config.php';
checkLogin();
header('Content-Type: application/json');

try {
    $participante_id = (int)($_POST['membro_id'] ?? $_POST['participante_id'] ?? 0);
    $tipo = clean_input($_POST['tipo'] ?? 'documento');
    $titulo = clean_input($_POST['titulo'] ?? '');
    $descricao = clean_input($_POST['descricao'] ?? '');
    
    if (!$participante_id || empty($titulo)) throw new Exception('Dados incompletos');
    if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) throw new Exception('Erro no upload');
    if ($_FILES['arquivo']['size'] > 5 * 1024 * 1024) throw new Exception('Arquivo muito grande (máx 5MB)');
    
    $upload_dir = 'uploads/materiais/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    
    $ext = pathinfo($_FILES['arquivo']['name'], PATHINFO_EXTENSION);
    $filename = uniqid('doc_') . '.' . $ext;
    $filepath = $upload_dir . $filename;
    
    if (!move_uploaded_file($_FILES['arquivo']['tmp_name'], $filepath)) throw new Exception('Falha ao salvar arquivo');
    
    $pdo = getConnection();
    $pdo->prepare("INSERT INTO materiais (participante_id,tipo,titulo,descricao,arquivo) VALUES (?,?,?,?,?)")
        ->execute([$participante_id, $tipo, $titulo, $descricao, $filepath]);
    
    echo json_encode(['success'=>true,'message'=>'Documento enviado!']);
} catch (Exception $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
?>
