<?php
require_once 'config.php';

// Aceita acesso público (via token) OU admin logado
$id  = (int)($_GET['id'] ?? 0);
$tk  = $_GET['tk'] ?? '';
$isAdmin = false;

if(session_status()===PHP_SESSION_NONE) session_start();
if(isset($_SESSION['logged_in']) && $_SESSION['logged_in']===true && ($_SESSION['user_role']??'')===('admin'??'')) {
    $isAdmin = true;
}

function gerarToken($id, $tipo='titular', $idx=0){
    $secret=DB_PASS.DB_NAME;
    $data=$id.':'.$tipo.':'.$idx;
    return base64_encode($data.':'.substr(hash_hmac('sha256',$data,$secret),0,12));
}

// Validar acesso
if(!$isAdmin){
    $tokenEsperado = gerarToken($id,'titular');
    if(!$id || !$tk || $tk !== $tokenEsperado){
        die("<div style='font-family:Inter,sans-serif;text-align:center;padding:80px 20px;color:#64748b'><h2>Link inválido</h2></div>");
    }
}

$pdo = getConnection();
$s = $pdo->prepare("SELECT p.*, e.nome as ev_nome, e.cor_tema, e.banner_base64, e.data_inicio, e.data_fim, e.local, e.config_form FROM participantes p LEFT JOIN eventos e ON p.evento_id=e.id WHERE p.id=?");
$s->execute([$id]);
$p = $s->fetch();

if(!$p){ die("<div style='font-family:Inter,sans-serif;text-align:center;padding:80px 20px;color:#64748b'><h2>Não encontrado</h2></div>"); }

$foto_s = $pdo->prepare("SELECT dados FROM fotos WHERE participante_id=? LIMIT 1");
$foto_s->execute([$id]); $foto_row = $foto_s->fetch();
$foto = $foto_row['dados'] ?? null;

$cf = json_decode($p['config_form']??'{}',true)?:[];
$cor = $p['cor_tema'] ?? '#1e40af';
$baseUrl=(isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on'?'https':'http').'://'.$_SERVER['HTTP_HOST'].rtrim(dirname($_SERVER['SCRIPT_NAME']),'/').'/';
$token = gerarToken($id,'titular');
$qrData = $baseUrl.'api_presenca.php?action=confirmar&token='.urlencode($token);
$qrUrl  = 'https://api.qrserver.com/v1/create-qr-code/?size=500x500&data='.urlencode($qrData).'&bgcolor=ffffff&color=000000&format=png&margin=12&ecc=H';

// Logos
$logos = [];
if(($cf['logo_bombeiro']??'1')==='1') $logos[]=['src'=>'assets/img/logobombeiro.png','alt'=>'Bombeiro'];
if(($cf['logo_policia']??'1')==='1')  $logos[]=['src'=>'assets/img/logopolicia.png','alt'=>'PM'];
if(($cf['logo_assego']??'1')==='1')   $logos[]=['src'=>'assets/img/logo_assego.png','alt'=>'ASSEGO'];
if(($cf['logo_sergio']??'1')==='1')   $logos[]=['src'=>'assets/img/logo_sergio.png','alt'=>'Sergio'];

// Data removida do convite
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0">
<title>Convite — <?=htmlspecialchars($p['nome'])?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:#0f172a;min-height:100vh;display:flex;flex-direction:column;align-items:center;padding:20px;gap:16px}
.toolbar{display:flex;gap:10px;flex-wrap:wrap;justify-content:center;width:100%;max-width:420px}
.tb-btn{display:flex;align-items:center;gap:8px;padding:10px 18px;border:none;border-radius:12px;font-size:14px;font-weight:700;cursor:pointer;text-decoration:none;transition:.2s}
.tb-btn:hover{transform:translateY(-2px)}
.tb-dl{background:linear-gradient(135deg,#059669,#10b981);color:white}
.tb-wpp{background:linear-gradient(135deg,#25d366,#128c7e);color:white}
.tb-back{background:rgba(255,255,255,.1);color:white}

/* ── CONVITE ── */
#convite{
    width:420px;
    border-radius:24px;
    overflow:hidden;
    box-shadow:0 30px 80px rgba(0,0,0,.6);
    background:#ffffff;
    position:relative;
}

.conv-banner{
    width:100%;
    height:220px;
    object-fit:cover;
    display:block;
}
.conv-banner-placeholder{
    width:100%;height:220px;
    background:linear-gradient(135deg,<?=$cor?> 0%,#0f172a 100%);
    display:flex;align-items:center;justify-content:center;
}

.conv-logos{
    display:flex;align-items:center;justify-content:center;gap:14px;
    padding:14px 20px 10px;
    background:white;
    border-bottom:1px solid #f1f5f9;
    flex-wrap:wrap;
}
.conv-logos img{height:36px;width:auto;max-width:80px;object-fit:contain;filter:drop-shadow(0 1px 2px rgba(0,0,0,.15))}

.conv-body{padding:20px 24px 24px;background:white}

.conv-evento{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:#94a3b8;margin-bottom:4px}
.conv-titulo{font-size:22px;font-weight:900;color:#0f172a;line-height:1.2;margin-bottom:12px}

.conv-divider{border:none;border-top:2px dashed #e2e8f0;margin:14px 0}

.conv-nome-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#94a3b8;margin-bottom:4px}
.conv-nome{font-size:20px;font-weight:800;color:<?=$cor?>;line-height:1.2;margin-bottom:12px}

.conv-meta{display:flex;gap:16px;flex-wrap:wrap;margin-bottom:16px}
.conv-meta-item{display:flex;align-items:center;gap:6px;font-size:12px;color:#64748b;font-weight:600}
.conv-meta-item i{color:<?=$cor?>;font-size:13px}

.conv-qr-area{display:flex;align-items:center;gap:16px;background:#f8fafc;border-radius:16px;padding:16px;border:2px solid #e2e8f0}
.conv-qr-img{width:150px;height:150px;border-radius:10px;flex-shrink:0;border:2px solid #e2e8f0;transition:.2s}.conv-qr-img:hover{transform:scale(1.04)}
.conv-qr-info{flex:1}
.conv-qr-title{font-size:13px;font-weight:800;color:#0f172a;margin-bottom:4px}
.conv-qr-sub{font-size:11px;color:#64748b;line-height:1.5}
.conv-qr-badge{display:inline-flex;align-items:center;gap:5px;background:<?=$cor?>;color:white;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700;margin-top:8px}

.conv-footer{background:linear-gradient(135deg,<?=$cor?> 0%,#0f172a 100%);padding:12px 20px;display:flex;align-items:center;justify-content:center;gap:8px}
.conv-footer-text{color:rgba(255,255,255,.8);font-size:11px;font-weight:600}

.conv-foto{width:56px;height:56px;border-radius:50%;object-fit:cover;border:3px solid white;box-shadow:0 4px 12px rgba(0,0,0,.2)}
.conv-foto-placeholder{width:56px;height:56px;border-radius:50%;background:rgba(255,255,255,.2);border:3px solid rgba(255,255,255,.5);display:flex;align-items:center;justify-content:center}

@media(max-width:460px){
    #convite{width:100%;border-radius:16px}
    body{padding:12px}
}
@media print{
    body{background:white;padding:0}
    .toolbar{display:none}
    #convite{box-shadow:none;border:1px solid #e2e8f0}
}
</style>
</head><body>

<div class="toolbar no-print">
    <button onclick="baixarImagem()" class="tb-btn tb-dl"><i class="fas fa-download"></i> Salvar Imagem</button>
    <button onclick="compartilharWpp()" class="tb-btn tb-wpp"><i class="fab fa-whatsapp"></i> Enviar WhatsApp</button>
    <button onclick="window.print()" class="tb-btn" style="background:rgba(255,255,255,.15);color:white"><i class="fas fa-print"></i> Imprimir</button>
    <?php if($isAdmin):?>
    <a href="participante_view.php?id=<?=$id?>" class="tb-btn tb-back"><i class="fas fa-arrow-left"></i> Voltar</a>
    <?php endif;?>
</div>

<!-- O CONVITE -->
<div id="convite">

    <!-- Banner do evento -->
    <div style="position:relative">
    <?php if(!empty($p['banner_base64'])): ?>
    <img src="<?=$p['banner_base64']?>" class="conv-banner" alt="Banner">
    <?php else: ?>
    <div class="conv-banner-placeholder">
        <span style="color:white;font-size:32px;font-weight:900;text-align:center;padding:20px;text-transform:uppercase;letter-spacing:2px;text-shadow:0 4px 20px rgba(0,0,0,.5)"><?=htmlspecialchars($p['ev_nome'])?></span>
    </div>
    <?php endif;?>
    </div><!-- /banner wrapper -->



    <!-- Logos sobrepostas no banner (canto superior esquerdo) -->
    <?php if(!empty($logos)): ?>
    <div style="position:absolute;top:12px;left:12px;display:flex;gap:8px;align-items:center;z-index:10;background:rgba(0,0,0,.45);padding:6px 10px;border-radius:10px;backdrop-filter:blur(4px)">
        <?php foreach($logos as $l): ?>
        <img src="<?=$l['src']?>" alt="<?=$l['alt']?>" style="height:28px;width:auto;max-width:60px;object-fit:contain;filter:drop-shadow(0 1px 3px rgba(0,0,0,.6))">
        <?php endforeach;?>
    </div>
    <?php endif;?>

    <!-- Corpo -->
    <div class="conv-body">
        <div class="conv-titulo"><?=htmlspecialchars($p['ev_nome'])?></div>

        <hr class="conv-divider">

        <!-- Nome + Foto -->
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px">
            <?php if($foto): ?>
            <img src="<?=$foto?>" class="conv-foto" alt="Foto">
            <?php else: ?>
            <div class="conv-foto-placeholder"><i class="fas fa-user" style="color:rgba(255,255,255,.6);font-size:22px"></i></div>
            <?php endif;?>
            <div>
                <div class="conv-nome-label">Participante</div>
                <div class="conv-nome"><?=htmlspecialchars($p['nome'])?></div>
            </div>
        </div>

        <!-- Meta: apenas local (sem data) -->
        <?php if($p['local']): ?>
        <div class="conv-meta">
            <div class="conv-meta-item"><i class="fas fa-map-marker-alt"></i> <?=htmlspecialchars($p['local'])?></div>
        </div>
        <?php endif;?>

        <!-- QR Code -->
        <div class="conv-qr-area">
            <img src="<?=$qrUrl?>" class="conv-qr-img" id="qrMainImg" alt="QR Code">
            <div class="conv-qr-info">
                <div class="conv-qr-title">QR Code de Entrada</div>
                <div class="conv-qr-sub">Apresente este código na entrada do evento para confirmar sua presença.</div>
                <div class="conv-qr-badge"><i class="fas fa-check-circle"></i> Aprovado</div>
            </div>
        </div>

    </div>

    <!-- Rodapé -->
    <div class="conv-footer">
        <i class="fas fa-shield-halved" style="color:rgba(255,255,255,.6);font-size:14px"></i>
        <span class="conv-footer-text">ASSEGO Eventos</span>
    </div>

</div><!-- /convite -->

<script>
async function baixarImagem(){
    const btn=document.querySelector('.tb-dl');
    const orig=btn.innerHTML; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Gerando...'; btn.disabled=true;
    try{
        const canvas=await html2canvas(document.getElementById('convite'),{scale:2,useCORS:true,allowTaint:true,backgroundColor:'#ffffff',logging:false});
        const a=document.createElement('a');
        a.href=canvas.toDataURL('image/png');
        a.download='convite-<?=urlencode(substr(preg_replace('/[^a-zA-Z0-9]/','-',$p['nome']),0,30))?>.png';
        a.click();
    }catch(e){alert('Erro ao gerar imagem. Tente usar Ctrl+Print Screen para capturar.');}
    btn.innerHTML=orig; btn.disabled=false;
}

async function compartilharWpp(){
    try{
        const canvas=await html2canvas(document.getElementById('convite'),{scale:2,useCORS:true,allowTaint:true,backgroundColor:'#ffffff',logging:false});
        canvas.toBlob(async blob=>{
            if(navigator.share && navigator.canShare && navigator.canShare({files:[new File([blob],'convite.png',{type:'image/png'})]})){
                await navigator.share({files:[new File([blob],'convite.png',{type:'image/png'})],title:'Convite <?=addslashes($p['ev_nome'])?>',text:'Seu convite para <?=addslashes($p['ev_nome'])?>'});
            } else {
                // Fallback: abrir WhatsApp com link do ingresso
                const link=encodeURIComponent('Seu convite para *<?=addslashes($p['ev_nome'])?>*\n\nNome: <?=addslashes($p['nome'])?>\n\nSeu ingresso e QR Code: <?=$baseUrl?>meu_ingresso.php?id=<?=$id?>&tk=<?=urlencode($token)?>');
                window.open('https://wa.me/?text='+link,'_blank');
            }
        },'image/png');
    }catch(e){
        const link=encodeURIComponent('Seu convite para *<?=addslashes($p['ev_nome'])?>*\n\nNome: <?=addslashes($p['nome'])?>\n\nSeu ingresso: <?=$baseUrl?>meu_ingresso.php?id=<?=$id?>&tk=<?=urlencode($token)?>');
        window.open('https://wa.me/?text='+link,'_blank');
    }
}

</script>
</body></html>