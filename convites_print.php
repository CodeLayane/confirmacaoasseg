<?php
require_once 'config.php';
if(session_status()===PHP_SESSION_NONE) session_start();
if(!isset($_SESSION['logged_in'])||$_SESSION['logged_in']!==true){ die('Acesso negado'); }

$pdo = getConnection();
$ids_raw = $_GET['ids'] ?? '';
$aba = $_GET['aba'] ?? 'convites';
$ids = array_filter(array_map('intval', explode(',', $ids_raw)));
if(empty($ids)){ die('Nenhum ID informado'); }

function gerarToken($id, $tipo='titular', $idx=0){
    $secret=DB_PASS.DB_NAME;
    $data=$id.':'.$tipo.':'.$idx;
    return base64_encode($data.':'.substr(hash_hmac('sha256',$data,$secret),0,12));
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$s = $pdo->prepare("SELECT p.*, e.nome as ev_nome, e.cor_tema, e.banner_base64, e.local, e.config_form FROM participantes p LEFT JOIN eventos e ON p.evento_id=e.id WHERE p.id IN ($placeholders) ORDER BY p.nome");
$s->execute($ids);
$participantes = $s->fetchAll();

$baseUrl=(isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on'?'https':'http').'://'.$_SERVER['HTTP_HOST'].rtrim(dirname($_SERVER['SCRIPT_NAME']),'/').'/';
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Convites — Imprimir</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:#f1f5f9;padding:20px}
.toolbar{display:flex;gap:10px;justify-content:center;margin-bottom:24px;flex-wrap:wrap}
.tb-btn{display:flex;align-items:center;gap:8px;padding:10px 20px;border:none;border-radius:12px;font-size:14px;font-weight:700;cursor:pointer;text-decoration:none;transition:.2s}
.tb-print{background:linear-gradient(135deg,#1e40af,#3b82f6);color:white}
.tb-back{background:#e2e8f0;color:#475569}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(360px,1fr));gap:24px;max-width:1200px;margin:0 auto}
.convite{background:white;border-radius:20px;overflow:hidden;box-shadow:0 8px 30px rgba(0,0,0,.12);break-inside:avoid}
.conv-banner{width:100%;height:180px;object-fit:cover;display:block}
.conv-banner-ph{width:100%;height:180px;display:flex;align-items:center;justify-content:center}
.conv-logos{position:absolute;top:10px;left:10px;display:flex;gap:8px;align-items:center;background:rgba(0,0,0,.45);padding:6px 10px;border-radius:10px;backdrop-filter:blur(4px)}
.conv-logos img{height:26px;width:auto;max-width:56px;object-fit:contain}
.banner-wrap{position:relative}
.conv-body{padding:18px 20px 20px}
.conv-ev{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#94a3b8;margin-bottom:3px}
.conv-titulo{font-size:18px;font-weight:900;color:#0f172a;margin-bottom:12px}
.conv-divider{border:none;border-top:2px dashed #e2e8f0;margin:10px 0}
.conv-nome-lbl{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:#94a3b8;margin-bottom:3px}
.conv-nome{font-size:18px;font-weight:800;line-height:1.2;margin-bottom:12px}
.conv-meta{display:flex;gap:12px;margin-bottom:14px;font-size:12px;color:#64748b;font-weight:600;flex-wrap:wrap}
.conv-meta i{font-size:12px}
.qr-area{display:flex;align-items:center;gap:14px;background:#f8fafc;border-radius:14px;padding:14px;border:2px solid #e2e8f0}
.qr-img{width:120px;height:120px;border-radius:10px;flex-shrink:0}
.qr-title{font-size:13px;font-weight:800;color:#0f172a;margin-bottom:4px}
.qr-sub{font-size:11px;color:#64748b;line-height:1.4}
.qr-badge{display:inline-flex;align-items:center;gap:5px;color:white;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700;margin-top:8px}
.conv-footer{padding:10px 16px;display:flex;align-items:center;justify-content:center;gap:6px}
.conv-footer span{font-size:11px;font-weight:600;opacity:.6}

@media print{
    body{background:white;padding:0}
    .toolbar{display:none}
    .grid{grid-template-columns:repeat(2,1fr);gap:16px;max-width:100%}
    .convite{box-shadow:none;border:1px solid #e2e8f0;border-radius:12px}
    @page{margin:12mm;size:A4}
}
</style>
</head><body>

<div class="toolbar no-print">
    <button onclick="window.print()" class="tb-btn tb-print"><i class="fas fa-print"></i> Imprimir / Salvar PDF</button>
    <button onclick="window.close()" class="tb-btn tb-back"><i class="fas fa-times"></i> Fechar</button>
</div>

<div class="grid">
<?php foreach($participantes as $p):
    $cf = json_decode($p['config_form']??'{}',true)?:[];
    $cor = $p['cor_tema'] ?? '#1e40af';
    $token = gerarToken($p['id']);
    $qrData = $baseUrl.'api_presenca.php?action=confirmar&token='.urlencode($token);
    $qrUrl  = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&data='.urlencode($qrData).'&bgcolor=ffffff&color=000000&format=png&margin=10&ecc=H';

    $logos = [];
    if(($cf['logo_bombeiro']??'1')==='1') $logos[]='assets/img/logobombeiro.png';
    if(($cf['logo_policia']??'1')==='1')  $logos[]='assets/img/logopolicia.png';
    if(($cf['logo_assego']??'1')==='1')   $logos[]='assets/img/logo_assego.png';
    if(($cf['logo_sergio']??'1')==='1')   $logos[]='assets/img/logo_sergio.png';

    $foto_s = $pdo->prepare("SELECT dados FROM fotos WHERE participante_id=? LIMIT 1");
    $foto_s->execute([$p['id']]); $foto_row=$foto_s->fetch();
    $foto = $foto_row['dados']??null;
?>
<div class="convite">
    <div class="banner-wrap">
        <?php if(!empty($p['banner_base64'])): ?>
        <img src="banner.php?id=<?=$p['evento_id']?>" class="conv-banner" crossorigin="anonymous">
        <?php else: ?>
        <div class="conv-banner-ph" style="background:linear-gradient(135deg,<?=$cor?>,#0f172a)">
            <span style="color:white;font-size:24px;font-weight:900;text-transform:uppercase;text-shadow:0 3px 12px rgba(0,0,0,.5)"><?=htmlspecialchars($p['ev_nome'])?></span>
        </div>
        <?php endif;?>
        <?php if(!empty($logos)): ?>
        <div class="conv-logos">
            <?php foreach($logos as $l): ?><img src="<?=$l?>" crossorigin="anonymous"><?php endforeach;?>
        </div>
        <?php endif;?>
    </div>
    <div class="conv-body">
        <div class="conv-titulo"><?=htmlspecialchars($p['ev_nome'])?></div>
        <hr class="conv-divider">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
            <?php if($foto): ?>
            <img src="<?=$foto?>" style="width:52px;height:52px;border-radius:50%;object-fit:cover;border:2px solid #dbeafe;flex-shrink:0">
            <?php else: ?>
            <div style="width:52px;height:52px;border-radius:50%;background:#e0f2fe;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-user" style="color:#94a3b8;font-size:20px"></i></div>
            <?php endif;?>
            <div>
                <div class="conv-nome-lbl">Participante</div>
                <div class="conv-nome" style="color:<?=$cor?>"><?=htmlspecialchars($p['nome'])?></div>
            </div>
        </div>
        <?php if($p['local']): ?>
        <div class="conv-meta"><span><i class="fas fa-map-marker-alt" style="color:<?=$cor?>"></i> <?=htmlspecialchars($p['local'])?></span></div>
        <?php endif;?>
        <div class="qr-area">
            <img src="<?=$qrUrl?>" class="qr-img" crossorigin="anonymous">
            <div>
                <div class="qr-title">QR Code de Entrada</div>
                <div class="qr-sub">Apresente este código na entrada para confirmar sua presença.</div>
                <div class="qr-badge" style="background:<?=$cor?>"><i class="fas fa-check-circle"></i> Aprovado</div>
            </div>
        </div>
    </div>
    <div class="conv-footer" style="background:linear-gradient(135deg,<?=$cor?>,#0f172a)">
        <i class="fas fa-shield-halved" style="color:rgba(255,255,255,.5);font-size:12px"></i>
        <span style="color:rgba(255,255,255,.7)">ASSEGO Eventos</span>
    </div>
</div>
<?php endforeach;?>
</div>
</body></html>
