<?php
require_once 'layout.php';
checkLogin();
$pdo = getConnection();
$evento_atual = getEventoAtual($pdo);
if(!$evento_atual){ header('Location: index.php'); exit(); }

function gerarToken($id, $tipo='titular', $idx=0){
    $secret=DB_PASS.DB_NAME;
    $data=$id.':'.$tipo.':'.$idx;
    return base64_encode($data.':'.substr(hash_hmac('sha256',$data,$secret),0,12));
}
$baseUrl=(isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on'?'https':'http').'://'.$_SERVER['HTTP_HOST'].rtrim(dirname($_SERVER['SCRIPT_NAME']),'/').'/';

$s = $pdo->prepare("SELECT p.id,p.nome,p.whatsapp,p.acompanhantes,p.campos_extras FROM participantes p WHERE p.evento_id=? AND p.aprovado=1 ORDER BY p.nome");
$s->execute([$evento_atual['id']]);
$todos = $s->fetchAll();

// Separar titulares e acompanhantes avulsos
$titulares = [];
$acompAvulsos = [];
foreach($todos as $p){
    $ce = json_decode($p['campos_extras']??'{}',true)?:[];
    if(($ce['_tipo']??'') === 'acompanhante_avulso'){
        $acompAvulsos[] = $p;
    } else {
        $titulares[] = $p;
    }
}
$aba = $_GET['aba'] ?? 'titulares';
$lista = $aba === 'acomps' ? $acompAvulsos : $titulares;
$total = count($lista);
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Convites — ASSEGO</title><?php renderCSS();?>
<style>
.conv-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px;margin-top:16px}
.conv-item{background:white;border-radius:14px;box-shadow:var(--shadow-md);border:1px solid #dbeafe;overflow:hidden;transition:.2s}
.conv-item:hover{box-shadow:var(--shadow-lg);transform:translateY(-2px)}
.conv-item-header{padding:12px 14px;display:flex;align-items:center;gap:10px;border-bottom:1px solid #f1f5f9}
.conv-num{width:30px;height:30px;border-radius:50%;background:var(--gradient);color:white;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0}
.conv-nome{font-weight:700;font-size:14px;color:#1e293b;line-height:1.2}
.conv-wpp{font-size:12px;color:#64748b;margin-top:2px;display:flex;align-items:center;gap:5px;flex-wrap:wrap}
.conv-body{padding:10px 14px;display:flex;gap:8px;flex-wrap:wrap}
.conv-btn{display:inline-flex;align-items:center;gap:5px;padding:7px 12px;border:none;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;text-decoration:none;transition:.2s;white-space:nowrap}
.conv-btn:hover{transform:translateY(-1px)}
.btn-conv{background:linear-gradient(135deg,#1e40af,#3b82f6);color:white}
.btn-wpp{background:linear-gradient(135deg,#25d366,#128c7e);color:white}
.btn-copy{background:#f1f5f9;color:#475569}
.tabs{display:flex;gap:0;border-radius:12px;overflow:hidden;border:2px solid #dbeafe;width:fit-content}
.tab{padding:10px 24px;font-size:13px;font-weight:700;cursor:pointer;border:none;background:white;color:var(--gray);transition:.2s;display:flex;align-items:center;gap:7px}
.tab.active{background:var(--gradient);color:white}
.tab-count{background:rgba(255,255,255,.3);padding:1px 7px;border-radius:10px;font-size:11px}
.tab:not(.active) .tab-count{background:#f1f5f9;color:var(--gray)}
.search-wrap{position:relative;flex:1;max-width:380px}
.search-wrap i{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#94a3b8}
.search-wrap input{width:100%;padding:9px 12px 9px 34px;border:2px solid #dbeafe;border-radius:10px;font-size:14px;outline:none;transition:.2s}
.search-wrap input:focus{border-color:#3b82f6}
.tag-acomp{background:#ede9fe;color:#7c3aed;padding:2px 8px;border-radius:8px;font-size:10px;font-weight:700}
</style>
</head><body>
<?php renderHeader('participantes');?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-envelope-open-text"></i> Convites</h1>
        <div style="font-size:13px;color:var(--gray);margin-top:3px"><i class="fas fa-calendar-alt"></i> <?=htmlspecialchars($evento_atual['nome'])?></div>
    </div>
    <div class="d-flex gap-2">
        <button onclick="baixarTodos()" id="btnBaixarTodos" class="btn btn-sm" style="background:linear-gradient(135deg,#059669,#10b981);color:white;border:none"><i class="fas fa-download"></i> Baixar Convites</button>
        <a href="participantes.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Voltar</a>
    </div>
</div>

<div class="content-container">

<!-- Abas -->
<div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:16px">
    <div class="tabs">
        <a href="?aba=titulares" class="tab <?=$aba==='titulares'?'active':''?>">
            <i class="fas fa-user"></i> Titulares
            <span class="tab-count"><?=count($titulares)?></span>
        </a>
        <a href="?aba=acomps" class="tab <?=$aba==='acomps'?'active':''?>">
            <i class="fas fa-user-friends"></i> Acompanhantes
            <span class="tab-count"><?=count($acompAvulsos)?></span>
        </a>
    </div>
    <div class="search-wrap">
        <i class="fas fa-search"></i>
        <input type="text" id="busca" placeholder="Buscar..." oninput="filtrar()">
    </div>
    <div style="font-size:13px;color:var(--gray)" id="contagem"><?=$total?> registros</div>
</div>

<!-- Grid -->
<div class="conv-grid" id="convGrid">
<?php foreach($lista as $i=>$p):
    $token = gerarToken($p['id']);
    $conviteUrl  = $baseUrl.'convite.php?id='.$p['id'].'&tk='.urlencode($token);
    $ingressoUrl = $baseUrl.'meu_ingresso.php?id='.$p['id'].'&tk='.urlencode($token);
    $wppNum = preg_replace('/[^0-9]/','', $p['whatsapp']??'');
    $wppMsg = urlencode("Olá *".($p['nome'])."*! 🎉\n\nSeu convite para *".($evento_atual['nome'])."* está pronto.\n\n📲 Seu ingresso com QR Code:\n".$ingressoUrl);
    $wppLink = $wppNum ? "https://wa.me/55{$wppNum}?text={$wppMsg}" : "https://wa.me/?text={$wppMsg}";
    $acomps = json_decode($p['acompanhantes']??'[]',true)?:[];
?>
<div class="conv-item" data-nome="<?=strtolower(htmlspecialchars($p['nome']))?>">
    <div class="conv-item-header">
        <div class="conv-num"><?=$i+1?></div>
        <div style="flex:1;min-width:0">
            <div class="conv-nome"><?=htmlspecialchars($p['nome'])?></div>
            <div class="conv-wpp">
                <?php if($wppNum): ?>
                <i class="fab fa-whatsapp" style="color:#25d366"></i><?=htmlspecialchars($p['whatsapp'])?>
                <?php else: ?>
                <span style="color:#ef4444;font-size:11px"><i class="fas fa-exclamation-triangle"></i> Sem WhatsApp</span>
                <?php endif;?>
                <?php if(count($acomps)>0):?>
                <span style="background:#fff7ed;border:1px solid #fed7aa;color:#d97706;padding:1px 7px;border-radius:8px;font-size:10px;font-weight:700">+<?=count($acomps)?> acomp.</span>
                <?php endif;?>
            </div>
        </div>
    </div>
    <div class="conv-body">
        <a href="<?=$conviteUrl?>" target="_blank" class="conv-btn btn-conv"><i class="fas fa-ticket"></i> Convite</a>
        <?php if($wppNum):?>
        <a href="<?=$wppLink?>" target="_blank" class="conv-btn btn-wpp"><i class="fab fa-whatsapp"></i> Enviar</a>
        <?php endif;?>
        <button onclick="copiar('<?=htmlspecialchars($conviteUrl,ENT_QUOTES)?>')" class="conv-btn btn-copy" title="Copiar link"><i class="fas fa-copy"></i></button>
    </div>
</div>
<?php endforeach;?>
</div>

<?php if($total===0):?>
<div style="text-align:center;padding:60px;color:var(--gray)">
    <i class="fas fa-users-slash" style="font-size:48px;color:#dbeafe;display:block;margin-bottom:16px"></i>
    <?=$aba==='acomps'?'Nenhum acompanhante avulso cadastrado. Use a página de Cadastro em Massa.':'Nenhum titular aprovado neste evento.'?>
    <?php if($aba==='acomps'):?>
    <br><a href="cadastro_massa.php" class="btn btn-primary" style="margin-top:16px"><i class="fas fa-users-gear"></i> Cadastro em Massa</a>
    <?php endif;?>
</div>
<?php endif;?>

</div>

<?php renderScripts();?>
<script>
function filtrar(){
    const q=document.getElementById('busca').value.toLowerCase();
    let vis=0;
    document.querySelectorAll('.conv-item').forEach(el=>{
        const ok=el.dataset.nome.includes(q);
        el.style.display=ok?'':'none';
        if(ok)vis++;
    });
    document.getElementById('contagem').textContent=vis+' registros';
}
function copiar(url){
    navigator.clipboard.writeText(url).then(()=>{
        Swal.fire({icon:'success',title:'Link copiado!',timer:1800,showConfirmButton:false});
    });
}

function baixarTodos(){
    // Coletar IDs e tokens dos cards visíveis
    const cards = [...document.querySelectorAll('.conv-item')].filter(c=>c.style.display!=='none');
    if(!cards.length){ Swal.fire({icon:'warning',title:'Nenhum registro',timer:1500,showConfirmButton:false}); return; }

    const ids = [];
    cards.forEach(card=>{
        const btn = card.querySelector('.btn-conv');
        if(!btn) return;
        try{
            const u = new URL(btn.href);
            ids.push(u.searchParams.get('id'));
        }catch(e){}
    });

    if(!ids.length) return;

    // Abrir página de impressão em massa com todos os convites
    const aba = '<?=$aba?>';
    window.open('convites_print.php?ids='+ids.join(',')+'&aba='+aba, '_blank');
}
</script>
</body></html>