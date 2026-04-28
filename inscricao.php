<?php
@ini_set('upload_max_filesize','128M');
@ini_set('post_max_size','140M');
@ini_set('max_execution_time','600');
@ini_set('max_input_time','600');
@ini_set('memory_limit','256M');
try{require_once 'config.php';$pdo=getConnection();}catch(Exception $e){die("Erro.");}
$param=$_GET['evento']??$_GET['slug']??'';$evento=null;$evento_inativo=false;
if($param){
    try{$s=$pdo->prepare("SELECT * FROM eventos WHERE slug=?");$s->execute([$param]);$evento=$s->fetch();}catch(Exception $e){}
    if(!$evento&&is_numeric($param)){$s=$pdo->prepare("SELECT * FROM eventos WHERE id=?");$s->execute([(int)$param]);$evento=$s->fetch();}
    if($evento&&!$evento['ativo']){$evento_inativo=true;$evento_nome=$evento['nome']??'';$evento_cor=$evento['cor_tema']??'#1e40af';$evento=null;}
    if($evento&&!empty($evento['data_fim'])){$fim=strtotime($evento['data_fim'].' 23:59:59');if($fim&&time()>$fim){$evento_inativo=true;$evento_nome=$evento['nome']??'';$evento_cor=$evento['cor_tema']??'#1e40af';$evento=null;}}
}
if($evento_inativo){
    $cor=$evento_cor??'#1e40af';
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Inscrições Encerradas</title>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">';
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">';
    echo '<style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:Inter,sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,'.$cor.' 0%,#0f172a 100%);padding:20px}.card{background:rgba(255,255,255,.95);border-radius:20px;padding:48px 36px;text-align:center;max-width:480px;width:100%;box-shadow:0 25px 60px rgba(0,0,0,.3)}.icon{width:80px;height:80px;background:linear-gradient(135deg,#fbbf24,#f59e0b);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 24px;font-size:36px;color:#fff}h1{font-size:22px;color:#1e293b;margin-bottom:8px}p{color:#64748b;font-size:15px;line-height:1.6;margin-bottom:8px}.evento-nome{background:#f1f5f9;padding:10px 20px;border-radius:10px;font-weight:700;color:#334155;font-size:16px;display:inline-block;margin:12px 0 20px}.info{background:#fffbeb;border:1px solid #fde68a;border-radius:12px;padding:16px;margin-top:20px;font-size:13px;color:#92400e}.info i{margin-right:6px}</style></head><body>';
    echo '<div class="card"><div class="icon"><i class="fas fa-calendar-xmark"></i></div><h1>Inscrições Encerradas</h1><p>As inscrições para este evento não estão disponíveis no momento.</p>';
    if(!empty($evento_nome)) echo '<div class="evento-nome"><i class="fas fa-calendar-alt" style="color:#64748b;margin-right:6px"></i>'.htmlspecialchars($evento_nome).'</div>';
    echo '<div class="info"><i class="fas fa-info-circle"></i> Para mais informações, entre em contato com a <strong>administração do evento</strong> ou com a <strong>ASSEGO</strong>.</div></div></body></html>';
    exit();
}
if(!$evento)die("<div style='font-family:Inter,sans-serif;text-align:center;padding:80px 20px;color:#64748b'><h2>Evento não encontrado</h2></div>");
$campos_extras=json_decode($evento['campos_extras']??'[]',true)?:[];
$cf=[];try{if(isset($evento['config_form'])&&$evento['config_form'])$cf=json_decode($evento['config_form'],true)?:[];}catch(Exception $e){}
$titulo_form=$cf['titulo']??('CADASTRE-SE PARA O '.strtoupper($evento['nome']));
$subtitulo_form=$cf['subtitulo']??($evento['descricao']??'');
$bg_pos_y=$cf['bg_pos_y']??'30';$bg_overlay=$cf['bg_overlay']??'0.3';
$bg_pos_y_mob=$cf['bg_pos_y_mob']??$bg_pos_y;$bg_overlay_mob=$cf['bg_overlay_mob']??$bg_overlay;
$poster_h_mob=$cf['poster_h_mobile']??'280';$poster_h_desk=$cf['poster_h_desktop']??'450';
$logo_size=$cf['logo_size']??'50';$titulo_size=$cf['titulo_size']??'15';$subtitulo_size=$cf['subtitulo_size']??'12';
$card_overlap=$cf['card_overlap']??'20';$card_overlap_mob=$cf['card_overlap_mob']??$card_overlap;
$show_title=true;if(isset($cf['show_title']))$show_title=($cf['show_title']==='1'||$cf['show_title']===1||$cf['show_title']===true);
$bg_fullscreen=($cf['bg_fullscreen']??'0')==='1'||($cf['layout_desktop']??'')==='bg';$titulo_font=$cf['titulo_font']??'Inter';
$show_foto=true;if(isset($cf['show_foto']))$show_foto=($cf['show_foto']==='1'||$cf['show_foto']===1||$cf['show_foto']===true);
$show_video=false;if(isset($cf['show_video']))$show_video=($cf['show_video']==='1'||$cf['show_video']===1||$cf['show_video']===true);
$req_foto=($cf['req_foto']??'0')==='1';$req_video=($cf['req_video']??'0')==='1';
$show_acomp=($cf['show_acompanhantes']??'0')==='1';$max_acomp=intval($cf['max_acompanhantes']??'3');
$acomp_unlimited=($max_acomp===0);$acomp_wa=($cf['acomp_whatsapp']??'1')==='1';
$acomp_cpf=($cf['acomp_cpf']??'0')==='1';$acomp_ig=($cf['acomp_instagram']??'0')==='1';$acomp_end=($cf['acomp_endereco']??'0')==='1';
// ── CADASTRO DIRETO: aprovação automática ────────────────────────────────────
$auto_aprovacao=($cf['auto_aprovacao']??'0')==='1';
// ── DADOS MILITARES ──────────────────────────────────────────────────────────
$show_militar=($cf['show_militar']??'0')==='1';
$militar_acomp=($cf['militar_acomp']??'0')==='1';
$layout_split=($cf['layout_desktop']??'normal')==='split';
$has_bg=!empty($evento['banner_base64']);$has_bg_mob=!empty($evento['banner_mobile_base64']??'');
$cor=$evento['cor_tema']??'#1e40af';$success=false;$error='';
if($_SERVER['REQUEST_METHOD']=='POST'){
    if(empty($_POST)&&isset($_SERVER['CONTENT_LENGTH'])&&(int)$_SERVER['CONTENT_LENGTH']>0){$maxPost=ini_get('post_max_size');$error="O arquivo enviado excedeu o limite do servidor ({$maxPost}). Reduza o tamanho do vídeo e tente novamente.";}
    if(!$error){
    $nome=htmlspecialchars(stripslashes(trim($_POST['nome']??'')));$whatsapp=preg_replace('/[^0-9]/','',$_POST['whatsapp']??'');
    $instagram=htmlspecialchars(stripslashes(trim($_POST['instagram']??'')));$endereco=htmlspecialchars(stripslashes(trim($_POST['endereco']??'')));
    $cidade=htmlspecialchars(stripslashes(trim($_POST['cidade']??'')));$estado=htmlspecialchars(stripslashes(trim($_POST['estado']??'')));
    $cep=preg_replace('/[^0-9-]/','',$_POST['cep']??'');$video_url=trim($_POST['video_url_ready']??'');
    $extras=[];foreach($campos_extras as $ce){$v=htmlspecialchars(stripslashes(trim($_POST['extra_'.$ce['nome']]??'')));$extras[$ce['nome']]=$v;if(($ce['obrigatorio']??false)&&empty($v))$error=$ce['label']." é obrigatório.";}
    // Dados militares do titular
    if($show_militar&&!empty($_POST['is_militar'])){
        $extras['_militar']='1';
        $extras['_corporacao']=htmlspecialchars(stripslashes(trim($_POST['corporacao']??'')));
        $extras['_patente']=htmlspecialchars(stripslashes(trim($_POST['patente']??'')));
    }
    if(empty($nome)||strlen($nome)<3)$error="Nome é obrigatório.";elseif(empty($whatsapp)||strlen($whatsapp)<10)$error="WhatsApp é obrigatório.";
    if(!$error&&$show_foto&&$req_foto&&empty($_POST['foto_base64']??''))$error="A foto é obrigatória.";
    if(!$error&&$show_video&&$req_video&&empty($video_url))$error="O vídeo é obrigatório.";
    if(!$error){$chk=$pdo->prepare("SELECT nome FROM participantes WHERE evento_id=? AND whatsapp=?");$chk->execute([$evento['id'],$whatsapp]);$existe=$chk->fetch();if($existe)$error="Este WhatsApp já está cadastrado neste evento! (Nome: ".$existe['nome'].")";}
    $acompanhantes_json='[]';
    if($show_acomp&&!empty($_POST['acomp_nome'])){
        $acomps=[];$an=$_POST['acomp_nome']??[];$aw=$_POST['acomp_whatsapp']??[];$ac_cpf=$_POST['acomp_cpf_field']??[];
        $ac_ig=$_POST['acomp_instagram']??[];$ac_cep=$_POST['acomp_cep']??[];$ac_end=$_POST['acomp_endereco']??[];
        $ac_bairro=$_POST['acomp_bairro']??[];$ac_cidade=$_POST['acomp_cidade']??[];$ac_estado=$_POST['acomp_estado']??[];
        $ac_militar=$_POST['acomp_militar']??[];$ac_corporacao=$_POST['acomp_corporacao']??[];$ac_patente=$_POST['acomp_patente']??[];
        for($ai=0;$ai<count($an);$ai++){
            $anome=trim($an[$ai]??'');if(empty($anome)||strlen($anome)<2)continue;
            $item=['nome'=>htmlspecialchars(stripslashes($anome))];
            if($acomp_wa)$item['whatsapp']=preg_replace('/[^0-9]/','',trim($aw[$ai]??''));
            if($acomp_cpf)$item['cpf']=preg_replace('/[^0-9]/','',trim($ac_cpf[$ai]??''));
            if($acomp_ig)$item['instagram']=htmlspecialchars(stripslashes(trim($ac_ig[$ai]??'')));
            if($militar_acomp&&!empty(($ac_militar[$ai]??''))){
                $item['militar']='1';
                $item['corporacao']=htmlspecialchars(stripslashes(trim($ac_corporacao[$ai]??'')));
                $item['patente']=htmlspecialchars(stripslashes(trim($ac_patente[$ai]??'')));
            }
            if($acomp_end){$item['cep']=preg_replace('/[^0-9]/','',trim($ac_cep[$ai]??''));$item['endereco']=htmlspecialchars(stripslashes(trim($ac_end[$ai]??'')));$item['bairro']=htmlspecialchars(stripslashes(trim($ac_bairro[$ai]??'')));$item['cidade']=htmlspecialchars(stripslashes(trim($ac_cidade[$ai]??'')));$item['estado']=htmlspecialchars(stripslashes(trim($ac_estado[$ai]??'')));}
            $acomps[]=$item;
        }
        if(!$acomp_unlimited&&count($acomps)>$max_acomp)$acomps=array_slice($acomps,0,$max_acomp);
        $acompanhantes_json=json_encode($acomps,JSON_UNESCAPED_UNICODE);
    }
    if(!$error){
        try{$pdo->beginTransaction();
            $cols=array_column($pdo->query("SHOW COLUMNS FROM participantes")->fetchAll(),'Field');
            $hasVideo=in_array('video_url',$cols);$hasAcomp=in_array('acompanhantes',$cols);
            $sql_cols="evento_id,nome,whatsapp,instagram,endereco,cidade,estado,cep,campos_extras";
            $sql_vals="?,?,?,?,?,?,?,?,?";
            $params=[$evento['id'],$nome,$whatsapp,$instagram,$endereco,$cidade,$estado,$cep,json_encode($extras,JSON_UNESCAPED_UNICODE)];
            if($hasVideo){$sql_cols.=",video_url";$sql_vals.=",?";$params[]=$video_url;}
            if($hasAcomp){$sql_cols.=",acompanhantes";$sql_vals.=",?";$params[]=$acompanhantes_json;}
            // ── Cadastro Direto: define aprovado conforme configuração do evento ──
            $aprovado_val=$auto_aprovacao?1:0;
            $sql_cols.=",aprovado";$sql_vals.=",{$aprovado_val}";
            if($auto_aprovacao&&in_array('aprovado_em',$cols)){
                date_default_timezone_set('America/Sao_Paulo');
                $sql_cols.=",aprovado_em,aprovado_por";
                $sql_vals.=",NOW(),'Sistema (Auto)'";
            }
            $pdo->prepare("INSERT INTO participantes ($sql_cols) VALUES ($sql_vals)")->execute($params);
            $pid=$pdo->lastInsertId();$fb=$_POST['foto_base64']??'';
            if(!empty($fb)&&strpos($fb,'data:image')===0)$pdo->prepare("INSERT INTO fotos (participante_id,dados) VALUES (?,?)")->execute([$pid,$fb]);
            $pdo->commit();$success=true;
        }catch(Exception $e){$pdo->rollBack();if(strpos($e->getMessage(),'Duplicate')!==false)$error="Você já está cadastrado neste evento!";else $error="Erro. Tente novamente.";}
    }
    }
}
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?=htmlspecialchars($evento['nome'])?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=<?=urlencode($titulo_font)?>:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:<?=$cor?>;color:white;min-height:100vh;
<?php if($bg_fullscreen&&$has_bg):?>background:url('banner.php?id=<?=$evento['id']?>') center <?=$bg_pos_y?>% / cover no-repeat fixed;<?php endif;?>}
<?php if($bg_fullscreen):?>
body::before{content:'';position:fixed;inset:0;background:rgba(5,5,20,<?=$bg_overlay?>);z-index:0;pointer-events:none}
body>*{position:relative;z-index:1}
.poster{width:100%;position:relative;overflow:hidden;background:transparent !important}
.poster-ov{display:none}.poster-fade{display:none}
<?php if($show_title):?>
.poster-text{position:relative;z-index:3;text-align:center;padding:60px 20px 40px;display:flex;flex-direction:column;align-items:center;justify-content:center}
.poster-text h1{font-family:'<?=$titulo_font?>',sans-serif;font-size:clamp(28px,6vw,56px);font-weight:900;text-transform:uppercase;letter-spacing:3px;text-shadow:0 6px 30px rgba(0,0,0,.8);line-height:1.1}
.poster-text p{font-size:14px;opacity:.7;margin-top:8px}
<?php else:?>.poster-text{display:none}<?php endif;?>
.form-wrap{padding:0 20px 40px;display:flex;flex-direction:column;align-items:center;position:relative;z-index:4;background:transparent}
@media(max-width:767px){
    body{<?php if($has_bg_mob):?>background-image:url('banner.php?id=<?=$evento['id']?>&mob');background-position:center <?=$bg_pos_y_mob?>%;<?php endif;?>}
    body::before{background:rgba(5,5,20,<?=$bg_overlay_mob?>)}
}
<?php else:?>
.poster{width:100%;position:relative;overflow:hidden;
<?php if($has_bg):?>background:url('banner.php?id=<?=$evento['id']?>') center <?=$bg_pos_y?>% / cover no-repeat;
<?php else:?>background:linear-gradient(180deg,#0a0a2e,#1a1a4e);<?php endif;?>}
.poster-ov{position:absolute;inset:0;background:rgba(5,5,20,<?=$bg_overlay?>)}
.poster-fade{position:absolute;bottom:0;left:0;right:0;height:150px;background:linear-gradient(transparent,<?=$cor?>);z-index:2}
.poster{min-height:<?=$poster_h_mob?>px;
<?php if($has_bg_mob):?>background:url('banner.php?id=<?=$evento['id']?>&mob') center <?=$bg_pos_y_mob?>% / cover no-repeat;<?php endif;?>}
.poster-ov{background:rgba(5,5,20,<?=$bg_overlay_mob?>)}
<?php if($show_title):?>
.poster-text{position:relative;z-index:3;text-align:center;padding:60px 20px 80px;min-height:<?=$poster_h_mob?>px;display:flex;flex-direction:column;align-items:center;justify-content:center}
.poster-text h1{font-family:'<?=$titulo_font?>',sans-serif;font-size:clamp(28px,6vw,56px);font-weight:900;text-transform:uppercase;letter-spacing:3px;text-shadow:0 6px 30px rgba(0,0,0,.8);line-height:1.1}
.poster-text p{font-size:14px;opacity:.7;margin-top:8px}
<?php else:?>.poster-text{display:none}<?php endif;?>
.form-wrap{padding:0 20px 40px;display:flex;flex-direction:column;align-items:center;margin-top:-<?=$card_overlap_mob?>px;position:relative;z-index:4;background:transparent}
@media(min-width:768px){
    .poster{min-height:<?=$poster_h_desk?>px;
    <?php if($has_bg):?>background:url('banner.php?id=<?=$evento['id']?>') center <?=$bg_pos_y?>% / cover no-repeat;<?php endif;?>}
    .poster-ov{background:rgba(5,5,20,<?=$bg_overlay?>)}
    <?php if($show_title):?>.poster-text{min-height:<?=$poster_h_desk?>px;padding:80px 20px 100px}<?php endif;?>
    .form-wrap{margin-top:-<?=$card_overlap?>px}
}
<?php endif;?>
.form-card{width:100%;max-width:680px;background:rgba(0,0,0,.55);border:1.5px solid rgba(255,255,255,.1);border-radius:16px;padding:28px;backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);position:relative}
@media(min-width:768px){.form-card{padding:36px}}
.bottom-bg{background:<?=$cor?>}
.form-title{text-align:center;margin-bottom:20px;padding-bottom:14px;border-bottom:1.5px solid rgba(255,255,255,.08)}
.form-title h2{font-family:'<?=$titulo_font?>',sans-serif;font-size:<?=$titulo_size?>px;font-weight:800;text-transform:uppercase;letter-spacing:2px;color:#f59e0b}
.form-title p{color:rgba(255,255,255,.4);font-size:<?=$subtitulo_size?>px;margin-top:4px}
.fg{margin-bottom:16px}
.fl{display:flex;align-items:center;gap:8px;color:white;font-size:13px;font-weight:700;margin-bottom:6px}
.fl i{color:#f59e0b;font-size:14px}
.fi{width:100%;padding:13px 16px;background:rgba(255,255,255,.06);border:1.5px solid rgba(255,255,255,.15);border-radius:10px;color:white;font-size:14px;font-weight:500;transition:.3s;outline:none}
.fi::placeholder{color:rgba(255,255,255,.28)}
.fi:focus{border-color:#f59e0b;background:rgba(255,255,255,.1);box-shadow:0 0 0 3px rgba(245,158,11,.1)}
select.fi{appearance:auto;cursor:pointer}select.fi option{background:#1a1a3e;color:white}
.fr{display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media(max-width:600px){.fr{grid-template-columns:1fr}}
.sl{color:#f59e0b;font-size:14px;font-weight:700;margin:22px 0 14px;display:flex;align-items:center;gap:8px}
.btn-submit{width:100%;padding:16px;margin-top:22px;background:linear-gradient(135deg,#f59e0b,#d97706);color:#0a0a1a;border:none;border-radius:12px;font-size:17px;font-weight:900;text-transform:uppercase;letter-spacing:3px;cursor:pointer;transition:.3s;box-shadow:0 8px 30px rgba(245,158,11,.3)}
.btn-submit:hover{transform:translateY(-2px);box-shadow:0 12px 40px rgba(245,158,11,.5)}
.disc{text-align:center;margin-top:14px;font-size:11px;color:rgba(255,255,255,.3)}
.sbox{text-align:center;padding:50px 20px}
.sicon{width:90px;height:90px;background:linear-gradient(135deg,#059669,#10b981);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:44px;margin:0 auto 20px}
.bagain{display:inline-flex;align-items:center;gap:8px;padding:12px 24px;background:rgba(255,255,255,.1);border:2px solid rgba(255,255,255,.2);border-radius:12px;color:white;text-decoration:none;font-weight:600}
.emsg{background:rgba(220,38,38,.2);border:1px solid rgba(220,38,38,.4);color:#fca5a5;padding:12px;border-radius:10px;margin-bottom:16px;font-size:13px;display:flex;align-items:center;gap:8px}
.logos-row{display:flex;justify-content:center;align-items:center;gap:20px;padding:30px 20px;background:transparent;flex-wrap:wrap}
.logos-row img{height:<?=$logo_size?>px;width:auto;max-width:<?=intval($logo_size)*2.5?>px;object-fit:contain;filter:drop-shadow(0 2px 4px rgba(0,0,0,.3))}
@media(max-width:500px){.logos-row img{height:<?=max(25,intval($logo_size)-10)?>px;max-width:<?=intval(max(25,intval($logo_size)-10))*2.5?>px}}
.photo-sec{text-align:center;margin-bottom:22px;padding-bottom:18px;border-bottom:1px solid rgba(255,255,255,.06)}
.photo-lbl{color:rgba(255,255,255,.6);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px}
.photo-circle{width:100px;height:100px;border-radius:50%;background:rgba(255,255,255,.06);border:2px solid rgba(255,255,255,.12);margin:0 auto 10px;display:flex;align-items:center;justify-content:center;overflow:hidden;cursor:pointer;transition:.3s}
.photo-circle:hover{border-color:#f59e0b;transform:scale(1.05)}.photo-circle img{width:100%;height:100%;object-fit:cover}.photo-circle i{font-size:32px;color:rgba(255,255,255,.18)}
.pbtns{display:flex;gap:8px;justify-content:center}
.pb{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:8px;font-size:11px;font-weight:600;cursor:pointer;border:none;transition:.2s}
.pb.cam{background:rgba(59,130,246,.25);color:#93c5fd;border:1px solid rgba(59,130,246,.35)}
.pb.gal{background:rgba(255,255,255,.08);color:rgba(255,255,255,.5);border:1px solid rgba(255,255,255,.15)}
.pb:hover{transform:translateY(-1px)}.phint{font-size:10px;color:rgba(255,255,255,.25);margin-top:6px}
<?php if($layout_split):?>
@media(min-width:1024px){
html{height:100%}
body{
  height:100vh;overflow:hidden;
  display:grid;
  grid-template-columns:42vw 48vw;
  align-content:center;
  justify-content:center;
  gap:0;padding:20px 0;
}

/* ── IMAGEM — lado esquerdo do card ─── */
.poster{
  border-radius:16px 0 0 16px;
  overflow:hidden;
  /* imagem completa, sem corte */
  background-size:contain!important;
  background-position:center center!important;
  background-repeat:no-repeat!important;
  background-color:transparent!important;
  position:relative;
  min-height:300px;
}
/* camada desfocada atrás — mesma imagem em cover, preenche o espaço vazio */
.poster-ov{
  position:absolute;
  inset:-12px;
  background-image:inherit!important;
  background-size:cover!important;
  background-position:center center!important;
  background-color:<?=$cor?>!important;
  filter:blur(18px) brightness(0.5) saturate(1.2);
  z-index:-1;
}
.poster-fade{display:none!important}
<?php if($show_title):?>
.poster-text{
  position:absolute;bottom:0;left:0;right:0;z-index:2;
  display:flex!important;flex-direction:column;
  align-items:center;justify-content:flex-end;
  padding:12px 20px 16px;min-height:unset!important;
  background:linear-gradient(transparent,rgba(0,0,0,.65));
}
.poster-text h1{font-family:'<?=$titulo_font?>',sans-serif;font-size:clamp(13px,1.6vw,22px);font-weight:900;text-transform:uppercase;letter-spacing:1px;text-shadow:0 2px 8px rgba(0,0,0,.9);text-align:center;line-height:1.2}
.poster-text p{font-size:11px;opacity:.6;margin-top:2px;text-align:center}
<?php else:?>
.poster-text{display:none!important}
<?php endif;?>

/* ── FORMULÁRIO — lado direito do card ─ */
.form-wrap{
  border-radius:0 16px 16px 0;
  background:rgba(0,0,0,.6);
  backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);
  border:1.5px solid rgba(255,255,255,.08);
  border-left:none;
  overflow-y:auto;
  max-height:calc(100vh - 40px);
  margin-top:0!important;padding:0;
  display:flex;flex-direction:column;align-items:stretch;
  scrollbar-width:thin;scrollbar-color:rgba(255,255,255,.15) transparent;
}
.form-card{
  background:transparent;border:none;border-radius:0;
  max-width:100%;width:100%;padding:20px 24px;flex:1;
}
/* título fixo no topo — não desaparece ao scrollar */
.form-title{
  position:sticky;top:0;z-index:10;
  background:rgba(15,10,20,.85);
  backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);
  margin:-20px -24px 14px;
  padding:14px 24px 12px;
  border-bottom:1.5px solid rgba(255,255,255,.08);
  margin-bottom:14px;
}
.form-title h2{font-size:<?=max(11,intval($titulo_size)-2)?>px}
.form-title p{font-size:<?=max(9,intval($subtitulo_size)-1)?>px;margin-top:2px}
.fg{margin-bottom:9px}
.fl{font-size:12px;margin-bottom:4px}
.fi{padding:9px 12px;font-size:13px}
.fr{gap:8px}
.sl{margin:12px 0 8px;font-size:12px}
.btn-submit{padding:12px;margin-top:12px;font-size:14px;letter-spacing:2px}
.disc{margin-top:6px;font-size:10px}
.photo-sec{margin-bottom:12px;padding-bottom:10px}
.photo-circle{width:68px;height:68px}
.photo-circle i{font-size:22px}
.logos-row{padding:8px 24px 14px;gap:14px;justify-content:center}
.logos-row img{height:<?=max(22,intval($logo_size)-14)?>px}
}
<?php endif;?>
</style></head><body>
<div class="poster"><div class="poster-ov"></div>
<div class="poster-text"><h1><?=htmlspecialchars($evento['nome'])?></h1><?php if($evento['descricao']):?><p><?=htmlspecialchars($evento['descricao'])?></p><?php endif;?></div>
<div class="poster-fade"></div></div>
<div class="form-wrap"><div class="form-card">
<?php if($success):?>
<div class="sbox"><div class="sicon"><i class="fas fa-check"></i></div><h3 style="font-size:22px;font-weight:800;margin-bottom:10px">Cadastro Realizado!</h3><p style="color:rgba(255,255,255,.5);margin-bottom:20px">Seus dados foram enviados com sucesso.</p><a href="inscricao.php?evento=<?=htmlspecialchars($evento['slug']??$param)?>" class="bagain"><i class="fas fa-plus"></i> Novo cadastro</a></div>
<?php else:?>
<div class="form-title"><h2><?=htmlspecialchars($titulo_form)?></h2><?php if($subtitulo_form):?><p><?=htmlspecialchars($subtitulo_form)?></p><?php else:?><p>Preencha seus dados abaixo</p><?php endif;?></div>
<?php if($error):?><div class="emsg"><i class="fas fa-exclamation-triangle"></i> <?=htmlspecialchars($error)?></div><?php endif;?>
<form method="POST" id="F" enctype="multipart/form-data">
<?php if($show_foto):?>
<div class="photo-sec">
<div class="photo-lbl">Sua Foto <?=$req_foto?'*':'(Opcional)'?></div>
<div class="photo-circle" id="pP" onclick="document.getElementById('fCam').click()"><i class="fas fa-user"></i></div>
<input type="hidden" name="foto_base64" id="fB64"><input type="file" id="fCam" class="d-none" accept="image/*" capture="environment"><input type="file" id="fGal" class="d-none" accept="image/*">
<div class="pbtns"><button type="button" class="pb cam" onclick="document.getElementById('fCam').click()"><i class="fas fa-camera"></i> Tirar foto</button><button type="button" class="pb gal" onclick="document.getElementById('fGal').click()"><i class="fas fa-image"></i> Galeria</button></div>
<div class="phint">Tire uma foto agora ou importe da galeria</div>
</div>
<?php endif;?>
<div class="fg"><label class="fl"><i class="fas fa-user"></i> Nome Completo *</label><input type="text" class="fi" name="nome" required placeholder="Seu nome completo" value="<?=$_POST['nome']??''?>"></div>
<div class="fr">
<div class="fg"><label class="fl"><i class="fab fa-whatsapp"></i> WhatsApp / Celular *</label><input type="text" class="fi" name="whatsapp" id="wa" required placeholder="(00) 00000-0000" value="<?=$_POST['whatsapp']??''?>"></div>
<div class="fg"><label class="fl"><i class="fab fa-instagram"></i> Instagram</label><input type="text" class="fi" name="instagram" placeholder="@ seuperfil" value="<?=ltrim($_POST['instagram']??'','@')?>"></div>
</div>
<?php foreach($campos_extras as $ce):?>
<div class="fg"><label class="fl"><i class="fas fa-<?=($ce['tipo']??'')=='number'?'hashtag':(($ce['tipo']??'')=='select'?'list':'pen')?>"></i> <?=htmlspecialchars($ce['label'])?> <?=($ce['obrigatorio']??false)?'*':''?></label>
<?php if(($ce['tipo']??'text')=='select'&&!empty($ce['opcoes'])):?>
<select name="extra_<?=$ce['nome']?>" class="fi" <?=($ce['obrigatorio']??false)?'required':''?>><option value="">Selecione...</option><?php foreach($ce['opcoes'] as $op):?><option value="<?=htmlspecialchars($op)?>" <?=($_POST['extra_'.$ce['nome']]??'')==$op?'selected':''?>><?=htmlspecialchars($op)?></option><?php endforeach;?></select>
<?php else:?><input type="<?=$ce['tipo']??'text'?>" name="extra_<?=$ce['nome']?>" class="fi" placeholder="<?=($ce['tipo']??'')=='number'?'Digite...':'Digite seu '.strtolower($ce['label'])?>" value="<?=htmlspecialchars($_POST['extra_'.$ce['nome']]??'')?>" <?=($ce['obrigatorio']??false)?'required':''?>><?php endif;?></div>
<?php endforeach;?>
<?php if($show_militar):?>
<div style="margin:18px 0;padding-top:18px;border-top:1px solid rgba(255,255,255,.08)">
<div class="sl" style="margin-top:0">Dados Militares</div>
<label style="display:flex;align-items:center;gap:10px;cursor:pointer;background:rgba(5,150,105,.15);border:1px solid rgba(5,150,105,.3);padding:12px 16px;border-radius:10px;margin-bottom:12px">
    <input type="checkbox" name="is_militar" id="isMilitarChk" style="width:22px;height:22px;accent-color:#10b981" value="1" <?=!empty($_POST['is_militar'])?'checked':''?>>
    <div>
        <div style="font-size:14px;font-weight:700;color:#6ee7b7">Sou militar</div>
        <div style="font-size:11px;color:rgba(255,255,255,.5);margin-top:2px">Marque se você é servidor da Polícia Militar ou Bombeiro Militar</div>
    </div>
</label>
<div id="militarFields" style="<?=!empty($_POST['is_militar'])?'':'display:none'?>">
<div class="fr">
<div class="fg">
<label class="fl"><i class="fas fa-building-shield"></i> Corporação *</label>
<select class="fi" name="corporacao" id="corporacao">
<option value="">Selecione...</option>
<option value="Polícia Militar (PM)" <?=($_POST['corporacao']??'')==='Polícia Militar (PM)'?'selected':''?>>Polícia Militar (PM)</option>
<option value="Bombeiro Militar (BM)" <?=($_POST['corporacao']??'')==='Bombeiro Militar (BM)'?'selected':''?>>Bombeiro Militar (BM)</option>
</select>
</div>
<div class="fg">
<label class="fl"><i class="fas fa-star"></i> Patente/Posto *</label>
<select class="fi" name="patente" id="patente">
<option value="">Selecione...</option>
<optgroup label="Oficiais" style="color:#93c5fd;font-weight:700">
<option value="Coronel" <?=($_POST['patente']??'')==='Coronel'?'selected':''?>>Coronel</option>
<option value="Tenente-Coronel" <?=($_POST['patente']??'')==='Tenente-Coronel'?'selected':''?>>Tenente-Coronel</option>
<option value="Major" <?=($_POST['patente']??'')==='Major'?'selected':''?>>Major</option>
<option value="Capitão" <?=($_POST['patente']??'')==='Capitão'?'selected':''?>>Capitão</option>
<option value="1º Tenente" <?=($_POST['patente']??'')==='1º Tenente'?'selected':''?>>1º Tenente</option>
<option value="2º Tenente" <?=($_POST['patente']??'')==='2º Tenente'?'selected':''?>>2º Tenente</option>
<option value="Aspirante a Oficial" <?=($_POST['patente']??'')==='Aspirante a Oficial'?'selected':''?>>Aspirante a Oficial</option>
</optgroup>
<optgroup label="Praças" style="color:#f59e0b;font-weight:700">
<option value="Subtenente" <?=($_POST['patente']??'')==='Subtenente'?'selected':''?>>Subtenente</option>
<option value="1º Sargento" <?=($_POST['patente']??'')==='1º Sargento'?'selected':''?>>1º Sargento</option>
<option value="2º Sargento" <?=($_POST['patente']??'')==='2º Sargento'?'selected':''?>>2º Sargento</option>
<option value="3º Sargento" <?=($_POST['patente']??'')==='3º Sargento'?'selected':''?>>3º Sargento</option>
<option value="Cabo" <?=($_POST['patente']??'')==='Cabo'?'selected':''?>>Cabo</option>
<option value="Soldado 1ª Classe" <?=($_POST['patente']??'')==='Soldado 1ª Classe'?'selected':''?>>Soldado 1ª Classe</option>
<option value="Soldado 2ª Classe" <?=($_POST['patente']??'')==='Soldado 2ª Classe'?'selected':''?>>Soldado 2ª Classe</option>
</optgroup>
</select>
</div>
</div>
</div>
</div>
<?php endif;?>
<div class="sl"><i class="fas fa-map-marker-alt"></i> Endereço</div>
<div class="fr">
<div class="fg"><label class="fl"><i class="fas fa-map-pin"></i> CEP</label><input type="text" class="fi" name="cep" id="cep" placeholder="00000-000"></div>
<div class="fg"><label class="fl"><i class="fas fa-road"></i> Logradouro</label><input type="text" class="fi" name="endereco" id="endereco" placeholder="Rua, Av, Bairro..."></div>
</div>
<div class="fr">
<div class="fg"><label class="fl"><i class="fas fa-city"></i> Cidade</label><input type="text" class="fi" name="cidade" id="cidade"></div>
<div class="fg"><label class="fl"><i class="fas fa-flag"></i> Estado</label><select class="fi" name="estado" id="estado"><option value="">UF</option><?php foreach(['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] as $u):?><option value="<?=$u?>"><?=$u?></option><?php endforeach;?></select></div>
</div>
<?php if($show_acomp):?>
<div style="margin:18px 0;padding-top:18px;border-top:1px solid rgba(255,255,255,.08)">
<div class="sl" style="margin-top:0"><i class="fas fa-user-friends"></i> Acompanhantes <?php if(!$acomp_unlimited):?><small style="font-weight:400;opacity:.5">(máx. <?=$max_acomp?>)</small><?php else:?><small style="font-weight:400;opacity:.5">(sem limite)</small><?php endif;?></div>
<div id="acompList"></div>
<div style="text-align:center;margin-top:8px">
<button type="button" id="btnAddAcomp" onclick="addAcomp()" style="background:rgba(245,158,11,.2);color:#f59e0b;border:1px solid rgba(245,158,11,.3);padding:8px 20px;font-size:13px;font-weight:600;border-radius:10px;cursor:pointer;display:inline-flex;align-items:center;gap:8px"><i class="fas fa-plus"></i> Adicionar Acompanhante</button>
</div>
</div>
<script>
const ACOMP_MILITAR=<?=$militar_acomp?'true':'false'?>;
const ACOMP_FIELDS={wa:<?=$acomp_wa?'true':'false'?>,cpf:<?=$acomp_cpf?'true':'false'?>,ig:<?=$acomp_ig?'true':'false'?>,end:<?=$acomp_end?'true':'false'?>};
</script>
<?php endif;?>
<?php if($show_video):?>
<div style="margin:18px 0;padding-top:18px;border-top:1px solid rgba(255,255,255,.08)">
<div class="photo-lbl" style="color:rgba(255,255,255,.7)"><i class="fas fa-video"></i> Vídeo <?=$req_video?'*':'(Opcional)'?></div>
<input type="hidden" name="video_url_ready" id="vidUrlReady" value="">
<div id="vidPreview" style="display:none;margin:10px auto;max-width:100%;border-radius:10px;overflow:hidden;border:2px solid rgba(16,185,129,.4)">
<video id="vidPlayer" controls playsinline style="width:100%;max-height:200px;background:#000"></video>
<div id="vidInfo" style="text-align:center;padding:4px;font-size:11px;color:rgba(255,255,255,.5)"></div>
<div style="text-align:center;padding:6px"><button type="button" onclick="removeVideo()" style="background:rgba(220,38,38,.3);color:#fca5a5;border:1px solid rgba(220,38,38,.4);border-radius:6px;padding:4px 12px;font-size:11px;cursor:pointer"><i class="fas fa-trash"></i> Remover</button></div>
</div>
<div id="vidProgress" style="display:none;margin:10px 0">
<div style="display:flex;justify-content:space-between;margin-bottom:4px"><span id="vidProgressText" style="font-size:11px;color:rgba(255,255,255,.6)">Enviando...</span><span id="vidProgressPct" style="font-size:11px;color:#10b981;font-weight:700">0%</span></div>
<div style="height:6px;background:rgba(255,255,255,.1);border-radius:3px;overflow:hidden"><div id="vidProgressBar" style="height:100%;background:linear-gradient(90deg,#10b981,#059669);width:0%;border-radius:3px;transition:width .3s"></div></div>
</div>
<div id="vidChoose" style="text-align:center;margin-bottom:8px">
<button type="button" onclick="document.getElementById('vidFile').click()" style="background:rgba(255,255,255,.1);color:rgba(255,255,255,.7);border:1px solid rgba(255,255,255,.2);padding:10px 24px;font-size:13px;font-weight:600;border-radius:10px;cursor:pointer;display:inline-flex;align-items:center;gap:8px"><i class="fas fa-video"></i> Selecionar Vídeo</button>
</div>
<input type="file" id="vidFile" class="d-none" accept="video/*">
<div class="phint"><i class="fas fa-info-circle"></i> MP4, MOV, AVI, WebM, 3GP — máx. 200MB</div>
<div id="vidFileName" style="display:none;text-align:center;margin-top:6px;font-size:11px;color:rgba(255,255,255,.6)"></div>
<div id="vidError" style="display:none;text-align:center;margin-top:6px;font-size:12px;color:#fca5a5;background:rgba(220,38,38,.15);padding:8px;border-radius:8px"></div>
<div id="vidDone" style="display:none;text-align:center;margin-top:6px;font-size:12px;color:#10b981;font-weight:600"><i class="fas fa-check-circle"></i> Vídeo enviado com sucesso!</div>
</div>
<?php endif;?>
<button type="submit" class="btn-submit" id="btnS">PARTICIPAR</button>
<p class="disc">Seus dados estão seguros e não serão compartilhados.</p>
</form>
<?php endif;?>
</div>
<?php
$logos=[];
if(($cf['logo_bombeiro']??'1')==='1')$logos[]='assets/img/logobombeiro.png';
if(($cf['logo_policia']??'1')==='1')$logos[]='assets/img/logopolicia.png';
if(($cf['logo_assego']??'1')==='1')$logos[]='assets/img/logo_assego.png';
if(($cf['logo_sergio']??'1')==='1')$logos[]='assets/img/logo_sergio.png';
$evIdLogos=$evento['id']??'';
if($evIdLogos){
    foreach(glob(__DIR__."/uploads/logos/logo_ev{$evIdLogos}_custom1.*") as $f)$logos[]='uploads/logos/'.basename($f);
    foreach(glob(__DIR__."/uploads/logos/logo_ev{$evIdLogos}_custom2.*") as $f)$logos[]='uploads/logos/'.basename($f);
}
if(!empty($logos)):?>
<div class="logos-row"><?php foreach($logos as $lg):?><img src="<?=htmlspecialchars($lg)?>"><?php endforeach;?></div>
<?php endif;?>
</div>
<script>
function proc(f){if(!f||!f.type.startsWith('image/'))return;const r=new FileReader();r.onload=function(ev){const img=new Image();img.onload=function(){const c=document.createElement('canvas');let w=img.width,h=img.height,m=800;if(w>m||h>m){if(w>h){h=Math.round(h*m/w);w=m}else{w=Math.round(w*m/h);h=m}}c.width=w;c.height=h;c.getContext('2d').drawImage(img,0,0,w,h);const b=c.toDataURL('image/jpeg',.8);document.getElementById('fB64').value=b;document.getElementById('pP').innerHTML=`<img src="${b}">`};img.src=ev.target.result};r.readAsDataURL(f)}
document.getElementById('fCam')?.addEventListener('change',function(){proc(this.files[0])});
document.getElementById('fGal')?.addEventListener('change',function(){proc(this.files[0])});
document.getElementById('isMilitarChk')?.addEventListener('change',function(){
    document.getElementById('militarFields').style.display=this.checked?'block':'none';
    if(this.checked){document.getElementById('corporacao').required=true;document.getElementById('patente').required=true;}
    else{document.getElementById('corporacao').required=false;document.getElementById('patente').required=false;}
});
document.getElementById('wa')?.addEventListener('input',function(e){let v=e.target.value.replace(/\D/g,'').substring(0,11);if(v.length>6)v='('+v.substring(0,2)+') '+v.substring(2,7)+'-'+v.substring(7);else if(v.length>2)v='('+v.substring(0,2)+') '+v.substring(2);else if(v.length>0)v='('+v;e.target.value=v});
document.getElementById('cep')?.addEventListener('input',function(e){let v=e.target.value.replace(/\D/g,'');if(v.length>8)v=v.slice(0,8);v=v.replace(/^(\d{5})(\d)/,'$1-$2');e.target.value=v;if(v.replace(/\D/g,'').length===8)fetch(`https://viacep.com.br/ws/${v.replace(/\D/g,'')}/json/`).then(r=>r.json()).then(d=>{if(!d.erro){document.getElementById('endereco').value=d.logradouro+(d.bairro?', '+d.bairro:'');document.getElementById('cidade').value=d.localidade;document.getElementById('estado').value=d.uf}}).catch(()=>{})});
document.getElementById('F')?.addEventListener('submit',function(e){const b=document.getElementById('btnS');b.innerHTML='<i class="fas fa-spinner fa-spin"></i> ENVIANDO...';b.disabled=true;b.style.opacity='.7';});
let videoUploading=false,currentUploadId=null;
document.getElementById('vidFile')?.addEventListener('change',async function(){
    const f=this.files[0];const errEl=document.getElementById('vidError');const doneEl=document.getElementById('vidDone');
    errEl.style.display='none';doneEl.style.display='none';if(!f)return;
    const isVideo=f.type.startsWith('video/')||/\.(mp4|mov|avi|webm|mkv|3gp|3gpp|m4v|mpg|mpeg|ogv|wmv|flv)$/i.test(f.name);
    if(!isVideo){errEl.innerHTML='<i class="fas fa-exclamation-triangle"></i> Formato não reconhecido.';errEl.style.display='block';this.value='';return;}
    if(f.size>200*1024*1024){errEl.innerHTML='<i class="fas fa-exclamation-triangle"></i> Vídeo muito grande ('+Math.round(f.size/1024/1024)+'MB). Máximo: 200MB.';errEl.style.display='block';this.value='';return;}
    try{document.getElementById('vidPlayer').src=URL.createObjectURL(f);}catch(e){}
    document.getElementById('vidPreview').style.display='block';document.getElementById('vidChoose').style.display='none';
    const sizeMB=f.size<1024*1024?(f.size/1024).toFixed(0)+'KB':(f.size/1024/1024).toFixed(1)+'MB';
    document.getElementById('vidFileName').style.display='block';document.getElementById('vidFileName').innerHTML='<i class="fas fa-file-video"></i> '+f.name+' ('+sizeMB+')';
    const CHUNK_SIZE=1*1024*1024;const totalChunks=Math.ceil(f.size/CHUNK_SIZE);
    document.getElementById('vidProgress').style.display='block';document.getElementById('vidProgressText').textContent='Iniciando envio...';document.getElementById('vidProgressPct').textContent='0%';document.getElementById('vidProgressBar').style.width='0%';
    const btnS=document.getElementById('btnS');btnS.disabled=true;btnS.style.opacity='.5';btnS.innerHTML='<i class="fas fa-spinner fa-spin"></i> AGUARDE O UPLOAD...';videoUploading=true;
    try{
        const startForm=new FormData();startForm.append('action','start');startForm.append('fileName',f.name);startForm.append('fileSize',f.size);startForm.append('totalChunks',totalChunks);
        const startRes=await fetch('upload_video.php',{method:'POST',body:startForm}).then(r=>r.json());if(startRes.error){throw new Error(startRes.error);}currentUploadId=startRes.uploadId;
        for(let i=0;i<totalChunks;i++){const start=i*CHUNK_SIZE;const end=Math.min(start+CHUNK_SIZE,f.size);const chunk=f.slice(start,end);const chunkForm=new FormData();chunkForm.append('action','chunk');chunkForm.append('uploadId',currentUploadId);chunkForm.append('chunkIndex',i);chunkForm.append('chunk',chunk,'chunk');let retries=3,chunkOk=false;while(retries>0&&!chunkOk){try{const cRes=await fetch('upload_video.php',{method:'POST',body:chunkForm}).then(r=>r.json());if(cRes.error)throw new Error(cRes.error);chunkOk=true;const pct=cRes.percent||Math.round(((i+1)/totalChunks)*100);document.getElementById('vidProgressBar').style.width=pct+'%';document.getElementById('vidProgressPct').textContent=pct+'%';document.getElementById('vidProgressText').textContent='Enviando... '+Math.round((i+1)*CHUNK_SIZE/1024/1024)+'MB de '+sizeMB;}catch(err){retries--;if(retries===0)throw err;await new Promise(r=>setTimeout(r,1000));}}}
        const finForm=new FormData();finForm.append('action','finish');finForm.append('uploadId',currentUploadId);const finRes=await fetch('upload_video.php',{method:'POST',body:finForm}).then(r=>r.json());if(finRes.error)throw new Error(finRes.error);
        document.getElementById('vidUrlReady').value=finRes.video_url;document.getElementById('vidProgress').style.display='none';doneEl.style.display='block';document.getElementById('vidInfo').textContent='Vídeo pronto para enviar';videoUploading=false;btnS.disabled=false;btnS.style.opacity='1';btnS.innerHTML='PARTICIPAR';currentUploadId=null;
    }catch(err){errEl.innerHTML='<i class="fas fa-exclamation-triangle"></i> Erro no upload: '+err.message;errEl.style.display='block';document.getElementById('vidProgress').style.display='none';videoUploading=false;btnS.disabled=false;btnS.style.opacity='1';btnS.innerHTML='PARTICIPAR';if(currentUploadId){const cForm=new FormData();cForm.append('action','cancel');cForm.append('uploadId',currentUploadId);fetch('upload_video.php',{method:'POST',body:cForm}).catch(()=>{});currentUploadId=null;}}
});
function removeVideo(){document.getElementById('vidFile').value='';document.getElementById('vidPreview').style.display='none';document.getElementById('vidChoose').style.display='block';document.getElementById('vidFileName').style.display='none';document.getElementById('vidError').style.display='none';document.getElementById('vidDone').style.display='none';document.getElementById('vidProgress').style.display='none';document.getElementById('vidUrlReady').value='';try{document.getElementById('vidPlayer').src='';}catch(e){}if(!videoUploading){const btnS=document.getElementById('btnS');btnS.disabled=false;btnS.style.opacity='1';btnS.innerHTML='PARTICIPAR';}if(currentUploadId){const cForm=new FormData();cForm.append('action','cancel');cForm.append('uploadId',currentUploadId);fetch('upload_video.php',{method:'POST',body:cForm}).catch(()=>{});currentUploadId=null;videoUploading=false;}}
let acompCount=0;
const maxAcomp=<?=$max_acomp??3?>;
const acompUnlimited=<?=$acomp_unlimited?'true':'false'?>;
const UFS=['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
function addAcomp(){
    if(!acompUnlimited&&acompCount>=maxAcomp)return;acompCount++;
    const div=document.createElement('div');div.className='acomp-item';div.style.cssText='background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.2);border-radius:10px;padding:12px;margin-bottom:8px;position:relative';
    let html=`<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px"><span style="color:#f59e0b;font-size:12px;font-weight:700"><i class="fas fa-user"></i> Acompanhante ${acompCount}</span><button type="button" onclick="removeAcomp(this)" style="background:rgba(220,38,38,.2);color:#fca5a5;border:1px solid rgba(220,38,38,.3);border-radius:6px;padding:2px 8px;font-size:11px;cursor:pointer"><i class="fas fa-times"></i></button></div><div class="fg" style="margin-bottom:6px"><input type="text" class="fi" name="acomp_nome[]" placeholder="Nome completo *" required style="font-size:13px;padding:10px 12px"></div>`;
    if(typeof ACOMP_FIELDS!=='undefined'&&ACOMP_FIELDS.wa){html+=`<div class="fg" style="margin-bottom:6px"><input type="text" class="fi acomp-wa" name="acomp_whatsapp[]" placeholder="WhatsApp" style="font-size:13px;padding:10px 12px"></div>`;}
    if(typeof ACOMP_FIELDS!=='undefined'&&ACOMP_FIELDS.cpf){html+=`<div class="fg" style="margin-bottom:6px"><input type="text" class="fi acomp-cpf" name="acomp_cpf_field[]" placeholder="CPF" style="font-size:13px;padding:10px 12px"></div>`;}
    if(typeof ACOMP_FIELDS!=='undefined'&&ACOMP_FIELDS.ig){html+=`<div class="fg" style="margin-bottom:6px"><input type="text" class="fi" name="acomp_instagram[]" placeholder="@instagram" style="font-size:13px;padding:10px 12px"></div>`;}
    if(typeof ACOMP_FIELDS!=='undefined'&&ACOMP_FIELDS.end){html+=`<div style="margin-top:6px;padding-top:6px;border-top:1px solid rgba(245,158,11,.15)"><div style="font-size:11px;color:#f59e0b;font-weight:600;margin-bottom:6px"><i class="fas fa-map-marker-alt"></i> Endereço</div><div class="fg" style="margin-bottom:6px"><input type="text" class="fi acomp-cep" name="acomp_cep[]" placeholder="CEP" maxlength="9" style="font-size:13px;padding:10px 12px"></div><div class="fg" style="margin-bottom:6px"><input type="text" class="fi acomp-end" name="acomp_endereco[]" placeholder="Rua, nº - Bairro" style="font-size:13px;padding:10px 12px"></div><div class="fg" style="margin-bottom:6px"><input type="text" class="fi acomp-bairro" name="acomp_bairro[]" placeholder="Bairro" style="font-size:13px;padding:10px 12px"></div><div class="fr"><div class="fg" style="margin-bottom:0"><input type="text" class="fi acomp-cidade" name="acomp_cidade[]" placeholder="Cidade" style="font-size:13px;padding:10px 12px"></div><div class="fg" style="margin-bottom:0"><select class="fi acomp-uf" name="acomp_estado[]" style="font-size:13px;padding:10px 12px"><option value="">UF</option>${UFS.map(u=>'<option value="'+u+'">'+u+'</option>').join('')}</select></div></div></div>`;}
    if(ACOMP_MILITAR){
        html+=`<div style="margin-top:8px;padding-top:8px;border-top:1px solid rgba(5,150,105,.2)">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-bottom:8px;font-size:12px;color:#6ee7b7">
            <input type="checkbox" class="acomp-is-militar" name="acomp_militar[]" value="1" style="width:16px;height:16px;accent-color:#10b981">
            É militar
        </label>
        <div class="acomp-militar-fields" style="display:none">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px">
        <select class="fi acomp-corporacao" name="acomp_corporacao[]" style="font-size:12px;padding:8px 10px">
            <option value="">Corporação...</option>
            <option value="Polícia Militar (PM)">Polícia Militar (PM)</option>
            <option value="Bombeiro Militar (BM)">Bombeiro Militar (BM)</option>
        </select>
        <select class="fi acomp-patente" name="acomp_patente[]" style="font-size:12px;padding:8px 10px">
            <option value="">Patente/Posto...</option>
            <optgroup label="Oficiais" style="color:#93c5fd;font-weight:700"><option>Coronel</option><option>Tenente-Coronel</option><option>Major</option><option>Capitão</option><option>1º Tenente</option><option>2º Tenente</option><option>Aspirante a Oficial</option></optgroup>
            <optgroup label="Praças" style="color:#f59e0b;font-weight:700"><option>Subtenente</option><option>1º Sargento</option><option>2º Sargento</option><option>3º Sargento</option><option>Cabo</option><option>Soldado 1ª Classe</option><option>Soldado 2ª Classe</option></optgroup>
        </select>
        </div></div></div>`;
    }
    div.innerHTML=html;document.getElementById('acompList').appendChild(div);
    const waInput=div.querySelector('.acomp-wa');if(waInput)waInput.addEventListener('input',function(e){let v=e.target.value.replace(/\D/g,'').substring(0,11);if(v.length>6)v='('+v.substring(0,2)+') '+v.substring(2,7)+'-'+v.substring(7);else if(v.length>2)v='('+v.substring(0,2)+') '+v.substring(2);else if(v.length>0)v='('+v;e.target.value=v});
    const cpfInput=div.querySelector('.acomp-cpf');if(cpfInput)cpfInput.addEventListener('input',function(e){let v=e.target.value.replace(/\D/g,'').substring(0,11);if(v.length>9)v=v.substring(0,3)+'.'+v.substring(3,6)+'.'+v.substring(6,9)+'-'+v.substring(9);else if(v.length>6)v=v.substring(0,3)+'.'+v.substring(3,6)+'.'+v.substring(6);else if(v.length>3)v=v.substring(0,3)+'.'+v.substring(3);e.target.value=v});
    const milChk=div.querySelector('.acomp-is-militar');
    if(milChk)milChk.addEventListener('change',function(){this.closest('.acomp-item').querySelector('.acomp-militar-fields').style.display=this.checked?'block':'none';});
    const cepInput=div.querySelector('.acomp-cep');if(cepInput){cepInput.addEventListener('input',function(e){let v=e.target.value.replace(/\D/g,'').substring(0,8);if(v.length>5)v=v.substring(0,5)+'-'+v.substring(5);e.target.value=v});cepInput.addEventListener('blur',function(){const cep=this.value.replace(/\D/g,'');if(cep.length===8){const item=this.closest('.acomp-item');fetch('https://viacep.com.br/ws/'+cep+'/json/').then(r=>r.json()).then(d=>{if(!d.erro){const endI=item.querySelector('.acomp-end');if(endI&&!endI.value)endI.value=d.logradouro||'';const bI=item.querySelector('.acomp-bairro');if(bI&&!bI.value)bI.value=d.bairro||'';const cI=item.querySelector('.acomp-cidade');if(cI)cI.value=d.localidade||'';const uI=item.querySelector('.acomp-uf');if(uI)uI.value=d.uf||'';}}).catch(()=>{})}});}
    checkAcompBtn();
}
function removeAcomp(btn){btn.closest('.acomp-item').remove();acompCount--;document.querySelectorAll('.acomp-item').forEach((item,i)=>{item.querySelector('span').innerHTML=`<i class="fas fa-user"></i> Acompanhante ${i+1}`;});checkAcompBtn();}
function checkAcompBtn(){const btn=document.getElementById('btnAddAcomp');if(btn){btn.style.display=(!acompUnlimited&&acompCount>=maxAcomp)?'none':'inline-flex';}}
</script>
</body></html>