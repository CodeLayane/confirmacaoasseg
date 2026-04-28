<?php
// config.php - Configurações do sistema ASSEGO Eventos

// Configurações do banco de dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'confirmacaoassego');  // Ajuste conforme seu servidor
define('DB_USER', 'layane');       // Ajuste conforme seu servidor
define('DB_PASS', '92106115@Lore');      // Ajuste conforme seu servidor

// Configurações do sistema
define('SITE_NAME', 'ASSEGO Eventos');
define('SITE_URL', 'https://confirmacao.assego.com.br/');

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// Função de conexão com o banco
function getConnection() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4, time_zone = '-03:00'"
                ]
            );
        } catch (PDOException $e) {
            die("Erro na conexão: " . $e->getMessage());
        }
    }
    return $pdo;
}

// Função para limpar dados de entrada
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Função para formatar telefone
function formatPhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) == 11) {
        return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $phone);
    } else {
        return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $phone);
    }
}

// Função para formatar CPF
function formatCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf);
}

// Verificar login
function checkLogin() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header('Location: login.php');
        exit();
    }
}

// Verificar se é admin
function isAdmin() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// Obter ID do usuário logado
function getUserId() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return $_SESSION['user_id'] ?? 0;
}

// Obter nome do usuário logado
function getUserName() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return $_SESSION['user_nome'] ?? 'Usuário';
}

// Obter eventos permitidos para o usuário
function getEventosPermitidos($pdo, $userId = null, $role = null) {
    if ($userId === null) $userId = getUserId();
    if ($role === null) $role = $_SESSION['user_role'] ?? 'operador';
    
    if ($role === 'admin') {
        // Admin vê todos os eventos
        return $pdo->query("SELECT * FROM eventos WHERE ativo = 1 ORDER BY data_inicio DESC")->fetchAll();
    } else {
        // Operador vê apenas eventos atribuídos
        $stmt = $pdo->prepare("
            SELECT e.* FROM eventos e 
            INNER JOIN usuario_eventos ue ON e.id = ue.evento_id 
            WHERE ue.usuario_id = ? AND e.ativo = 1 
            ORDER BY e.data_inicio DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
}

// Verificar se o usuário tem acesso a um evento específico
function temAcessoEvento($pdo, $eventoId, $userId = null, $role = null) {
    if ($userId === null) $userId = getUserId();
    if ($role === null) $role = $_SESSION['user_role'] ?? 'operador';
    
    if ($role === 'admin') return true;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario_eventos WHERE usuario_id = ? AND evento_id = ?");
    $stmt->execute([$userId, $eventoId]);
    return $stmt->fetchColumn() > 0;
}

// Obter evento atual da sessão
// FIX: Removido "AND ativo = 1" para que ao desativar um evento ele continue
// selecionado no painel, sem pular automaticamente para outro evento.
function getEventoAtual($pdo) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $eventoId = $_SESSION['evento_atual'] ?? null;
    
    if ($eventoId) {
        $stmt = $pdo->prepare("SELECT * FROM eventos WHERE id = ?");
        $stmt->execute([$eventoId]);
        return $stmt->fetch();
    }
    return null;
}

// Definir evento atual
function setEventoAtual($eventoId) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['evento_atual'] = $eventoId;
}

// Registrar auditoria
function logAuditoria($pdo, $acao, $entidade, $entidade_id=null, $detalhes=null, $evento_id=null) {
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'auditoria'");
        if ($check->rowCount() === 0) return;
        if (session_status() === PHP_SESSION_NONE) session_start();
        date_default_timezone_set('America/Sao_Paulo');
        $uid = $_SESSION['user_id'] ?? 0;
        $unome = $_SESSION['user_nome'] ?? 'Sistema';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $det = $detalhes ? json_encode($detalhes, JSON_UNESCAPED_UNICODE) : null;
        $eid = $evento_id ?? ($_SESSION['evento_atual'] ?? null);
        $agora = date('Y-m-d H:i:s');
        $pdo->prepare("INSERT INTO auditoria (usuario_id,usuario_nome,acao,entidade,entidade_id,detalhes,evento_id,ip,created_at) VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$uid, $unome, $acao, $entidade, $entidade_id, $det, $eid, $ip, $agora]);
    } catch(Exception $e) {}
}
?>