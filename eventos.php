<?php
require_once 'layout.php';
if(!isAdmin()){header('Location: index.php');exit();}
function gerarSlug($s){$s=mb_strtolower($s,'UTF-8');$s=preg_replace('/[áàãâä]/u','a',$s);$s=preg_replace('/[éèêë]/u','e',$s);$s=preg_replace('/[íìîï]/u','i',$s);$s=preg_replace('/[óòõôö]/u','o',$s);$s=preg_replace('/[úùûü]/u','u',$s);$s=preg_replace('/[ç]/u','c',$s);$s=preg_replace('/[^a-z0-9]+/','-',$s);return trim($s,'-');}
$action=$_GET['action']??'list';$message='';$error='';

// ── Toggle rápido ativo/inativo ──────────────────────────────────────────────
if($action=='toggle_ativo'&&isset($_GET['id'])){
    $id=(int)$_GET['id'];
    $s=$pdo->prepare("SELECT ativo,nome FROM eventos WHERE id=?");$s->execute([$id]);$ev=$s->fetch();
    if($ev){
        $novo=($ev['ativo']==1)?0:1;
        $pdo->prepare("UPDATE eventos SET ativo=? WHERE id=?")->execute([$novo,$id]);
        logAuditoria($pdo,'toggle_formulario','evento',$id,['ativo'=>$novo,'nome'=>$ev['nome']]);
        $msg=$novo?'Formulário aberto!':'Formulário fechado! Os dados continuam acessíveis no painel.';
        header("Location: eventos.php?message=".urlencode($msg));exit();
    }
}

if($_SERVER['REQUEST_METHOD']=='POST'){
    $nome=clean_input($_POST['nome']);$slug=clean_input($_POST['slug']??'');if(empty($slug))$slug=gerarSlug($nome);else $slug=gerarSlug($slug);
    $descricao=clean_input($_POST['descricao']??'');$data_inicio=$_POST['data_inicio']??null;$data_fim=$_POST['data_fim']??null;
    $local=clean_input($_POST['local']??'');$cor_tema=$_POST['cor_tema']??'#1e40af';$ativo_ev=isset($_POST['ativo'])?1:0;
    $cn=$_POST['campo_nome']??[];$cl=$_POST['campo_label']??[];$ct=$_POST['campo_tipo']??[];$co=$_POST['campo_obrigatorio']??[];$cop=$_POST['campo_opcoes']??[];
    $campos=[];for($i=0;$i<count($cn);$i++){if(empty($cn[$i]))continue;$c=['nome'=>preg_replace('/[^a-z0-9_]/','',strtolower($cn[$i])),'label'=>$cl[$i]??$cn[$i],'tipo'=>$ct[$i]??'text','obrigatorio'=>in_array((string)$i,$co)];if(!empty($cop[$i]))$c['opcoes']=array_map('trim',explode(',',$cop[$i]));$campos[]=$c;}
    $cj=json_encode($campos,JSON_UNESCAPED_UNICODE);$banner=$_POST['banner_base64']??'';$bannerMob=$_POST['banner_mobile_base64']??'';
    $config_form=json_encode([
        'titulo'=>clean_input($_POST['titulo_form']??''),
        'subtitulo'=>clean_input($_POST['subtitulo_form']??''),
        'titulo_size'=>$_POST['titulo_size']??'15',
        'subtitulo_size'=>$_POST['subtitulo_size']??'12',
        'titulo_font'=>$_POST['titulo_font']??'Inter',
        'bg_fullscreen'=>(($_POST['layout_desktop']??'split')==='bg')?'1':'0',
        'card_overlap'=>$_POST['card_overlap']??'20',
        'card_overlap_mob'=>$_POST['card_overlap_mob']??'20',
        'bg_pos_y'=>$_POST['bg_pos_y']??'30',
        'bg_overlay'=>$_POST['bg_overlay']??'0.3',
        'poster_h_desktop'=>$_POST['poster_h_desktop']??'450',
        'bg_pos_y_mob'=>$_POST['bg_pos_y_mob']??'30',
        'bg_overlay_mob'=>$_POST['bg_overlay_mob']??'0.3',
        'poster_h_mobile'=>$_POST['poster_h_mobile']??'280',
        'show_title'=>isset($_POST['show_title'])?'1':'0',
        'show_foto'=>isset($_POST['show_foto'])?'1':'0',
        'show_video'=>isset($_POST['show_video'])?'1':'0',
        'req_foto'=>isset($_POST['req_foto'])?'1':'0',
        'req_video'=>isset($_POST['req_video'])?'1':'0',
        'logo_bombeiro'=>isset($_POST['logo_bombeiro'])?'1':'0',
        'logo_policia'=>isset($_POST['logo_policia'])?'1':'0',
        'logo_assego'=>isset($_POST['logo_assego'])?'1':'0',
        'logo_sergio'=>isset($_POST['logo_sergio'])?'1':'0',
        'logo_size'=>$_POST['logo_size']??'50',
        'show_acompanhantes'=>isset($_POST['show_acompanhantes'])?'1':'0',
        'max_acompanhantes'=>(isset($_POST['acomp_sem_limite'])&&$_POST['acomp_sem_limite']==='1')?'0':($_POST['max_acompanhantes']??'3'),
        'acomp_whatsapp'=>isset($_POST['acomp_whatsapp'])?'1':'0',
        'acomp_cpf'=>isset($_POST['acomp_cpf'])?'1':'0',
        'acomp_instagram'=>isset($_POST['acomp_instagram'])?'1':'0',
        'acomp_endereco'=>isset($_POST['acomp_endereco'])?'1':'0',
        'auto_aprovacao'=>isset($_POST['auto_aprovacao'])?'1':'0',
        'show_militar'=>isset($_POST['show_militar'])?'1':'0',
        'militar_acomp'=>isset($_POST['militar_acomp'])?'1':'0',
        'layout_desktop'=>$_POST['layout_desktop']??'normal',
    ],JSON_UNESCAPED_UNICODE);
    $logoDir=__DIR__.'/uploads/logos';if(!is_dir($logoDir))mkdir($logoDir,0755,true);
    $eid_logo=$action=='edit'?$_POST['id']:'new';
    for($li=1;$li<=2;$li++){
        if(!empty($_FILES["logo_custom{$li}"]['name'])&&$_FILES["logo_custom{$li}"]['error']===UPLOAD_ERR_OK){
            $lf=$_FILES["logo_custom{$li}"];$lext=strtolower(pathinfo($lf['name'],PATHINFO_EXTENSION));
            if(in_array($lext,['png','jpg','jpeg','gif','svg','webp'])){$lname="logo_ev{$eid_logo}_custom{$li}.{$lext}";move_uploaded_file($lf['tmp_name'],$logoDir.'/'.$lname);}
        }
        if(isset($_POST["remove_logo_custom{$li}"])&&$_POST["remove_logo_custom{$li}"]=='1'){foreach(glob($logoDir."/logo_ev{$eid_logo}_custom{$li}.*") as $old)@unlink($old);}
    }
    try{
        $cols=array_column($pdo->query("SHOW COLUMNS FROM eventos")->fetchAll(),'Field');$hs=in_array('slug',$cols);$hc=in_array('config_form',$cols);
        if($action=='add'){$sc="nome,descricao,data_inicio,data_fim,local,cor_tema,campos_extras,banner_base64,ativo";$sv="?,?,?,?,?,?,?,?,?";$p=[$nome,$descricao,$data_inicio?:null,$data_fim?:null,$local,$cor_tema,$cj,$banner?:null,$ativo_ev];if($hs){$sc.=",slug";$sv.=",?";$p[]=$slug;}if($hc){$sc.=",config_form";$sv.=",?";$p[]=$config_form;}$pdo->prepare("INSERT INTO eventos ($sc) VALUES ($sv)")->execute($p);$newId=$pdo->lastInsertId();
            foreach(glob($logoDir."/logo_evnew_custom*") as $old){$nw=str_replace('evnew','ev'.$newId,$old);rename($old,$nw);}
            logAuditoria($pdo,'criar','evento',$newId,['nome'=>$nome]);$message="Criado!";}
        else{$id=$_POST['id'];$set="nome=?,descricao=?,data_inicio=?,data_fim=?,local=?,cor_tema=?,campos_extras=?,ativo=?";$p=[$nome,$descricao,$data_inicio?:null,$data_fim?:null,$local,$cor_tema,$cj,$ativo_ev];if($hs){$set.=",slug=?";$p[]=$slug;}if($hc){$set.=",config_form=?";$p[]=$config_form;}$p[]=$id;$pdo->prepare("UPDATE eventos SET $set WHERE id=?")->execute($p);
            if(!empty($banner))$pdo->prepare("UPDATE eventos SET banner_base64=? WHERE id=?")->execute([$banner,$id]);
            $hm=in_array('banner_mobile_base64',$cols);if($hm&&!empty($bannerMob))$pdo->prepare("UPDATE eventos SET banner_mobile_base64=? WHERE id=?")->execute([$bannerMob,$id]);
            logAuditoria($pdo,'editar','evento',$id,['nome'=>$nome,'ativo'=>$ativo_ev]);$message="Atualizado!";}
        header("Location: eventos.php?message=".urlencode($message));exit();
    }catch(PDOException $e){$error="Erro: ".$e->getMessage();}
}
if($action=='delete'&&isset($_GET['id'])){$pdo->prepare("DELETE FROM participantes WHERE evento_id=?")->execute([$_GET['id']]);$pdo->prepare("DELETE FROM eventos WHERE id=?")->execute([$_GET['id']]);header("Location: eventos.php?message=".urlencode("Excluído!"));exit();}
$evento=null;if($action=='edit'&&isset($_GET['id'])){$s=$pdo->prepare("SELECT * FROM eventos WHERE id=?");$s->execute([$_GET['id']]);$evento=$s->fetch();}
// ── Lista TODOS os eventos (incluindo inativos) para o admin ver tudo ────────
$eventos_list=$pdo->query("SELECT e.id,e.nome,e.slug,e.cor_tema,e.ativo,e.created_at,(e.banner_base64 IS NOT NULL AND e.banner_base64!='') as has_banner,(SELECT COUNT(*) FROM participantes WHERE evento_id=e.id AND aprovado=1) as total_part,(SELECT COUNT(*) FROM participantes WHERE evento_id=e.id AND aprovado=0) as total_pend FROM eventos e ORDER BY e.ativo DESC,e.created_at DESC")->fetchAll();
if(isset($_GET['message']))$message=$_GET['message'];$base=(isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on'?"https":"http")."://".$_SERVER['HTTP_HOST'].rtrim(dirname($_SERVER['SCRIPT_NAME']),'/').'/';
$cf=[];if($evento&&isset($evento['config_form']))$cf=json_decode($evento['config_form'],true)?:[];
function qrUrl($url,$size=300){return 'https://api.qrserver.com/v1/create-qr-code/?size='.$size.'x'.$size.'&data='.urlencode($url).'&bgcolor=ffffff&color=000000&format=png&margin=10';}
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Eventos - ASSEGO</title><?php renderCSS();?>
<style>
<?php $pvCor=$evento?$evento['cor_tema']:'#1e40af';?>

/* Preview — escopado aos wrappers, não vaza para o admin */
.preview-frame{width:360px;margin:0 auto;border-radius:20px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.3);border:3px solid #334155}
#pvMobWrap.preview-frame              {background:<?=$pvCor?>}
#pvMobWrap .pv-form                   {background:<?=$pvCor?>;padding:16px}
#pvMobWrap .pv-logos                  {background:<?=$pvCor?>}
#pvMobWrap .pv-poster .pv-fade        {background:linear-gradient(transparent,<?=$pvCor?>)}
#pvDeskWrap                           {background:<?=$pvCor?>}
#pvDeskWrap .pv-logos                 {background:<?=$pvCor?>}
#pvDeskWrap .pv-poster .pv-fade       {background:linear-gradient(transparent,<?=$pvCor?>)}

.pv-poster{position:relative;overflow:hidden;height:180px;background:#0a0a2e}
.pv-poster img{width:100%;height:100%;object-fit:cover;display:block;transition:.3s}
.pv-poster .pv-ov{position:absolute;inset:0;transition:.3s}
.pv-poster .pv-title{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;z-index:2;color:white;font-weight:900;text-transform:uppercase;letter-spacing:2px;text-shadow:0 3px 15px rgba(0,0,0,.7);text-align:center;padding:10px;font-size:20px;line-height:1.1}
.pv-poster .pv-title small{font-size:10px;font-weight:400;opacity:.7;letter-spacing:0;text-transform:none;margin-top:4px}
.pv-poster .pv-fade{position:absolute;bottom:0;left:0;right:0;height:40px;z-index:2}
.pv-form-card{background:rgba(0,0,0,.45);border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:16px;margin-top:-24px;position:relative;z-index:3;backdrop-filter:blur(8px)}
.pv-ftitle{text-align:center;margin-bottom:12px;padding-bottom:10px;border-bottom:1px solid rgba(255,255,255,.08)}
.pv-ftitle h3{color:#f59e0b;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1.5px}
.pv-ftitle p{color:rgba(255,255,255,.4);font-size:9px;margin-top:3px}
.pv-field{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);border-radius:6px;padding:8px 10px;margin-bottom:6px;color:rgba(255,255,255,.25);font-size:10px;display:flex;align-items:center;gap:6px}
.pv-field i{color:#f59e0b;font-size:9px}
.pv-row{display:grid;grid-template-columns:1fr 1fr;gap:6px}
.pv-btn{background:linear-gradient(135deg,#f59e0b,#d97706);color:#0a0a1a;padding:10px;border-radius:8px;font-weight:900;font-size:12px;text-transform:uppercase;letter-spacing:2px;text-align:center;margin-top:10px}
.pv-logos{display:flex;justify-content:center;gap:8px;padding:10px;align-items:center;flex-wrap:wrap}
.pv-logos img.pvl{height:28px;width:auto;max-width:70px;object-fit:contain;filter:drop-shadow(0 1px 3px rgba(0,0,0,.5));transition:.3s}

.rg{display:flex;align-items:center;gap:12px}.rg input[type=range]{flex:1;accent-color:#4f46e5}.rg span{min-width:50px;text-align:right;font-weight:600;color:var(--primary)}
.sticky-preview{position:sticky;top:80px}
.qr-box{background:white;border-radius:16px;padding:24px;text-align:center;box-shadow:var(--shadow-md);border:1px solid #dbeafe;margin-top:20px}

/* ── Accordion sections ─────────────────────────────────────────────── */
.ev-section{border-radius:12px;border:1px solid #e2e8f0;margin-bottom:10px;overflow:hidden;transition:.2s}
.ev-section.open{border-color:#bfdbfe;box-shadow:0 2px 8px rgba(30,64,175,.08)}
.ev-sec-hdr{display:flex;align-items:center;gap:12px;padding:14px 18px;cursor:pointer;background:#f8fafc;user-select:none;transition:.15s}
.ev-sec-hdr:hover{background:#f0f9ff}
.ev-section.open .ev-sec-hdr{background:linear-gradient(135deg,#eff6ff,#f0fdf4)}
.ev-sec-icon{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0;transition:.2s}
.ev-sec-title{flex:1;font-weight:700;font-size:14px;color:#1e293b}
.ev-sec-sub{font-size:11px;color:var(--gray);font-weight:400;margin-top:1px}
.ev-sec-arrow{font-size:12px;color:var(--gray);transition:transform .25s}
.ev-section.open .ev-sec-arrow{transform:rotate(180deg)}
.ev-sec-body{padding:0 18px;max-height:0;overflow:hidden;transition:max-height .3s ease,padding .3s ease}
.ev-section.open .ev-sec-body{max-height:9999px;padding:16px 18px 20px}

/* ── Sticky save bar ────────────────────────────────────────────────── */
.ev-save-bar{position:sticky;top:64px;z-index:100;background:#fff;border-bottom:2px solid #e2e8f0;padding:8px 0 10px;margin:-1px 0 18px;display:flex;align-items:center;justify-content:space-between;gap:16px;box-shadow:0 3px 12px rgba(0,0,0,.08)}
.ev-save-bar-info{min-width:0;flex:1;overflow:hidden}
.ev-save-bar-label{font-size:10px;font-weight:600;color:var(--gray);text-transform:uppercase;letter-spacing:.8px;margin-bottom:1px}
.ev-save-bar-name{font-weight:800;font-size:16px;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ev-save-bar .btn-save-main{background:linear-gradient(135deg,#1e40af,#3b82f6);color:white;border:none;border-radius:10px;padding:9px 20px;font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:7px;transition:.15s;white-space:nowrap}
.ev-save-bar .btn-save-main:hover{transform:translateY(-1px);box-shadow:0 4px 14px rgba(30,64,175,.35)}
.ev-save-bar .btn-cancel{color:var(--gray);font-size:13px;font-weight:600;text-decoration:none;padding:8px 12px;border-radius:8px;transition:.15s;white-space:nowrap}
.ev-save-bar .btn-cancel:hover{background:#f1f5f9;color:#475569}
.qr-box img{border-radius:12px;border:3px solid #e0f2fe}
.qr-box .qr-label{color:var(--primary);font-size:13px;font-weight:700;margin-top:12px}
.qr-box .qr-url{color:var(--gray);font-size:11px;word-break:break-all;margin-top:4px}
.qr-modal-body{text-align:center;padding:30px}
.qr-modal-body img{border-radius:16px;border:4px solid #dbeafe;margin-bottom:16px}
.qr-modal-body .qr-ev-name{font-size:20px;font-weight:700;color:var(--primary);margin-bottom:4px}
.qr-modal-body .qr-ev-url{font-size:12px;color:var(--gray);word-break:break-all;margin-bottom:16px}

/* ── Badge de status do formulário ─────────────────────────────────── */
.form-status-badge{display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:20px;font-size:12px;font-weight:700;margin-bottom:10px}
.form-status-badge.aberto{background:#dcfce7;color:#15803d;border:1px solid #86efac}
.form-status-badge.aberto::before{content:'';width:8px;height:8px;border-radius:50%;background:#16a34a;display:inline-block;animation:pulse-green 2s infinite}
.form-status-badge.fechado{background:#fee2e2;color:#b91c1c;border:1px solid #fca5a5}
.form-status-badge.fechado::before{content:'';width:8px;height:8px;border-radius:50%;background:#dc2626;display:inline-block}
@keyframes pulse-green{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.6;transform:scale(1.3)}}

/* ── Toggle de formulário ───────────────────────────────────────────── */
.btn-toggle-form{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:10px;font-size:12px;font-weight:700;border:none;cursor:pointer;transition:.2s}
.btn-toggle-form.abrir{background:linear-gradient(135deg,#059669,#10b981);color:white}
.btn-toggle-form.fechar{background:linear-gradient(135deg,#dc2626,#ef4444);color:white}
.btn-toggle-form:hover{transform:translateY(-1px);opacity:.9}

/* Card de evento fechado fica levemente acinzentado */
.form-card.ev-fechado{opacity:.85;border-left:4px solid #fca5a5}
</style></head><body>
<?php renderHeader('eventos');?>
<?php if($message):?><div class="content-container"><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?=htmlspecialchars($message)?></div></div><?php endif;?>
<?php if($error):?><div class="content-container"><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?=htmlspecialchars($error)?></div></div><?php endif;?>

<?php if($action=='list'):?>
<div class="page-header">
    <h1 class="page-title"><i class="fas fa-calendar-alt"></i> Eventos</h1>
    <a href="eventos.php?action=add" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Novo</a>
</div>
<div class="content-container">

<!-- Legenda rápida -->
<div style="display:flex;gap:16px;margin-bottom:20px;flex-wrap:wrap;align-items:center">
    <span style="font-size:12px;color:var(--gray);font-weight:600"><i class="fas fa-info-circle" style="color:#3b82f6"></i> Status do formulário público:</span>
    <span class="form-status-badge aberto">Formulário Aberto</span>
    <span class="form-status-badge fechado">Formulário Fechado</span>
    <span style="font-size:11px;color:var(--gray)">— Fechar o formulário <strong>não apaga os dados</strong>, apenas impede novos cadastros.</span>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(380px,1fr));gap:20px">
<?php foreach($eventos_list as $ev):
    $link=$base.($ev['slug']??$ev['id']);
    $qr=qrUrl($link,250);
    $aberto=($ev['ativo']==1);
?>
<div class="form-card <?=$aberto?'':'ev-fechado'?>" style="padding:0;overflow:hidden">

    <!-- Banner ou cor -->
    <?php if($ev['has_banner']):?>
        <div style="height:120px;overflow:hidden;position:relative">
            <img src="banner.php?id=<?=$ev['id']?>" style="width:100%;height:100%;object-fit:cover">
            <!-- Status badge flutuante sobre o banner -->
            <div style="position:absolute;top:10px;right:10px">
                <span class="form-status-badge <?=$aberto?'aberto':'fechado'?>" style="box-shadow:0 2px 8px rgba(0,0,0,.3)">
                    <?=$aberto?'Formulário Aberto':'Formulário Fechado'?>
                </span>
            </div>
        </div>
    <?php else:?>
        <div style="height:80px;background:linear-gradient(135deg,<?=$ev['cor_tema']?:'#1e40af'?>,#3b82f6);display:flex;align-items:center;justify-content:between;padding:0 16px;position:relative">
            <span style="color:white;font-size:18px;font-weight:700;flex:1"><?=htmlspecialchars($ev['nome'])?></span>
            <span class="form-status-badge <?=$aberto?'aberto':'fechado'?>" style="margin:0;background:rgba(255,255,255,.9);flex-shrink:0">
                <?=$aberto?'Aberto':'Fechado'?>
            </span>
        </div>
    <?php endif;?>

    <div style="padding:16px 20px 20px">
        <!-- Nome -->
        <div style="margin-bottom:8px">
            <h4 style="color:var(--primary);margin:0"><?=htmlspecialchars($ev['nome'])?></h4>
        </div>

        <!-- Contadores -->
        <div style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap">
            <span class="badge badge-info"><i class="fas fa-users"></i> <?=$ev['total_part']?> cadastrados</span>
            <?php if($ev['total_pend']>0):?><span class="badge" style="background:#fef3c7;color:#92400e"><i class="fas fa-clock"></i> <?=$ev['total_pend']?> pendentes</span><?php endif;?>
            <?php if(!$aberto&&$ev['total_part']>0):?>
                <span class="badge" style="background:#f0f9ff;color:#1e40af;border:1px solid #dbeafe"><i class="fas fa-database"></i> Dados preservados</span>
            <?php endif;?>
        </div>

        <!-- Link -->
        <div style="background:#f0f9ff;border:1px solid #dbeafe;border-radius:8px;padding:8px 12px;margin-bottom:12px;display:flex;align-items:center;gap:8px">
            <code style="font-size:11px;color:var(--primary);flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=$link?></code>
            <button onclick="navigator.clipboard.writeText('<?=$link?>').then(()=>Swal.fire({icon:'success',title:'Copiado!',timer:1500,showConfirmButton:false}))" style="background:var(--primary);color:white;border:none;border-radius:6px;padding:4px 8px;cursor:pointer;font-size:11px"><i class="fas fa-copy"></i></button>
        </div>

        <!-- Botões de ação -->
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <a href="eventos.php?action=edit&id=<?=$ev['id']?>" class="btn btn-info btn-sm"><i class="fas fa-edit"></i> Editar</a>
            <button onclick="showQR('<?=htmlspecialchars($ev['nome'])?>','<?=$link?>','<?=$qr?>')" class="btn btn-sm" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);color:white;border:none"><i class="fas fa-qrcode"></i> QR Code</button>

            <!-- Toggle Abrir/Fechar formulário -->
            <?php if($aberto):?>
            <button onclick="confirmarToggle(<?=$ev['id']?>,'fechar','<?=htmlspecialchars(addslashes($ev['nome']))?>')"
                class="btn-toggle-form fechar" title="Fechar formulário público (dados preservados)">
                <i class="fas fa-lock"></i> Fechar Form
            </button>
            <?php else:?>
            <button onclick="confirmarToggle(<?=$ev['id']?>,'abrir','<?=htmlspecialchars(addslashes($ev['nome']))?>')"
                class="btn-toggle-form abrir" title="Reabrir formulário público">
                <i class="fas fa-lock-open"></i> Abrir Form
            </button>
            <?php endif;?>

            <button onclick="Swal.fire({title:'Excluir evento?',text:'Isso removerá TODOS os participantes permanentemente!',icon:'warning',showCancelButton:true,confirmButtonColor:'#dc2626',cancelButtonText:'Cancelar',confirmButtonText:'Excluir tudo'}).then(r=>{if(r.isConfirmed)location='eventos.php?action=delete&id=<?=$ev['id']?>'})" class="btn btn-danger btn-sm" title="Excluir permanentemente"><i class="fas fa-trash"></i></button>
            <a href="?set_evento=<?=$ev['id']?>" class="btn btn-success btn-sm"><i class="fas fa-arrow-right"></i> Acessar</a>
            <a href="scanner.php?evento=<?=$ev['id']?>" target="_blank" class="btn btn-sm" style="background:linear-gradient(135deg,#0f172a,#1e293b);color:white;border:none"><i class="fas fa-qrcode"></i> Scanner</a>
        </div>

        <?php if(!$aberto):?>
        <div style="margin-top:10px;padding:8px 12px;background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;font-size:11px;color:#92400e;display:flex;align-items:center;gap:6px">
            <i class="fas fa-info-circle"></i>
            <span>Formulário <strong>fechado</strong> — novos cadastros bloqueados. <strong>Dados e painel funcionando normalmente.</strong></span>
        </div>
        <?php endif;?>
    </div>
</div>
<?php endforeach;?></div></div>

<!-- Modal QR Code -->
<div class="modal fade" id="qrModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content" style="border-radius:20px;border:none;overflow:hidden">
<div class="modal-header" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);color:white;border:none"><h5 class="modal-title"><i class="fas fa-qrcode"></i> QR Code do Evento</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
<div class="qr-modal-body">
<img id="qrModalImg" src="" width="280" height="280" alt="QR Code">
<div class="qr-ev-name" id="qrModalName"></div>
<div class="qr-ev-url" id="qrModalUrl"></div>
<div class="d-flex gap-2 justify-content-center flex-wrap">
<button onclick="downloadQR()" class="btn btn-primary"><i class="fas fa-download"></i> Baixar QR</button>
<button onclick="navigator.clipboard.writeText(document.getElementById('qrModalUrl').textContent).then(()=>Swal.fire({icon:'success',title:'Link copiado!',timer:1500,showConfirmButton:false}))" class="btn btn-secondary"><i class="fas fa-copy"></i> Copiar Link</button>
<button onclick="printQR()" class="btn btn-sm" style="background:linear-gradient(135deg,#059669,#10b981);color:white;border:none"><i class="fas fa-print"></i> Imprimir</button>
</div>
</div></div></div></div>

<?php elseif($action=='add'||$action=='edit'):$ce=$evento?(json_decode($evento['campos_extras']??'[]',true)?:[]):[];
$showT=($cf['show_title']??'1')==='1'||!$evento;
$showF=($cf['show_foto']??'1')==='1'||!$evento;
$showV=($cf['show_video']??'0')==='1';
$editLink=$evento?$base."inscricao.php?evento=".($evento['slug']??$evento['id']):'';
$editQR=$editLink?qrUrl($editLink,300):'';
?>
<div class="content-container"><div class="row g-4">
<div class="col-lg-7"><div class="form-card" style="padding:20px 24px">

<form method="POST" id="evForm" enctype="multipart/form-data">
<?php if($action=='edit'):?><input type="hidden" name="id" value="<?=$evento['id']?>"><?php endif;?>

<!-- Barra sticky salvar -->
<div class="ev-save-bar">
  <div class="ev-save-bar-info">
    <div class="ev-save-bar-label"><?=$action=='add'?'Novo evento':'Editando evento'?></div>
    <div class="ev-save-bar-name" id="evSaveName"><?=$action=='add'?'Novo Evento':htmlspecialchars($evento['nome']??'')?></div>
  </div>
  <div class="d-flex align-items-center gap-2">
    <a href="eventos.php" class="btn-cancel">Cancelar</a>
    <button type="submit" class="btn-save-main"><i class="fas fa-save"></i> Salvar alterações</button>
  </div>
</div>

<script>function toggleSec(id){var s=document.getElementById(id);if(s)s.classList.toggle('open');}</script>

<!-- SEÇÃO 1: Dados -->
<div class="ev-section open" id="sec-dados">
<div class="ev-sec-hdr" onclick="toggleSec('sec-dados')">
  <div class="ev-sec-icon" style="background:#dbeafe;color:#1e40af"><i class="fas fa-info-circle"></i></div>
  <div><div class="ev-sec-title">Dados do Evento</div><div class="ev-sec-sub">Nome, data, local e configurações básicas</div></div>
  <i class="fas fa-chevron-down ev-sec-arrow"></i>
</div>
<div class="ev-sec-body">
<div class="row g-3 mb-3">
<div class="col-md-5"><label class="form-label">Nome *</label><input type="text" name="nome" id="evNome" class="form-control" required value="<?=$evento?htmlspecialchars($evento['nome']):''?>"></div>
<div class="col-md-4"><label class="form-label">Slug</label><input type="text" name="slug" class="form-control" value="<?=$evento?htmlspecialchars($evento['slug']??''):''?>" placeholder="auto"></div>
<div class="col-md-3"><label class="form-label">Cor</label><input type="color" name="cor_tema" id="evCor" class="form-control" value="<?=$evento?$evento['cor_tema']:'#1e40af'?>" style="height:44px"></div>
<div class="col-12"><label class="form-label">Descrição</label><textarea name="descricao" id="evDesc" class="form-control" rows="2"><?=$evento?htmlspecialchars($evento['descricao']):''?></textarea></div>
<div class="col-md-4"><label class="form-label">Data do Evento * <small style="color:var(--gray);font-weight:400">(será o PIN do scanner)</small></label><input type="date" name="data_inicio" class="form-control" required value="<?=$evento?$evento['data_inicio']:''?>"></div>
<div class="col-md-4"><label class="form-label">Data Fim</label><input type="date" name="data_fim" class="form-control" value="<?=$evento?$evento['data_fim']:''?>"></div>
<div class="col-md-4"><label class="form-label">Local</label><input type="text" name="local" class="form-control" value="<?=$evento?htmlspecialchars($evento['local']):''?>"></div>
<div class="col-12">
    <div style="padding:14px 16px;background:<?=($evento&&!$evento['ativo'])?'#fff7ed':'#f0fdf4'?>;border-radius:10px;border:1px solid <?=($evento&&!$evento['ativo'])?'#fed7aa':'#bbf7d0'?>">
        <label style="display:flex;align-items:center;gap:10px;font-weight:600;cursor:pointer">
            <input type="checkbox" name="ativo" style="width:22px;height:22px" <?=(!$evento||$evento['ativo'])?'checked':''?>>
            <div>
                <i class="fas <?=($evento&&!$evento['ativo'])?'fa-lock':'fa-lock-open'?>" style="color:<?=($evento&&!$evento['ativo'])?'#d97706':'#16a34a'?>"></i>
                Formulário Público Ativo
                <div style="font-size:11px;font-weight:400;color:var(--gray);margin-top:2px">Desmarcar fecha o formulário de inscrição — <strong>os dados são mantidos</strong></div>
            </div>
        </label>
    </div>
</div>
<div class="col-12">
    <?php $autoAprov=($cf['auto_aprovacao']??'0')==='1';?>
    <div style="padding:14px 16px;background:<?=$autoAprov?'#eff6ff':'#f8fafc'?>;border-radius:10px;border:1px solid <?=$autoAprov?'#bfdbfe':'#e2e8f0'?>">
        <label style="display:flex;align-items:center;gap:10px;font-weight:600;cursor:pointer">
            <input type="checkbox" name="auto_aprovacao" id="autoAprovacao" style="width:22px;height:22px" <?=$autoAprov?'checked':''?>>
            <div>
                <i class="fas fa-bolt" style="color:#3b82f6"></i>
                Cadastro Direto <span style="background:#dbeafe;color:#1e40af;font-size:11px;padding:1px 8px;border-radius:10px;font-weight:700;margin-left:4px">SEM APROVAÇÃO</span>
                <div style="font-size:11px;font-weight:400;color:var(--gray);margin-top:2px">Participantes são aprovados automaticamente ao se cadastrar — sem precisar de aprovação manual</div>
            </div>
        </label>
    </div>
</div>
</div>
<div class="col-12">
    <?php $showMilitar=($cf['show_militar']??'0')==='1';$militarAcomp=($cf['militar_acomp']??'0')==='1';?>
    <div style="padding:14px 16px;background:<?=$showMilitar?'#f0fdf4':'#f8fafc'?>;border-radius:10px;border:1px solid <?=$showMilitar?'#bbf7d0':'#e2e8f0'?>">
        <label style="display:flex;align-items:center;gap:10px;font-weight:600;cursor:pointer">
            <input type="checkbox" name="show_militar" id="showMilitarChk" style="width:22px;height:22px" <?=$showMilitar?'checked':''?>>
            <div>
                Dados Militares
                <div style="font-size:11px;font-weight:400;color:var(--gray);margin-top:2px">Participante pode informar se é militar — Corporação (PM/BM) e Patente/Posto</div>
            </div>
        </label>
        <div id="militarConfig" style="margin-top:12px;padding-top:12px;border-top:1px solid #bbf7d0;<?=$showMilitar?'':'display:none'?>">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;padding:8px 12px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;width:fit-content">
                <input type="checkbox" name="militar_acomp" style="width:18px;height:18px" <?=$militarAcomp?'checked':''?>>
                <i class="fas fa-user-friends" style="color:#059669"></i>
                <span style="font-weight:600">Acompanhantes também podem informar dados militares</span>
            </label>
        </div>
    </div>
</div>
</div><!-- /ev-sec-body --></div><!-- /sec-dados -->

<!-- SEÇÃO 2: Imagem -->
<div class="ev-section" id="sec-imagem">
<div class="ev-sec-hdr" onclick="toggleSec('sec-imagem')">
  <div class="ev-sec-icon" style="background:#fef3c7;color:#d97706"><i class="fas fa-image"></i></div>
  <div><div class="ev-sec-title">Imagem de Fundo</div><div class="ev-sec-sub">Banner, layout e ajustes visuais</div></div>
  <i class="fas fa-chevron-down ev-sec-arrow"></i>
</div>
<div class="ev-sec-body">
<?php
$layoutDesk=$cf['layout_desktop']??'split';
$bgFull=($cf['bg_fullscreen']??'0')==='1';
// compatibilidade: se era bg_fullscreen sem layout_desktop, mapeia para 'bg'
if($bgFull&&$layoutDesk==='normal')$layoutDesk='bg';
if($layoutDesk==='normal')$layoutDesk='split'; // legado → split
?>
<div style="margin-bottom:16px;padding:14px 16px;background:#eff6ff;border-radius:10px;border:1px solid #bfdbfe">
<div style="font-weight:700;color:#1e40af;margin-bottom:12px"><i class="fas fa-image" style="color:#3b82f6"></i> Layout da Imagem (PC)</div>
<div style="display:flex;gap:10px;flex-wrap:wrap">
<label style="flex:1;min-width:180px;display:flex;align-items:center;gap:10px;cursor:pointer;padding:12px 14px;border-radius:10px;border:2px solid <?=$layoutDesk==='split'?'#3b82f6':'#dbeafe'?>;background:<?=$layoutDesk==='split'?'#dbeafe':'white'?>">
  <input type="radio" name="layout_desktop" value="split" <?=$layoutDesk==='split'?'checked':''?> style="accent-color:#3b82f6;width:18px;height:18px">
  <div>
    <div style="font-weight:700;font-size:13px"><i class="fas fa-columns"></i> Imagem ao Lado</div>
    <div style="font-size:11px;color:var(--gray)">Banner à esquerda, formulário à direita</div>
  </div>
</label>
<label style="flex:1;min-width:180px;display:flex;align-items:center;gap:10px;cursor:pointer;padding:12px 14px;border-radius:10px;border:2px solid <?=$layoutDesk==='bg'?'#3b82f6':'#dbeafe'?>;background:<?=$layoutDesk==='bg'?'#dbeafe':'white'?>">
  <input type="radio" name="layout_desktop" value="bg" <?=$layoutDesk==='bg'?'checked':''?> style="accent-color:#3b82f6;width:18px;height:18px">
  <div>
    <div style="font-weight:700;font-size:13px"><i class="fas fa-expand"></i> Imagem de Fundo</div>
    <div style="font-size:11px;color:var(--gray)">Imagem cobre toda a página por trás do formulário</div>
  </div>
</label>
</div>
</div>
<div style="display:flex;gap:0;margin-bottom:16px">
<button type="button" class="btn btn-sm" id="tabDesk" onclick="switchImgTab('desk')" style="border-radius:8px 0 0 8px;background:#1e40af;color:white;border:1px solid #1e40af;padding:8px 20px;font-weight:700"><i class="fas fa-desktop"></i> Desktop</button>
<button type="button" class="btn btn-sm" id="tabMob" onclick="switchImgTab('mob')" style="border-radius:0 8px 8px 0;background:white;color:#1e40af;border:1px solid #dbeafe;padding:8px 20px;font-weight:700"><i class="fas fa-mobile-alt"></i> Celular</button>
</div>
<div id="imgDesk" class="row g-3 mb-4">
<div class="col-12"><label class="form-label"><i class="fas fa-desktop"></i> Imagem Desktop</label><input type="file" id="bgFile" class="form-control" accept="image/*"><input type="hidden" name="banner_base64" id="bgB64">
<?php if($evento&&$evento['banner_base64']):?><div style="margin-top:8px;border-radius:8px;overflow:hidden;max-height:100px"><img src="banner.php?id=<?=$evento['id']?>" style="width:100%;height:100px;object-fit:cover;border-radius:8px"></div><?php endif;?></div>
<div class="col-md-4"><label class="form-label">Posição Vertical</label><div class="rg"><input type="range" name="bg_pos_y" id="rPosY" min="0" max="100" value="<?=$cf['bg_pos_y']??'30'?>"><span id="vPosY"><?=$cf['bg_pos_y']??'30'?>%</span></div></div>
<div class="col-md-4"><label class="form-label">Escurecimento</label><div class="rg"><input type="range" name="bg_overlay" id="rOv" min="0" max="0.9" step="0.05" value="<?=$cf['bg_overlay']??'0.3'?>"><span id="vOv"><?=round(($cf['bg_overlay']??0.3)*100)?>%</span></div></div>
<div class="col-md-4"><label class="form-label">Altura (px)</label><div class="rg"><input type="range" name="poster_h_desktop" id="rHDesk" min="200" max="800" step="10" value="<?=$cf['poster_h_desktop']??'450'?>"><span id="vHDesk"><?=$cf['poster_h_desktop']??'450'?>px</span></div></div>
<div class="col-md-6"><label class="form-label">Sobreposição do Card</label><div class="rg"><input type="range" name="card_overlap" id="rOverlap" min="0" max="100" step="5" value="<?=$cf['card_overlap']??'20'?>"><span id="vOverlap"><?=$cf['card_overlap']??'20'?>px</span></div></div>
</div>
<div id="imgMob" class="row g-3 mb-4" style="display:none">
<div class="col-12"><label class="form-label"><i class="fas fa-mobile-alt"></i> Imagem Celular <small style="color:var(--gray)">(se vazio, usa a do Desktop)</small></label><input type="file" id="bgFileMob" class="form-control" accept="image/*"><input type="hidden" name="banner_mobile_base64" id="bgB64Mob">
<?php if($evento&&!empty($evento['banner_mobile_base64'])):?><div style="margin-top:8px;border-radius:8px;overflow:hidden;max-height:100px"><img src="banner.php?id=<?=$evento['id']?>&mob" style="width:100%;height:100px;object-fit:cover;border-radius:8px"></div><?php endif;?></div>
<div class="col-md-4"><label class="form-label">Posição Vertical</label><div class="rg"><input type="range" name="bg_pos_y_mob" id="rPosYMob" min="0" max="100" value="<?=$cf['bg_pos_y_mob']??($cf['bg_pos_y']??'30')?>"><span id="vPosYMob"><?=$cf['bg_pos_y_mob']??($cf['bg_pos_y']??'30')?>%</span></div></div>
<div class="col-md-4"><label class="form-label">Escurecimento</label><div class="rg"><input type="range" name="bg_overlay_mob" id="rOvMob" min="0" max="0.9" step="0.05" value="<?=$cf['bg_overlay_mob']??($cf['bg_overlay']??'0.3')?>"><span id="vOvMob"><?=round(($cf['bg_overlay_mob']??($cf['bg_overlay']??0.3))*100)?>%</span></div></div>
<div class="col-md-4"><label class="form-label">Altura (px)</label><div class="rg"><input type="range" name="poster_h_mobile" id="rHMob" min="150" max="600" step="10" value="<?=$cf['poster_h_mobile']??'280'?>"><span id="vHMob"><?=$cf['poster_h_mobile']??'280'?>px</span></div></div>
<div class="col-md-6"><label class="form-label">Sobreposição do Card</label><div class="rg"><input type="range" name="card_overlap_mob" id="rOverlapMob" min="0" max="100" step="5" value="<?=$cf['card_overlap_mob']??($cf['card_overlap']??'20')?>"><span id="vOverlapMob"><?=$cf['card_overlap_mob']??($cf['card_overlap']??'20')?>px</span></div></div>
</div>
</div><!-- /ev-sec-body --></div><!-- /sec-imagem -->

<!-- SEÇÃO 3: Textos -->
<div class="ev-section" id="sec-textos">
<div class="ev-sec-hdr" onclick="toggleSec('sec-textos')">
  <div class="ev-sec-icon" style="background:#f0fdf4;color:#059669"><i class="fas fa-pen-fancy"></i></div>
  <div><div class="ev-sec-title">Textos</div><div class="ev-sec-sub">Título, subtítulo e tipografia do formulário</div></div>
  <i class="fas fa-chevron-down ev-sec-arrow"></i>
</div>
<div class="ev-sec-body">
<div class="row g-3 mb-4">
<div class="col-12"><label style="display:flex;align-items:center;gap:8px;font-weight:600;cursor:pointer;margin-bottom:12px"><input type="checkbox" name="show_title" id="showTitle" style="width:22px;height:22px" <?=$showT?'checked':''?>> Exibir título do evento sobre a imagem</label></div>
<div class="col-md-5"><label class="form-label">Título do Formulário</label><input type="text" name="titulo_form" id="evTitulo" class="form-control" value="<?=htmlspecialchars($cf['titulo']??'')?>" placeholder="CADASTRE-SE PARA O..."></div>
<div class="col-md-5"><label class="form-label">Subtítulo</label><input type="text" name="subtitulo_form" id="evSub" class="form-control" value="<?=htmlspecialchars($cf['subtitulo']??'')?>" placeholder="Preencha seus dados abaixo"></div>
<div class="col-md-2"><label class="form-label">Fonte</label><select name="titulo_font" id="tituloFont" class="form-select" style="font-size:13px">
<?php $fonts=['Inter','Montserrat','Oswald','Playfair Display','Poppins','Raleway','Roboto','Bebas Neue','Russo One','Righteous'];$curFont=$cf['titulo_font']??'Inter';foreach($fonts as $f):?>
<option value="<?=$f?>" <?=$curFont===$f?'selected':''?> style="font-family:'<?=$f?>'"><?=$f?></option>
<?php endforeach;?></select></div>
<div class="col-md-6"><label class="form-label">Tamanho do Título (px)</label><div class="rg"><input type="range" name="titulo_size" id="rTitSize" min="10" max="28" value="<?=$cf['titulo_size']??'15'?>"><span id="vTitSize"><?=$cf['titulo_size']??'15'?>px</span></div></div>
<div class="col-md-6"><label class="form-label">Tamanho do Subtítulo (px)</label><div class="rg"><input type="range" name="subtitulo_size" id="rSubSize" min="9" max="20" value="<?=$cf['subtitulo_size']??'12'?>"><span id="vSubSize"><?=$cf['subtitulo_size']??'12'?>px</span></div></div>
</div>
</div><!-- /ev-sec-body --></div><!-- /sec-textos -->

<!-- SEÇÃO 4: Campos -->
<div class="ev-section" id="sec-campos">
<div class="ev-sec-hdr" onclick="toggleSec('sec-campos')">
  <div class="ev-sec-icon" style="background:#f5f3ff;color:#7c3aed"><i class="fas fa-sliders-h"></i></div>
  <div><div class="ev-sec-title">Campos do Formulário</div><div class="ev-sec-sub">Foto, vídeo e acompanhantes</div></div>
  <i class="fas fa-chevron-down ev-sec-arrow"></i>
</div>
<div class="ev-sec-body">
<?php $reqF=($cf['req_foto']??'0')==='1';$reqV=($cf['req_video']??'0')==='1';?>
<div class="row g-3 mb-4">
<div class="col-md-6"><div style="padding:14px 16px;background:#f0f9ff;border-radius:10px;border:1px solid #dbeafe">
<label style="display:flex;align-items:center;gap:10px;font-weight:600;cursor:pointer"><input type="checkbox" name="show_foto" id="showFoto" style="width:22px;height:22px" <?=$showF?'checked':''?>> <div><i class="fas fa-camera" style="color:#1e40af"></i> Exibir campo de Foto<div style="font-size:11px;color:var(--gray);font-weight:400">Permite enviar foto pelo formulário</div></div></label>
<label style="display:flex;align-items:center;gap:8px;margin-top:10px;padding-top:10px;border-top:1px solid #dbeafe;cursor:pointer;font-size:13px"><input type="checkbox" name="req_foto" style="width:18px;height:18px" <?=$reqF?'checked':''?>> <span style="color:#dc2626;font-weight:600">Obrigatório *</span></label>
</div></div>
<div class="col-md-6"><div style="padding:14px 16px;background:#f0f9ff;border-radius:10px;border:1px solid #dbeafe">
<label style="display:flex;align-items:center;gap:10px;font-weight:600;cursor:pointer"><input type="checkbox" name="show_video" id="showVideo" style="width:22px;height:22px" <?=$showV?'checked':''?>> <div><i class="fas fa-video" style="color:#7c3aed"></i> Exibir campo de Vídeo<div style="font-size:11px;color:var(--gray);font-weight:400">Permite enviar vídeo ou link</div></div></label>
<label style="display:flex;align-items:center;gap:8px;margin-top:10px;padding-top:10px;border-top:1px solid #dbeafe;cursor:pointer;font-size:13px"><input type="checkbox" name="req_video" style="width:18px;height:18px" <?=$reqV?'checked':''?>> <span style="color:#dc2626;font-weight:600">Obrigatório *</span></label>
</div></div>
</div>
<?php $showAcomp=($cf['show_acompanhantes']??'0')==='1';$maxAcomp=$cf['max_acompanhantes']??'3';
$acWa=($cf['acomp_whatsapp']??'1')==='1';$acCpf=($cf['acomp_cpf']??'0')==='1';$acIg=($cf['acomp_instagram']??'0')==='1';$acEnd=($cf['acomp_endereco']??'0')==='1';?>
<div class="row g-3 mb-4" style="margin-top:12px">
<div class="col-12"><div style="padding:14px 16px;background:#fffbeb;border-radius:10px;border:1px solid #fde68a">
<label style="display:flex;align-items:center;gap:10px;font-weight:600;cursor:pointer"><input type="checkbox" name="show_acompanhantes" id="showAcomp" style="width:22px;height:22px" <?=$showAcomp?'checked':''?>> <div><i class="fas fa-user-friends" style="color:#d97706"></i> Permitir Acompanhantes<div style="font-size:11px;color:var(--gray);font-weight:400">Participante pode adicionar acompanhantes no formulário</div></div></label>
<div id="acompConfig" style="margin-top:12px;padding-top:12px;border-top:1px solid #fde68a;<?=$showAcomp?'':'display:none'?>">
<label class="form-label">Máximo de Acompanhantes</label>
<div style="display:flex;align-items:center;gap:12px;max-width:400px">
<label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;white-space:nowrap"><input type="checkbox" id="acompSemLimite" style="width:18px;height:18px" <?=($maxAcomp==='0')?'checked':''?> onchange="document.getElementById('rMaxAcomp').disabled=this.checked;document.getElementById('rMaxAcomp').parentElement.style.opacity=this.checked?'0.3':'1'"> Sem limite</label>
<div class="rg" style="flex:1;<?=($maxAcomp==='0')?'opacity:0.3':''?>"><input type="range" name="max_acompanhantes" id="rMaxAcomp" min="1" max="10" value="<?=($maxAcomp==='0')?'5':$maxAcomp?>" <?=($maxAcomp==='0')?'disabled':''?>><span id="vMaxAcomp"><?=($maxAcomp==='0')?'5':$maxAcomp?></span></div>
</div>
<input type="hidden" name="acomp_sem_limite" id="acompSemLimiteH" value="<?=($maxAcomp==='0')?'1':'0'?>">
<div style="margin-top:14px;padding-top:12px;border-top:1px solid #fde68a">
<label class="form-label" style="margin-bottom:10px"><i class="fas fa-cog" style="color:#d97706"></i> Campos do Acompanhante</label>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
<label style="display:flex;align-items:center;gap:8px;padding:8px 12px;background:#fff;border:1px solid #fde68a;border-radius:8px;cursor:pointer;font-size:13px"><input type="checkbox" name="acomp_whatsapp" style="width:18px;height:18px" <?=$acWa?'checked':''?>> <i class="fab fa-whatsapp" style="color:#25d366"></i> WhatsApp</label>
<label style="display:flex;align-items:center;gap:8px;padding:8px 12px;background:#fff;border:1px solid #fde68a;border-radius:8px;cursor:pointer;font-size:13px"><input type="checkbox" name="acomp_cpf" style="width:18px;height:18px" <?=$acCpf?'checked':''?>> <i class="fas fa-id-card" style="color:#6366f1"></i> CPF</label>
<label style="display:flex;align-items:center;gap:8px;padding:8px 12px;background:#fff;border:1px solid #fde68a;border-radius:8px;cursor:pointer;font-size:13px"><input type="checkbox" name="acomp_instagram" style="width:18px;height:18px" <?=$acIg?'checked':''?>> <i class="fab fa-instagram" style="color:#e1306c"></i> Instagram</label>
<label style="display:flex;align-items:center;gap:8px;padding:8px 12px;background:#fff;border:1px solid #fde68a;border-radius:8px;cursor:pointer;font-size:13px"><input type="checkbox" name="acomp_endereco" style="width:18px;height:18px" <?=$acEnd?'checked':''?>> <i class="fas fa-map-marker-alt" style="color:#ef4444"></i> Endereço Completo</label>
</div></div></div></div></div>
</div>
</div><!-- /ev-sec-body --></div><!-- /sec-campos -->

<!-- SEÇÃO 5: Campos Extras -->
<div class="ev-section" id="sec-extras">
<div class="ev-sec-hdr" onclick="toggleSec('sec-extras')">
  <div class="ev-sec-icon" style="background:#fff7ed;color:#ea580c"><i class="fas fa-list-check"></i></div>
  <div><div class="ev-sec-title">Campos Extras</div><div class="ev-sec-sub">Campos personalizados adicionais</div></div>
  <i class="fas fa-chevron-down ev-sec-arrow"></i>
</div>
<div class="ev-sec-body">
<div id="camposC"><?php foreach($ce as $i=>$c):?>
<div class="campo-row" style="display:grid;grid-template-columns:1fr 1fr 100px 60px 1fr 36px;gap:6px;margin-bottom:8px;align-items:end">
<div><label class="form-label" style="font-size:10px">ID</label><input type="text" name="campo_nome[]" class="form-control" value="<?=htmlspecialchars($c['nome'])?>"></div>
<div><label class="form-label" style="font-size:10px">Label</label><input type="text" name="campo_label[]" class="form-control" value="<?=htmlspecialchars($c['label'])?>"></div>
<div><label class="form-label" style="font-size:10px">Tipo</label><select name="campo_tipo[]" class="form-select"><option value="text" <?=($c['tipo']??'')=='text'?'selected':''?>>Texto</option><option value="number" <?=($c['tipo']??'')=='number'?'selected':''?>>Número</option><option value="select" <?=($c['tipo']??'')=='select'?'selected':''?>>Seleção</option></select></div>
<div style="text-align:center"><label class="form-label" style="font-size:10px">Obr</label><br><input type="checkbox" name="campo_obrigatorio[]" value="<?=$i?>" <?=($c['obrigatorio']??false)?'checked':''?> style="width:18px;height:18px"></div>
<div><label class="form-label" style="font-size:10px">Opções</label><input type="text" name="campo_opcoes[]" class="form-control" value="<?=htmlspecialchars(implode(',',$c['opcoes']??[]))?>"></div>
<div><button type="button" class="btn btn-danger btn-sm" style="padding:4px 8px" onclick="this.closest('.campo-row').remove()"><i class="fas fa-times"></i></button></div>
</div><?php endforeach;?></div>
<button type="button" class="btn btn-secondary btn-sm" onclick="addCampo()"><i class="fas fa-plus"></i> Campo</button>
</div><!-- /ev-sec-body --></div><!-- /sec-extras -->

<!-- SEÇÃO 6: Logos -->
<div class="ev-section" id="sec-logos">
<div class="ev-sec-hdr" onclick="toggleSec('sec-logos')">
  <div class="ev-sec-icon" style="background:#fdf4ff;color:#9333ea"><i class="fas fa-images"></i></div>
  <div><div class="ev-sec-title">Logos do Formulário</div><div class="ev-sec-sub">Logos exibidas no rodapé do formulário</div></div>
  <i class="fas fa-chevron-down ev-sec-arrow"></i>
</div>
<div class="ev-sec-body">
<?php
$logB=($cf['logo_bombeiro']??'1')==='1'||!$evento;$logP=($cf['logo_policia']??'1')==='1'||!$evento;
$logA=($cf['logo_assego']??'1')==='1'||!$evento;$logS=($cf['logo_sergio']??'1')==='1'||!$evento;
$logoSize=$cf['logo_size']??'50';$evId=$evento?$evento['id']:'';$custom1=null;$custom2=null;
if($evId){foreach(glob(__DIR__."/uploads/logos/logo_ev{$evId}_custom1.*") as $f)$custom1='uploads/logos/'.basename($f);foreach(glob(__DIR__."/uploads/logos/logo_ev{$evId}_custom2.*") as $f)$custom2='uploads/logos/'.basename($f);}
?>
<div class="row g-3 mb-3"><div class="col-12"><label class="form-label">Tamanho das Logos</label><div class="rg"><input type="range" name="logo_size" id="rLogoSize" min="25" max="90" step="5" value="<?=$logoSize?>"><span id="vLogoSize"><?=$logoSize?>px</span></div></div></div>
<div class="row g-3 mb-4">
<div class="col-6 col-md-3"><label style="display:flex;flex-direction:column;align-items:center;gap:8px;cursor:pointer;padding:12px;background:#f0f9ff;border-radius:10px;border:1px solid #dbeafe;text-align:center" class="logo-toggle"><img src="assets/img/logobombeiro.png" style="height:40px;object-fit:contain;<?=$logB?'':'opacity:.3'?>" class="logo-preview-img"><div style="display:flex;align-items:center;gap:6px"><input type="checkbox" name="logo_bombeiro" style="width:18px;height:18px" <?=$logB?'checked':''?> class="logo-chk"> <span style="font-size:11px;font-weight:600">Bombeiro</span></div></label></div>
<div class="col-6 col-md-3"><label style="display:flex;flex-direction:column;align-items:center;gap:8px;cursor:pointer;padding:12px;background:#f0f9ff;border-radius:10px;border:1px solid #dbeafe;text-align:center" class="logo-toggle"><img src="assets/img/logopolicia.png" style="height:40px;object-fit:contain;<?=$logP?'':'opacity:.3'?>" class="logo-preview-img"><div style="display:flex;align-items:center;gap:6px"><input type="checkbox" name="logo_policia" style="width:18px;height:18px" <?=$logP?'checked':''?> class="logo-chk"> <span style="font-size:11px;font-weight:600">Polícia</span></div></label></div>
<div class="col-6 col-md-3"><label style="display:flex;flex-direction:column;align-items:center;gap:8px;cursor:pointer;padding:12px;background:#f0f9ff;border-radius:10px;border:1px solid #dbeafe;text-align:center" class="logo-toggle"><img src="assets/img/logo_assego.png" style="height:40px;object-fit:contain;<?=$logA?'':'opacity:.3'?>" class="logo-preview-img"><div style="display:flex;align-items:center;gap:6px"><input type="checkbox" name="logo_assego" style="width:18px;height:18px" <?=$logA?'checked':''?> class="logo-chk"> <span style="font-size:11px;font-weight:600">ASSEGO</span></div></label></div>
<div class="col-6 col-md-3"><label style="display:flex;flex-direction:column;align-items:center;gap:8px;cursor:pointer;padding:12px;background:#f0f9ff;border-radius:10px;border:1px solid #dbeafe;text-align:center" class="logo-toggle"><img src="assets/img/logo_sergio.png" style="height:40px;object-fit:contain;<?=$logS?'':'opacity:.3'?>" class="logo-preview-img"><div style="display:flex;align-items:center;gap:6px"><input type="checkbox" name="logo_sergio" style="width:18px;height:18px" <?=$logS?'checked':''?> class="logo-chk"> <span style="font-size:11px;font-weight:600">Sérgio</span></div></label></div>
</div>
<div class="row g-3 mb-4">
<div class="col-md-6"><label class="form-label">Logo Personalizado 1</label>
<?php if($custom1):?><div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;background:#f0f9ff;padding:8px 12px;border-radius:8px;border:1px solid #dbeafe"><img src="<?=$custom1?>" style="height:36px;object-fit:contain"><input type="hidden" name="remove_logo_custom1" id="rmL1" value="0"><button type="button" class="btn btn-danger btn-sm" onclick="document.getElementById('rmL1').value='1';this.parentElement.style.display='none'"><i class="fas fa-trash"></i></button></div><?php endif;?>
<input type="file" name="logo_custom1" class="form-control" accept="image/*"></div>
<div class="col-md-6"><label class="form-label">Logo Personalizado 2</label>
<?php if($custom2):?><div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;background:#f0f9ff;padding:8px 12px;border-radius:8px;border:1px solid #dbeafe"><img src="<?=$custom2?>" style="height:36px;object-fit:contain"><input type="hidden" name="remove_logo_custom2" id="rmL2" value="0"><button type="button" class="btn btn-danger btn-sm" onclick="document.getElementById('rmL2').value='1';this.parentElement.style.display='none'"><i class="fas fa-trash"></i></button></div><?php endif;?>
<input type="file" name="logo_custom2" class="form-control" accept="image/*"></div>
</div>
</div><!-- /ev-sec-body --></div><!-- /sec-logos -->
</form>
<?php if($action=='edit'&&$editLink):?>
<div style="margin-top:10px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px 16px;display:flex;align-items:center;gap:14px">
  <img src="<?=$editQR?>" width="72" height="72" alt="QR Code" id="editQrImg" style="border-radius:8px;border:2px solid #dbeafe;flex-shrink:0">
  <div style="min-width:0;flex:1">
    <div style="font-weight:700;font-size:13px;color:var(--primary);margin-bottom:2px"><?=htmlspecialchars($evento['nome'])?></div>
    <div style="font-size:10px;color:var(--gray);word-break:break-all;margin-bottom:8px;line-height:1.4"><?=$editLink?></div>
    <div style="display:flex;gap:6px;flex-wrap:wrap">
      <button onclick="downloadQREdit()" class="btn btn-primary btn-sm" style="font-size:11px;padding:4px 10px"><i class="fas fa-download"></i> Baixar QR</button>
      <button onclick="navigator.clipboard.writeText('<?=$editLink?>').then(()=>Swal.fire({icon:'success',title:'Copiado!',timer:1500,showConfirmButton:false}))" class="btn btn-secondary btn-sm" style="font-size:11px;padding:4px 10px"><i class="fas fa-copy"></i> Copiar Link</button>
      <button onclick="printQREdit()" class="btn btn-sm" style="font-size:11px;padding:4px 10px;background:linear-gradient(135deg,#059669,#10b981);color:white;border:none"><i class="fas fa-print"></i> Imprimir</button>
    </div>
  </div>
</div>
<?php endif;?>
</div></div>

<!-- PREVIEWS -->
<div class="col-lg-5"><div class="sticky-preview">
<div style="display:flex;gap:0;margin-bottom:12px;justify-content:center">
<button type="button" class="btn btn-sm" id="pvTabMob" onclick="switchPvTab('mob')" style="border-radius:8px 0 0 8px;background:#1e40af;color:white;border:1px solid #1e40af;padding:6px 16px;font-weight:700;font-size:12px"><i class="fas fa-mobile-alt"></i> Celular</button>
<button type="button" class="btn btn-sm" id="pvTabDesk" onclick="switchPvTab('desk')" style="border-radius:0 8px 8px 0;background:white;color:#1e40af;border:1px solid #dbeafe;padding:6px 16px;font-weight:700;font-size:12px"><i class="fas fa-desktop"></i> Desktop</button>
</div>
<div id="pvMobWrap" class="preview-frame" style="width:320px">
    <div class="pv-poster" id="pvPosterMob">
        <?php if($evento&&($evento['banner_mobile_base64']||$evento['banner_base64'])):?><img src="banner.php?id=<?=$evento['id']?>&mob" id="pvImgMob" style="object-position:center <?=$cf['bg_pos_y_mob']??($cf['bg_pos_y']??'30')?>%">
        <?php else:?><img id="pvImgMob" style="display:none"><?php endif;?>
        <div class="pv-ov" id="pvOvMob" style="background:rgba(5,5,20,<?=$cf['bg_overlay_mob']??($cf['bg_overlay']??'0.3')?>)"></div>
        <div class="pv-title" id="pvTitle" style="<?=$showT?'':'display:none'?>"><?=$evento?htmlspecialchars($evento['nome']):'NOME'?><small id="pvDesc"><?=$evento?htmlspecialchars($evento['descricao']??''):''?></small></div>
        <div class="pv-fade"></div>
    </div>
    <div class="pv-form"><div class="pv-form-card">
        <div class="pv-ftitle"><h3 id="pvFTitle"><?=htmlspecialchars($cf['titulo']??'CADASTRE-SE')?></h3><p id="pvFSub"><?=htmlspecialchars($cf['subtitulo']??'Preencha seus dados abaixo')?></p></div>
        <div class="pv-field" id="pvFoto" style="<?=$showF?'':'display:none'?>"><i class="fas fa-camera"></i> Foto</div>
        <div class="pv-field"><i class="fas fa-user"></i> Nome *</div>
        <div class="pv-row"><div class="pv-field"><i class="fab fa-whatsapp"></i> WhatsApp *</div><div class="pv-field"><i class="fab fa-instagram"></i> Instagram</div></div>
        <div style="color:rgba(255,255,255,.3);font-size:8px;margin:4px 0"><i class="fas fa-map-marker-alt" style="color:#f59e0b"></i> Endereço / Campos</div>
        <div class="pv-field" id="pvVideo" style="<?=$showV?'':'display:none'?>"><i class="fas fa-video"></i> Vídeo</div>
        <div class="pv-btn">PARTICIPAR</div>
    </div></div>
    <div class="pv-logos" id="pvLogos">
        <?php if($logB):?><img src="assets/img/logobombeiro.png" class="pvl"><?php endif;?>
        <?php if($logP):?><img src="assets/img/logopolicia.png" class="pvl"><?php endif;?>
        <?php if($logA):?><img src="assets/img/logo_assego.png" class="pvl"><?php endif;?>
        <?php if($logS):?><img src="assets/img/logo_sergio.png" class="pvl"><?php endif;?>
    </div>
</div>
<div id="pvDeskWrap" style="display:none;width:100%;max-width:520px;margin:0 auto;border-radius:12px;overflow:hidden;box-shadow:0 10px 40px rgba(0,0,0,.2);border:2px solid #334155">
  <!-- barra do browser falsa -->
  <div style="background:#1e293b;padding:6px 12px;display:flex;align-items:center;gap:6px">
    <span style="width:8px;height:8px;border-radius:50%;background:#ef4444;display:inline-block"></span>
    <span style="width:8px;height:8px;border-radius:50%;background:#f59e0b;display:inline-block"></span>
    <span style="width:8px;height:8px;border-radius:50%;background:#10b981;display:inline-block"></span>
    <span style="flex:1;text-align:center;font-size:9px;color:#64748b">confirmacao.assego.com.br</span>
  </div>

  <!-- layout BG: imagem no topo, formulário abaixo -->
  <div id="pvDeskBg" style="background:<?=$cor?>;display:<?=$layoutDesk==='bg'?'block':'none'?>">
    <div class="pv-poster" id="pvPosterDesk" style="height:130px">
      <?php if($evento&&$evento['banner_base64']):?><img src="banner.php?id=<?=$evento['id']?>" id="pvImgDesk" style="object-position:center <?=$cf['bg_pos_y']??'30'?>%"><?php else:?><img id="pvImgDesk" style="display:none"><?php endif;?>
      <div class="pv-ov" id="pvOvDesk" style="background:rgba(5,5,20,<?=$cf['bg_overlay']??'0.3'?>)"></div>
      <div class="pv-title" id="pvTitleDesk" style="font-size:12px;<?=$showT?'':'display:none'?>"><?=$evento?htmlspecialchars($evento['nome']):'NOME'?></div>
      <div class="pv-fade"></div>
    </div>
    <div style="padding:10px;background:<?=$cor?>">
      <div style="background:rgba(0,0,0,.5);border:1px solid rgba(255,255,255,.1);border-radius:10px;padding:12px">
        <div style="text-align:center;margin-bottom:8px;padding-bottom:7px;border-bottom:1px solid rgba(255,255,255,.08)">
          <div id="pvFTitleD" style="font-size:8px;font-weight:800;color:#f59e0b;text-transform:uppercase;letter-spacing:1px"><?=htmlspecialchars($cf['titulo']??'CADASTRE-SE')?></div>
          <div id="pvFSubD" style="font-size:7px;color:rgba(255,255,255,.35);margin-top:2px"><?=htmlspecialchars($cf['subtitulo']??'Preencha seus dados')?></div>
        </div>
        <div class="pv-field" style="padding:4px 7px;font-size:8px;margin-bottom:4px"><i class="fas fa-user"></i> Nome *</div>
        <div class="pv-row" style="margin-bottom:4px">
          <div class="pv-field" style="padding:4px 7px;font-size:8px"><i class="fab fa-whatsapp"></i> WhatsApp *</div>
          <div class="pv-field" style="padding:4px 7px;font-size:8px"><i class="fab fa-instagram"></i> Instagram</div>
        </div>
        <div class="pv-btn" style="padding:5px;font-size:8px;margin-top:5px">PARTICIPAR</div>
      </div>
    </div>
    <div class="pv-logos" id="pvLogosDesk" style="padding:6px;background:<?=$cor?>">
      <?php if($logB):?><img src="assets/img/logobombeiro.png" class="pvl" style="height:18px"><?php endif;?>
      <?php if($logP):?><img src="assets/img/logopolicia.png" class="pvl" style="height:18px"><?php endif;?>
      <?php if($logA):?><img src="assets/img/logo_assego.png" class="pvl" style="height:18px"><?php endif;?>
      <?php if($logS):?><img src="assets/img/logo_sergio.png" class="pvl" style="height:18px"><?php endif;?>
    </div>
  </div>

  <!-- layout SPLIT: imagem à esquerda, formulário à direita -->
  <div id="pvDeskSplit" style="display:<?=$layoutDesk==='split'?'flex':'none'?>;height:270px">
    <!-- painel esquerdo: imagem -->
    <div style="width:42%;position:relative;overflow:hidden;background:<?=$cor?>">
      <div id="pvSplitBlur" style="position:absolute;inset:-8px;z-index:0;filter:blur(8px) brightness(.45) saturate(1.2);background-size:cover;background-position:center"></div>
      <img id="pvSplitImg" src="" style="position:absolute;inset:0;width:100%;height:100%;object-fit:contain;z-index:1;display:none">
      <div style="position:absolute;bottom:0;left:0;right:0;padding:6px 8px;background:linear-gradient(transparent,rgba(0,0,0,.75));z-index:2;text-align:center">
        <span id="pvSplitName" style="font-size:7px;font-weight:800;color:white;text-transform:uppercase;letter-spacing:.5px"><?=$evento?htmlspecialchars($evento['nome']):'NOME'?></span>
      </div>
    </div>
    <!-- painel direito: formulário -->
    <div style="width:58%;background:rgba(0,0,0,.62);display:flex;flex-direction:column;overflow:hidden">
      <!-- título fixo (simula sticky) -->
      <div style="background:rgba(15,10,20,.9);padding:7px 10px;border-bottom:1px solid rgba(255,255,255,.08)">
        <div id="pvFTitleDS" style="font-size:7px;font-weight:800;color:#f59e0b;text-transform:uppercase;letter-spacing:1px;text-align:center"><?=htmlspecialchars($cf['titulo']??'CADASTRE-SE')?></div>
        <div id="pvFSubDS" style="font-size:6px;color:rgba(255,255,255,.35);text-align:center;margin-top:1px"><?=htmlspecialchars($cf['subtitulo']??'Preencha seus dados')?></div>
      </div>
      <!-- campos -->
      <div style="padding:7px 9px;flex:1">
        <div class="pv-field" style="padding:4px 7px;font-size:7px;margin-bottom:4px"><i class="fas fa-user"></i> Nome *</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px;margin-bottom:4px">
          <div class="pv-field" style="padding:4px 7px;font-size:7px"><i class="fab fa-whatsapp"></i> WhatsApp *</div>
          <div class="pv-field" style="padding:4px 7px;font-size:7px"><i class="fab fa-instagram"></i> Instagram</div>
        </div>
        <div class="pv-field" style="padding:4px 7px;font-size:7px;margin-bottom:4px"><i class="fas fa-city"></i> Cidade</div>
        <div class="pv-btn" style="padding:5px;font-size:7px;margin-top:4px">PARTICIPAR</div>
      </div>
      <!-- logos -->
      <div id="pvLogosSplit" style="display:flex;justify-content:center;gap:5px;padding:5px;background:rgba(0,0,0,.3)">
        <?php if($logB):?><img src="assets/img/logobombeiro.png" style="height:13px;object-fit:contain"><?php endif;?>
        <?php if($logP):?><img src="assets/img/logopolicia.png" style="height:13px;object-fit:contain"><?php endif;?>
        <?php if($logA):?><img src="assets/img/logo_assego.png" style="height:13px;object-fit:contain"><?php endif;?>
        <?php if($logS):?><img src="assets/img/logo_sergio.png" style="height:13px;object-fit:contain"><?php endif;?>
      </div>
    </div>
  </div>
</div>
</div></div>
</div></div>

<script>
let ci=<?=count($ce)?>;function addCampo(){document.getElementById('camposC').insertAdjacentHTML('beforeend',`<div class="campo-row" style="display:grid;grid-template-columns:1fr 1fr 100px 60px 1fr 36px;gap:6px;margin-bottom:8px;align-items:end"><div><label class="form-label" style="font-size:10px">ID</label><input type="text" name="campo_nome[]" class="form-control" placeholder="cpf"></div><div><label class="form-label" style="font-size:10px">Label</label><input type="text" name="campo_label[]" class="form-control" placeholder="CPF"></div><div><label class="form-label" style="font-size:10px">Tipo</label><select name="campo_tipo[]" class="form-select"><option value="text">Texto</option><option value="number">Número</option><option value="select">Seleção</option></select></div><div style="text-align:center"><label class="form-label" style="font-size:10px">Obr</label><br><input type="checkbox" name="campo_obrigatorio[]" value="${ci}" style="width:18px;height:18px"></div><div><label class="form-label" style="font-size:10px">Opções</label><input type="text" name="campo_opcoes[]" class="form-control" placeholder="Op1,Op2"></div><div><button type="button" class="btn btn-danger btn-sm" style="padding:4px 8px" onclick="this.closest('.campo-row').remove()"><i class="fas fa-times"></i></button></div></div>`);ci++;}
function switchImgTab(t){document.getElementById('imgDesk').style.display=t==='desk'?'flex':'none';document.getElementById('imgMob').style.display=t==='mob'?'flex':'none';document.getElementById('tabDesk').style.background=t==='desk'?'#1e40af':'white';document.getElementById('tabDesk').style.color=t==='desk'?'white':'#1e40af';document.getElementById('tabMob').style.background=t==='mob'?'#1e40af':'white';document.getElementById('tabMob').style.color=t==='mob'?'white':'#1e40af';}
function switchPvTab(t){document.getElementById('pvMobWrap').style.display=t==='mob'?'block':'none';document.getElementById('pvDeskWrap').style.display=t==='desk'?'block':'none';document.getElementById('pvTabMob').style.background=t==='mob'?'#1e40af':'white';document.getElementById('pvTabMob').style.color=t==='mob'?'white':'#1e40af';document.getElementById('pvTabDesk').style.background=t==='desk'?'#1e40af':'white';document.getElementById('pvTabDesk').style.color=t==='desk'?'white':'#1e40af';}
const ft=document.getElementById('pvFTitle'),fs=document.getElementById('pvFSub'),tt=document.getElementById('pvTitle'),desc=document.getElementById('pvDesc');
// Inicializa previews com imagens já existentes
(function(){
  const di=document.getElementById('pvImgDesk');
  if(di&&di.style.display!=='none'&&di.src&&!di.src.endsWith(location.href)){
    const si=document.getElementById('pvSplitImg');const sb=document.getElementById('pvSplitBlur');
    if(si){si.src=di.src;si.style.display='block';}if(sb)sb.style.backgroundImage='url('+di.src+')';
  }
  // Inicializa preview mobile: se pvImgMob não tem src próprio, usa o desktop
  const im=document.getElementById('pvImgMob');
  if(im&&(im.style.display==='none'||!im.src||im.src.endsWith(location.href))){
    if(di&&di.src&&!di.src.endsWith(location.href)){im.src=di.src;im.style.display='block';}
  }
})();
// Troca layout desktop conforme radio
document.querySelectorAll('[name=layout_desktop]').forEach(r=>r.addEventListener('change',function(){const s=this.value==='split';document.getElementById('pvDeskBg').style.display=s?'none':'block';document.getElementById('pvDeskSplit').style.display=s?'flex':'none';}));
document.getElementById('bgFile')?.addEventListener('change',function(e){const f=e.target.files[0];if(!f)return;const r=new FileReader();r.onload=function(ev){document.getElementById('bgB64').value=ev.target.result;const i=document.getElementById('pvImgDesk');i.src=ev.target.result;i.style.display='block';const si=document.getElementById('pvSplitImg');const sb=document.getElementById('pvSplitBlur');if(si){si.src=ev.target.result;si.style.display='block';}if(sb)sb.style.backgroundImage='url('+ev.target.result+')';// Atualiza preview mobile se não tiver imagem mobile própria
const bgMob=document.getElementById('bgB64Mob');const im=document.getElementById('pvImgMob');if(im&&(!bgMob||!bgMob.value)){im.src=ev.target.result;im.style.display='block';}};r.readAsDataURL(f)});
document.getElementById('bgFileMob')?.addEventListener('change',function(e){const f=e.target.files[0];if(!f)return;const r=new FileReader();r.onload=function(ev){document.getElementById('bgB64Mob').value=ev.target.result;const i=document.getElementById('pvImgMob');i.src=ev.target.result;i.style.display='block'};r.readAsDataURL(f)});
document.getElementById('rPosY')?.addEventListener('input',function(){document.getElementById('vPosY').textContent=this.value+'%';document.getElementById('pvImgDesk').style.objectPosition='center '+this.value+'%'});
document.getElementById('rOv')?.addEventListener('input',function(){document.getElementById('vOv').textContent=Math.round(this.value*100)+'%';document.getElementById('pvOvDesk').style.background='rgba(5,5,20,'+this.value+')'});
document.getElementById('rHDesk')?.addEventListener('input',function(){document.getElementById('vHDesk').textContent=this.value+'px';document.getElementById('pvPosterDesk').style.height=Math.round(this.value*0.31)+'px'});
document.getElementById('rOverlap')?.addEventListener('input',function(){document.getElementById('vOverlap').textContent=this.value+'px'});
document.getElementById('rPosYMob')?.addEventListener('input',function(){document.getElementById('vPosYMob').textContent=this.value+'%';document.getElementById('pvImgMob').style.objectPosition='center '+this.value+'%'});
document.getElementById('rOvMob')?.addEventListener('input',function(){document.getElementById('vOvMob').textContent=Math.round(this.value*100)+'%';document.getElementById('pvOvMob').style.background='rgba(5,5,20,'+this.value+')'});
document.getElementById('rHMob')?.addEventListener('input',function(){document.getElementById('vHMob').textContent=this.value+'px';document.getElementById('pvPosterMob').style.height=Math.round(this.value*0.56)+'px'});
document.getElementById('rOverlapMob')?.addEventListener('input',function(){document.getElementById('vOverlapMob').textContent=this.value+'px'});
document.getElementById('rLogoSize')?.addEventListener('input',function(){document.getElementById('vLogoSize').textContent=this.value+'px';document.querySelectorAll('.pvl').forEach(l=>l.style.height=Math.round(this.value*.56)+'px')});
document.querySelectorAll('.logo-chk').forEach(chk=>{chk.addEventListener('change',function(){this.closest('.logo-toggle').querySelector('.logo-preview-img').style.opacity=this.checked?'1':'.3';updatePvLogos()})});
function updatePvLogos(){const map={bombeiro:'assets/img/logobombeiro.png',policia:'assets/img/logopolicia.png',assego:'assets/img/logo_assego.png',sergio:'assets/img/logo_sergio.png'};const names=['bombeiro','policia','assego','sergio'];let html='';document.querySelectorAll('.logo-chk').forEach((c,i)=>{if(c.checked&&names[i])html+=`<img src="${map[names[i]]}" class="pvl">`;});document.getElementById('pvLogos').innerHTML=html;document.getElementById('pvLogosDesk').innerHTML=html;const sz=document.getElementById('rLogoSize')?.value||50;document.querySelectorAll('.pvl').forEach(l=>l.style.height=Math.round(sz*.56)+'px')}
document.getElementById('evNome')?.addEventListener('input',function(){const t=this.value||'NOME';tt.childNodes[0].textContent=t;const td=document.getElementById('pvTitleDesk');if(td)td.textContent=t;const sn=document.getElementById('pvSplitName');if(sn)sn.textContent=t;const sb=document.getElementById('evSaveName');if(sb)sb.textContent=this.value||'Novo Evento';});
document.getElementById('evDesc')?.addEventListener('input',function(){desc.textContent=this.value});
document.getElementById('evTitulo')?.addEventListener('input',function(){const v=this.value||'CADASTRE-SE';ft.textContent=v;const d=document.getElementById('pvFTitleD');if(d)d.textContent=v;const ds=document.getElementById('pvFTitleDS');if(ds)ds.textContent=v;});
document.getElementById('evSub')?.addEventListener('input',function(){const v=this.value||'Preencha seus dados abaixo';fs.textContent=v;const d=document.getElementById('pvFSubD');if(d)d.textContent=v;const ds=document.getElementById('pvFSubDS');if(ds)ds.textContent=v;});
document.getElementById('rTitSize')?.addEventListener('input',function(){document.getElementById('vTitSize').textContent=this.value+'px';ft.style.fontSize=Math.round(this.value*0.7)+'px'});
document.getElementById('rSubSize')?.addEventListener('input',function(){document.getElementById('vSubSize').textContent=this.value+'px';fs.style.fontSize=Math.round(this.value*0.7)+'px'});
document.getElementById('showTitle')?.addEventListener('change',function(){tt.style.display=this.checked?'flex':'none';document.getElementById('pvTitleDesk').style.display=this.checked?'flex':'none'});
document.getElementById('showFoto')?.addEventListener('change',function(){document.getElementById('pvFoto').style.display=this.checked?'flex':'none'});
document.getElementById('showVideo')?.addEventListener('change',function(){document.getElementById('pvVideo').style.display=this.checked?'flex':'none'});
document.getElementById('showAcomp')?.addEventListener('change',function(){document.getElementById('acompConfig').style.display=this.checked?'block':'none';});
document.getElementById('showMilitarChk')?.addEventListener('change',function(){document.getElementById('militarConfig').style.display=this.checked?'block':'none';});
document.getElementById('rMaxAcomp')?.addEventListener('input',function(){document.getElementById('vMaxAcomp').textContent=this.value});
document.getElementById('acompSemLimite')?.addEventListener('change',function(){document.getElementById('acompSemLimiteH').value=this.checked?'1':'0'});
// Cor do evento — SOMENTE nos previews
document.getElementById('evCor')?.addEventListener('input',function(){
    const c=this.value;
    const mob=document.getElementById('pvMobWrap');
    if(mob){mob.style.background=c;mob.querySelectorAll('.pv-form').forEach(el=>el.style.background=c);mob.querySelectorAll('.pv-logos').forEach(el=>el.style.background=c);mob.querySelectorAll('.pv-fade').forEach(el=>el.style.background='linear-gradient(transparent,'+c+')');}
    const db=document.getElementById('pvDeskBg');
    if(db){db.style.background=c;db.querySelectorAll('.pv-logos').forEach(el=>el.style.background=c);db.querySelectorAll('.pv-fade').forEach(el=>el.style.background='linear-gradient(transparent,'+c+')');}
    const sp=document.getElementById('pvDeskSplit');
    if(sp){const left=sp.querySelector('div:first-child');if(left)left.style.background=c;}
});
function downloadQREdit(){const a=document.createElement('a');a.href=document.getElementById('editQrImg').src;a.download='qrcode-evento.png';a.click();}
function printQREdit(){const img=document.getElementById('editQrImg');const w=window.open('','_blank','width=500,height=600');w.document.write(`<html><head><title>QR Code</title><style>body{text-align:center;font-family:Inter,Arial,sans-serif;padding:40px}h2{color:#1e3a8a}p{color:#64748b;font-size:14px;word-break:break-all}img{margin:20px 0}</style></head><body><h2><?=$evento?htmlspecialchars($evento['nome']):''?></h2><p>Escaneie</p><img src="${img.src}" width=300 height=300><p><?=$editLink??''?></p></body></html>`);w.document.close();w.onload=function(){w.print()}}
</script>
<?php endif;?>

<?php renderScripts();?>
<script>
function showQR(nome,url,qrSrc){document.getElementById('qrModalImg').src=qrSrc;document.getElementById('qrModalName').textContent=nome;document.getElementById('qrModalUrl').textContent=url;new bootstrap.Modal(document.getElementById('qrModal')).show();}
function downloadQR(){const img=document.getElementById('qrModalImg');const a=document.createElement('a');a.href=img.src;a.download='qrcode-'+document.getElementById('qrModalName').textContent.replace(/\s/g,'-')+'.png';a.click();}
function printQR(){const img=document.getElementById('qrModalImg');const nome=document.getElementById('qrModalName').textContent;const url=document.getElementById('qrModalUrl').textContent;const w=window.open('','_blank','width=500,height=600');w.document.write(`<html><head><title>QR Code</title><style>body{text-align:center;font-family:Inter,Arial,sans-serif;padding:40px}h2{color:#1e3a8a;margin-bottom:8px}p{color:#64748b;font-size:14px;word-break:break-all}img{margin:20px 0}</style></head><body><h2>${nome}</h2><p>Escaneie para se cadastrar</p><img src="${img.src}" width="300" height="300"><p style="margin-top:16px">${url}</p></body></html>`);w.document.close();w.onload=function(){w.print();};}

// Confirmação antes de fechar/abrir formulário
function confirmarToggle(id, acao, nome){
    const fechar = (acao === 'fechar');
    Swal.fire({
        title: fechar ? 'Fechar formulário?' : 'Reabrir formulário?',
        html: fechar
            ? `<p>O formulário público do evento <strong>${nome}</strong> será <strong>fechado</strong>.</p><p style="color:#059669;font-size:13px;margin-top:8px"><i class="fas fa-database"></i> <strong>Os dados e participantes serão preservados.</strong></p><p style="color:#64748b;font-size:12px">Você pode reabrir a qualquer momento.</p>`
            : `<p>O formulário público do evento <strong>${nome}</strong> será <strong>reaberto</strong>.</p><p style="font-size:13px;color:#64748b;margin-top:8px">Novos cadastros serão permitidos novamente.</p>`,
        icon: fechar ? 'warning' : 'question',
        showCancelButton: true,
        confirmButtonColor: fechar ? '#dc2626' : '#059669',
        cancelButtonText: 'Cancelar',
        confirmButtonText: fechar ? '<i class="fas fa-lock"></i> Fechar formulário' : '<i class="fas fa-lock-open"></i> Reabrir formulário',
    }).then(r => {
        if(r.isConfirmed) location.href = 'eventos.php?action=toggle_ativo&id=' + id;
    });
}
</script>
</body></html>