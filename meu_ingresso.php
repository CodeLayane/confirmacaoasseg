<?php
require_once 'config.php';
$pdo = getConnection();

// Token do participante na URL
$id  = (int)($_GET['id'] ?? 0);
$tk  = $_GET['tk'] ?? '';

// Validar token
function gerarToken($id, $tipo, $idx = 0) {
    $secret = DB_PASS . DB_NAME;
    $data = $id . ':' . $tipo . ':' . $idx;
    return base64_encode($data . ':' . substr(hash_hmac('sha256', $data, $secret), 0, 12));
}

if (!$id || !$tk || $tk !== gerarToken($id, 'titular')) {
    die("<div style='font-family:Inter,sans-serif;text-align:center;padding:80px 20px;color:#64748b'><h2>Link inválido</h2><p>Este link de ingresso não é válido.</p></div>");
}

$s = $pdo->prepare("SELECT p.*, e.nome as ev_nome, e.cor_tema, e.banner_base64, e.data_inicio, e.local FROM participantes p LEFT JOIN eventos e ON p.evento_id=e.id WHERE p.id=?");
$s->execute([$id]);
$p = $s->fetch();

if (!$p || !$p['aprovado']) {
    die("<div style='font-family:Inter,sans-serif;text-align:center;padding:80px 20px;color:#64748b'><h2>Ingresso não disponível</h2><p>Seu cadastro ainda não foi aprovado ou não foi encontrado.</p></div>");
}

$acomps = [];
try { $acomps = json_decode($p['acompanhantes'] ?? '[]', true) ?: []; } catch(Exception $e) {}
$foto_s = $pdo->prepare("SELECT dados FROM fotos WHERE participante_id=? LIMIT 1");
$foto_s->execute([$id]); $foto_row = $foto_s->fetch();
$foto = $foto_row['dados'] ?? null;

$cor = $p['cor_tema'] ?? '#1e40af';

// Gerar QR URL
function qrUrl($token, $baseUrl) {
    $url = $baseUrl . 'api_presenca.php?action=confirmar&token=' . urlencode($token);
    return 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($url) . '&bgcolor=ffffff&color=000000&format=png&margin=10&ecc=M';
}

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/';
$tokenTitular = gerarToken($id, 'titular');
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Meu Ingresso — <?=htmlspecialchars($p['ev_nome'])?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:linear-gradient(135deg,<?=$cor?> 0%,#0f172a 100%);min-height:100vh;padding:20px}
.container{max-width:480px;margin:0 auto}
.ingresso{background:white;border-radius:24px;overflow:hidden;box-shadow:0 25px 60px rgba(0,0,0,.4);margin-bottom:16px}
.ing-header{background:linear-gradient(135deg,<?=$cor?>,#1e40af);padding:24px;color:white;text-align:center;position:relative}
.ing-foto{width:80px;height:80px;border-radius:50%;border:3px solid rgba(255,255,255,.5);object-fit:cover;margin:0 auto 12px;display:block;background:rgba(255,255,255,.2)}
.ing-nome{font-size:20px;font-weight:800;margin-bottom:4px}
.ing-evento{font-size:13px;opacity:.8;font-weight:500}
.status-badge{display:inline-flex;align-items:center;gap:6px;background:rgba(16,185,129,.3);border:1px solid rgba(16,185,129,.5);color:#6ee7b7;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;margin-top:8px}
.ing-body{padding:20px}
.ing-info{display:flex;justify-content:space-around;padding:12px 0;border-bottom:1px solid #f1f5f9;margin-bottom:16px;flex-wrap:wrap;gap:8px}
.ing-info-item{text-align:center}
.ing-info-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#94a3b8;margin-bottom:2px}
.ing-info-val{font-size:13px;font-weight:700;color:#1e293b}
.qr-section{text-align:center;padding:16px 0}
.qr-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#94a3b8;margin-bottom:10px}
.qr-img{width:220px;height:220px;border-radius:12px;border:3px solid #e2e8f0;display:block;margin:0 auto}
.qr-hint{font-size:11px;color:#94a3b8;margin-top:8px}
.tear{border-top:2px dashed #e2e8f0;margin:0 16px;position:relative}
.tear::before,.tear::after{content:'';width:20px;height:20px;background:linear-gradient(135deg,<?=$cor?> 0%,#0f172a 100%);border-radius:50%;position:absolute;top:-10px}
.tear::before{left:-26px}.tear::after{right:-26px}
.acomp-card{background:white;border-radius:20px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,.3);margin-bottom:12px}
.acomp-header{background:linear-gradient(135deg,#7c3aed,#6366f1);padding:16px 20px;color:white;display:flex;align-items:center;gap:12px}
.acomp-avatar{width:44px;height:44px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.acomp-nome{font-size:16px;font-weight:700}
.acomp-sub{font-size:11px;opacity:.7;margin-top:2px}
.acomp-body{padding:16px;text-align:center}
.share-btn{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:12px;background:linear-gradient(135deg,#25d366,#128c7e);color:white;border:none;border-radius:12px;font-size:14px;font-weight:700;cursor:pointer;text-decoration:none;margin-top:10px}
.share-btn.copy{background:linear-gradient(135deg,#3b82f6,#1d4ed8)}
.page-title{text-align:center;color:white;margin-bottom:20px}
.page-title h1{font-size:22px;font-weight:800;margin-bottom:4px}
.page-title p{font-size:13px;opacity:.7}
</style>
</head><body>
<div class="container">
<div class="page-title">
    <h1><i class="fas fa-ticket"></i> Meu Ingresso</h1>
    <p>Apresente o QR Code na entrada do evento</p>
</div>

<!-- Ingresso Principal -->
<div class="ingresso">
    <div class="ing-header">
        <?php if($foto):?>
        <img src="<?=$foto?>" class="ing-foto" alt="Foto">
        <?php else:?>
        <div class="ing-foto" style="display:flex;align-items:center;justify-content:center"><i class="fas fa-user" style="font-size:32px;color:rgba(255,255,255,.6)"></i></div>
        <?php endif;?>
        <div class="ing-nome"><?=htmlspecialchars(strtoupper($p['nome']))?></div>
        <div class="ing-evento"><i class="fas fa-calendar-alt"></i> <?=htmlspecialchars($p['ev_nome'])?></div>
        <div class="status-badge"><i class="fas fa-check-circle"></i> Aprovado</div>
    </div>
    <div class="ing-body">
        <div class="ing-info">
            <?php if($p['local'] ?? ''): ?><div class="ing-info-item"><div class="ing-info-label">Local</div><div class="ing-info-val"><?=htmlspecialchars($p['local'])?></div></div><?php endif;?>
            <?php if($p['data_inicio'] ?? ''): ?><div class="ing-info-item"><div class="ing-info-label">Data</div><div class="ing-info-val"><?=date('d/m/Y', strtotime($p['data_inicio']))?></div></div><?php endif;?>
            <?php if(count($acomps) > 0): ?><div class="ing-info-item"><div class="ing-info-label">Acompanhantes</div><div class="ing-info-val"><?=count($acomps)?></div></div><?php endif;?>
        </div>
        <div class="qr-section">
            <div class="qr-label"><i class="fas fa-qrcode"></i> Seu QR Code de Entrada</div>
            <img src="<?=qrUrl($tokenTitular, $baseUrl)?>" class="qr-img" alt="QR Code">
            <div class="qr-hint">Apresente este código na entrada</div>
        </div>
    </div>
    <div class="tear"></div>
    <div style="padding:16px;text-align:center">
        <a href="<?=qrUrl($tokenTitular, $baseUrl)?>" download="ingresso-<?=urlencode($p['nome'])?>.png" class="share-btn" style="background:linear-gradient(135deg,#059669,#10b981)">
            <i class="fas fa-download"></i> Salvar QR Code
        </a>
        <button onclick="compartilhar('<?=htmlspecialchars($p['nome'])?>','<?=$baseUrl?>meu_ingresso.php?id=<?=$id?>&tk=<?=urlencode($tokenTitular)?>')" class="share-btn" style="margin-top:8px">
            <i class="fab fa-whatsapp"></i> Compartilhar via WhatsApp
        </button>
    </div>
</div>

<!-- Acompanhantes -->
<?php if(count($acomps) > 0): ?>
<div style="text-align:center;color:white;margin:20px 0 12px"><h3><i class="fas fa-user-friends"></i> Ingressos dos Acompanhantes</h3><p style="font-size:12px;opacity:.7;margin-top:4px">Envie o QR Code para cada acompanhante</p></div>
<?php foreach($acomps as $ai => $ac): ?>
<?php $tokenAc = gerarToken($id, 'acomp', $ai); $linkAc = $baseUrl.'meu_ingresso.php?id='.$id.'&tk='.urlencode(gerarToken($id,'titular')).'#acomp-'.$ai; ?>
<div class="acomp-card" id="acomp-<?=$ai?>">
    <div class="acomp-header">
        <div class="acomp-avatar"><i class="fas fa-user"></i></div>
        <div>
            <div class="acomp-nome"><?=htmlspecialchars(strtoupper($ac['nome'] ?? '?'))?></div>
            <div class="acomp-sub">Acompanhante de <?=htmlspecialchars($p['nome'])?></div>
        </div>
    </div>
    <div class="acomp-body">
        <img src="<?=qrUrl($tokenAc, $baseUrl)?>" class="qr-img" style="width:180px;height:180px" alt="QR Acompanhante">
        <div class="qr-hint" style="margin-bottom:10px">QR Code de entrada — Acompanhante <?=$ai+1?></div>
        <?php
        $waMsg = urlencode("Olá " . ($ac['nome'] ?? '') . "! Aqui está seu ingresso para o evento *" . $p['ev_nome'] . "*.\n\nApresente este QR Code na entrada:\n" . qrUrl($tokenAc, $baseUrl));
        $waNum = preg_replace('/[^0-9]/', '', $ac['whatsapp'] ?? '');
        $waLink = $waNum ? "https://wa.me/55{$waNum}?text={$waMsg}" : "https://wa.me/?text={$waMsg}";
        ?>
        <a href="<?=$waLink?>" target="_blank" class="share-btn"><i class="fab fa-whatsapp"></i> Enviar para <?=htmlspecialchars(($ac['nome'] ?? 'Acompanhante'))?></a>
        <a href="<?=qrUrl($tokenAc, $baseUrl)?>" download="ingresso-acomp-<?=$ai+1?>.png" class="share-btn copy" style="margin-top:8px"><i class="fas fa-download"></i> Salvar QR Code</a>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

</div>
<script>
function compartilhar(nome, link) {
    const txt = `Meu ingresso para o evento!\n\nNome: ${nome}\nAcesse seu QR Code: ${link}`;
    if(navigator.share){ navigator.share({title:'Meu Ingresso', text:txt, url:link}).catch(()=>{}); }
    else { window.open('https://wa.me/?text='+encodeURIComponent(txt),'_blank'); }
}
</script>
</body></html>
