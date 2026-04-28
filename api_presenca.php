<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache,no-store');
require_once 'config.php';

// Gera/valida token de um participante
function gerarToken($id, $tipo, $idx = 0) {
    $secret = DB_PASS . DB_NAME;
    $data = $id . ':' . $tipo . ':' . $idx;
    return base64_encode($data . ':' . substr(hash_hmac('sha256', $data, $secret), 0, 12));
}

function validarToken($token) {
    $decoded = base64_decode($token);
    if (!$decoded) return null;
    $parts = explode(':', $decoded);
    if (count($parts) !== 4) return null;
    [$id, $tipo, $idx, $hash] = $parts;
    $secret = DB_PASS . DB_NAME;
    $data = $id . ':' . $tipo . ':' . $idx;
    $expected = substr(hash_hmac('sha256', $data, $secret), 0, 12);
    if (!hash_equals($expected, $hash)) return null;
    return ['id' => (int)$id, 'tipo' => $tipo, 'idx' => (int)$idx];
}

$action = $_GET['action'] ?? 'validate';
$pdo = getConnection();

// ── Gerar token (para montar QR na meu_ingresso.php) ─────────────────────────
if ($action === 'token') {
    $id  = (int)($_GET['id'] ?? 0);
    $tipo = $_GET['tipo'] ?? 'titular';
    $idx  = (int)($_GET['idx'] ?? 0);
    if (!$id) { echo json_encode(['error' => 'no_id']); exit(); }
    echo json_encode(['token' => gerarToken($id, $tipo, $idx)]);
    exit();
}

// ── Validar e confirmar presença (chamado pelo scanner) ───────────────────────
if ($action === 'confirmar') {
    $token = $_GET['token'] ?? '';
    $payload = validarToken($token);

    if (!$payload) {
        echo json_encode(['ok' => false, 'msg' => 'QR Code inválido ou adulterado.', 'status' => 'invalido']);
        exit();
    }

    $id  = $payload['id'];
    $tipo = $payload['tipo'];
    $idx  = $payload['idx'];

    // Buscar participante
    $s = $pdo->prepare("SELECT p.*, e.nome as ev_nome FROM participantes p LEFT JOIN eventos e ON p.evento_id=e.id WHERE p.id=?");
    $s->execute([$id]);
    $p = $s->fetch();

    if (!$p) {
        echo json_encode(['ok' => false, 'msg' => 'Participante não encontrado.', 'status' => 'invalido']);
        exit();
    }

    if (!$p['aprovado']) {
        echo json_encode(['ok' => false, 'msg' => 'Cadastro ainda não aprovado.', 'status' => 'nao_aprovado',
            'nome' => $p['nome'], 'evento' => $p['ev_nome']]);
        exit();
    }

    date_default_timezone_set('America/Sao_Paulo');
    $agora = date('Y-m-d H:i:s');
    $operador = $_GET['op'] ?? 'Scanner';

    if ($tipo === 'titular') {
        // Verificar se já confirmou
        if ($p['presenca_confirmada']) {
            echo json_encode([
                'ok' => false,
                'msg' => 'Presença já confirmada anteriormente.',
                'status' => 'ja_confirmado',
                'nome' => $p['nome'],
                'evento' => $p['ev_nome'],
                'presenca_em' => $p['presenca_em'],
            ]);
            exit();
        }
        $pdo->prepare("UPDATE participantes SET presenca_confirmada=1, presenca_em=?, presenca_por=? WHERE id=?")
            ->execute([$agora, $operador, $id]);

        // Acompanhantes
        $acomps = [];
        try { $acomps = json_decode($p['acompanhantes'] ?? '[]', true) ?: []; } catch(Exception $e){}

        echo json_encode([
            'ok' => true,
            'status' => 'confirmado',
            'nome' => $p['nome'],
            'evento' => $p['ev_nome'],
            'presenca_em' => $agora,
            'acomps_count' => count($acomps),
            'foto' => !empty($pdo->prepare("SELECT 1 FROM fotos WHERE participante_id=? LIMIT 1")->execute([$id])),
        ]);
    } else {
        // Acompanhante
        $acomps = [];
        try { $acomps = json_decode($p['acompanhantes'] ?? '[]', true) ?: []; } catch(Exception $e){}

        if (!isset($acomps[$idx])) {
            echo json_encode(['ok' => false, 'msg' => 'Acompanhante não encontrado.', 'status' => 'invalido']);
            exit();
        }

        $ac = $acomps[$idx];
        $presAcomps = [];
        try { $presAcomps = json_decode($p['presenca_acomps'] ?? '[]', true) ?: []; } catch(Exception $e){}

        if (in_array($idx, $presAcomps)) {
            echo json_encode([
                'ok' => false,
                'msg' => 'Presença deste acompanhante já confirmada.',
                'status' => 'ja_confirmado',
                'nome' => $ac['nome'] ?? '?',
                'titular' => $p['nome'],
                'evento' => $p['ev_nome'],
            ]);
            exit();
        }

        $presAcomps[] = $idx;
        $pdo->prepare("UPDATE participantes SET presenca_acomps=? WHERE id=?")
            ->execute([json_encode($presAcomps), $id]);

        echo json_encode([
            'ok' => true,
            'status' => 'confirmado',
            'nome' => $ac['nome'] ?? '?',
            'titular' => $p['nome'],
            'evento' => $p['ev_nome'],
            'presenca_em' => $agora,
        ]);
    }
    exit();
}

echo json_encode(['error' => 'invalid_action']);
