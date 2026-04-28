<?php
require_once 'layout.php';
if(!isset($_GET['id'])){header('Location: index.php');exit();}
$id=(int)$_GET['id'];
$s=$pdo->prepare("SELECT p.*,e.nome as ev_nome,e.campos_extras as ev_campos FROM participantes p LEFT JOIN eventos e ON p.evento_id=e.id WHERE p.id=?");$s->execute([$id]);$p=$s->fetch();
if(!$p){header('Location: index.php');exit();}
$s=$pdo->prepare("SELECT * FROM fotos WHERE participante_id=? LIMIT 1");$s->execute([$id]);$foto=$s->fetch();
$campos=json_decode($p['ev_campos']??'[]',true)?:[];$extras=json_decode($p['campos_extras']??'{}',true)?:[];
$s=$pdo->prepare("SELECT * FROM materiais WHERE participante_id=? ORDER BY created_at DESC");$s->execute([$id]);$materiais=$s->fetchAll();
$acomps=[];try{if(!empty($p['acompanhantes']))$acomps=json_decode($p['acompanhantes'],true)?:[];}catch(Exception $e){}

// Histórico: buscar auditoria deste participante
$hist=[];
try{
    $hs=$pdo->prepare("SELECT acao,usuario_nome,created_at,detalhes FROM auditoria WHERE entidade='participante' AND entidade_id=? ORDER BY created_at ASC");
    $hs->execute([$id]);$hist=$hs->fetchAll();
}catch(Exception $e){}

// Verificar colunas aprovado_em / aprovado_por
$cols_p=array_column($pdo->query("SHOW COLUMNS FROM participantes")->fetchAll(),'Field');
$has_aprovado_em = in_array('aprovado_em',$cols_p);
// Token de ingresso
function gerarTokenView($id){
    $secret=DB_PASS.DB_NAME;$data=$id.':titular:0';
    return base64_encode($data.':'.substr(hash_hmac('sha256',$data,$secret),0,12));
}
$token_ingresso=gerarTokenView($p['id']);
$base_url=(isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on'?'https':'http').'://'.$_SERVER['HTTP_HOST'].rtrim(dirname($_SERVER['SCRIPT_NAME']),'/').'/';
$link_ingresso=$base_url.'convite.php?id='.$p['id'].'&tk='.urlencode($token_ingresso);
$has_aprovado_por = in_array('aprovado_por',$cols_p);
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?=htmlspecialchars($p['nome'])?> - ASSEGO</title><?php renderCSS();?>
<style>
/* Timeline do histórico */
.timeline{position:relative;padding-left:28px}
.timeline::before{content:'';position:absolute;left:10px;top:0;bottom:0;width:2px;background:#dbeafe}
.tl-item{position:relative;margin-bottom:16px}
.tl-dot{position:absolute;left:-23px;top:4px;width:14px;height:14px;border-radius:50%;border:2px solid white;box-shadow:0 0 0 2px #dbeafe}
.tl-body{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:10px 14px}
.tl-time{font-size:11px;color:#94a3b8;font-family:monospace}
.tl-label{font-size:13px;font-weight:700;color:#1e293b;margin:2px 0 1px}
.tl-detail{font-size:12px;color:#64748b}
/* Cards de dados */
.info-card{background:white;border-radius:14px;box-shadow:var(--shadow-md);border:1px solid #dbeafe;margin-bottom:20px;overflow:hidden}
.info-card-header{padding:16px 22px;border-bottom:2px solid #dbeafe;display:flex;align-items:center;gap:10px}
.info-card-header h3{margin:0;font-size:16px;font-weight:700;color:var(--primary)}
.info-card-body{padding:20px 22px}
.data-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:18px}
.data-item label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:#94a3b8;display:block;margin-bottom:3px}
.data-item .val{font-size:15px;font-weight:500;color:#1e293b}
@media print{
    .no-print{display:none!important}
    .info-card{box-shadow:none!important;border:1px solid #dbeafe!important;page-break-inside:avoid}
    .profile-banner{background:#1e3a8a!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
    @page{margin:12mm;size:A4}
}
</style></head><body>
<?php renderHeader('participantes');?>

<!-- Banner do participante -->
<div class="profile-banner" style="background:linear-gradient(135deg,#1e3a8a,#3b82f6);color:white;padding:28px 24px">
<div style="max-width:1200px;margin:0 auto;display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:20px">
<div style="display:flex;align-items:center;gap:20px">
<div style="width:100px;height:100px;border-radius:16px;overflow:hidden;background:white;box-shadow:var(--shadow-lg);flex-shrink:0">
<?php if($foto&&!empty($foto['dados'])):?><img src="<?=$foto['dados']?>" style="width:100%;height:100%;object-fit:cover"><?php else:?><div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#e2e8f0;color:#64748b;font-size:40px"><i class="fas fa-user"></i></div><?php endif;?>
</div>
<div>
    <h1 style="font-size:26px;margin:0 0 6px"><?=htmlspecialchars($p['nome'])?></h1>
    <div style="display:flex;gap:16px;flex-wrap:wrap;opacity:.9;font-size:14px">
        <?php if($p['whatsapp']):?><span><i class="fab fa-whatsapp"></i> <?=htmlspecialchars($p['whatsapp'])?></span><?php endif;?>
        <?php if($p['instagram']):?><span><i class="fab fa-instagram"></i> @<?=htmlspecialchars(ltrim($p['instagram'],'@'))?></span><?php endif;?>
    </div>
    <div style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap">
        <span style="background:rgba(255,255,255,.2);padding:3px 12px;border-radius:20px;font-size:12px"><?=htmlspecialchars($p['ev_nome'])?></span>
        <span style="background:<?=($p['ativo']??1)?'rgba(16,185,129,.4)':'rgba(239,68,68,.3)'?>;padding:3px 12px;border-radius:20px;font-size:12px"><?=($p['ativo']??1)?'Ativo':'Inativo'?></span>
        <?php if(count($acomps)>0):?>
        <span style="background:rgba(245,158,11,.4);padding:3px 12px;border-radius:20px;font-size:12px"><i class="fas fa-user-friends"></i> <?=count($acomps)?> acompanhante<?=count($acomps)>1?'s':''?></span>
        <?php endif;?>
    </div>
    <!-- Datas visíveis no banner -->
    <div style="margin-top:12px;display:flex;gap:12px;flex-wrap:wrap">
        <div style="display:flex;align-items:center;gap:8px;background:rgba(255,255,255,.15);padding:6px 14px;border-radius:10px">
            <i class="fas fa-calendar-plus" style="color:#93c5fd;font-size:12px"></i>
            <div>
                <div style="font-size:9px;opacity:.7;font-weight:700;text-transform:uppercase;letter-spacing:.5px">Cadastrado em</div>
                <div style="font-size:13px;font-weight:700"><?=date('d/m/Y H:i',strtotime($p['created_at']))?></div>
            </div>
        </div>
        <!-- Presença confirmada badge -->
<?php
$colsPresenca=array_column($pdo->query("SHOW COLUMNS FROM participantes")->fetchAll(),'Field');
$hasPresenca=in_array('presenca_confirmada',$colsPresenca);
if($hasPresenca&&$p['presenca_confirmada']):?>
<div style="display:flex;align-items:center;gap:8px;background:rgba(16,185,129,.25);padding:6px 14px;border-radius:10px;border:1px solid rgba(16,185,129,.4)">
    <i class="fas fa-person-walking-arrow-right" style="color:#6ee7b7;font-size:12px"></i>
    <div>
        <div style="font-size:9px;opacity:.7;font-weight:700;text-transform:uppercase;letter-spacing:.5px">Presença Confirmada</div>
        <div style="font-size:13px;font-weight:700"><?=date('d/m/Y H:i',strtotime($p['presenca_em']))?></div>
        <?php if(!empty($p['presenca_por'])):?><div style="font-size:11px;opacity:.8">por <?=htmlspecialchars($p['presenca_por'])?></div><?php endif;?>
    </div>
</div>
<?php endif;?>
<?php if($p['aprovado']):?>
        <div style="display:flex;align-items:center;gap:8px;background:rgba(16,185,129,.25);padding:6px 14px;border-radius:10px;border:1px solid rgba(16,185,129,.4)">
            <i class="fas fa-check-circle" style="color:#6ee7b7;font-size:12px"></i>
            <div>
                <div style="font-size:9px;opacity:.7;font-weight:700;text-transform:uppercase;letter-spacing:.5px">Aprovado em</div>
                <div style="font-size:13px;font-weight:700">
                <?php if($has_aprovado_em&&!empty($p['aprovado_em'])):?><?=date('d/m/Y H:i',strtotime($p['aprovado_em']))?><?php elseif(!empty($p['updated_at'])&&$p['updated_at']!=$p['created_at']):?><?=date('d/m/Y H:i',strtotime($p['updated_at']))?><?php else:?>—<?php endif;?>
                </div>
                <?php if($has_aprovado_por&&!empty($p['aprovado_por'])):?>
                <div style="font-size:11px;opacity:.8">por <?=htmlspecialchars($p['aprovado_por'])?></div>
                <?php endif;?>
            </div>
        </div>
        <?php else:?>
        <div style="display:flex;align-items:center;gap:8px;background:rgba(245,158,11,.25);padding:6px 14px;border-radius:10px;border:1px solid rgba(245,158,11,.4)">
            <i class="fas fa-clock" style="color:#fcd34d;font-size:12px"></i>
            <div>
                <div style="font-size:9px;opacity:.7;font-weight:700;text-transform:uppercase;letter-spacing:.5px">Status</div>
                <div style="font-size:13px;font-weight:700">Aguardando aprovação</div>
            </div>
        </div>
        <?php endif;?>
    </div>
</div>
</div>
<?php $isPending=isset($_GET['pending'])&&$_GET['pending']=='1';?>
<div style="display:flex;gap:10px;flex-wrap:wrap" class="no-print">
<?php if($isPending):?>
<button onclick="if(confirm('Aprovar?'))location='api_realtime.php?action=aprovar&evento_id=<?=$p['evento_id']?>&id=<?=$p['id']?>&redirect=solicitacoes'" class="btn btn-success"><i class="fas fa-check"></i> Aprovar</button>
<button onclick="if(confirm('Rejeitar?'))location='api_realtime.php?action=rejeitar&evento_id=<?=$p['evento_id']?>&id=<?=$p['id']?>&redirect=solicitacoes'" class="btn btn-danger"><i class="fas fa-times"></i> Rejeitar</button>
<a href="solicitacoes.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
<?php else:?>
<a href="participantes.php?action=edit&id=<?=$p['id']?>" class="btn btn-secondary"><i class="fas fa-edit"></i> Editar</a>
<a href="<?=$link_ingresso?>" target="_blank" class="btn btn-success no-print" title="Ver convite com QR Code"><i class="fas fa-ticket"></i> Convite</a>
<button onclick="window.print()" class="btn btn-secondary"><i class="fas fa-print"></i> Imprimir</button>
<a href="participantes.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
<?php endif;?>
</div>
</div>
</div>

<div style="max-width:1200px;margin:0 auto;padding:24px">
<div class="row g-4">

<!-- Coluna principal -->
<div class="col-lg-8">

<!-- Dados pessoais -->
<div class="info-card">
    <div class="info-card-header"><i class="fas fa-user" style="color:var(--primary)"></i><h3>Dados Pessoais</h3></div>
    <div class="info-card-body">
        <div class="data-grid">
            <div class="data-item"><label>Nome</label><div class="val"><?=htmlspecialchars($p['nome'])?></div></div>
            <div class="data-item"><label>WhatsApp</label><div class="val"><?=htmlspecialchars($p['whatsapp']??'—')?></div></div>
            <div class="data-item"><label>Instagram</label><div class="val"><?=!empty($p['instagram'])?'@'.htmlspecialchars(ltrim($p['instagram'],'@')):'—'?></div></div>
            <?php if(!empty($p['video_url'])):$vid=$p['video_url'];$isLocal=!preg_match('/^https?:\/\//',$vid);$isYT=preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([\w-]+)/',$vid,$ytm);?>
            <div class="data-item" style="grid-column:1/-1"><label><i class="fas fa-video" style="color:#7c3aed"></i> Vídeo</label>
            <?php if($isLocal):?><video controls style="width:100%;max-height:280px;border-radius:10px;background:#000;border:2px solid #e0f2fe"><source src="<?=htmlspecialchars($vid)?>"></video>
            <?php elseif($isYT):?><div style="position:relative;padding-bottom:56.25%;height:0;border-radius:10px;overflow:hidden;border:2px solid #e0f2fe"><iframe src="https://www.youtube.com/embed/<?=$ytm[1]?>" style="position:absolute;top:0;left:0;width:100%;height:100%" frameborder="0" allowfullscreen></iframe></div>
            <?php else:?><a href="<?=htmlspecialchars($vid)?>" target="_blank" style="display:inline-flex;align-items:center;gap:8px;padding:10px 18px;background:linear-gradient(135deg,#7c3aed,#a78bfa);color:white;border-radius:10px;text-decoration:none;font-weight:600"><i class="fas fa-external-link-alt"></i> Abrir Vídeo</a><?php endif;?>
            </div>
            <?php endif;?>
        </div>
    </div>
</div>

<!-- Campos extras -->
<?php if(!empty($campos)&&!empty($extras)):?>
<div class="info-card">
    <div class="info-card-header"><i class="fas fa-list-check" style="color:var(--primary)"></i><h3>Dados do Evento</h3></div>
    <div class="info-card-body"><div class="data-grid">
        <?php foreach($campos as $c):?><div class="data-item"><label><?=htmlspecialchars($c['label'])?></label><div class="val"><?=htmlspecialchars($extras[$c['nome']]??'—')?></div></div><?php endforeach;?>
    </div></div>
</div>
<?php endif;?>

<!-- Dados Militares -->
<?php if(!empty($extras['_militar'])&&$extras['_militar']==='1'):?>
<div class="info-card">
    <div class="info-card-header"><i class="fas fa-shield-halved" style="color:#059669"></i><h3>Dados Militares</h3></div>
    <div class="info-card-body"><div class="data-grid">
        <div class="data-item"><label>Corporação</label><div class="val"><?=htmlspecialchars($extras['_corporacao']??'—')?></div></div>
        <div class="data-item"><label>Patente / Posto</label><div class="val"><?=htmlspecialchars($extras['_patente']??'—')?></div></div>
    </div></div>
</div>
<?php endif;?>

<!-- Endereço -->
<div class="info-card">
    <div class="info-card-header"><i class="fas fa-map-marker-alt" style="color:#ef4444"></i><h3>Endereço</h3></div>
    <div class="info-card-body"><div class="data-grid">
        <div class="data-item"><label>Endereço</label><div class="val"><?=htmlspecialchars($p['endereco']??'—')?></div></div>
        <div class="data-item"><label>Cidade / UF</label><div class="val"><?=htmlspecialchars(($p['cidade']??'').($p['estado']?' — '.$p['estado']:''))?:' —'?></div></div>
        <div class="data-item"><label>CEP</label><div class="val"><?=htmlspecialchars($p['cep']??'—')?></div></div>
    </div></div>
</div>

<!-- Acompanhantes -->
<?php if(!empty($acomps)):?>
<div class="info-card">
    <div class="info-card-header"><i class="fas fa-user-friends" style="color:#d97706"></i><h3>Acompanhantes (<?=count($acomps)?>)</h3></div>
    <div class="info-card-body">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px">
        <?php foreach($acomps as $ai=>$ac):?>
        <div style="background:#fffbeb;padding:14px;border-radius:10px;border:1px solid #fde68a">
            <div style="font-size:11px;color:#d97706;font-weight:700;margin-bottom:6px"><i class="fas fa-user"></i> Acompanhante <?=$ai+1?></div>
            <div style="font-size:15px;font-weight:700;color:#92400e;margin-bottom:6px"><?=htmlspecialchars($ac['nome']??'')?></div>
            <?php if(!empty($ac['whatsapp'])):?><div style="font-size:13px;color:#78716c"><i class="fab fa-whatsapp" style="color:#25d366"></i> <?=formatPhone($ac['whatsapp'])?></div><?php endif;?>
            <?php if(!empty($ac['cpf'])):?><div style="font-size:13px;color:#78716c"><i class="fas fa-id-card" style="color:#6366f1"></i> <?=formatCPF($ac['cpf'])?></div><?php endif;?>
            <?php if(!empty($ac['instagram'])):?><div style="font-size:13px;color:#78716c"><i class="fab fa-instagram" style="color:#e1306c"></i> <?=htmlspecialchars($ac['instagram'])?></div><?php endif;?>
            <?php if(!empty($ac['militar'])&&$ac['militar']==='1'):?>
            <div style="font-size:12px;color:#059669;margin-top:6px;padding-top:6px;border-top:1px solid #fde68a">
                <i class="fas fa-shield-halved" style="color:#059669"></i>
                <strong><?=htmlspecialchars($ac['corporacao']??'')?></strong>
                <?php if(!empty($ac['patente'])):?> · <?=htmlspecialchars($ac['patente'])?><?php endif;?>
            </div>
            <?php endif;?>
            <?php if(!empty($ac['cidade'])||!empty($ac['endereco'])):?>
            <div style="font-size:12px;color:#a8a29e;margin-top:6px;padding-top:6px;border-top:1px solid #fde68a">
                <i class="fas fa-map-marker-alt" style="color:#ef4444"></i>
                <?=htmlspecialchars(implode(', ',array_filter([$ac['endereco']??'',$ac['bairro']??'',$ac['cidade']??'',!empty($ac['estado'])?$ac['estado']:'',!empty($ac['cep'])?'CEP '.$ac['cep']:''])))?>
            </div>
            <?php endif;?>
        </div>
        <?php endforeach;?>
        </div>
    </div>
</div>
<?php endif;?>

<?php if(!empty($p['observacoes'])):?>
<div class="info-card">
    <div class="info-card-header"><i class="fas fa-sticky-note" style="color:#f59e0b"></i><h3>Observações</h3></div>
    <div class="info-card-body"><p style="white-space:pre-wrap;margin:0;color:#475569"><?=htmlspecialchars($p['observacoes'])?></p></div>
</div>
<?php endif;?>

</div><!-- /col-lg-8 -->

<!-- Coluna lateral: Histórico de Registro -->
<div class="col-lg-4">

<!-- Card de registro e aprovação -->
<div class="info-card">
    <div class="info-card-header"><i class="fas fa-history" style="color:#6366f1"></i><h3>Histórico</h3></div>
    <div class="info-card-body" style="padding:16px 18px">
        <div class="timeline">

            <!-- 1. Cadastro -->
            <div class="tl-item">
                <div class="tl-dot" style="background:#3b82f6"></div>
                <div class="tl-body">
                    <div class="tl-time"><i class="fas fa-calendar-plus" style="color:#3b82f6"></i> Cadastro</div>
                    <div class="tl-label"><?=date('d/m/Y',strtotime($p['created_at']))?></div>
                    <div class="tl-detail"><i class="fas fa-clock" style="color:#94a3b8"></i> <?=date('H:i:s',strtotime($p['created_at']))?></div>
                </div>
            </div>

            <!-- 2. Aprovação -->
            <?php if($p['aprovado']):?>
            <div class="tl-item">
                <div class="tl-dot" style="background:#10b981"></div>
                <div class="tl-body" style="border-color:#bbf7d0;background:#f0fdf4">
                    <div class="tl-time"><i class="fas fa-check-circle" style="color:#10b981"></i> Aprovado</div>
                    <?php if($has_aprovado_em&&!empty($p['aprovado_em'])):?>
                    <div class="tl-label"><?=date('d/m/Y',strtotime($p['aprovado_em']))?></div>
                    <div class="tl-detail"><i class="fas fa-clock" style="color:#94a3b8"></i> <?=date('H:i:s',strtotime($p['aprovado_em']))?></div>
                    <?php elseif(!empty($p['updated_at'])&&$p['updated_at']!=$p['created_at']):?>
                    <div class="tl-label"><?=date('d/m/Y',strtotime($p['updated_at']))?></div>
                    <div class="tl-detail"><i class="fas fa-clock" style="color:#94a3b8"></i> <?=date('H:i:s',strtotime($p['updated_at']))?> <small>(última atualização)</small></div>
                    <?php else:?>
                    <div class="tl-label" style="color:#16a34a">Ativo</div>
                    <?php endif;?>
                    <?php if($has_aprovado_por&&!empty($p['aprovado_por'])):?>
                    <div class="tl-detail" style="margin-top:4px"><i class="fas fa-user-check" style="color:#10b981"></i> por <strong><?=htmlspecialchars($p['aprovado_por'])?></strong></div>
                    <?php endif;?>
                </div>
            </div>
            <?php else:?>
            <div class="tl-item">
                <div class="tl-dot" style="background:#f59e0b"></div>
                <div class="tl-body" style="border-color:#fde68a;background:#fffbeb">
                    <div class="tl-time"><i class="fas fa-clock" style="color:#f59e0b"></i> Aguardando aprovação</div>
                    <div class="tl-label" style="color:#92400e">Pendente</div>
                </div>
            </div>
            <?php endif;?>

            <!-- 3. Auditoria: outras ações -->
            <?php foreach($hist as $h):
                if($h['acao']==='criar') continue; // já mostrado no cadastro
                $acMap=['editar'=>['#d97706','fa-edit','Editado'],'excluir'=>['#dc2626','fa-trash','Excluído'],'aprovar'=>['#10b981','fa-check','Aprovado via painel'],'reprovar'=>['#dc2626','fa-times','Reprovado']];
                $ui=$acMap[$h['acao']]??['#64748b','fa-circle',ucfirst($h['acao'])];
            ?>
            <div class="tl-item">
                <div class="tl-dot" style="background:<?=$ui[0]?>"></div>
                <div class="tl-body">
                    <div class="tl-time"><i class="fas <?=$ui[1]?>" style="color:<?=$ui[0]?>"></i> <?=$ui[2]?></div>
                    <div class="tl-label"><?=date('d/m/Y',strtotime($h['created_at']))?></div>
                    <div class="tl-detail"><i class="fas fa-clock" style="color:#94a3b8"></i> <?=date('H:i:s',strtotime($h['created_at']))?><?php if(!empty($h['usuario_nome'])):?> · <i class="fas fa-user" style="color:#94a3b8"></i> <?=htmlspecialchars($h['usuario_nome'])?><?php endif;?></div>
                </div>
            </div>
            <?php endforeach;?>

        </div><!-- /timeline -->

        <!-- Resumo compacto -->
        <div style="margin-top:16px;padding-top:16px;border-top:1px solid #e2e8f0;display:grid;grid-template-columns:1fr 1fr;gap:10px">
            <div style="text-align:center;background:#f8fafc;border-radius:8px;padding:10px">
                <div style="font-size:10px;color:#94a3b8;text-transform:uppercase;font-weight:700">ID</div>
                <div style="font-size:18px;font-weight:800;color:var(--primary)">#<?=$p['id']?></div>
            </div>
            <div style="text-align:center;background:#f8fafc;border-radius:8px;padding:10px">
                <div style="font-size:10px;color:#94a3b8;text-transform:uppercase;font-weight:700">Acomp.</div>
                <div style="font-size:18px;font-weight:800;color:#d97706"><?=count($acomps)?></div>
            </div>
        </div>
    </div>
</div>

<!-- Foto (se houver) -->
<?php if($foto&&!empty($foto['dados'])):?>
<div class="info-card no-print">
    <div class="info-card-header"><i class="fas fa-camera" style="color:#0891b2"></i><h3>Foto</h3></div>
    <div class="info-card-body" style="text-align:center;padding:16px">
        <img src="<?=$foto['dados']?>" style="max-width:100%;border-radius:12px;box-shadow:var(--shadow-md)">
    </div>
</div>
<?php endif;?>

</div><!-- /col-lg-4 -->
</div><!-- /row -->
</div><!-- /container -->

<?php renderScripts();?></body></html>