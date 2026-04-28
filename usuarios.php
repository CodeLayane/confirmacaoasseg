<?php
require_once 'layout.php';
if(!isAdmin()){header('Location: index.php');exit();}
$action=$_GET['action']??'list';$message='';$error='';

if($_SERVER['REQUEST_METHOD']=='POST'){
    $nome=clean_input($_POST['nome']);$email=clean_input($_POST['email']);$senha=$_POST['senha']??'';$senha2=$_POST['senha2']??'';$role=$_POST['role']??'operador';$ativo=isset($_POST['ativo'])?1:0;$eventos_ids=$_POST['eventos']??[];
    
    // Validar senhas
    if($action=='add'&&empty($senha)){$error="Senha obrigatória.";}
    elseif(!empty($senha)&&$senha!==$senha2){$error="As senhas não coincidem.";}
    
    if(!$error){
        try{
            if($action=='add'){
                $hash=password_hash($senha,PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO usuarios (nome,email,senha,role,ativo) VALUES (?,?,?,?,?)")->execute([$nome,$email,$hash,$role,$ativo]);
                $uid=$pdo->lastInsertId();
                foreach($eventos_ids as $eid)$pdo->prepare("INSERT IGNORE INTO usuario_eventos (usuario_id,evento_id) VALUES (?,?)")->execute([$uid,$eid]);
                logAuditoria($pdo,'criar','usuario',$uid,['nome'=>$nome,'role'=>$role]);
                header("Location: usuarios.php?message=".urlencode("Usuário '$nome' criado!"));exit();
            }else{
                $id=$_POST['id'];
                $pdo->prepare("UPDATE usuarios SET nome=?,email=?,role=?,ativo=? WHERE id=?")->execute([$nome,$email,$role,$ativo,$id]);
                if(!empty($senha)){
                    $hash=password_hash($senha,PASSWORD_DEFAULT);
                    $pdo->prepare("UPDATE usuarios SET senha=? WHERE id=?")->execute([$hash,$id]);
                }
                $pdo->prepare("DELETE FROM usuario_eventos WHERE usuario_id=?")->execute([$id]);
                foreach($eventos_ids as $eid)$pdo->prepare("INSERT IGNORE INTO usuario_eventos (usuario_id,evento_id) VALUES (?,?)")->execute([$id,$eid]);
                logAuditoria($pdo,'editar','usuario',$id,['nome'=>$nome,'role'=>$role,'ativo'=>$ativo]);
                header("Location: usuarios.php?message=".urlencode("Usuário atualizado!"));exit();
            }
        }catch(PDOException $e){
            $error=($e->getCode()=='23000')?"Login já existe.":"Erro: ".$e->getMessage();
        }
    }
}

if($action=='delete'&&isset($_GET['id'])){
    $did=(int)$_GET['id'];
    if($did==getUserId())$error="Não pode excluir seu próprio usuário.";
    else{
        $dn=$pdo->prepare("SELECT nome FROM usuarios WHERE id=?");$dn->execute([$did]);$delNome=$dn->fetchColumn()?:'';
        $pdo->prepare("DELETE FROM usuario_eventos WHERE usuario_id=?")->execute([$did]);
        $pdo->prepare("DELETE FROM usuarios WHERE id=?")->execute([$did]);
        logAuditoria($pdo,'excluir','usuario',$did,['nome'=>$delNome]);
        header("Location: usuarios.php?message=".urlencode("Excluído!"));exit();
    }
}

$usuario=null;$usuario_eventos=[];
if($action=='edit'&&isset($_GET['id'])){
    $s=$pdo->prepare("SELECT * FROM usuarios WHERE id=?");$s->execute([$_GET['id']]);$usuario=$s->fetch();
    $s=$pdo->prepare("SELECT evento_id FROM usuario_eventos WHERE usuario_id=?");$s->execute([$_GET['id']]);$usuario_eventos=$s->fetchAll(PDO::FETCH_COLUMN);
}

$usuarios_list=$pdo->query("SELECT u.*,(SELECT COUNT(*) FROM usuario_eventos WHERE usuario_id=u.id) as total_eventos FROM usuarios u ORDER BY u.ativo ASC, u.nome")->fetchAll();
$colsU=array_column($pdo->query("SHOW COLUMNS FROM usuarios")->fetchAll(),'Field');$hasObsU=in_array('observacao',$colsU);
$todos_eventos=$pdo->query("SELECT id,nome FROM eventos ORDER BY nome")->fetchAll();
$pendentes_count=0;foreach($usuarios_list as $u)if(!$u['ativo'])$pendentes_count++;

if(isset($_GET['message']))$message=$_GET['message'];

// Link de cadastro público
$base=(isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on'?"https":"http")."://".$_SERVER['HTTP_HOST'].rtrim(dirname($_SERVER['SCRIPT_NAME']),'/').'/';
$link_cadastro=$base."cadastro_operador.php";
$qrUrl='https://api.qrserver.com/v1/create-qr-code/?size=250x250&data='.urlencode($link_cadastro).'&bgcolor=ffffff&color=1e3a8a&format=png&margin=10';
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Usuários - ASSEGO</title><?php renderCSS();?>
<style>
.pw-wrap{position:relative}
.pw-wrap .eye-btn{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:#94a3b8;cursor:pointer;font-size:16px;padding:4px}
.pw-wrap .eye-btn:hover{color:#1e3a8a}
</style>
</head><body>
<?php renderHeader('usuarios');?>
<?php if($message):?><div class="content-container"><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?=htmlspecialchars($message)?></div></div><?php endif;?>
<?php if($error):?><div class="content-container"><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?=htmlspecialchars($error)?></div></div><?php endif;?>

<?php if($action=='list'):?>
<div class="page-header">
    <h1 class="page-title"><i class="fas fa-user-shield"></i> Gestão de Usuários <?php if($pendentes_count):?><span style="background:#fef3c7;color:#92400e;padding:4px 10px;border-radius:20px;font-size:13px;font-weight:600;margin-left:8px"><i class="fas fa-clock"></i> <?=$pendentes_count?> pendente<?=$pendentes_count>1?'s':''?></span><?php endif;?></h1>
    <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-sm" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);color:white;border:none" data-bs-toggle="modal" data-bs-target="#modalLinkOp"><i class="fas fa-share-alt"></i> Link de Acesso</button>
        <a href="usuarios.php?action=add" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Novo Usuário</a>
    </div>
</div>
<div class="content-container"><div class="table-container" style="margin:0">
<table class="table"><thead><tr><th>Nome</th><th>Login</th><th>Perfil</th><th class="text-center">Eventos</th><th class="text-center">Status</th><th class="text-center">Ações</th></tr></thead>
<tbody>
<?php foreach($usuarios_list as $u):?>
<tr<?=!$u['ativo']?' style="background:#fffbeb"':''?>>
<td><strong><?=htmlspecialchars($u['nome'])?></strong><?php if(!$u['ativo']):?> <span style="background:#fef3c7;color:#92400e;padding:2px 6px;border-radius:6px;font-size:10px;font-weight:700">PENDENTE</span><?php endif;?></td>
<td><?=htmlspecialchars($u['email'])?></td>
<td><?php if($u['role']=='admin'):?><span style="background:linear-gradient(135deg,#f59e0b,#fbbf24);color:#78350f;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700"><i class="fas fa-crown"></i> Admin</span><?php else:?><span style="background:#dbeafe;color:#1e40af;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600">Operador</span><?php endif;?></td>
<td class="text-center"><span class="badge badge-info"><?=$u['role']=='admin'?'Todos':$u['total_eventos']?></span></td>
<td class="text-center"><?php if($u['ativo']):?><span style="background:#dcfce7;color:#16a34a;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600">Ativo</span><?php else:?><span style="background:#fee2e2;color:#dc2626;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600">Inativo</span><?php endif;?></td>
<td class="text-center"><div class="action-buttons" style="justify-content:center">
<a href="usuarios.php?action=edit&id=<?=$u['id']?>" class="btn-action edit" title="Editar"><i class="fas fa-edit"></i></a>
<?php if($u['id']!=getUserId()):?><button onclick="Swal.fire({title:'Excluir?',icon:'warning',showCancelButton:true,confirmButtonColor:'#dc2626',confirmButtonText:'Excluir'}).then(r=>{if(r.isConfirmed)location='usuarios.php?action=delete&id=<?=$u['id']?>'})" class="btn-action delete" title="Excluir"><i class="fas fa-trash"></i></button><?php endif;?>
</div></td></tr>
<?php endforeach;?></tbody></table></div></div>

<!-- Modal Link Cadastro Operador -->
<div class="modal fade" id="modalLinkOp" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content" style="border-radius:16px;border:none">
<div class="modal-header" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);color:white;border-radius:16px 16px 0 0"><h5 class="modal-title"><i class="fas fa-user-plus"></i> Link de Cadastro</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
<div class="modal-body p-4 text-center">
<img src="<?=$qrUrl?>" width="180" height="180" alt="QR Code" style="border-radius:12px;border:3px solid #e0e7ff;margin-bottom:16px" id="qrOpImg">
<p class="text-muted small mb-2">Compartilhe para que pessoas solicitem acesso ao sistema</p>
<div class="input-group mb-3"><input type="text" class="form-control bg-light" id="linkOpInput" value="<?=$link_cadastro?>" readonly style="font-size:13px"><button class="btn btn-primary" onclick="navigator.clipboard.writeText(document.getElementById('linkOpInput').value).then(()=>Swal.fire({icon:'success',title:'Copiado!',timer:1500,showConfirmButton:false}))"><i class="fas fa-copy"></i></button></div>
<div class="d-flex gap-2 justify-content-center flex-wrap">
<button onclick="(function(){const a=document.createElement('a');a.href=document.getElementById('qrOpImg').src;a.download='qr-cadastro-operador.png';a.click()})()" class="btn btn-sm btn-outline-primary"><i class="fas fa-download"></i> Baixar QR</button>
<button onclick="(function(){const w=window.open('','_blank','width=500,height=600');w.document.write('<html><head><title>QR Cadastro</title><style>body{text-align:center;font-family:Inter,Arial;padding:40px}h2{color:#1e3a8a;margin-bottom:8px}p{color:#64748b;font-size:14px}img{margin:20px 0}</style></head><body><h2>ASSEGO Eventos</h2><p>Cadastro de Operadores</p><img src='+document.getElementById('qrOpImg').src+' width=300 height=300><p style=margin-top:16px;word-break:break-all><?=$link_cadastro?></p></body></html>');w.document.close();w.onload=function(){w.print()}})()" class="btn btn-sm btn-outline-success"><i class="fas fa-print"></i> Imprimir</button>
</div>
<div class="alert alert-info small py-2 mt-3 mb-0"><i class="fas fa-info-circle"></i> Usuários cadastrados ficam <strong>inativos</strong> até você ativar e atribuir eventos.</div>
</div></div></div></div>

<?php elseif($action=='add'||$action=='edit'):?>
<div class="content-container"><div class="form-card">
<h2 style="color:var(--primary);margin-bottom:24px"><i class="fas <?=$action=='add'?'fa-user-plus':'fa-user-edit'?>"></i> <?=$action=='add'?'Novo Usuário':'Editar Usuário'?></h2>
<form method="POST">
<?php if($action=='edit'):?><input type="hidden" name="id" value="<?=$usuario['id']?>"><?php endif;?>
<div class="row g-3 mb-4">
    <div class="col-md-6"><label class="form-label">Nome Completo *</label><input type="text" name="nome" class="form-control" required value="<?=$usuario?htmlspecialchars($usuario['nome']):''?>"></div>
    <div class="col-md-6"><label class="form-label">Login *</label><input type="text" name="email" class="form-control" required value="<?=$usuario?htmlspecialchars($usuario['email']):''?>"></div>
    <div class="col-md-3">
        <label class="form-label">Senha <?=$action=='edit'?'(vazio=manter)':'*'?></label>
        <div class="pw-wrap"><input type="password" name="senha" id="pwA" class="form-control" <?=$action=='add'?'required':''?> placeholder="<?=$action=='edit'?'Manter atual':'Criar senha'?>">
        <button type="button" class="eye-btn" onclick="togglePw('pwA',this)"><i class="fas fa-eye"></i></button></div>
    </div>
    <div class="col-md-3">
        <label class="form-label">Confirmar Senha</label>
        <div class="pw-wrap"><input type="password" name="senha2" id="pwB" class="form-control" placeholder="Repetir">
        <button type="button" class="eye-btn" onclick="togglePw('pwB',this)"><i class="fas fa-eye"></i></button></div>
        <div id="pwMatchAdmin" style="font-size:11px;margin-top:4px;height:14px"></div>
    </div>
    <div class="col-md-3"><label class="form-label">Perfil</label><select name="role" class="form-select">
        <option value="operador" <?=($usuario&&$usuario['role']=='operador')?'selected':''?>>Operador</option>
        <option value="admin" <?=($usuario&&$usuario['role']=='admin')?'selected':''?>>Administrador</option>
    </select></div>
    <div class="col-md-3"><label class="form-label">Status</label><div class="d-flex align-items-center gap-3 mt-1"><input type="checkbox" name="ativo" style="width:24px;height:24px" <?=(!$usuario||$usuario['ativo'])?'checked':''?>><label>Ativo</label></div></div>
</div>
<h4 style="color:var(--primary);margin-bottom:16px"><i class="fas fa-calendar-check"></i> Eventos Permitidos <small style="color:var(--gray);font-weight:400;font-size:12px">(Admin acessa todos)</small></h4>
<?php if($hasObsU&&$usuario&&!empty($usuario['observacao'])):?>
<div style="background:#fffbeb;border:1px solid #fde68a;border-radius:12px;padding:14px 18px;margin-bottom:20px;font-size:13px">
<div style="font-weight:700;color:#92400e;margin-bottom:4px"><i class="fas fa-comment-dots"></i> Observação do usuário:</div>
<div style="color:#78350f"><?=htmlspecialchars($usuario['observacao'])?></div>
</div>
<?php endif;?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:10px;margin-bottom:24px">
<?php foreach($todos_eventos as $ev):?>
<label style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:#f0f9ff;border:2px solid #dbeafe;border-radius:10px;cursor:pointer">
    <input type="checkbox" name="eventos[]" value="<?=$ev['id']?>" style="width:20px;height:20px" <?=in_array($ev['id'],$usuario_eventos)?'checked':''?>>
    <span style="font-weight:500"><?=htmlspecialchars($ev['nome'])?></span>
</label>
<?php endforeach;?>
<?php if(empty($todos_eventos)):?><p style="color:var(--gray)">Nenhum evento. <a href="eventos.php?action=add">Criar</a></p><?php endif;?>
</div>
<div class="d-flex gap-2"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button><a href="usuarios.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancelar</a></div>
</form></div></div>
<?php endif;?>
<?php renderScripts();?>
<script>
function togglePw(id,btn){const i=document.getElementById(id);const p=i.type==='password';i.type=p?'text':'password';btn.querySelector('i').className=p?'fas fa-eye-slash':'fas fa-eye';}
document.getElementById('pwB')?.addEventListener('input',function(){
    const p1=document.getElementById('pwA').value;const p2=this.value;const el=document.getElementById('pwMatchAdmin');
    if(!p2){el.innerHTML='';return;}
    el.innerHTML=p1===p2?'<span style="color:#059669"><i class="fas fa-check-circle"></i> OK</span>':'<span style="color:#dc2626"><i class="fas fa-times-circle"></i> Não coincidem</span>';
});
</script>
</body></html>
