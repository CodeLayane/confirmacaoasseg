<?php
require_once 'layout.php';
// Presenças acessível para admin e operador
$pdo = getConnection();

if(!$evento_atual){ ?>
<div class="no-evento-alert">
    <i class="fas fa-calendar-times" style="font-size:48px;color:#dbeafe;display:block;margin-bottom:16px"></i>
    <h3 style="color:var(--primary)">Nenhum evento selecionado</h3>
</div>
<?php } else {

// Verificar colunas
$cols = array_column($pdo->query("SHOW COLUMNS FROM participantes")->fetchAll(),'Field');
$hasPresenca = in_array('presenca_confirmada',$cols);

if(!$hasPresenca){ ?>
<div class="content-container">
<div class="alert alert-danger">
    <i class="fas fa-exclamation-triangle"></i> 
    Colunas de presença não encontradas. Execute a migração SQL primeiro:
    <code style="display:block;margin-top:8px;padding:8px;background:#fee2e2;border-radius:6px;font-size:12px">
    ALTER TABLE participantes ADD COLUMN presenca_confirmada tinyint(1) DEFAULT 0, ADD COLUMN presenca_em datetime DEFAULT NULL, ADD COLUMN presenca_por varchar(255) DEFAULT NULL, ADD COLUMN presenca_acomps json DEFAULT NULL;
    </code>
</div>
</div>
<?php } else {

$eid = $evento_atual['id'];

// Stats
$s = $pdo->prepare("SELECT COUNT(*) FROM participantes WHERE evento_id=? AND aprovado=1"); $s->execute([$eid]); $total_aprov = (int)$s->fetchColumn();
$s = $pdo->prepare("SELECT COUNT(*) FROM participantes WHERE evento_id=? AND aprovado=1 AND presenca_confirmada=1"); $s->execute([$eid]); $total_conf = (int)$s->fetchColumn();
$total_pend = $total_aprov - $total_conf;
$pct = $total_aprov > 0 ? round($total_conf/$total_aprov*100) : 0;

// Buscar confirmados
$busca = $_GET['busca'] ?? '';
$filtro = $_GET['filtro'] ?? 'todos';
$tipo  = $_GET['tipo'] ?? 'todos';
$where = "WHERE p.evento_id=? AND p.aprovado=1";
$params = [$eid];
if($filtro==='confirmados') { $where.=" AND p.presenca_confirmada=1"; }
if($filtro==='ausentes')    { $where.=" AND p.presenca_confirmada=0"; }
if($tipo==='titular')       { $where.=" AND campos_extras NOT LIKE '%acompanhante_avulso%'"; }
if($tipo==='acomp')         { $where.=" AND campos_extras LIKE '%acompanhante_avulso%'"; }
if($busca){ $where.=" AND p.nome LIKE ?"; $params[]="%$busca%"; }

// Count por tipo
$cTitular=(int)$pdo->query("SELECT COUNT(*) FROM participantes WHERE evento_id=$eid AND aprovado=1 AND campos_extras NOT LIKE '%acompanhante_avulso%'")->fetchColumn();
$cAcomp=(int)$pdo->query("SELECT COUNT(*) FROM participantes WHERE evento_id=$eid AND aprovado=1 AND campos_extras LIKE '%acompanhante_avulso%'")->fetchColumn();

$s = $pdo->prepare("SELECT p.id,p.nome,p.whatsapp,p.presenca_confirmada,p.presenca_em,p.presenca_por,p.presenca_acomps,p.acompanhantes,p.campos_extras,(SELECT 1 FROM fotos WHERE participante_id=p.id LIMIT 1) as has_foto FROM participantes p $where ORDER BY p.presenca_em DESC, p.nome ASC");
$s->execute($params);
$lista = $s->fetchAll();
?>

<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Presenças — ASSEGO</title><?php renderCSS();?>
<style>

.pres-table tr.confirmado{background:#f0fdf4}
.pres-table tr.ausente{background:#fff}
.badge-pres{background:#dcfce7;color:#166534;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:700;display:inline-flex;align-items:center;gap:5px}
.badge-aus{background:#fee2e2;color:#991b1b;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:700;display:inline-flex;align-items:center;gap:5px}
.scanner-embed{background:linear-gradient(135deg,#0f172a,#1e293b);border-radius:16px;padding:20px;color:white;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
.filter-tab{padding:7px 16px;border:2px solid #dbeafe;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;color:var(--gray);background:white;transition:.2s;display:inline-flex;align-items:center;gap:6px}
.filter-tab.active{background:var(--gradient);color:white;border-color:transparent}
</style>
</head><body>
<?php renderHeader('presencas');?>

<!-- Scanner Banner -->
<div class="content-container" style="padding-bottom:0">
<div class="scanner-embed">
    <div>
        <div style="font-size:18px;font-weight:800;margin-bottom:4px"><i class="fas fa-qrcode"></i> Scanner de Entrada</div>
        <div style="font-size:13px;opacity:.7">Abra no celular de quem ficará na entrada do evento</div>
        <?php
        $scanPin='????';
        if(!empty($evento_atual['data_inicio'])){
            $scanPin=date('md',strtotime($evento_atual['data_inicio']));
        }
        ?>
        <div style="margin-top:8px;font-size:12px;opacity:.6"><i class="fas fa-lock"></i> PIN: <strong style="letter-spacing:4px;font-size:16px;opacity:1"><?=$scanPin?></strong> (MMDD da data do evento)</div>
    </div>
    <a href="scanner.php?evento=<?=$eid?>" target="_blank" class="btn" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8);color:white;font-size:14px;padding:12px 24px;border-radius:12px;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:8px;flex-shrink:0">
        <i class="fas fa-qrcode"></i> Abrir Scanner
    </a>
</div>
</div>



<!-- Filtros e busca -->
<div style="background:white;padding:16px 24px;margin:0 24px 20px;border-radius:16px;box-shadow:var(--shadow-md);border:1px solid #dbeafe;display:flex;gap:12px;flex-wrap:wrap;align-items:center">
    <a href="?filtro=todos&tipo=<?=$tipo?>&busca=<?=urlencode($busca)?>" class="filter-tab <?=$filtro==='todos'?'active':''?>"><i class="fas fa-list"></i> Todos <span style="background:rgba(255,255,255,.3);padding:1px 7px;border-radius:8px;font-size:11px"><?=$total_aprov?></span></a>
    <a href="?filtro=confirmados&tipo=<?=$tipo?>&busca=<?=urlencode($busca)?>" class="filter-tab <?=$filtro==='confirmados'?'active':''?>" style="<?=$filtro==='confirmados'?'':'border-color:#bbf7d0;color:#059669'?>"><i class="fas fa-check-circle"></i> Confirmados <span style="background:rgba(255,255,255,.3);padding:1px 7px;border-radius:8px;font-size:11px"><?=$total_conf?></span></a>
    <a href="?filtro=ausentes&tipo=<?=$tipo?>&busca=<?=urlencode($busca)?>" class="filter-tab <?=$filtro==='ausentes'?'active':''?>" style="<?=$filtro==='ausentes'?'':'border-color:#fca5a5;color:#dc2626'?>"><i class="fas fa-clock"></i> Ausentes <span style="background:rgba(255,255,255,.3);padding:1px 7px;border-radius:8px;font-size:11px"><?=$total_pend?></span></a>
    <span style="color:#e2e8f0;margin:0 4px">|</span>
    <a href="?filtro=<?=$filtro?>&tipo=todos&busca=<?=urlencode($busca)?>" class="filter-tab <?=$tipo==='todos'?'active':''?>"><i class="fas fa-users"></i> Todos</a>
    <a href="?filtro=<?=$filtro?>&tipo=titular&busca=<?=urlencode($busca)?>" class="filter-tab <?=$tipo==='titular'?'active':''?>" style="<?=$tipo==='titular'?'':'border-color:#dbeafe;color:#1e40af'?>"><i class="fas fa-user"></i> Titulares <span style="background:rgba(255,255,255,.3);padding:1px 7px;border-radius:8px;font-size:11px"><?=$cTitular?></span></a>
    <a href="?filtro=<?=$filtro?>&tipo=acomp&busca=<?=urlencode($busca)?>" class="filter-tab <?=$tipo==='acomp'?'active':''?>" style="<?=$tipo==='acomp'?'':'border-color:#ede9fe;color:#7c3aed'?>"><i class="fas fa-user-friends"></i> Acompanhantes <span style="background:rgba(255,255,255,.3);padding:1px 7px;border-radius:8px;font-size:11px"><?=$cAcomp?></span></a>
    <form method="GET" style="flex:1;min-width:200px;display:flex;gap:8px">
        <input type="hidden" name="filtro" value="<?=$filtro?>"><input type="hidden" name="tipo" value="<?=$tipo?>">
        <input type="text" name="busca" class="form-control" placeholder="Buscar nome..." value="<?=htmlspecialchars($busca)?>" style="flex:1">
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
        <?php if($busca):?><a href="?filtro=<?=$filtro?>" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i></a><?php endif;?>
    </form>
</div>

<!-- Tabela -->
<div class="table-container">
    <div class="table-header">
        <h3 class="table-title"><i class="fas fa-list-check"></i> Lista de Presenças</h3>
        <span style="color:var(--gray);font-size:13px"><?=count($lista)?> registros</span>
    </div>
    <div style="overflow-x:auto">
    <table class="table pres-table">
        <thead><tr>
            <th style="width:40px">#</th>
            <th>Participante</th>
            <th>WhatsApp</th>
            <th class="text-center">Status</th>
            <th>Horário</th>
            <th>Confirmado por</th>
        </tr></thead>
        <tbody>
        <?php foreach($lista as $i=>$p):
            $acomps = json_decode($p['acompanhantes']??'[]',true)?:[];
            $presAcomps = json_decode($p['presenca_acomps']??'[]',true)?:[];
            $ce = json_decode($p['campos_extras']??'{}',true)?:[];
            $isAcompAvulso = ($ce['_tipo']??'')==='acompanhante_avulso';
        ?>
        <tr class="<?=$p['presenca_confirmada']?'confirmado':'ausente'?>">
            <td style="color:var(--gray);font-size:12px"><?=$i+1?></td>
            <td>
                <div style="display:flex;align-items:center;gap:10px">
                    <?php if($p['has_foto']): ?>
                    <img src="foto.php?id=<?=$p['id']?>" style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid #dbeafe">
                    <?php else: ?>
                    <div style="width:36px;height:36px;border-radius:50%;background:#e0f2fe;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-user" style="color:#94a3b8;font-size:14px"></i></div>
                    <?php endif;?>
                    <div>
                        <div style="font-weight:700"><?=htmlspecialchars($p['nome'])?></div>
                        <?php if($isAcompAvulso):?><span style="background:#ede9fe;color:#7c3aed;padding:1px 7px;border-radius:8px;font-size:10px;font-weight:700">Acomp. avulso</span><?php endif;?>
                    </div>
                </div>
            </td>
            <td style="font-size:13px"><?=htmlspecialchars($p['whatsapp']??'—')?></td>
            <td class="text-center">
                <?php if($p['presenca_confirmada']):?>
                <span class="badge-pres"><i class="fas fa-check-circle"></i> Presente</span>
                <?php else:?>
                <span class="badge-aus"><i class="fas fa-clock"></i> Ausente</span>
                <?php endif;?>
            </td>
            <td style="font-size:13px;white-space:nowrap">
                <?php if($p['presenca_em']): ?>
                <i class="fas fa-clock" style="color:#10b981"></i>
                <?=date('d/m H:i',strtotime($p['presenca_em']))?>
                <?php else:?>—<?php endif;?>
            </td>
            <td style="font-size:13px"><?=htmlspecialchars($p['presenca_por']??'—')?></td>

        </tr>
        <?php endforeach;?>
        <?php if(!count($lista)):?>
        <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--gray)"><i class="fas fa-search" style="font-size:32px;color:#dbeafe;display:block;margin-bottom:8px"></i>Nenhum resultado</td></tr>
        <?php endif;?>
        </tbody>
    </table>
    </div>
</div>

<?php } } ?>
<?php renderScripts();?>
</body></html>