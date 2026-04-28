<?php
session_start();
// Registrar logout na auditoria ANTES de destruir a sessão
try {
    require_once 'config.php';
    $pdo = getConnection();
    logAuditoria($pdo, 'logout', 'usuario', $_SESSION['user_id'] ?? null, ['nome' => $_SESSION['user_nome'] ?? '']);
} catch(Exception $e) {}

$_SESSION = array();
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
}
session_destroy();
header("Location: login.php");
exit();
?>
