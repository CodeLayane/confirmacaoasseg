<?php
@ini_set('upload_max_filesize','128M');@ini_set('post_max_size','140M');@ini_set('max_execution_time','600');@ini_set('memory_limit','256M');
require_once 'layout.php';
if(!$evento_atual){header('Location: index.php');exit();}
$eid=$evento_atual['id'];$campos_extras=json_decode($evento_atual['campos_extras']??'[]',true)?:[];
$action=$_GET['action']??'list';$message='';$errors=[];
$participante=null;
if($action=='edit'&&isset($_GET['id'])){$s=$pdo->prepare("SELECT * FROM participantes WHERE id=? AND evento_id=?");$s->execute([$_GET['id'],$eid]);$participante=$s->fetch();if(!$participante){header("Location: participantes.php");exit();}}
if($_SERVER['REQUEST_METHOD']=='POST'&&($action=='add'||$action=='edit')){
    $nome=clean_input($_POST['nome']??'');$whatsapp=preg_replace('/[^0-9]/','',$_POST['whatsapp']??'');$instagram=clean_input($_POST['instagram']??'');
    $endereco=clean_input($_POST['endereco']??'');$cidade=clean_input($_POST['cidade']??'');$estado=clean_input($_POST['estado']??'');$cep=preg_replace('/[^0-9-]/','',$_POST['cep']??'');
    $observacoes=clean_input($_POST['observacoes']??'');$ativo=isset($_POST['ativo'])?1:0;
    $video_url='';$remove_video=(isset($_POST['remove_video'])&&$_POST['remove_video']=='1');
    if(!$remove_video){
        if(isset($_FILES['video_file'])&&$_FILES['video_file']['error']!==UPLOAD_ERR_NO_FILE){
            $vf=$_FILES['video_file'];
            if($vf['error']!==UPLOAD_ERR_OK){$errors[]="Erro no upload do vídeo (código ".$vf['error'].").";}
            else{$mime=mime_content_type($vf['tmp_name'])?:'';$ext=strtolower(pathinfo($vf['name'],PATHINFO_EXTENSION));$mimeOk=strpos($mime,'video/')===0||$mime==='application/octet-stream';$extOk=in_array($ext,['mp4','mov','avi','webm','mkv','3gp','3gpp','m4v','mpg','mpeg','ogv','wmv','flv']);
            if(!$mimeOk&&!$extOk){$errors[]="Formato de vídeo não suportado ({$ext}).";}
            elseif($vf['size']>100*1024*1024){$errors[]="Vídeo muito grande (".round($vf['size']/1024/1024)."MB). Máx: 100MB.";}
            else{$vdir=__DIR__.'/uploads/videos';if(!is_dir($vdir))mkdir($vdir,0755,true);if(!$extOk&&$mimeOk){$mimeToExt=['video/mp4'=>'mp4','video/quicktime'=>'mov','video/webm'=>'webm','video/3gpp'=>'3gp'];$ext=$mimeToExt[$mime]??'mp4';}$vname=time().'_'.mt_rand(1000,9999).'.'.$ext;if(move_uploaded_file($vf['tmp_name'],$vdir.'/'.$vname))$video_url='uploads/videos/'.$vname;else $errors[]="Erro ao salvar vídeo.";}}
        } elseif(!empty(trim($_POST['video_link']??''))){$video_url=clean_input($_POST['video_link']);}
        elseif($action=='edit'&&$participante&&!empty($participante['video_url']??'')){$video_url=$participante['video_url'];}
    }
    // Preservar chaves internas (_militar, _corporacao, _patente) do participante existente
    $extras=[];
    if($action=='edit'&&$participante){$ev=$json=json_decode($participante['campos_extras']??'{}',true)?:[];foreach(['_militar','_corporacao','_patente'] as $k){if(isset($ev[$k]))$extras[$k]=$ev[$k];}}
    foreach($campos_extras as $ce){$v=clean_input($_POST['extra_'.$ce['nome']]??'');$extras[$ce['nome']]=$v;if(($ce['obrigatorio']??false)&&empty($v))$errors[]=$ce['label']." obrigatório.";}
    // Dados militares enviados pelo admin
    if(isset($_POST['is_militar'])){$extras['_militar']='1';$extras['_corporacao']=clean_input($_POST['corporacao']??'');$extras['_patente']=clean_input($_POST['patente']??'');}
    elseif(isset($_POST['militar_enviado'])){$extras['_militar']='0';unset($extras['_corporacao']);unset($extras['_patente']);}
    if(empty($nome)||strlen($nome)<3)$errors[]="Nome obrigatório.";
    if(empty($errors)&&!empty($whatsapp)){$dupSql="SELECT id,nome FROM participantes WHERE evento_id=? AND whatsapp=?";$dupParams=[$eid,$whatsapp];if($action=='edit'){$dupSql.=" AND id!=?";$dupParams[]=$_POST['id'];}$dupChk=$pdo->prepare($dupSql);$dupChk->execute($dupParams);$dupRow=$dupChk->fetch();if($dupRow)$errors[]="WhatsApp já cadastrado neste evento! (Nome: ".$dupRow['nome'].")";}
    if(empty($errors)){
        try{$ej=json_encode($extras,JSON_UNESCAPED_UNICODE);
            $cols=array_column($pdo->query("SHOW COLUMNS FROM participantes")->fetchAll(),'Field');$hasVid=in_array('video_url',$cols);
            if($action=='add'){
                $sql="INSERT INTO participantes (evento_id,nome,whatsapp,instagram,endereco,cidade,estado,cep,observacoes,campos_extras,aprovado,ativo".($hasVid?",video_url":"").") VALUES (?,?,?,?,?,?,?,?,?,?,1,?".($hasVid?",?":"").")";
                $p=[$eid,$nome,$whatsapp,$instagram,$endereco,$cidade,$estado,$cep,$observacoes,$ej,$ativo];if($hasVid)$p[]=$video_url;
                $pdo->prepare($sql)->execute($p);$pid=$pdo->lastInsertId();
                logAuditoria($pdo,'criar','participante',$pid,['nome'=>$nome]);
            } else {
                $id=$_POST['id'];
                $sql="UPDATE participantes SET nome=?,whatsapp=?,instagram=?,endereco=?,cidade=?,estado=?,cep=?,observacoes=?,campos_extras=?,ativo=?".($hasVid?",video_url=?":"")." WHERE id=? AND evento_id=?";
                $p=[$nome,$whatsapp,$instagram,$endereco,$cidade,$estado,$cep,$observacoes,$ej,$ativo];if($hasVid)$p[]=$video_url;$p[]=$id;$p[]=$eid;
                $pdo->prepare($sql)->execute($p);$pid=$id;
                logAuditoria($pdo,'editar','participante',$id,['nome'=>$nome]);
            }
            $fb=$_POST['foto_base64']??'';
            if(!empty($fb)&&strpos($fb,'data:image')===0){$chk=$pdo->prepare("SELECT id FROM fotos WHERE participante_id=?");$chk->execute([$pid]);if($chk->fetch())$pdo->prepare("UPDATE fotos SET dados=?,updated_at=NOW() WHERE participante_id=?")->execute([$fb,$pid]);else $pdo->prepare("INSERT INTO fotos (participante_id,dados) VALUES (?,?)")->execute([$pid,$fb]);}
            if(isset($_POST['remove_photo'])&&$_POST['remove_photo']=='1')$pdo->prepare("DELETE FROM fotos WHERE participante_id=?")->execute([$pid]);
            header("Location: participantes.php?message=".urlencode($action=='add'?"Cadastrado!":"Atualizado!"));exit();
        }catch(PDOException $e){$errors[]="Erro: ".$e->getMessage();}
    }
}
if($action=='delete'&&isset($_GET['id'])){$delId=(int)$_GET['id'];$dn=$pdo->prepare("SELECT nome FROM participantes WHERE id=?");$dn->execute([$delId]);$delNome=$dn->fetchColumn()?:'';$pdo->prepare("DELETE FROM fotos WHERE participante_id=?")->execute([$delId]);$pdo->prepare("DELETE FROM materiais WHERE participante_id=?")->execute([$delId]);$pdo->prepare("DELETE FROM participantes WHERE id=? AND evento_id=?")->execute([$delId,$eid]);logAuditoria($pdo,'excluir','participante',$delId,['nome'=>$delNome]);if(isset($_GET['ajax'])){header('Content-Type:application/json');echo json_encode(['ok'=>true]);exit();}header("Location: participantes.php?message=".urlencode("Excluído!"));exit();}
$search=$_GET['search']??'';$page=max(1,(int)($_GET['page']??1));$per_page=(int)($_GET['per_page']??25);$offset=($page-1)*$per_page;
$where="WHERE p.aprovado=1 AND p.evento_id=?";$params=[$eid];
if($search){$where.=" AND (p.nome LIKE ? OR p.whatsapp LIKE ? OR p.instagram LIKE ? OR p.campos_extras LIKE ?)";$l="%$search%";$params=array_merge($params,[$l,$l,$l,$l]);}
$s=$pdo->prepare("SELECT COUNT(*) FROM participantes p $where");$s->execute($params);$total_records=(int)$s->fetchColumn();$total_pages=ceil($total_records/$per_page);
$s=$pdo->prepare("SELECT p.*, (SELECT 1 FROM fotos WHERE participante_id=p.id LIMIT 1) as has_foto FROM participantes p $where ORDER BY p.nome LIMIT $per_page OFFSET $offset");$s->execute($params);$lista=$s->fetchAll();
$foto_atual=null;if($participante){$s=$pdo->prepare("SELECT * FROM fotos WHERE participante_id=? LIMIT 1");$s->execute([$participante['id']]);$foto_atual=$s->fetch();}
$extras_vals=$participante?(json_decode($participante['campos_extras']??'{}',true)?:[]):[];
if(isset($_GET['message']))$message=$_GET['message'];
$slug_ev=$evento_atual['slug']??null;$base=(isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on'?"https":"http")."://".$_SERVER['HTTP_HOST'].rtrim(dirname($_SERVER['SCRIPT_NAME']),'/').'/';
$link_cadastro=$base."inscricao.php?evento=".($slug_ev?:$evento_atual['id']);

// ── Totais de acompanhantes ───────────────────────────────────────────────────
$total_acomps = 0;
if($action=='list'){
    try{
        $sa=$pdo->prepare("SELECT acompanhantes FROM participantes WHERE evento_id=? AND aprovado=1 AND acompanhantes IS NOT NULL AND acompanhantes!='' AND acompanhantes!='[]'");
        $sa->execute([$eid]);
        foreach($sa->fetchAll() as $row){$arr=json_decode($row['acompanhantes'],true);if(is_array($arr))$total_acomps+=count($arr);}
    }catch(Exception $e){}
}
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Participantes</title><?php renderCSS();?></head><body>
<?php renderHeader('participantes');?>
<?php if($message):?><div class="content-container"><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?=htmlspecialchars($message)?></div></div><?php endif;?>

<?php if($action=='list'):?>
<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-users"></i> Participantes — <?=htmlspecialchars($evento_atual['nome'])?></h1>
        <!-- Resumo rápido de totais -->
        <div style="display:flex;gap:12px;margin-top:10px;flex-wrap:wrap">
            <span style="background:#dbeafe;color:#1e40af;padding:5px 14px;border-radius:20px;font-size:13px;font-weight:700">
                <i class="fas fa-users"></i> <?=$total_records?> participantes
            </span>
            <?php if($total_acomps>0):?>
            <span style="background:#fff7ed;color:#d97706;padding:5px 14px;border-radius:20px;font-size:13px;font-weight:700;border:1px solid #fed7aa">
                <i class="fas fa-user-friends"></i> <?=$total_acomps?> acompanhantes
            </span>
            <span style="background:#f0fdf4;color:#16a34a;padding:5px 14px;border-radius:20px;font-size:13px;font-weight:700;border:1px solid #bbf7d0">
                <i class="fas fa-users"></i> <?=($total_records+$total_acomps)?> total geral
            </span>
            <?php endif;?>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="participantes.php?action=add" class="btn btn-success"><i class="fas fa-plus-circle"></i> Adicionar</a>
        <button class="btn" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);color:white;border:none" data-bs-toggle="modal" data-bs-target="#mLink"><i class="fas fa-share-alt"></i> Link</button>
        <a href="export.php?format=excel" class="btn btn-info"><i class="fas fa-download"></i> Excel</a>
        <a href="convites_evento.php" class="btn" style="background:linear-gradient(135deg,#7c3aed,#6366f1);color:white"><i class="fas fa-envelope-open-text"></i> Convites</a>
    </div>
</div>
<div style="background:white;padding:16px 24px;margin:0 24px 16px;border-radius:12px;border:1px solid #dbeafe">
<form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:end">
<div style="flex:1;min-width:200px"><input type="text" name="search" class="form-control" placeholder="Buscar..." value="<?=htmlspecialchars($search)?>"></div>
<select name="per_page" class="form-select" style="width:auto" onchange="this.form.submit()"><option value="25" <?=$per_page==25?'selected':''?>>25</option><option value="50" <?=$per_page==50?'selected':''?>>50</option><option value="100" <?=$per_page==100?'selected':''?>>100</option></select>
<button class="btn btn-primary"><i class="fas fa-search"></i></button></form></div>
<!-- Abas de filtro -->
<div style="display:flex;gap:8px;padding:0 24px 12px;flex-wrap:wrap">
    <button class="tab-btn tab-active" data-tab="todos" onclick="filterTab('todos',this)">
        <i class="fas fa-users"></i> Todos <span class="tab-count"><?=($total_records+$total_acomps)?></span>
    </button>
    <button class="tab-btn" data-tab="titular" onclick="filterTab('titular',this)">
        <i class="fas fa-user"></i> Cadastrados <span class="tab-count"><?=$total_records?></span>
    </button>
    <?php if($total_acomps>0):?>
    <button class="tab-btn tab-acomp" data-tab="acompanhante" onclick="filterTab('acompanhante',this)">
        <i class="fas fa-user-friends"></i> Acompanhantes <span class="tab-count"><?=$total_acomps?></span>
    </button>
    <?php endif;?>
</div>
<style>
.tab-btn{display:inline-flex;align-items:center;gap:6px;padding:7px 16px;border-radius:20px;border:2px solid #e2e8f0;background:white;font-size:13px;font-weight:600;color:#64748b;cursor:pointer;transition:.2s}
.tab-btn:hover{border-color:#93c5fd;color:#1e40af}
.tab-btn.tab-active{background:#1e40af;color:white;border-color:#1e40af}
.tab-btn.tab-acomp.tab-active{background:#d97706;border-color:#d97706}
.tab-count{background:rgba(255,255,255,.25);padding:1px 7px;border-radius:10px;font-size:11px}
.tab-btn:not(.tab-active) .tab-count{background:#f1f5f9;color:#475569}
</style>
<div class="table-container">
    <div class="table-header">
        <h3 class="table-title" id="tabTitle">Todos</h3>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
            <span style="color:var(--gray);font-size:13px" id="tabInfo"><?=($total_records+$total_acomps)?> registros</span>
        </div>
    </div>
<div style="overflow-x:auto"><table class="table" style="table-layout:auto"><thead><tr>
<th style="min-width:200px">Participante</th><th style="min-width:120px">WhatsApp</th><th style="min-width:100px">Instagram</th>
<?php foreach(array_slice($campos_extras,0,2) as $ce):?><th><?=htmlspecialchars($ce['label'])?></th><?php endforeach;?>
<th class="text-center" style="width:130px">Ações</th></tr></thead><tbody>
<?php if(!empty($lista)):foreach($lista as $p):$ex=json_decode($p['campos_extras']??'{}',true)?:[];$hasFoto=!empty($p['has_foto']);
$acomps_p=[];try{$acomps_p=json_decode($p['acompanhantes']??'[]',true)?:[];}catch(Exception $e){}
?>
<tr data-row="titular">
<td><div style="display:flex;align-items:center;gap:10px">
<div style="width:36px;height:36px;min-width:36px;border-radius:50%;overflow:hidden;background:<?=$hasFoto?'#f0fdf4':'#e0f2fe'?>;display:flex;align-items:center;justify-content:center;border:2px solid <?=$hasFoto?'#bbf7d0':'#dbeafe'?>">
<?php if($hasFoto):?><img src="foto.php?id=<?=$p['id']?>" style="width:100%;height:100%;object-fit:cover" loading="lazy" onerror="this.parentElement.innerHTML='<i class=\'fas fa-user\' style=\'color:#94a3b8;font-size:14px\'></i>'"><?php else:?><i class="fas fa-user" style="color:#94a3b8;font-size:14px"></i><?php endif;?>
</div>
<div>
    <span class="fw-semibold"><?=htmlspecialchars(strtoupper($p['nome']))?></span>
    <?php if(count($acomps_p)>0):?>
    <span style="background:#fff7ed;color:#d97706;border:1px solid #fed7aa;padding:1px 7px;border-radius:10px;font-size:10px;font-weight:700;margin-left:6px"><i class="fas fa-user-friends"></i> +<?=count($acomps_p)?></span>
    <?php endif;?>
</div>
</div></td>
<td style="white-space:nowrap"><?=htmlspecialchars($p['whatsapp']??'-')?></td>
<td><?=!empty($p['instagram'])?'@'.htmlspecialchars(ltrim($p['instagram'],'@')):'-'?></td>
<?php foreach(array_slice($campos_extras,0,2) as $ce):?><td><?=htmlspecialchars($ex[$ce['nome']]??'-')?></td><?php endforeach;?>
<td><div class="action-buttons">
<a href="participante_view.php?id=<?=$p['id']?>" class="btn-action view"><i class="fas fa-eye"></i></a>
<a href="participantes.php?action=edit&id=<?=$p['id']?>" class="btn-action edit"><i class="fas fa-edit"></i></a>
<button onclick="Swal.fire({title:'Excluir?',icon:'warning',showCancelButton:true,confirmButtonColor:'#dc2626',confirmButtonText:'Excluir'}).then(r=>{if(r.isConfirmed)location='participantes.php?action=delete&id=<?=$p['id']?>'})" class="btn-action delete"><i class="fas fa-trash"></i></button>
</div></td></tr>
<?php foreach($acomps_p as $ai=>$ac):?>
<tr data-row="acompanhante" style="background:#fffbeb">
<td style="padding:5px 12px 5px 52px"><div style="display:flex;align-items:center;gap:8px">
    <i class="fas fa-level-up-alt fa-rotate-90" style="color:#d97706;font-size:10px"></i>
    <span style="color:#92400e;font-size:12px;font-weight:600"><?=htmlspecialchars(strtoupper($ac['nome']??''))?></span>
    <span style="font-size:10px;color:#d97706;background:#fef3c7;padding:1px 6px;border-radius:4px">acomp. de <?=htmlspecialchars(explode(' ',$p['nome'])[0])?></span>
</div></td>
<td style="color:#92400e;font-size:12px;padding:5px 12px"><?=htmlspecialchars($ac['whatsapp']??'-')?></td>
<td style="color:#92400e;font-size:12px;padding:5px 12px"><?=htmlspecialchars($ac['instagram']??'-')?></td>
<?php foreach(array_slice($campos_extras,0,2) as $ce):?><td style="font-size:12px;padding:5px 12px">-</td><?php endforeach;?>
<td></td></tr>
<?php endforeach;?>
<?php endforeach;else:?><tr><td colspan="<?=4+count(array_slice($campos_extras,0,2))?>"><div class="empty-state"><div class="empty-icon"><i class="fas fa-users-slash"></i></div><p class="empty-text">Nenhum participante</p></div></td></tr><?php endif;?>
</tbody></table></div>
<?php if($total_pages>1):?><div class="pagination-container"><div style="color:var(--gray)"><?=min($offset+1,$total_records)?> a <?=min($offset+$per_page,$total_records)?> de <?=$total_records?></div><nav><ul class="pagination"><?php for($i=max(1,$page-2);$i<=min($total_pages,$page+2);$i++):?><li class="page-item <?=$i==$page?'active':''?>"><a class="page-link" href="?page=<?=$i?><?=$search?"&search=$search":''?><?=$per_page!=25?"&per_page=$per_page":''?>"><?=$i?></a></li><?php endfor;?></ul></nav></div><?php endif;?>
</div>
<?php $pQr='https://api.qrserver.com/v1/create-qr-code/?size=250x250&data='.urlencode($link_cadastro).'&bgcolor=ffffff&color=000000&format=png&margin=10';?>
<div class="modal fade" id="mLink" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content" style="border-radius:16px;border:none"><div class="modal-header" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);color:white;border-radius:16px 16px 0 0"><h5 class="modal-title"><i class="fas fa-link"></i> Link</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body p-4 text-center">
<img src="<?=$pQr?>" width="180" height="180" alt="QR" style="border-radius:12px;border:3px solid #e0f2fe;margin-bottom:12px" id="pQrImg">
<p class="text-muted small mb-2">Escaneie ou copie o link</p>
<div class="input-group mb-3"><input type="text" class="form-control bg-light" id="lI" value="<?=$link_cadastro?>" readonly><button class="btn btn-primary" onclick="navigator.clipboard.writeText(document.getElementById('lI').value).then(()=>Swal.fire({icon:'success',title:'Copiado!',timer:1500,showConfirmButton:false}))"><i class="fas fa-copy"></i></button></div>
<div class="d-flex gap-2 justify-content-center"><button onclick="(function(){const a=document.createElement('a');a.href=document.getElementById('pQrImg').src;a.download='qrcode.png';a.click()})()" class="btn btn-sm btn-outline-primary"><i class="fas fa-download"></i> Baixar QR</button></div>
</div></div></div></div>

<?php elseif($action=='add'||$action=='edit'):?>
<div class="content-container"><div class="form-card">
<h2 style="color:var(--primary);margin-bottom:24px;padding-bottom:16px;border-bottom:2px solid #dbeafe"><i class="fas <?=$action=='add'?'fa-user-plus':'fa-user-edit'?>"></i> <?=$action=='add'?'Adicionar':'Editar'?></h2>
<?php if(!empty($errors)):?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e):?><li><?=htmlspecialchars($e)?></li><?php endforeach;?></ul></div><?php endif;?>
<form method="POST" enctype="multipart/form-data">
<?php if($action=='edit'):?><input type="hidden" name="id" value="<?=$participante['id']?>"><?php endif;?>
<div style="text-align:center;margin-bottom:24px"><div id="pP" style="width:100px;height:100px;border-radius:50%;background:#e0f2fe;margin:8px auto;display:flex;align-items:center;justify-content:center;overflow:hidden;border:3px solid white;box-shadow:0 4px 6px rgba(0,0,0,.1);cursor:pointer" onclick="document.getElementById('fI').click()">
<?php if($foto_atual&&!empty($foto_atual['dados'])):?><img src="<?=$foto_atual['dados']?>" style="width:100%;height:100%;object-fit:cover"><?php else:?><i class="fas fa-camera" style="font-size:28px;color:#94a3b8"></i><?php endif;?>
</div><input type="file" id="fI" class="d-none" accept="image/*"><input type="hidden" name="foto_base64" id="fB"><input type="hidden" name="remove_photo" id="rP" value="0">
<button type="button" class="btn btn-sm btn-secondary" onclick="document.getElementById('fI').click()"><i class="fas fa-image"></i> Foto</button></div>
<div class="row g-3 mb-4">
<div class="col-md-8"><label class="form-label">Nome *</label><input type="text" name="nome" class="form-control" required value="<?=$participante?htmlspecialchars($participante['nome']):''?>"></div>
<div class="col-md-6"><label class="form-label">WhatsApp</label><input type="text" name="whatsapp" id="wa" class="form-control" placeholder="(00) 00000-0000" value="<?=$participante?htmlspecialchars($participante['whatsapp']??''):''?>"></div>
<div class="col-md-6"><label class="form-label">Instagram</label><div class="input-group"><span class="input-group-text">@</span><input type="text" name="instagram" class="form-control" value="<?=$participante?htmlspecialchars(ltrim($participante['instagram']??'','@')):''?>"></div></div>
</div>
<?php if(!empty($campos_extras)):?><h4 style="color:var(--primary);margin-bottom:16px"><i class="fas fa-list-check"></i> Dados do Evento</h4><div class="row g-3 mb-4">
<?php foreach($campos_extras as $ce):$val=$extras_vals[$ce['nome']]??'';?>
<div class="col-md-<?=($ce['tipo']??'text')=='select'?'4':'6'?>"><label class="form-label"><?=htmlspecialchars($ce['label'])?> <?=($ce['obrigatorio']??false)?'*':''?></label>
<?php if(($ce['tipo']??'text')=='select'&&!empty($ce['opcoes'])):?><select name="extra_<?=$ce['nome']?>" class="form-select" <?=($ce['obrigatorio']??false)?'required':''?>><option value="">Selecione...</option><?php foreach($ce['opcoes'] as $op):?><option value="<?=htmlspecialchars($op)?>" <?=$val==$op?'selected':''?>><?=htmlspecialchars($op)?></option><?php endforeach;?></select>
<?php else:?><input type="<?=$ce['tipo']??'text'?>" name="extra_<?=$ce['nome']?>" class="form-control" value="<?=htmlspecialchars($val)?>" <?=($ce['obrigatorio']??false)?'required':''?>><?php endif;?></div><?php endforeach;?></div><?php endif;?>
<?php
$cfEv=[];try{if(!empty($evento_atual['config_form']))$cfEv=json_decode($evento_atual['config_form'],true)?:[];}catch(Exception $e){}
$showMilitarAdm=($cfEv['show_militar']??'0')==='1';
$isMilitar=($extras_vals['_militar']??'')==='1';
if($showMilitarAdm):?>
<h4 style="color:#059669;margin-bottom:12px"><i class="fas fa-shield-halved"></i> Dados Militares</h4>
<input type="hidden" name="militar_enviado" value="1">
<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:16px;margin-bottom:20px">
<label style="display:flex;align-items:center;gap:10px;cursor:pointer;margin-bottom:12px">
    <input type="checkbox" name="is_militar" id="adm_is_militar" value="1" <?=$isMilitar?'checked':''?> style="width:20px;height:20px;accent-color:#10b981">
    <span style="font-weight:600;color:#065f46">É militar</span>
</label>
<div id="admMilitarFields" style="<?=$isMilitar?'':'display:none'?>;display:<?=$isMilitar?'grid':'none'?>;grid-template-columns:1fr 1fr;gap:12px">
<div><label class="form-label">Corporação</label>
<select name="corporacao" class="form-select">
<option value="">Selecione...</option>
<option value="Polícia Militar (PM)" <?=($extras_vals['_corporacao']??'')==='Polícia Militar (PM)'?'selected':''?>>Polícia Militar (PM)</option>
<option value="Bombeiro Militar (BM)" <?=($extras_vals['_corporacao']??'')==='Bombeiro Militar (BM)'?'selected':''?>>Bombeiro Militar (BM)</option>
</select></div>
<div><label class="form-label">Patente / Posto</label>
<select name="patente" class="form-select">
<option value="">Selecione...</option>
<?php foreach(['Coronel','Tenente-Coronel','Major','Capitão','1º Tenente','2º Tenente','Aspirante a Oficial','Subtenente','1º Sargento','2º Sargento','3º Sargento','Cabo','Soldado 1ª Classe','Soldado 2ª Classe'] as $pat):?>
<option value="<?=$pat?>" <?=($extras_vals['_patente']??'')===$pat?'selected':''?>><?=$pat?></option>
<?php endforeach;?>
</select></div>
</div>
</div>
<script>document.getElementById('adm_is_militar')?.addEventListener('change',function(){document.getElementById('admMilitarFields').style.display=this.checked?'grid':'none'});</script>
<?php endif;?>
<h4 style="color:var(--primary);margin-bottom:16px"><i class="fas fa-map-marker-alt"></i> Endereço</h4>
<div class="row g-3 mb-4">
<div class="col-md-3"><label class="form-label">CEP</label><input type="text" name="cep" id="cep" class="form-control" value="<?=$participante?htmlspecialchars($participante['cep']??''):''?>"></div>
<div class="col-md-9"><label class="form-label">Endereço</label><input type="text" name="endereco" id="endereco" class="form-control" value="<?=$participante?htmlspecialchars($participante['endereco']??''):''?>"></div>
<div class="col-md-6"><label class="form-label">Cidade</label><input type="text" name="cidade" id="cidade" class="form-control" value="<?=$participante?htmlspecialchars($participante['cidade']??''):''?>"></div>
<div class="col-md-6"><label class="form-label">Estado</label><select name="estado" id="estado" class="form-select"><option value="">UF</option><?php foreach(['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] as $u):?><option value="<?=$u?>" <?=($participante&&($participante['estado']??'')==$u)?'selected':''?>><?=$u?></option><?php endforeach;?></select></div>
</div>
<div class="mb-4"><label class="form-label">Observações</label><textarea name="observacoes" class="form-control" rows="3"><?=$participante?htmlspecialchars($participante['observacoes']??''):''?></textarea></div>
<div class="mb-4">
<h4 style="color:#7c3aed;margin-bottom:12px"><i class="fas fa-video"></i> Vídeo</h4>
<input type="hidden" name="remove_video" id="rmVid" value="0">
<?php $curVid=$participante?($participante['video_url']??''):'';$isLocal=$curVid&&!preg_match('/^https?:\/\//',$curVid);?>
<?php if($curVid):?>
<div id="curVidBox" style="background:#f0f9ff;border:1px solid #dbeafe;border-radius:10px;padding:12px;margin-bottom:12px">
<?php if($isLocal):?><video controls style="width:100%;max-height:200px;border-radius:8px;background:#000"><source src="<?=htmlspecialchars($curVid)?>"></video>
<?php else:?><div style="display:flex;align-items:center;gap:8px"><i class="fas fa-link" style="color:#7c3aed"></i><a href="<?=htmlspecialchars($curVid)?>" target="_blank" style="color:#7c3aed;word-break:break-all;font-size:13px"><?=htmlspecialchars(mb_strimwidth($curVid,0,60,'...'))?></a></div><?php endif;?>
<button type="button" class="btn btn-danger btn-sm mt-2" onclick="document.getElementById('rmVid').value='1';document.getElementById('curVidBox').style.display='none';document.getElementById('newVidBox').style.display='block'"><i class="fas fa-trash"></i> Remover vídeo</button>
</div><?php endif;?>
<div id="newVidBox" style="<?=$curVid?'display:none':''?>">
<div class="row g-3">
<div class="col-md-6"><label class="form-label"><i class="fas fa-upload" style="color:#7c3aed"></i> Enviar arquivo</label><input type="file" name="video_file" class="form-control" accept="video/*" id="admVidFile"><div style="font-size:11px;color:var(--gray);margin-top:4px">MP4, MOV, AVI, WebM — máx. 100MB</div></div>
<div class="col-md-6"><label class="form-label"><i class="fas fa-link" style="color:#7c3aed"></i> Ou cole um link</label><input type="url" name="video_link" class="form-control" placeholder="https://youtube.com/..." id="admVidLink"></div>
</div>
<div id="admVidPreview" style="display:none;margin-top:10px"><video id="admVidPlayer" controls style="width:100%;max-height:200px;border-radius:8px;background:#000"></video></div>
</div></div>
<div class="d-flex align-items-center gap-3 mb-4"><input type="checkbox" name="ativo" style="width:24px;height:24px" <?=(!$participante||($participante['ativo']??1))?'checked':''?>><label style="font-weight:600">Ativo</label></div>
<div class="d-flex gap-2"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button><a href="participantes.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancelar</a></div>
</form></div></div>
<?php endif;?>
<?php renderScripts();?>
<script>
document.getElementById('fI')?.addEventListener('change',function(e){const f=e.target.files[0];if(!f)return;const r=new FileReader();r.onload=function(ev){const img=new Image();img.onload=function(){const c=document.createElement('canvas');let w=img.width,h=img.height,m=800;if(w>m||h>m){if(w>h){h=Math.round(h*m/w);w=m}else{w=Math.round(w*m/h);h=m}}c.width=w;c.height=h;c.getContext('2d').drawImage(img,0,0,w,h);const b=c.toDataURL('image/jpeg',.8);document.getElementById('fB').value=b;document.getElementById('pP').innerHTML=`<img src="${b}" style="width:100%;height:100%;object-fit:cover">`};img.src=ev.target.result};r.readAsDataURL(f)});
document.getElementById('wa')?.addEventListener('input',function(e){let v=e.target.value.replace(/\D/g,'').substring(0,11);if(v.length>6)v='('+v.substring(0,2)+') '+v.substring(2,7)+'-'+v.substring(7);else if(v.length>2)v='('+v.substring(0,2)+') '+v.substring(2);else if(v.length>0)v='('+v;e.target.value=v});
document.getElementById('cep')?.addEventListener('input',function(e){let v=e.target.value.replace(/\D/g,'');if(v.length>8)v=v.slice(0,8);v=v.replace(/^(\d{5})(\d)/,'$1-$2');e.target.value=v;if(v.replace(/\D/g,'').length===8)fetch(`https://viacep.com.br/ws/${v.replace(/\D/g,'')}/json/`).then(r=>r.json()).then(d=>{if(!d.erro){document.getElementById('endereco').value=d.logradouro+(d.bairro?', '+d.bairro:'');document.getElementById('cidade').value=d.localidade;document.getElementById('estado').value=d.uf}}).catch(()=>{})});
document.getElementById('admVidFile')?.addEventListener('change',function(){const f=this.files[0];if(!f)return;if(f.size>100*1024*1024){alert('Vídeo muito grande! Máx 100MB');this.value='';return;}document.getElementById('admVidPlayer').src=URL.createObjectURL(f);document.getElementById('admVidPreview').style.display='block';document.getElementById('admVidLink').value='';});
function filterTab(type,btn){
    document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('tab-active'));
    btn.classList.add('tab-active');
    const rows=document.querySelectorAll('tbody tr[data-row]');
    let visible=0;
    rows.forEach(tr=>{
        if(type==='todos'||(tr.dataset.row===type)){tr.style.display='';visible++;}
        else tr.style.display='none';
    });
    const titles={todos:'Todos',titular:'Cadastrados',acompanhante:'Acompanhantes'};
    const t=document.getElementById('tabTitle');const info=document.getElementById('tabInfo');
    if(t)t.textContent=titles[type]||'';
    if(info)info.textContent=visible+' registro'+(visible!==1?'s':'');
}
</script></body></html>
