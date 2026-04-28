<?php
if(session_status()===PHP_SESSION_NONE) session_start();
require_once 'config.php';
checkLogin();
$pdo = getConnection();

// ── Revalidação automática de permissões ─────────────────────────────────────
// Compara o updated_at do usuário no banco com o guardado na sessão.
// Se o admin editou o usuário (mudou eventos, role, ativo), a sessão é
// atualizada imediatamente — sem precisar fazer logout.
try {
    $uid_check = getUserId();
    $stmt_check = $pdo->prepare("SELECT updated_at, ativo FROM usuarios WHERE id=?");
    $stmt_check->execute([$uid_check]);
    $userRow = $stmt_check->fetch();

    // Se o usuário foi desativado, força logout imediato
    if($userRow && !$userRow['ativo']) {
        session_destroy();
        header('Location: login.php?msg=conta_inativa');
        exit();
    }

    $dbUpdated = $userRow['updated_at'] ?? '';
    if($dbUpdated !== ($_SESSION['user_db_updated'] ?? '')) {
        // Permissões mudaram — guarda novo timestamp na sessão
        $_SESSION['user_db_updated'] = $dbUpdated;
        // Força re-seleção do evento na próxima verificação abaixo
        $_SESSION['force_event_recheck'] = true;
    }
} catch(Exception $e) {}

$evento_atual = getEventoAtual($pdo);
$eventos_permitidos = getEventosPermitidos($pdo);

// ── Revalidar evento atual contra lista de permitidos ─────────────────────────
if(!isAdmin()) {
    $ids_permitidos = array_column($eventos_permitidos, 'id');

    // Evento atual não está mais na lista de permitidos → trocar
    if($evento_atual && !in_array($evento_atual['id'], $ids_permitidos)) {
        if(!empty($eventos_permitidos)) {
            setEventoAtual($eventos_permitidos[0]['id']);
            $evento_atual = $eventos_permitidos[0];
        } else {
            unset($_SESSION['evento_atual']);
            $evento_atual = null;
        }
    }

    // Usuário não tinha evento mas agora tem → auto-selecionar
    if(!$evento_atual && !empty($eventos_permitidos)) {
        setEventoAtual($eventos_permitidos[0]['id']);
        $evento_atual = $eventos_permitidos[0];
    }
}

// Troca de evento via GET
if(isset($_GET['set_evento'])){
    $eid=(int)$_GET['set_evento'];
    if(temAcessoEvento($pdo,$eid)){
        setEventoAtual($eid);
        $evento_atual=getEventoAtual($pdo);
    }
    header("Location: index.php");
    exit();
}

if(!$evento_atual && !empty($eventos_permitidos)){
    setEventoAtual($eventos_permitidos[0]['id']);
    $evento_atual=$eventos_permitidos[0];
}

$nav_pendentes=0;
if($evento_atual){
    try{
        $s=$pdo->prepare("SELECT COUNT(*) FROM participantes WHERE aprovado=0 AND evento_id=?");
        $s->execute([$evento_atual['id']]);
        $nav_pendentes=(int)$s->fetchColumn();
    }catch(Exception $e){}
}
$cor_tema='#1e40af'; // Cor fixa do admin — NÃO usar cor_tema do evento aqui

function renderHeader($pagina_ativa='index'){
    global $evento_atual,$eventos_permitidos,$nav_pendentes;
    $userName=getUserName();$isAdmin=isAdmin();
?>
<header class="top-header">
<div class="header-content">
<div class="logo-section">
<img src="assets/img/logo_assego.png" alt="ASSEGO" style="height:48px;width:48px;object-fit:contain;">
<h1>ASSEGO</h1>
<span class="logo-badge"><?=$evento_atual?htmlspecialchars($evento_atual['nome']):'Eventos'?></span>
<span id="rtIndicator" style="width:8px;height:8px;border-radius:50%;background:#10b981;display:inline-block;margin-left:4px;animation:rtPulse 2s infinite" title="Tempo real ativo"></span>
</div>
<div class="user-menu">
<?php if(count($eventos_permitidos)>1):?>
<div class="dropdown" style="position:relative">
<button class="btn-ev" onclick="document.getElementById('evDD').classList.toggle('show')"><i class="fas fa-calendar-alt"></i><span class="d-none-m"><?=$evento_atual?htmlspecialchars(mb_strimwidth($evento_atual['nome'],0,20,'...')):'Selecionar'?></span><i class="fas fa-chevron-down" style="font-size:10px"></i></button>
<div class="ev-dd" id="evDD"><?php foreach($eventos_permitidos as $ev):?><a href="?set_evento=<?=$ev['id']?>" class="ev-opt <?=($evento_atual&&$evento_atual['id']==$ev['id'])?'active':''?>"><?=htmlspecialchars($ev['nome'])?><?php if(!$ev['ativo']):?> <span style="font-size:10px;color:#dc2626">(fechado)</span><?php endif;?></a><?php endforeach;?></div>
</div>
<?php endif;?>
<div class="user-info"><div class="user-avatar"><i class="fas fa-user"></i></div><span class="user-name d-none-m"><?=htmlspecialchars($userName)?></span><?php if($isAdmin):?><span class="admin-tag">ADMIN</span><?php endif;?></div>
<a href="logout.php" class="btn btn-danger btn-icon" title="Sair"><i class="fas fa-sign-out-alt"></i></a>
</div></div></header>
<nav class="nav-menu"><div class="menu-items">
<a href="index.php" class="menu-item <?=$pagina_ativa=='index'?'active':''?>"><i class="fas fa-home"></i><span>Início</span></a>
<a href="participantes.php" class="menu-item <?=$pagina_ativa=='participantes'?'active':''?>"><i class="fas fa-users"></i><span>Participantes</span></a>
<?php if($isAdmin):?><a href="eventos.php" class="menu-item <?=$pagina_ativa=='eventos'?'active':''?>"><i class="fas fa-calendar-alt"></i><span>Eventos</span></a><?php endif;?>
<a href="relatorios.php" class="menu-item <?=$pagina_ativa=='relatorios'?'active':''?>"><i class="fas fa-chart-bar"></i><span>Relatórios</span></a>
<a href="solicitacoes.php" class="menu-item <?=$pagina_ativa=='solicitacoes'?'active':''?>" style="position:relative"><i class="fas fa-user-clock"></i><span>Pendentes</span>
<?php if($nav_pendentes>0):?><span class="nav-badge rt-badge"><?=$nav_pendentes?></span><?php endif;?></a>
<a href="presencas.php" class="menu-item <?=$pagina_ativa=='presencas'?'active':''?>"><i class="fas fa-qrcode"></i><span>Presenças</span></a>
<?php if($isAdmin):?><a href="usuarios.php" class="menu-item <?=$pagina_ativa=='usuarios'?'active':''?>"><i class="fas fa-user-shield"></i><span>Usuários</span></a>
<a href="auditoria.php" class="menu-item <?=$pagina_ativa=='auditoria'?'active':''?>"><i class="fas fa-shield-alt"></i><span>Auditoria</span></a>
<?php endif;?>
</div></nav>
<?php }

function renderCSS(){?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<style>
:root{--primary:#1e40af;--primary-light:#3b82f6;--secondary:#2563eb;--success:#059669;--danger:#dc2626;--warning:#d97706;--dark:#0f172a;--gray:#64748b;--gradient:linear-gradient(135deg,var(--primary),var(--primary-light));--shadow-md:0 4px 6px -1px rgba(0,0,0,.1);--shadow-lg:0 10px 15px -3px rgba(0,0,0,.1);--shadow-xl:0 20px 25px -5px rgba(0,0,0,.1)}
*{margin:0;padding:0;box-sizing:border-box}body{font-family:'Inter',sans-serif;background:#f0f9ff;color:var(--dark);font-size:14px;line-height:1.6}
.top-header{background:linear-gradient(135deg,#1e3a8a,var(--primary-light),var(--secondary));padding:12px 0;box-shadow:var(--shadow-lg);position:sticky;top:0;z-index:1000}
.header-content{display:flex;justify-content:space-between;align-items:center;padding:0 24px}
.logo-section{display:flex;align-items:center;gap:12px}.logo-section h1{color:white;font-size:22px;font-weight:700;margin:0}
.logo-badge{background:rgba(255,255,255,.2);color:white;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:500}
.user-menu{display:flex;align-items:center;gap:12px}
.user-info{display:flex;align-items:center;gap:10px;padding:6px 14px;background:rgba(255,255,255,.2);border-radius:12px}
.user-avatar{width:32px;height:32px;border-radius:8px;background:white;display:flex;align-items:center;justify-content:center;color:var(--secondary)}
.user-name{color:white;font-weight:600;font-size:13px}.admin-tag{background:rgba(245,158,11,.3);color:#fbbf24;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700}
.btn-ev{display:flex;align-items:center;gap:8px;padding:8px 14px;background:rgba(245,158,11,.3);border:1px solid rgba(245,158,11,.5);color:#fef3c7;border-radius:10px;cursor:pointer;font-size:13px;font-weight:600}
.ev-dd{display:none;position:absolute;top:100%;right:0;margin-top:8px;min-width:250px;background:white;border-radius:12px;box-shadow:var(--shadow-xl);border:1px solid #dbeafe;overflow:hidden;z-index:1001}
.ev-dd.show{display:block}.ev-opt{display:block;padding:12px 16px;color:var(--dark);text-decoration:none;border-bottom:1px solid #f1f5f9;font-size:14px}.ev-opt:hover{background:#f0f9ff}.ev-opt.active{background:#dbeafe;color:var(--primary);font-weight:600}
.nav-menu{background:white;padding:14px 24px;box-shadow:var(--shadow-md);border-bottom:2px solid #dbeafe;position:sticky;top:60px;z-index:999}
.menu-items{display:flex;gap:8px;flex-wrap:wrap;justify-content:center}
.menu-item{display:flex;flex-direction:column;align-items:center;padding:10px 18px;border-radius:12px;text-decoration:none;color:var(--gray);transition:.3s;background:#f0f9ff;border:1px solid #e0f2fe;position:relative}
.menu-item:hover{color:var(--primary);transform:translateY(-2px);box-shadow:var(--shadow-lg);border-color:var(--primary-light);background:#dbeafe}
.menu-item.active{color:white;background:var(--gradient);box-shadow:var(--shadow-lg);border-color:transparent}
.menu-item i{font-size:18px;margin-bottom:3px}.menu-item span{font-size:11px;font-weight:500}
.nav-badge{position:absolute;top:-6px;right:-6px;background:#ef4444;color:white;border-radius:50%;width:20px;height:20px;font-size:11px;display:flex;align-items:center;justify-content:center;font-weight:700}
@keyframes rtPulse{0%,100%{opacity:1}50%{opacity:.3}}
.stats-container{padding:24px;display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px}
.stat-card{background:white;padding:24px;border-radius:16px;box-shadow:var(--shadow-md);border:1px solid #dbeafe;transition:.3s}.stat-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-xl)}
.stat-header{display:flex;justify-content:space-between;align-items:flex-start}
.stat-icon{width:48px;height:48px;background:var(--gradient);border-radius:12px;display:flex;align-items:center;justify-content:center;color:white;font-size:22px}
.stat-value{font-size:32px;font-weight:700;color:var(--dark);margin-bottom:6px;transition:all .3s}.stat-label{color:var(--gray);font-size:13px}
.content-container{padding:24px}
.form-card{background:white;padding:30px;border-radius:16px;box-shadow:var(--shadow-md);border:1px solid #dbeafe}
.page-header{background:white;padding:20px 24px;margin:0 24px 24px;border-radius:16px;box-shadow:var(--shadow-md);border:1px solid #dbeafe;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px}
.page-title{font-size:24px;font-weight:700;color:var(--primary);margin:0}
.form-label{font-size:13px;font-weight:600;color:#1e3a8a;text-transform:uppercase;letter-spacing:.5px}
.form-control,.form-select{padding:10px 16px;font-size:14px;border:2px solid #dbeafe;border-radius:10px;background:#f0f9ff;transition:.3s}
.form-control:focus,.form-select:focus{border-color:var(--secondary);background:white;box-shadow:0 0 0 3px rgba(37,99,235,.1);outline:none}
.table-container{background:white;margin:0 24px 24px;border-radius:16px;box-shadow:var(--shadow-md);overflow:hidden;border:1px solid #dbeafe}
.table-header{padding:20px 24px;background:#f0f9ff;border-bottom:1px solid #e0f2fe;display:flex;justify-content:space-between;align-items:center}
.table-title{font-size:18px;font-weight:600;color:var(--primary)}
.table{width:100%;margin:0}.table thead{background:#f0f9ff;border-bottom:2px solid #dbeafe}
.table thead th{padding:14px 16px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--gray)}
.table tbody tr{border-bottom:1px solid #e0f2fe;transition:.3s}.table tbody tr:hover{background:#dbeafe}
.table tbody td{padding:14px 16px;vertical-align:middle}
.btn{padding:10px 20px;font-size:14px;font-weight:600;border-radius:10px;transition:.3s;border:none;display:inline-flex;align-items:center;gap:8px;text-decoration:none;cursor:pointer}
.btn-primary{background:var(--gradient);color:white;box-shadow:var(--shadow-md)}.btn-primary:hover{transform:translateY(-2px);color:white}
.btn-success{background:linear-gradient(135deg,#059669,#10b981);color:white}.btn-success:hover{transform:translateY(-2px);color:white}
.btn-info{background:linear-gradient(135deg,#0284c7,#0ea5e9);color:white}.btn-info:hover{transform:translateY(-2px);color:white}
.btn-danger{background:linear-gradient(135deg,#dc2626,#ef4444);color:white}.btn-danger:hover{transform:translateY(-2px);color:white}
.btn-secondary{background:#e2e8f0;color:#475569}
.btn-sm{padding:6px 12px;font-size:12px}.btn-icon{width:40px;height:40px;padding:0;display:inline-flex;align-items:center;justify-content:center;border-radius:10px}
.action-buttons{display:flex;gap:6px}
.btn-action{width:32px;height:32px;display:inline-flex;align-items:center;justify-content:center;border-radius:8px;transition:.3s;border:none;cursor:pointer;font-size:14px;background:#e0f2fe;color:var(--primary);text-decoration:none}
.btn-action:hover{transform:translateY(-2px);box-shadow:var(--shadow-md)}
.btn-action.view:hover{background:var(--secondary);color:white}.btn-action.edit:hover{background:#0891b2;color:white}.btn-action.delete:hover{background:#dc2626;color:white}
.alert{padding:16px 20px;border-radius:12px;margin-bottom:20px;font-weight:500;display:flex;align-items:center;gap:12px}
.alert-success{background:#d1fae5;color:#065f46;border:1px solid #6ee7b7}.alert-danger{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5}
.badge{padding:6px 12px;border-radius:20px;font-size:12px;font-weight:600}.badge-info{background:linear-gradient(135deg,#3b82f6,#60a5fa);color:white}.badge-success{background:#dcfce7;color:#16a34a}
.pagination-container{padding:20px 24px;background:#f0f9ff;border-top:1px solid #dbeafe;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px}
.pagination{display:flex;gap:8px;margin:0;list-style:none}
.page-link{padding:8px 12px;border-radius:8px;background:white;color:var(--secondary);font-weight:600;font-size:14px;border:1px solid #dbeafe;text-decoration:none}
.page-link:hover{background:var(--secondary);color:white}.page-item.active .page-link{background:var(--primary);color:white}
.empty-state{padding:60px 20px;text-align:center}.empty-icon{font-size:48px;color:#dbeafe;margin-bottom:16px}.empty-text{color:var(--gray);margin-bottom:24px}
.no-evento-alert{margin:40px 24px;padding:40px;background:white;border-radius:16px;text-align:center;box-shadow:var(--shadow-md);border:2px dashed #dbeafe}
.rt-toast{position:fixed;bottom:24px;right:24px;background:linear-gradient(135deg,#1e40af,#3b82f6);color:#fff;padding:14px 22px;border-radius:14px;font-size:14px;font-weight:600;z-index:9999;box-shadow:0 8px 30px rgba(0,0,0,.25);animation:slideIn .4s ease;display:flex;align-items:center;gap:10px}
@keyframes slideIn{from{transform:translateX(120px);opacity:0}to{transform:translateX(0);opacity:1}}
@media(max-width:768px){
.header-content{padding:0 12px}.logo-section h1{font-size:18px}.logo-badge,.d-none-m{display:none!important}
.logo-section img{height:38px!important;width:38px!important}
.nav-menu{padding:10px 8px;top:52px}.menu-items{display:grid;grid-template-columns:repeat(3,1fr);gap:6px}
.menu-item{padding:10px 4px}.menu-item span{font-size:10px}.menu-item i{font-size:16px}
.stats-container{padding:12px;grid-template-columns:repeat(2,1fr);gap:10px}.stat-value{font-size:24px}
.table-container,.page-header{margin:0 12px 12px}.content-container{padding:12px}
.table th,.table td{font-size:12px;padding:8px 6px}.btn-ev span{display:none}
}
@media(max-width:400px){.stats-container{grid-template-columns:1fr}}
</style>
<?php }

function renderScripts(){global $evento_atual;?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('click',e=>{if(!e.target.closest('.dropdown'))document.getElementById('evDD')?.classList.remove('show');});
window.ASSEGO_RT=(function(){
    const EID=<?=$evento_atual?$evento_atual['id']:0?>;
    const INTERVAL=8000;
    let lastPendentes=-1,lastTotal=-1,callbacks=[];
    function toast(msg,icon=''){const t=document.createElement('div');t.className='rt-toast';t.innerHTML=`<span style="font-size:20px">${icon}</span> ${msg}`;document.body.appendChild(t);setTimeout(()=>{t.style.opacity='0';t.style.transition='all .5s';t.style.transform='translateX(50px)';setTimeout(()=>t.remove(),500)},4000);}
    function animateValue(el,newVal){if(!el)return;const cur=parseInt(el.textContent.replace(/\D/g,''))||0;if(cur===newVal)return;el.style.transform='scale(1.15)';el.style.color='#2563eb';setTimeout(()=>{el.textContent=newVal.toLocaleString('pt-BR');el.style.transform='scale(1)';el.style.color='';},200);}
    function updateBadges(count){document.querySelectorAll('.rt-badge').forEach(b=>{b.textContent=count;b.style.display=count>0?'flex':'none';});}
    async function poll(){
        if(!EID)return;
        try{
            const r=await fetch(`api_realtime.php?action=stats&evento_id=${EID}`,{cache:'no-store'});
            if(!r.ok)return;const d=await r.json();
            animateValue(document.getElementById('rt-total'),d.total);
            animateValue(document.getElementById('rt-novos'),d.novos);
            animateValue(document.getElementById('rt-pendentes'),d.pendentes);
            updateBadges(d.pendentes);
            if(lastPendentes>=0&&d.pendentes>lastPendentes)toast('Novo cadastro pendente!','📋');
            if(lastTotal>=0&&d.total>lastTotal)toast('Novo participante aprovado!','✅');
            lastPendentes=d.pendentes;lastTotal=d.total;
            callbacks.forEach(fn=>fn(d));
            const ind=document.getElementById('rtIndicator');if(ind)ind.style.background='#10b981';
        }catch(e){const ind=document.getElementById('rtIndicator');if(ind)ind.style.background='#ef4444';}
    }
    return{start:function(){if(EID){poll();setInterval(poll,INTERVAL);}},onUpdate:function(fn){callbacks.push(fn);},toast:toast,refresh:poll,getEventoId:function(){return EID;}};
})();
ASSEGO_RT.start();
setTimeout(()=>{document.querySelectorAll('.alert').forEach(a=>{a.style.transition='opacity .5s';a.style.opacity='0';setTimeout(()=>a.remove(),500)})},5000);
</script>
<?php }?>