<?php
require_once 'layout.php';
if(!$evento_atual){header('Location: index.php');exit();}
$eid=$evento_atual['id'];$ce=json_decode($evento_atual['campos_extras']??'[]',true)?:[];

// === MÉTRICAS BÁSICAS ===
$s=$pdo->prepare("SELECT COUNT(*) FROM participantes WHERE aprovado=1 AND evento_id=?");$s->execute([$eid]);$total=(int)$s->fetchColumn();
$s=$pdo->prepare("SELECT COUNT(*) FROM participantes WHERE aprovado=1 AND ativo=0 AND evento_id=?");$s->execute([$eid]);$inat=(int)$s->fetchColumn();
$s=$pdo->prepare("SELECT COUNT(*) FROM participantes WHERE aprovado=0 AND evento_id=?");$s->execute([$eid]);$pend=(int)$s->fetchColumn();
$ativos=$total-$inat;

// === MÉTRICAS DE ENGAJAMENTO ===
$s=$pdo->prepare("SELECT COUNT(DISTINCT f.participante_id) FROM fotos f INNER JOIN participantes p ON p.id=f.participante_id WHERE p.aprovado=1 AND p.evento_id=?");
$s->execute([$eid]);$com_foto=(int)$s->fetchColumn();
$sem_foto=$total-$com_foto;
$pct_foto=$total>0?round(($com_foto/$total)*100,1):0;

$s=$pdo->prepare("SELECT COUNT(*) FROM participantes WHERE aprovado=1 AND evento_id=? AND instagram IS NOT NULL AND instagram!=''");
$s->execute([$eid]);$com_insta=(int)$s->fetchColumn();
$sem_insta=$total-$com_insta;
$pct_insta=$total>0?round(($com_insta/$total)*100,1):0;

$s=$pdo->prepare("SELECT COUNT(*) FROM participantes WHERE aprovado=1 AND evento_id=? AND cidade IS NOT NULL AND cidade!='' AND estado IS NOT NULL AND estado!=''");
$s->execute([$eid]);$com_endereco=(int)$s->fetchColumn();
$sem_endereco=$total-$com_endereco;
$pct_endereco=$total>0?round(($com_endereco/$total)*100,1):0;

$s=$pdo->prepare("SELECT COUNT(*) FROM participantes WHERE aprovado=1 AND evento_id=? AND cep IS NOT NULL AND cep!=''");
$s->execute([$eid]);$com_cep=(int)$s->fetchColumn();
$pct_cep=$total>0?round(($com_cep/$total)*100,1):0;

// === LOCALIZAÇÃO ===
$cidades=$pdo->prepare("SELECT cidade,estado,COUNT(*) as t FROM participantes WHERE aprovado=1 AND evento_id=? AND cidade IS NOT NULL AND cidade!='' GROUP BY cidade,estado ORDER BY t DESC LIMIT 10");
$cidades->execute([$eid]);$cidades=$cidades->fetchAll();

$estados=$pdo->prepare("SELECT estado,COUNT(*) as t FROM participantes WHERE aprovado=1 AND evento_id=? AND estado IS NOT NULL AND estado!='' GROUP BY estado ORDER BY t DESC LIMIT 10");
$estados->execute([$eid]);$estados=$estados->fetchAll();

// === EXTRAS ===
$extras_stats=[];
foreach($ce as $c){
    if(($c['tipo']??'')=='select'&&!empty($c['opcoes'])){
        $s=$pdo->prepare("SELECT campos_extras FROM participantes WHERE aprovado=1 AND evento_id=?");$s->execute([$eid]);
        $counts=[];while($r=$s->fetch()){$vals=json_decode($r['campos_extras']??'{}',true)?:[];$v=$vals[$c['nome']]??'';if($v)$counts[$v]=($counts[$v]??0)+1;}
        $extras_stats[$c['label']]=$counts;
    }
}

// === POR MÊS ===
$mensal=$pdo->prepare("SELECT DATE_FORMAT(created_at,'%Y-%m') as mes,COUNT(*) as t FROM participantes WHERE aprovado=1 AND evento_id=? GROUP BY mes ORDER BY mes DESC LIMIT 6");
$mensal->execute([$eid]);$mensal=array_reverse($mensal->fetchAll());

// === POR DIA (últimos 7 dias) ===
$diario=$pdo->prepare("SELECT DATE(created_at) as dia,COUNT(*) as t FROM participantes WHERE aprovado=1 AND evento_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY) GROUP BY dia ORDER BY dia");
$diario->execute([$eid]);$diario=$diario->fetchAll();

// === ÚLTIMOS CADASTROS ===
$ultimos=$pdo->prepare("SELECT nome,cidade,estado,created_at FROM participantes WHERE aprovado=1 AND evento_id=? ORDER BY created_at DESC LIMIT 5");
$ultimos->execute([$eid]);$ultimos=$ultimos->fetchAll();
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Relatórios - ASSEGO</title><?php renderCSS();?>
<style>
.metric-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:24px}
.metric-box{background:white;border-radius:14px;padding:18px 14px;border:1px solid #e0f2fe;text-align:center;transition:.3s}
.metric-box:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(30,64,175,.12)}
.metric-number{font-size:30px;font-weight:800;line-height:1}
.metric-label{font-size:11px;color:var(--gray);margin-top:4px;font-weight:600;text-transform:uppercase;letter-spacing:.5px}
.metric-sub{font-size:11px;color:#94a3b8;margin-top:2px}
.detail-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(360px,1fr));gap:20px}
.card-section{background:white;border-radius:16px;padding:24px;border:1px solid #e0f2fe}
.card-title{color:var(--primary);margin-bottom:16px;font-size:16px;font-weight:700;display:flex;align-items:center;gap:8px}
.bar-row{margin-bottom:12px}
.bar-label{display:flex;justify-content:space-between;margin-bottom:4px;font-size:13px}
.bar-name{font-weight:600;color:#1e293b}
.bar-value{color:var(--gray)}
.bar-track{height:8px;background:#e0f2fe;border-radius:4px;overflow:hidden}
.bar-fill{height:100%;border-radius:4px;transition:width .6s ease}
.eng-row{display:flex;align-items:center;justify-content:space-between;padding:14px 0;border-bottom:1px solid #f0f9ff}
.eng-row:last-child{border:none}
.eng-left{display:flex;align-items:center;gap:12px}
.eng-icon{width:42px;height:42px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:18px;min-width:42px}
.eng-title{font-weight:600;font-size:14px;color:#1e293b}
.eng-detail{font-size:12px;color:#94a3b8}
.eng-right{text-align:right}
.eng-pct{font-size:22px;font-weight:800}
.eng-count{font-size:11px;color:#94a3b8}
.recent-item{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid #f0f9ff}
.recent-item:last-child{border:none}
.recent-avatar{width:36px;height:36px;border-radius:50%;background:#e0f2fe;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:14px;min-width:36px}
.recent-name{font-weight:600;font-size:13px;color:#1e293b}
.recent-sub{font-size:11px;color:#94a3b8}
@media(max-width:768px){.metric-grid{grid-template-columns:repeat(3,1fr)}.detail-grid{grid-template-columns:1fr}}
</style>
</head><body>
<?php renderHeader('relatorios');?>
<div class="page-header"><h1 class="page-title"><i class="fas fa-chart-bar"></i> Relatórios — <?=htmlspecialchars($evento_atual['nome'])?></h1>
<div class="d-flex gap-2"><a href="export.php?format=excel" class="btn btn-success"><i class="fas fa-file-excel"></i> Excel</a><a href="export.php?format=pdf" class="btn btn-danger"><i class="fas fa-file-pdf"></i> PDF</a></div></div>

<!-- Cards principais -->
<div class="stats-container">
<div class="stat-card"><div class="stat-header"><div><div class="stat-value"><?=$total?></div><div class="stat-label">Cadastrados</div></div><div class="stat-icon"><i class="fas fa-users"></i></div></div></div>
<div class="stat-card"><div class="stat-header"><div><div class="stat-value" style="color:#059669"><?=$ativos?></div><div class="stat-label">Ativos</div></div><div class="stat-icon" style="background:linear-gradient(135deg,#059669,#10b981)"><i class="fas fa-user-check"></i></div></div></div>
<div class="stat-card"><div class="stat-header"><div><div class="stat-value"><?=$inat?></div><div class="stat-label">Inativos</div></div><div class="stat-icon" style="background:linear-gradient(135deg,#dc2626,#ef4444)"><i class="fas fa-user-times"></i></div></div></div>
<div class="stat-card"><div class="stat-header"><div><div class="stat-value"><?=$pend?></div><div class="stat-label">Pendentes</div></div><div class="stat-icon" style="background:linear-gradient(135deg,#d97706,#f59e0b)"><i class="fas fa-user-clock"></i></div></div></div>
</div>

<div class="content-container" style="padding-top:0">

<!-- Engajamento -->
<div class="card-section" style="margin-bottom:20px">
<h3 class="card-title"><i class="fas fa-chart-pie" style="color:#7c3aed"></i> Engajamento dos Participantes</h3>

<div class="eng-row">
    <div class="eng-left">
        <div class="eng-icon" style="background:#dbeafe;color:#1e40af"><i class="fas fa-camera"></i></div>
        <div><div class="eng-title">Foto Enviada</div><div class="eng-detail"><?=$com_foto?> enviaram · <?=$sem_foto?> sem foto</div></div>
    </div>
    <div class="eng-right">
        <div class="eng-pct" style="color:<?=$pct_foto>=50?'#059669':'#dc2626'?>"><?=$pct_foto?>%</div>
        <div class="eng-count"><?=$com_foto?>/<?=$total?></div>
    </div>
</div>

<div class="eng-row">
    <div class="eng-left">
        <div class="eng-icon" style="background:#fce7f3;color:#db2777"><i class="fab fa-instagram"></i></div>
        <div><div class="eng-title">Instagram Informado</div><div class="eng-detail"><?=$com_insta?> informaram · <?=$sem_insta?> sem Instagram</div></div>
    </div>
    <div class="eng-right">
        <div class="eng-pct" style="color:<?=$pct_insta>=50?'#059669':'#dc2626'?>"><?=$pct_insta?>%</div>
        <div class="eng-count"><?=$com_insta?>/<?=$total?></div>
    </div>
</div>

<div class="eng-row">
    <div class="eng-left">
        <div class="eng-icon" style="background:#d1fae5;color:#059669"><i class="fas fa-map-marker-alt"></i></div>
        <div><div class="eng-title">Endereço Completo</div><div class="eng-detail"><?=$com_endereco?> com cidade/estado · <?=$sem_endereco?> incompletos</div></div>
    </div>
    <div class="eng-right">
        <div class="eng-pct" style="color:<?=$pct_endereco>=50?'#059669':'#dc2626'?>"><?=$pct_endereco?>%</div>
        <div class="eng-count"><?=$com_endereco?>/<?=$total?></div>
    </div>
</div>

<div class="eng-row">
    <div class="eng-left">
        <div class="eng-icon" style="background:#fef3c7;color:#d97706"><i class="fas fa-envelope"></i></div>
        <div><div class="eng-title">CEP Preenchido</div><div class="eng-detail"><?=$com_cep?> preencheram · <?=$total-$com_cep?> sem CEP</div></div>
    </div>
    <div class="eng-right">
        <div class="eng-pct" style="color:<?=$pct_cep>=50?'#059669':'#dc2626'?>"><?=$pct_cep?>%</div>
        <div class="eng-count"><?=$com_cep?>/<?=$total?></div>
    </div>
</div>
</div>

<!-- Números rápidos -->
<div class="metric-grid">
    <div class="metric-box"><div class="metric-number" style="color:#1e40af"><?=$com_foto?></div><div class="metric-label">Com Foto</div><div class="metric-sub"><?=$pct_foto?>%</div></div>
    <div class="metric-box"><div class="metric-number" style="color:#db2777"><?=$com_insta?></div><div class="metric-label">Com Instagram</div><div class="metric-sub"><?=$pct_insta?>%</div></div>
    <div class="metric-box"><div class="metric-number" style="color:#059669"><?=$com_endereco?></div><div class="metric-label">Com Endereço</div><div class="metric-sub"><?=$pct_endereco?>%</div></div>
    <div class="metric-box"><div class="metric-number" style="color:#d97706"><?=count($cidades)?></div><div class="metric-label">Cidades</div><div class="metric-sub">diferentes</div></div>
    <div class="metric-box"><div class="metric-number" style="color:#7c3aed"><?=count($estados)?></div><div class="metric-label">Estados</div><div class="metric-sub">representados</div></div>
    <div class="metric-box"><div class="metric-number" style="color:#0891b2"><?=!empty($diario)?array_sum(array_column($diario,'t')):0?></div><div class="metric-label">Novos (7 dias)</div><div class="metric-sub">cadastros</div></div>
</div>

<!-- Gráficos detalhados -->
<div class="detail-grid">

<?php if(!empty($cidades)):?>
<div class="card-section"><h3 class="card-title"><i class="fas fa-map-marker-alt" style="color:#059669"></i> Top 10 Cidades</h3>
<?php foreach($cidades as $c):$pct=$total>0?round(($c['t']/$total)*100,1):0;?>
<div class="bar-row"><div class="bar-label"><span class="bar-name"><?=htmlspecialchars($c['cidade'])?><?=$c['estado']?' - '.$c['estado']:''?></span><span class="bar-value"><?=$c['t']?> (<?=$pct?>%)</span></div>
<div class="bar-track"><div class="bar-fill" style="background:linear-gradient(135deg,#059669,#10b981);width:<?=$pct?>%"></div></div></div>
<?php endforeach;?></div>
<?php endif;?>

<?php if(!empty($estados)):?>
<div class="card-section"><h3 class="card-title"><i class="fas fa-flag" style="color:#0891b2"></i> Por Estado</h3>
<?php foreach($estados as $e):$pct=$total>0?round(($e['t']/$total)*100,1):0;?>
<div class="bar-row"><div class="bar-label"><span class="bar-name"><?=htmlspecialchars($e['estado'])?></span><span class="bar-value"><?=$e['t']?> (<?=$pct?>%)</span></div>
<div class="bar-track"><div class="bar-fill" style="background:linear-gradient(135deg,#0891b2,#22d3ee);width:<?=$pct?>%"></div></div></div>
<?php endforeach;?></div>
<?php endif;?>

<?php if(!empty($mensal)):?>
<div class="card-section"><h3 class="card-title"><i class="fas fa-chart-line" style="color:#1e40af"></i> Cadastros por Mês</h3>
<?php $maxm=max(array_column($mensal,'t'));foreach($mensal as $m):$pct=$maxm>0?round(($m['t']/$maxm)*100):0;?>
<div class="bar-row"><div class="bar-label"><span class="bar-name"><?=date('M/Y',strtotime($m['mes'].'-01'))?></span><span class="bar-value"><?=$m['t']?></span></div>
<div class="bar-track"><div class="bar-fill" style="background:var(--gradient);width:<?=$pct?>%"></div></div></div>
<?php endforeach;?></div>
<?php endif;?>

<?php if(!empty($diario)):?>
<div class="card-section"><h3 class="card-title"><i class="fas fa-calendar-day" style="color:#d97706"></i> Últimos 7 Dias</h3>
<?php $maxd=max(array_column($diario,'t'));$dow=['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];foreach($diario as $d):$pct=$maxd>0?round(($d['t']/$maxd)*100):0;$dw=$dow[date('w',strtotime($d['dia']))];?>
<div class="bar-row"><div class="bar-label"><span class="bar-name"><?=$dw?> <?=date('d/m',strtotime($d['dia']))?></span><span class="bar-value"><?=$d['t']?> novos</span></div>
<div class="bar-track"><div class="bar-fill" style="background:linear-gradient(135deg,#d97706,#f59e0b);width:<?=$pct?>%"></div></div></div>
<?php endforeach;?></div>
<?php endif;?>

<?php foreach($extras_stats as $label=>$counts):?>
<div class="card-section"><h3 class="card-title"><i class="fas fa-chart-pie" style="color:#7c3aed"></i> Por <?=htmlspecialchars($label)?></h3>
<?php arsort($counts);foreach($counts as $opt=>$cnt):$pct=$total>0?round(($cnt/$total)*100,1):0;?>
<div class="bar-row"><div class="bar-label"><span class="bar-name"><?=htmlspecialchars($opt)?></span><span class="bar-value"><?=$cnt?> (<?=$pct?>%)</span></div>
<div class="bar-track"><div class="bar-fill" style="background:linear-gradient(135deg,#7c3aed,#a78bfa);width:<?=$pct?>%"></div></div></div>
<?php endforeach;?></div>
<?php endforeach;?>

<?php if(!empty($ultimos)):?>
<div class="card-section"><h3 class="card-title"><i class="fas fa-clock" style="color:#6366f1"></i> Últimos Cadastros</h3>
<?php foreach($ultimos as $u):?>
<div class="recent-item">
    <div class="recent-avatar"><i class="fas fa-user"></i></div>
    <div style="flex:1;min-width:0">
        <div class="recent-name"><?=htmlspecialchars($u['nome'])?></div>
        <div class="recent-sub"><?=htmlspecialchars($u['cidade']??'')?><?=$u['estado']?' - '.$u['estado']:''?> · <?=date('d/m H:i',strtotime($u['created_at']))?></div>
    </div>
</div>
<?php endforeach;?></div>
<?php endif;?>

</div></div>
<?php renderScripts();?></body></html>
