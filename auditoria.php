<?php
require_once 'layout.php';
if(!isAdmin()){header('Location: index.php');exit();}

// ── Filtros ──────────────────────────────────────────────────────────────────
$filtro_acao   = $_GET['acao']   ?? '';
$filtro_user   = $_GET['usuario']?? '';
$filtro_data   = $_GET['data']   ?? '';
$filtro_search = trim($_GET['q'] ?? '');
$page = max(1,(int)($_GET['page']??1));
$per  = 25;

// ── Query base ───────────────────────────────────────────────────────────────
$where = ['1=1'];
$params = [];

if($filtro_acao){
    $where[] = 'a.acao = ?';
    $params[] = $filtro_acao;
}
if($filtro_user){
    $where[] = 'a.usuario_nome LIKE ?';
    $params[] = '%'.$filtro_user.'%';
}
if($filtro_data){
    $where[] = 'DATE(a.created_at) = ?';
    $params[] = $filtro_data;
}
if($filtro_search){
    $where[] = '(a.usuario_nome LIKE ? OR a.detalhes LIKE ? OR a.entidade LIKE ?)';
    $params[] = '%'.$filtro_search.'%';
    $params[] = '%'.$filtro_search.'%';
    $params[] = '%'.$filtro_search.'%';
}

$sql_where = implode(' AND ',$where);

// Total
$total_stmt = $pdo->prepare("SELECT COUNT(*) FROM auditoria a WHERE $sql_where");
$total_stmt->execute($params);
$total = (int)$total_stmt->fetchColumn();
$total_pages = max(1, ceil($total/$per));
$page = min($page,$total_pages);
$offset = ($page-1)*$per;

// Registros
$stmt = $pdo->prepare("SELECT a.*, e.nome as evento_nome FROM auditoria a LEFT JOIN eventos e ON a.evento_id=e.id WHERE $sql_where ORDER BY a.created_at DESC LIMIT $per OFFSET $offset");
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Usuários distintos para filtro
$usuarios = $pdo->query("SELECT DISTINCT usuario_nome FROM auditoria ORDER BY usuario_nome")->fetchAll(PDO::FETCH_COLUMN);
// Ações distintas
$acoes_db = $pdo->query("SELECT DISTINCT acao FROM auditoria ORDER BY acao")->fetchAll(PDO::FETCH_COLUMN);

// ── Mapas de UI ───────────────────────────────────────────────────────────────
function acaoUI($acao){
    $map = [
        'login'             => ['icon'=>'fa-sign-in-alt',     'color'=>'#6366f1','bg'=>'#ede9fe','label'=>'Login'],
        'logout'            => ['icon'=>'fa-sign-out-alt',    'color'=>'#64748b','bg'=>'#f1f5f9','label'=>'Logout'],
        'criar'             => ['icon'=>'fa-plus-circle',     'color'=>'#059669','bg'=>'#d1fae5','label'=>'Criação'],
        'editar'            => ['icon'=>'fa-edit',            'color'=>'#d97706','bg'=>'#fef3c7','label'=>'Edição'],
        'excluir'           => ['icon'=>'fa-trash',           'color'=>'#dc2626','bg'=>'#fee2e2','label'=>'Exclusão'],
        'aprovar'           => ['icon'=>'fa-check-circle',    'color'=>'#059669','bg'=>'#d1fae5','label'=>'Aprovação'],
        'reprovar'          => ['icon'=>'fa-times-circle',    'color'=>'#dc2626','bg'=>'#fee2e2','label'=>'Reprovação'],
        'toggle_formulario' => ['icon'=>'fa-toggle-on',       'color'=>'#0284c7','bg'=>'#e0f2fe','label'=>'Form Aberto/Fechado'],
        'visualizar'        => ['icon'=>'fa-eye',             'color'=>'#0891b2','bg'=>'#e0f2fe','label'=>'Visualização'],
        'exportar'          => ['icon'=>'fa-file-export',     'color'=>'#7c3aed','bg'=>'#ede9fe','label'=>'Exportação'],
    ];
    $d = $map[$acao] ?? ['icon'=>'fa-circle','color'=>'#64748b','bg'=>'#f1f5f9','label'=>ucfirst($acao)];
    return $d;
}

function entidadeUI($entidade){
    $map = [
        'evento'       => ['icon'=>'fa-calendar-alt','label'=>'Evento'],
        'participante' => ['icon'=>'fa-user',        'label'=>'Participante'],
        'usuario'      => ['icon'=>'fa-user-shield', 'label'=>'Usuário'],
        'relatorio'    => ['icon'=>'fa-chart-bar',   'label'=>'Relatório'],
    ];
    return $map[$entidade] ?? ['icon'=>'fa-database','label'=>ucfirst($entidade)];
}

function detalhesFormatados($det_json, $acao, $entidade){
    if(!$det_json) return '';
    $d = json_decode($det_json, true);
    if(!$d) return '<span style="color:#94a3b8;font-size:12px">'.htmlspecialchars($det_json).'</span>';
    $items = [];
    $labels = [
        'nome'=>'Nome','email'=>'E-mail','role'=>'Perfil','ativo'=>'Status',
        'aprovado'=>'Aprovado','whatsapp'=>'WhatsApp','instagram'=>'Instagram',
        'cidade'=>'Cidade','estado'=>'UF','evento_id'=>'Evento ID',
    ];
    foreach($d as $k=>$v){
        $label = $labels[$k] ?? ucfirst(str_replace('_',' ',$k));
        if($k==='ativo'||$k==='aprovado'){
            $v = $v ? '<span style="color:#059669;font-weight:700">Sim</span>' : '<span style="color:#dc2626;font-weight:700">Não</span>';
        } elseif($k==='role'){
            $v = $v==='admin' ? '<span style="background:#fef3c7;color:#92400e;padding:1px 7px;border-radius:10px;font-size:11px;font-weight:700">Admin</span>'
                              : '<span style="background:#e0f2fe;color:#0369a1;padding:1px 7px;border-radius:10px;font-size:11px;font-weight:700">Operador</span>';
        } else {
            $v = '<span style="color:#334155">'.htmlspecialchars((string)$v).'</span>';
        }
        $items[] = '<span style="color:#94a3b8;font-size:11px">'.$label.':</span> '.$v;
    }
    return implode(' &nbsp;·&nbsp; ', $items);
}
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Auditoria - ASSEGO</title><?php renderCSS();?>
<style>
/* ── Layout da Auditoria ─────────────────────────────────── */
.audit-filters{background:white;padding:20px 24px;border-radius:16px;box-shadow:var(--shadow-md);border:1px solid #dbeafe;margin-bottom:20px}
.audit-filters form{display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end}
.audit-filters .fg{display:flex;flex-direction:column;gap:4px;flex:1;min-width:160px}
.audit-filters label{font-size:11px;font-weight:700;color:#1e3a8a;text-transform:uppercase;letter-spacing:.5px}
.audit-filters input,.audit-filters select{padding:9px 14px;font-size:13px;border:2px solid #dbeafe;border-radius:10px;background:#f8fafc;color:#1e293b;outline:none;transition:.2s}
.audit-filters input:focus,.audit-filters select:focus{border-color:#3b82f6;background:white}

/* ── Timeline ────────────────────────────────────────────── */
.audit-timeline{display:flex;flex-direction:column;gap:0}
.audit-day-header{display:flex;align-items:center;gap:12px;padding:12px 0 8px;position:sticky;top:120px;z-index:10;background:#f0f9ff}
.audit-day-header .day-line{flex:1;height:1px;background:#dbeafe}
.audit-day-label{background:linear-gradient(135deg,#1e40af,#3b82f6);color:white;padding:4px 14px;border-radius:20px;font-size:12px;font-weight:700;white-space:nowrap}

.audit-item{display:flex;gap:14px;padding:14px 20px;background:white;border-radius:14px;margin-bottom:8px;box-shadow:0 1px 4px rgba(0,0,0,.06);border:1px solid #e0f2fe;transition:.2s;align-items:flex-start}
.audit-item:hover{box-shadow:var(--shadow-md);border-color:#bfdbfe;transform:translateX(2px)}

/* Ícone da ação */
.audit-icon{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:16px}

/* Conteúdo principal */
.audit-main{flex:1;min-width:0}
.audit-header-row{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px}
.audit-user{font-weight:700;color:#1e293b;font-size:14px}
.audit-badge{padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700}
.audit-entity{display:inline-flex;align-items:center;gap:4px;font-size:12px;color:#64748b;background:#f1f5f9;padding:2px 8px;border-radius:8px}
.audit-event{display:inline-flex;align-items:center;gap:4px;font-size:12px;color:#0369a1;background:#e0f2fe;padding:2px 8px;border-radius:8px;font-weight:600}
.audit-details{font-size:12px;color:#64748b;margin-top:4px;line-height:1.7}

/* Metadados à direita */
.audit-meta{display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex-shrink:0;min-width:110px}
.audit-time{font-size:15px;font-weight:700;color:#1e40af;font-variant-numeric:tabular-nums}
.audit-ip{font-size:10px;color:#94a3b8;font-family:monospace}

/* Stats no topo */
.audit-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:20px}
.audit-stat-card{background:white;border-radius:12px;padding:16px 20px;box-shadow:var(--shadow-md);border:1px solid #dbeafe;display:flex;align-items:center;gap:12px}
.ast-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.ast-num{font-size:22px;font-weight:800;color:#1e293b}
.ast-lbl{font-size:11px;color:#64748b;font-weight:600}

/* Paginação */
.audit-pagination{display:flex;justify-content:space-between;align-items:center;padding:16px 20px;background:white;border-radius:12px;box-shadow:var(--shadow-md);border:1px solid #dbeafe;margin-top:16px;flex-wrap:wrap;gap:12px}
.page-btns{display:flex;gap:6px}
.page-btn{padding:7px 13px;border-radius:8px;border:1px solid #dbeafe;background:white;color:#1e40af;font-weight:600;font-size:13px;cursor:pointer;text-decoration:none;transition:.2s}
.page-btn:hover{background:#dbeafe}.page-btn.active{background:#1e40af;color:white;border-color:#1e40af}
.page-btn.disabled{opacity:.4;pointer-events:none}

@media(max-width:768px){
    .audit-item{padding:10px 12px;gap:10px}
    .audit-meta{min-width:80px}.audit-time{font-size:13px}
    .audit-day-header{top:100px}
    .audit-stats{grid-template-columns:repeat(2,1fr)}
}
</style></head><body>
<?php renderHeader('auditoria');?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-shield-alt"></i> Auditoria</h1>
        <div style="font-size:12px;color:var(--gray);margin-top:4px">Histórico completo de ações do sistema</div>
    </div>
    <div style="display:flex;gap:10px;align-items:center">
        <span style="background:#f0f9ff;border:1px solid #dbeafe;padding:8px 16px;border-radius:10px;font-size:13px;color:var(--primary);font-weight:600">
            <i class="fas fa-list"></i> <?=number_format($total,0,'.','.') ?> registros
        </span>
        <a href="auditoria.php" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Limpar filtros</a>
    </div>
</div>

<div class="content-container">

<!-- Stats rápidos -->
<?php
$stats_q = $pdo->query("SELECT acao, COUNT(*) as total FROM auditoria GROUP BY acao ORDER BY total DESC LIMIT 5");
$stats = $stats_q->fetchAll();
$stat_icons = ['login'=>['#6366f1','#ede9fe','fa-sign-in-alt'],'logout'=>['#64748b','#f1f5f9','fa-sign-out-alt'],'editar'=>['#d97706','#fef3c7','fa-edit'],'criar'=>['#059669','#d1fae5','fa-plus-circle'],'excluir'=>['#dc2626','#fee2e2','fa-trash'],'aprovar'=>['#059669','#d1fae5','fa-check'],'toggle_formulario'=>['#0284c7','#e0f2fe','fa-toggle-on']];
?>
<div class="audit-stats">
<?php foreach($stats as $st):
    $ui = acaoUI($st['acao']);
    $isActive = ($filtro_acao===$st['acao']);
?>
<a href="?acao=<?=urlencode($st['acao'])?><?=($filtro_data?'&data='.$filtro_data:'')?><?=($filtro_user?'&usuario='.urlencode($filtro_user):'')?>" style="text-decoration:none">
<div class="audit-stat-card" style="cursor:pointer;border-color:<?=$isActive?$ui['color']:'#dbeafe'?>;<?=$isActive?'box-shadow:0 0 0 2px '.$ui['color'].'30':''?>">
    <div class="ast-icon" style="background:<?=$ui['bg']?>;color:<?=$ui['color']?>"><i class="fas <?=$ui['icon']?>"></i></div>
    <div>
        <div class="ast-num"><?=$st['total']?></div>
        <div class="ast-lbl"><?=$ui['label']?></div>
    </div>
</div>
</a>
<?php endforeach;?>
</div>

<!-- Filtros -->
<div class="audit-filters">
<form method="GET">
    <div class="fg" style="max-width:220px">
        <label><i class="fas fa-search"></i> Buscar</label>
        <input type="text" name="q" value="<?=htmlspecialchars($filtro_search)?>" placeholder="Nome, detalhe, entidade...">
    </div>
    <div class="fg" style="max-width:180px">
        <label><i class="fas fa-bolt"></i> Ação</label>
        <select name="acao">
            <option value="">Todas as ações</option>
            <?php foreach($acoes_db as $ac):$ui=acaoUI($ac);?>
            <option value="<?=$ac?>" <?=$filtro_acao===$ac?'selected':''?>><?=$ui['label']?></option>
            <?php endforeach;?>
        </select>
    </div>
    <div class="fg" style="max-width:180px">
        <label><i class="fas fa-user"></i> Usuário</label>
        <select name="usuario">
            <option value="">Todos os usuários</option>
            <?php foreach($usuarios as $u):?>
            <option value="<?=htmlspecialchars($u)?>" <?=$filtro_user===$u?'selected':''?>><?=htmlspecialchars($u)?></option>
            <?php endforeach;?>
        </select>
    </div>
    <div class="fg" style="max-width:160px">
        <label><i class="fas fa-calendar"></i> Data</label>
        <input type="date" name="data" value="<?=htmlspecialchars($filtro_data)?>">
    </div>
    <button type="submit" class="btn btn-primary" style="align-self:flex-end"><i class="fas fa-filter"></i> Filtrar</button>
</form>
</div>

<!-- Timeline -->
<?php if(empty($logs)):?>
<div style="text-align:center;padding:60px 20px;background:white;border-radius:16px;border:1px solid #dbeafe">
    <i class="fas fa-search" style="font-size:48px;color:#dbeafe;display:block;margin-bottom:16px"></i>
    <div style="font-size:18px;font-weight:700;color:var(--primary);margin-bottom:8px">Nenhum registro encontrado</div>
    <div style="color:var(--gray)">Tente outros filtros ou <a href="auditoria.php" style="color:var(--primary)">limpe os filtros</a></div>
</div>
<?php else:?>
<div class="audit-timeline">
<?php
$current_day = '';
foreach($logs as $log):
    $dt = new DateTime($log['created_at']);
    $day = $dt->format('d/m/Y');
    $hora = $dt->format('H:i:s');
    $ui = acaoUI($log['acao']);
    $ent = entidadeUI($log['entidade']??'');
    $dets = detalhesFormatados($log['detalhes']??'',$log['acao'],$log['entidade']??'');

    // Separador de dia
    if($day !== $current_day):
        $current_day = $day;
        $hoje = (new DateTime())->format('d/m/Y');
        $ontem = (new DateTime('-1 day'))->format('d/m/Y');
        $day_label = $day===$hoje ? 'Hoje · '.$day : ($day===$ontem ? 'Ontem · '.$day : $day);
?>
    <div class="audit-day-header">
        <div class="day-line"></div>
        <div class="audit-day-label"><i class="fas fa-calendar-day"></i> <?=$day_label?></div>
        <div class="day-line"></div>
    </div>
<?php endif;?>

    <div class="audit-item">
        <!-- Ícone da ação -->
        <div class="audit-icon" style="background:<?=$ui['bg']?>;color:<?=$ui['color']?>">
            <i class="fas <?=$ui['icon']?>"></i>
        </div>

        <!-- Conteúdo -->
        <div class="audit-main">
            <div class="audit-header-row">
                <!-- Usuário -->
                <span class="audit-user"><?=htmlspecialchars($log['usuario_nome']??'Sistema')?></span>
                <span style="color:#94a3b8;font-size:12px">—</span>
                <!-- Badge da ação -->
                <span class="audit-badge" style="background:<?=$ui['bg']?>;color:<?=$ui['color']?>">
                    <i class="fas <?=$ui['icon']?>" style="font-size:10px"></i> <?=$ui['label']?>
                </span>
                <!-- Entidade -->
                <?php if($log['entidade']):?>
                <span class="audit-entity">
                    <i class="fas <?=$ent['icon']?>" style="font-size:10px"></i>
                    <?=$ent['label']?> <?=$log['entidade_id']?'#'.$log['entidade_id']:''?>
                </span>
                <?php endif;?>
                <!-- Evento relacionado -->
                <?php if(!empty($log['evento_nome'])):?>
                <span class="audit-event">
                    <i class="fas fa-calendar-alt" style="font-size:10px"></i>
                    <?=htmlspecialchars($log['evento_nome'])?>
                </span>
                <?php endif;?>
            </div>
            <!-- Detalhes legíveis -->
            <?php if($dets):?>
            <div class="audit-details"><?=$dets?></div>
            <?php endif;?>
        </div>

        <!-- Horário + IP -->
        <div class="audit-meta">
            <span class="audit-time"><?=$hora?></span>
            <?php if($log['ip']):?>
            <span class="audit-ip"><i class="fas fa-network-wired" style="font-size:9px"></i> <?=htmlspecialchars($log['ip'])?></span>
            <?php endif;?>
        </div>
    </div>

<?php endforeach;?>
</div>

<!-- Paginação -->
<div class="audit-pagination">
    <div style="font-size:13px;color:var(--gray)">
        Mostrando <strong><?=($offset+1)?>–<?=min($offset+$per,$total)?></strong> de <strong><?=number_format($total,0,'.','.') ?></strong> registros
    </div>
    <div class="page-btns">
        <?php
        $qs = http_build_query(array_filter(['acao'=>$filtro_acao,'usuario'=>$filtro_user,'data'=>$filtro_data,'q'=>$filtro_search]));
        $qs_sep = $qs ? '&' : '';
        ?>
        <a href="?<?=$qs.$qs_sep?>page=1" class="page-btn <?=$page<=1?'disabled':''?>"><i class="fas fa-angle-double-left"></i></a>
        <a href="?<?=$qs.$qs_sep?>page=<?=max(1,$page-1)?>" class="page-btn <?=$page<=1?'disabled':''?>"><i class="fas fa-angle-left"></i></a>

        <?php
        $start_p = max(1,$page-2); $end_p = min($total_pages,$start_p+4);
        for($p=$start_p;$p<=$end_p;$p++):?>
        <a href="?<?=$qs.$qs_sep?>page=<?=$p?>" class="page-btn <?=$p===$page?'active':''?>"><?=$p?></a>
        <?php endfor;?>

        <a href="?<?=$qs.$qs_sep?>page=<?=min($total_pages,$page+1)?>" class="page-btn <?=$page>=$total_pages?'disabled':''?>"><i class="fas fa-angle-right"></i></a>
        <a href="?<?=$qs.$qs_sep?>page=<?=$total_pages?>" class="page-btn <?=$page>=$total_pages?'disabled':''?>"><i class="fas fa-angle-double-right"></i></a>
    </div>
</div>
<?php endif;?>

</div><!-- /content-container -->
<?php renderScripts();?>
</body></html>