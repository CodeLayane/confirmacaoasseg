<?php
session_start();
require_once 'config.php';

// PIN dinâmico baseado na data do evento (formato DDMM)
$pdo = getConnection();
$evento_id = (int)($_GET['evento'] ?? $_POST['evento_id_keep'] ?? $_SESSION['scanner_evento_id'] ?? 0);
$evento_scanner = null;
$pin_evento = '1234'; // fallback

if($evento_id){
    $_SESSION['scanner_evento_id'] = $evento_id;
    // Reset auth so PIN is re-checked for this event
    if(isset($_SESSION['scanner_last_evento']) && $_SESSION['scanner_last_evento'] !== $evento_id){
        unset($_SESSION['scanner_auth']);
    }
    $_SESSION['scanner_last_evento'] = $evento_id;
    $se = $pdo->prepare("SELECT id,nome,data_inicio FROM eventos WHERE id=?");
    $se->execute([$evento_id]);
    $evento_scanner = $se->fetch();
    if($evento_scanner && !empty($evento_scanner['data_inicio'])){
        // PIN = DDMM da data do evento
        $pin_evento = date('md', strtotime($evento_scanner['data_inicio']));
    }
} elseif(!empty($_SESSION['scanner_evento_id'])){
    $se = $pdo->prepare("SELECT id,nome,data_inicio FROM eventos WHERE id=?");
    $se->execute([$_SESSION['scanner_evento_id']]);
    $evento_scanner = $se->fetch();
    if($evento_scanner && !empty($evento_scanner['data_inicio'])){
        $pin_evento = date('md', strtotime($evento_scanner['data_inicio']));
    }
}

$authed = ($_SESSION['scanner_auth'] ?? false);

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['pin'])){
    if($_POST['pin']===$pin_evento){ 
        $_SESSION['scanner_auth']=true;
        $_SESSION['scanner_pin_used']=$pin_evento;
        $authed=true;
    }
    else { $pin_error=true; }
}
if(isset($_GET['logout'])){ unset($_SESSION['scanner_auth']); header('Location: scanner.php'); exit(); }
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
<title>Scanner — ASSEGO</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
<style>
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent}
body{font-family:'Inter',sans-serif;background:#0f172a;height:100vh;overflow:hidden;color:white}

/* ── PIN ── */
.pin-screen{display:flex;flex-direction:column;align-items:center;justify-content:center;height:100vh;padding:20px;background:linear-gradient(135deg,#0f172a,#1e293b)}
.pin-card{background:#1e293b;border-radius:24px;padding:40px 32px;width:100%;max-width:360px;text-align:center;border:1px solid #334155}
.pin-icon{width:72px;height:72px;background:linear-gradient(135deg,#1e40af,#3b82f6);border-radius:20px;display:flex;align-items:center;justify-content:center;font-size:32px;margin:0 auto 20px;box-shadow:0 8px 24px rgba(30,64,175,.4)}
.pin-title{font-size:24px;font-weight:800;margin-bottom:6px}
.pin-sub{font-size:13px;color:#64748b;margin-bottom:28px}
.pin-input{width:100%;padding:16px;background:#0f172a;border:2px solid #334155;border-radius:14px;color:white;font-size:28px;text-align:center;letter-spacing:10px;font-weight:800;outline:none;margin-bottom:14px;transition:.3s}
.pin-input:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.2)}
.pin-btn{width:100%;padding:16px;background:linear-gradient(135deg,#1e40af,#3b82f6);color:white;border:none;border-radius:14px;font-size:16px;font-weight:700;cursor:pointer;box-shadow:0 8px 24px rgba(30,64,175,.4)}
.pin-error{background:rgba(220,38,38,.15);border:1px solid rgba(220,38,38,.4);color:#fca5a5;padding:10px;border-radius:10px;font-size:13px;margin-bottom:14px;display:flex;align-items:center;gap:8px;justify-content:center}

/* ── SCANNER LAYOUT ── */
.scanner-wrap{display:flex;height:100vh;overflow:hidden}

/* Coluna esquerda: câmera */
.cam-col{flex:0 0 auto;width:340px;display:flex;flex-direction:column;background:#0f172a;border-right:1px solid #1e293b}
@media(max-width:700px){
    body{overflow-y:auto}
    .scanner-wrap{flex-direction:column;height:auto;min-height:100vh;overflow:visible}
    .cam-col{width:100%;flex:0 0 auto;height:auto}
    .list-col{flex:1;min-height:300px;overflow:visible}
    .list-scroll{max-height:none;overflow:visible}
}

.cam-header{padding:12px 16px;background:#1e293b;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #334155;flex-shrink:0}
.cam-title{font-size:14px;font-weight:700;display:flex;align-items:center;gap:8px}
.cam-logout{background:rgba(255,255,255,.08);border:none;color:#64748b;padding:5px 10px;border-radius:8px;cursor:pointer;font-size:12px;font-weight:600}

.counters{display:flex;gap:8px;padding:10px 12px;background:#0f172a;flex-shrink:0}
.cnt{flex:1;background:#1e293b;border-radius:10px;padding:8px;text-align:center}
.cnt-val{font-size:20px;font-weight:800;line-height:1}
.cnt-lbl{font-size:9px;color:#64748b;margin-top:2px;font-weight:600;text-transform:uppercase}

.cam-body{flex:1;padding:10px 12px;display:flex;flex-direction:column;gap:10px;overflow:hidden}

#qr-reader{border-radius:14px;overflow:hidden;border:2px solid #334155;background:#000;min-height:280px}
#qr-reader video{border-radius:12px}

.op-row{flex-shrink:0}
.op-input{width:100%;padding:9px 12px;background:#1e293b;border:1px solid #334155;border-radius:10px;color:white;font-size:13px;outline:none}

/* Resultado flash */
.result-flash{border-radius:14px;padding:14px;text-align:center;animation:slideDown .3s ease;flex-shrink:0}
@keyframes slideDown{from{transform:translateY(-10px);opacity:0}to{transform:translateY(0);opacity:1}}
.flash-ok{background:linear-gradient(135deg,#065f46,#059669);border:2px solid #10b981}
.flash-ja{background:linear-gradient(135deg,#78350f,#d97706);border:2px solid #f59e0b}
.flash-err{background:linear-gradient(135deg,#7f1d1d,#dc2626);border:2px solid #ef4444}
.flash-icon{font-size:28px;display:block;margin-bottom:4px}
.flash-nome{font-size:15px;font-weight:800;line-height:1.2}
.flash-sub{font-size:11px;opacity:.8;margin-top:3px}
.btn-next{width:100%;padding:9px;background:rgba(255,255,255,.1);border:none;color:white;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;margin-top:8px}

/* Coluna direita: lista */
.list-col{flex:1;display:flex;flex-direction:column;overflow:hidden}
.list-header{padding:12px 16px;background:#1e293b;border-bottom:1px solid #334155;display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.list-title{font-size:14px;font-weight:700;display:flex;align-items:center;gap:8px}
.list-search{padding:8px 12px 10px;background:#0f172a;flex-shrink:0}
.list-search input{width:100%;padding:8px 12px;background:#1e293b;border:1px solid #334155;border-radius:10px;color:white;font-size:13px;outline:none}
.list-scroll{flex:1;overflow-y:auto;padding:8px 10px}
.list-item{background:#1e293b;border-radius:10px;padding:10px 12px;margin-bottom:6px;display:flex;align-items:center;gap:10px;animation:fadeIn .3s ease}
@keyframes fadeIn{from{opacity:0;transform:translateX(10px)}to{opacity:1;transform:translateX(0)}}
.li-avatar{width:36px;height:36px;border-radius:50%;object-fit:cover;flex-shrink:0;border:2px solid #334155}
.li-avatar-ph{width:36px;height:36px;border-radius:50%;background:#334155;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.li-info{flex:1;min-width:0}
.li-nome{font-size:13px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.li-sub{font-size:11px;color:#64748b;margin-top:1px}
.li-badge{font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px;flex-shrink:0}
.badge-ok{background:rgba(16,185,129,.2);color:#10b981;border:1px solid rgba(16,185,129,.3)}
.badge-acomp{background:rgba(245,158,11,.2);color:#f59e0b;border:1px solid rgba(245,158,11,.3)}
.empty-list{text-align:center;padding:40px 20px;color:#475569}
.empty-list i{font-size:36px;display:block;margin-bottom:8px;color:#334155}
</style>
</head><body>

<?php if(!$authed): ?>
<div class="pin-screen">
<div class="pin-card">
    <div class="pin-icon"><i class="fas fa-shield-halved"></i></div>
    <div class="pin-title">Scanner de Entrada</div>
    <div class="pin-sub">
        <?php if($evento_scanner): ?>
        <strong style="color:#3b82f6"><?=htmlspecialchars($evento_scanner['nome'])?></strong><br>
        <?php endif;?>
        Digite o PIN para acessar
    </div>
    <?php if(isset($pin_error)):?>
    <div class="pin-error"><i class="fas fa-times-circle"></i> PIN incorreto. Tente novamente.</div>
    <?php endif;?>
    <form method="POST"><input type="hidden" name="evento_id_keep" value="<?=$evento_id?>">
        <input type="password" name="pin" class="pin-input" placeholder="••••" autofocus maxlength="8" inputmode="numeric">
        <button type="submit" class="pin-btn"><i class="fas fa-unlock"></i> Entrar</button>
    </form>
</div>
</div>

<?php else: ?>

<div class="scanner-wrap">

    <!-- ── Câmera ── -->
    <div class="cam-col">
        <div class="cam-header">
            <div class="cam-title"><i class="fas fa-qrcode" style="color:#3b82f6"></i> <?=htmlspecialchars($evento_scanner['nome']??'Scanner ASSEGO')?></div>
            <button class="cam-logout" onclick="if(confirm('Sair?'))location='scanner.php?logout=1'"><i class="fas fa-sign-out-alt"></i> Sair</button>
        </div>

        <div class="counters">
            <div class="cnt"><div class="cnt-val" id="cntOk" style="color:#10b981">0</div><div class="cnt-lbl">Confirmados</div></div>
            <div class="cnt"><div class="cnt-val" id="cntJa" style="color:#f59e0b">0</div><div class="cnt-lbl">Repetidos</div></div>
            <div class="cnt"><div class="cnt-val" id="cntErr" style="color:#ef4444">0</div><div class="cnt-lbl">Inválidos</div></div>
        </div>

        <div class="cam-body">
            <div class="op-row">
                <input type="text" class="op-input" id="opName" placeholder="Operador..." value="Scanner Entrada">
            </div>

            <div id="qr-reader"></div>

            <div id="resultFlash" style="display:none" class="result-flash">
                <span class="flash-icon" id="fIcon"></span>
                <div class="flash-nome" id="fNome"></div>
                <div class="flash-sub" id="fSub"></div>
                <button class="btn-next" onclick="reiniciar()"><i class="fas fa-camera"></i> Próximo</button>
            </div>
        </div>
    </div>

    <!-- ── Lista ── -->
    <div class="list-col">
        <div class="list-header">
            <div class="list-title"><i class="fas fa-list-check" style="color:#10b981"></i> Presenças Confirmadas</div>
            <span id="listCount" style="font-size:12px;color:#64748b;font-weight:700">0</span>
        </div>
        <div class="list-search">
            <input type="text" id="listSearch" placeholder="Buscar confirmados..." oninput="filtrarLista()">
        </div>
        <div class="list-scroll" id="listScroll">
            <div class="empty-list" id="emptyMsg">
                <i class="fas fa-qrcode"></i>
                <div>Nenhuma presença confirmada ainda</div>
                <div style="font-size:11px;margin-top:4px">Escaneie os QR Codes para começar</div>
            </div>
        </div>
    </div>

</div>

<script>
let cntOk=0,cntJa=0,cntErr=0;
let scanning=true;
let html5QrCode;
let confirmados=[];

function tocar(tipo){
    try{
        const ctx=new(window.AudioContext||window.webkitAudioContext)();
        const o=ctx.createOscillator(),g=ctx.createGain();
        o.connect(g);g.connect(ctx.destination);
        if(tipo==='ok'){o.frequency.value=880;g.gain.value=0.3;o.start();setTimeout(()=>{o.stop();ctx.close();},150);}
        else if(tipo==='ja'){o.frequency.value=440;g.gain.value=0.2;o.start();setTimeout(()=>{o.frequency.value=400;},100);setTimeout(()=>{o.stop();ctx.close();},300);}
        else{o.frequency.value=220;g.gain.value=0.3;o.start();setTimeout(()=>{o.stop();ctx.close();},400);}
    }catch(e){}
}

function vibrar(tipo){
    if(!navigator.vibrate) return;
    if(tipo==='ok') navigator.vibrate(200);
    else if(tipo==='ja') navigator.vibrate([100,50,100]);
    else navigator.vibrate([200,100,200]);
}

function mostrarFlash(data){
    scanning=false;
    if(html5QrCode) html5QrCode.pause();

    const flash=document.getElementById('resultFlash');
    const icon=document.getElementById('fIcon');
    const nome=document.getElementById('fNome');
    const sub=document.getElementById('fSub');

    flash.style.display='block';
    flash.className='result-flash';
    document.getElementById('qr-reader').style.display='none';

    if(data.ok){
        flash.classList.add('flash-ok');
        icon.textContent='✅';
        nome.textContent=data.nome;
        sub.textContent=data.titular?'Acomp. de '+data.titular:data.presenca_em||'Presença confirmada!';
        cntOk++; document.getElementById('cntOk').textContent=cntOk;
        tocar('ok'); vibrar('ok');
        adicionarNaLista(data,'ok');
        // Auto-avançar após 2.5s
        setTimeout(reiniciar, 2500);
    } else if(data.status==='ja_confirmado'){
        flash.classList.add('flash-ja');
        icon.textContent='⚠️';
        nome.textContent=data.nome;
        sub.textContent='Presença já confirmada';
        cntJa++; document.getElementById('cntJa').textContent=cntJa;
        tocar('ja'); vibrar('ja');
        setTimeout(reiniciar, 2000);
    } else {
        flash.classList.add('flash-err');
        icon.textContent='❌';
        nome.textContent='QR Inválido';
        sub.textContent=data.msg||'Não reconhecido';
        cntErr++; document.getElementById('cntErr').textContent=cntErr;
        tocar('err'); vibrar('err');
        setTimeout(reiniciar, 2000);
    }
}

function adicionarNaLista(data, tipo){
    document.getElementById('emptyMsg').style.display='none';

    const hora=new Date().toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit'});
    const item=document.createElement('div');
    item.className='list-item';
    item.dataset.nome=(data.nome||'').toLowerCase();

    const isAcomp=!!data.titular;
    item.innerHTML=`
        <div class="li-avatar-ph"><i class="fas fa-${isAcomp?'user-friends':'user'}" style="color:#64748b;font-size:14px"></i></div>
        <div class="li-info">
            <div class="li-nome">${data.nome||''}</div>
            <div class="li-sub">${isAcomp?'Acomp. de '+data.titular+' · ':''}<i class="fas fa-clock" style="font-size:9px"></i> ${hora}</div>
        </div>
        <span class="li-badge ${isAcomp?'badge-acomp':'badge-ok'}">${isAcomp?'Acomp.':'✓'}</span>
    `;

    const scroll=document.getElementById('listScroll');
    scroll.insertBefore(item, scroll.firstChild);
    confirmados.unshift({nome:(data.nome||'').toLowerCase()});

    const cnt=confirmados.length;
    document.getElementById('listCount').textContent=cnt;
}

function filtrarLista(){
    const q=document.getElementById('listSearch').value.toLowerCase();
    document.querySelectorAll('.list-item').forEach(el=>{
        el.style.display=(!q||el.dataset.nome.includes(q))?'flex':'none';
    });
}

function reiniciar(){
    document.getElementById('resultFlash').style.display='none';
    document.getElementById('qr-reader').style.display='block';
    scanning=true;
    if(html5QrCode) html5QrCode.resume();
}


async function onQrScan(decoded){
    if(!scanning) return;
    scanning=false;

    let token='';
    try{ const u=new URL(decoded); token=u.searchParams.get('token')||''; }
    catch(e){ token=decoded; }

    if(!token){ mostrarFlash({ok:false,msg:'Não é um ingresso ASSEGO'}); return; }

    const op=document.getElementById('opName').value||'Scanner';
    try{
        const r=await fetch('api_presenca.php?action=confirmar&token='+encodeURIComponent(token)+'&op='+encodeURIComponent(op),{cache:'no-store'});
        mostrarFlash(await r.json());
    }catch(e){ mostrarFlash({ok:false,msg:'Erro de rede'}); }
}

// Iniciar scanner
html5QrCode = new Html5Qrcode('qr-reader', {verbose: false});

html5QrCode.start(
    {facingMode: 'environment'},
    {fps: 15, qrbox: {width: 220, height: 220}},
    onQrScan,
    () => {}
).catch(err => {
    console.warn('Camera error:', err);
    document.getElementById('qr-reader').innerHTML =
        '<div style="padding:40px;text-align:center;color:#94a3b8"><i class="fas fa-camera-slash" style="font-size:40px;display:block;margin-bottom:12px"></i><strong>Câmera bloqueada</strong><br><br>No iPhone: use o Safari.<br>No Android: permita câmera nas configurações do Chrome.</div>';
});
</script>
<?php endif;?>
</body></html>