<?php
if(session_status()===PHP_SESSION_NONE) session_start();
require_once 'config.php';
checkLogin();
header('Content-Type: application/json');
$pdo = getConnection();
$id = (int)($_GET['id'] ?? 0);
if(!$id){ echo json_encode(['error'=>'no_id']); exit(); }

$s = $pdo->prepare("
    SELECT p.*, e.campos_extras as ev_campos,
    (SELECT 1 FROM fotos WHERE participante_id=p.id LIMIT 1) as foto
    FROM participantes p
    LEFT JOIN eventos e ON p.evento_id = e.id
    WHERE p.id = ?
");
$s->execute([$id]);
$p = $s->fetch();

if(!$p){ echo json_encode(['error'=>'not_found']); exit(); }

// Campos extras com labels
$campos = json_decode($p['ev_campos']??'[]', true) ?: [];
$extras_raw = json_decode($p['campos_extras']??'{}', true) ?: [];
$extras_fmt = [];
foreach($campos as $c){
    $val = $extras_raw[$c['nome']] ?? '';
    if($val !== '') $extras_fmt[$c['label']] = $val;
}

// Acompanhantes
$acomps = [];
try{ if(!empty($p['acompanhantes'])) $acomps = json_decode($p['acompanhantes'], true) ?: []; }catch(Exception $e){}

// Datas formatadas
date_default_timezone_set('America/Sao_Paulo');
$created_fmt = $p['created_at'] ? date('d/m/Y H:i', strtotime($p['created_at'])) : '';
$aprovado_em_fmt = '';
if(!empty($p['aprovado_em'])) $aprovado_em_fmt = date('d/m/Y H:i', strtotime($p['aprovado_em']));

echo json_encode([
    'id'              => $p['id'],
    'nome'            => $p['nome'],
    'whatsapp'        => $p['whatsapp'] ?? '',
    'instagram'       => $p['instagram'] ?? '',
    'endereco'        => $p['endereco'] ?? '',
    'cidade'          => $p['cidade'] ?? '',
    'estado'          => $p['estado'] ?? '',
    'cep'             => $p['cep'] ?? '',
    'observacoes'     => $p['observacoes'] ?? '',
    'foto'            => (bool)$p['foto'],
    'aprovado'        => (bool)$p['aprovado'],
    'created_at_fmt'  => $created_fmt,
    'aprovado_em_fmt' => $aprovado_em_fmt,
    'aprovado_por'    => $p['aprovado_por'] ?? '',
    'extras'          => $extras_fmt,
    'acomps'          => $acomps,
], JSON_UNESCAPED_UNICODE);
?>