<?php
session_start();
if(!isset($_SESSION['captcha_num1'])||!isset($_SESSION['captcha_num2'])){
    $_SESSION['captcha_num1']=rand(1,9);$_SESSION['captcha_num2']=rand(1,9);
}
$error_message="";
if($_SERVER['REQUEST_METHOD']=='POST'){
    $username=trim($_POST['username']??'');$password=$_POST['password']??'';$captcha_answer=trim($_POST['captcha']??'');
    $correct=$_SESSION['captcha_num1']+$_SESSION['captcha_num2'];
    if(empty($captcha_answer))$error_message="Resolva a soma!";
    elseif(intval($captcha_answer)!=$correct)$error_message="Soma incorreta!";
    else{
        try{
            require_once 'config.php';$pdo=getConnection();
            $stmt=$pdo->prepare("SELECT * FROM usuarios WHERE email=? AND ativo=1");$stmt->execute([$username]);$user=$stmt->fetch();
            if($user&&password_verify($password,$user['senha'])){
                $_SESSION['logged_in']=true;$_SESSION['user_id']=$user['id'];$_SESSION['user_nome']=$user['nome'];$_SESSION['user_email']=$user['email'];$_SESSION['user_role']=$user['role'];
                logAuditoria($pdo,'login','usuario',$user['id'],['nome'=>$user['nome']]);
                unset($_SESSION['captcha_num1'],$_SESSION['captcha_num2']);
                header('Location: index.php');exit();
            }else{
                $chk=$pdo->prepare("SELECT ativo FROM usuarios WHERE email=?");$chk->execute([$username]);$found=$chk->fetch();
                if($found&&!$found['ativo'])$error_message="Sua conta ainda não foi ativada. Entre em contato com o administrador do sistema.";
                else $error_message="Usuário ou senha incorretos!";
            }
        }catch(Exception $e){$error_message="Erro no sistema. Tente novamente.";}
    }
    if($error_message){$_SESSION['captcha_num1']=rand(1,9);$_SESSION['captcha_num2']=rand(1,9);}
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Confirmação ASSEGO — Login</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f0f4f8;position:relative;overflow:hidden}

/* Subtle background */
.bg{position:fixed;inset:0;z-index:0;background:linear-gradient(160deg,#e0e8f5 0%,#cdd8ec 40%,#b8c8e0 100%)}
.bg::after{content:'';position:absolute;inset:0;opacity:.03;background-image:url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23000' fill-opacity='1'%3E%3Cpath d='M20 20.5V18H0v-2h20v-2l2 3-2 3z'/%3E%3C/g%3E%3C/svg%3E")}
.decor{position:fixed;border-radius:50%;opacity:.12;z-index:0}
.decor1{width:500px;height:500px;background:#1e3a8a;top:-200px;right:-150px}
.decor2{width:400px;height:400px;background:#2563eb;bottom:-200px;left:-150px}

.container{position:relative;z-index:10;width:100%;max-width:420px;padding:20px}

/* Card */
.card{background:white;border-radius:20px;overflow:hidden;box-shadow:0 20px 60px rgba(15,23,42,.12),0 4px 16px rgba(15,23,42,.06);animation:fadeUp .6s ease}
@keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}

/* Header inside card */
.card-top{background:linear-gradient(135deg,#1e3a8a,#2563eb);padding:28px 32px;text-align:center}
.card-top img{width:70px;height:70px;object-fit:contain;filter:drop-shadow(0 4px 12px rgba(0,0,0,.3));margin-bottom:10px}
.card-top h1{color:white;font-size:18px;font-weight:800;letter-spacing:.5px}
.card-top p{color:rgba(255,255,255,.5);font-size:11px;margin-top:2px}

/* Body */
.card-body{padding:28px 32px}

/* Form */
.fg{margin-bottom:18px}
.fl{display:block;color:#475569;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px}
.fi-wrap{position:relative;background:#f8fafc;border:2px solid #e2e8f0;border-radius:12px;transition:.3s}
.fi-wrap:focus-within{border-color:#3b82f6;background:white;box-shadow:0 0 0 3px rgba(59,130,246,.1)}
.fi-wrap i.ic{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:14px;transition:.3s}
.fi-wrap:focus-within i.ic{color:#3b82f6}
.fi{width:100%;padding:12px 14px 12px 42px;border:none;background:transparent;font-size:14px;font-weight:500;color:#0f172a;outline:none;font-family:inherit}
.fi::placeholder{color:#94a3b8}
.fi-wrap .eye{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:#cbd5e1;cursor:pointer;font-size:15px;padding:4px;transition:.3s}
.fi-wrap .eye:hover{color:#3b82f6}

/* Captcha inline */
.captcha{display:flex;align-items:center;gap:10px;background:#f0f4ff;border:2px solid #dbeafe;border-radius:12px;padding:10px 14px;margin-bottom:22px}
.captcha-label{color:#64748b;font-size:11px;font-weight:600;white-space:nowrap}
.captcha-nums{display:flex;align-items:center;gap:6px;white-space:nowrap}
.captcha-nums span{color:#1e3a8a;font-size:20px;font-weight:800}
.captcha-nums .op{color:#3b82f6;font-size:16px;font-weight:600}
.captcha-input{width:56px;padding:8px;border:2px solid #dbeafe;border-radius:8px;font-size:18px;font-weight:700;text-align:center;color:#1e3a8a;outline:none;background:white;font-family:inherit;transition:.3s;flex-shrink:0}
.captcha-input:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.1)}

/* Button */
.btn-login{width:100%;padding:14px;color:white;border:none;border-radius:12px;font-size:15px;font-weight:700;cursor:pointer;transition:.3s;background:linear-gradient(135deg,#1e3a8a,#2563eb);letter-spacing:.5px}
.btn-login:hover{transform:translateY(-2px);box-shadow:0 8px 30px rgba(30,58,138,.35)}

/* Error */
.err{background:#fef2f2;border:1px solid #fecaca;color:#dc2626;padding:10px 14px;border-radius:10px;margin-bottom:18px;font-size:13px;font-weight:500;display:flex;align-items:center;gap:8px;animation:shake .4s ease}
.err.inactive{background:#fffbeb;border-color:#fde68a;color:#92400e}
@keyframes shake{0%,100%{transform:translateX(0)}25%{transform:translateX(-5px)}75%{transform:translateX(5px)}}

/* Link */
.link{text-align:center;margin-top:18px;padding-top:18px;border-top:1px solid #e2e8f0}
.link a{color:#64748b;font-size:13px;font-weight:500;text-decoration:none;transition:.3s;display:inline-flex;align-items:center;gap:6px}
.link a:hover{color:#2563eb}

@media(max-width:480px){
    .container{padding:12px}
    .card-body{padding:22px 20px}
    .card-top{padding:22px 20px}
    .captcha{flex-wrap:wrap;justify-content:center}
}
</style>
</head>
<body>
<div class="bg"></div>
<div class="decor decor1"></div>
<div class="decor decor2"></div>

<div class="container">
    <div class="card">
        <div class="card-top">
            <img src="assets/img/logo_assego.png" alt="ASSEGO">
            <h1>Confirmação ASSEGO</h1>
            <p>Sistema de Gestão de Presenças</p>
        </div>
        <div class="card-body">
            <?php if($error_message):?>
            <div class="err<?=strpos($error_message,'ativada')!==false?' inactive':''?>">
                <i class="fas fa-<?=strpos($error_message,'ativada')!==false?'clock':'exclamation-triangle'?>"></i>
                <?=$error_message?>
            </div>
            <?php endif;?>

            <form method="POST" autocomplete="off">
                <div class="fg">
                    <label class="fl">Usuário</label>
                    <div class="fi-wrap">
                        <i class="fas fa-user ic"></i>
                        <input type="text" class="fi" name="username" placeholder="Seu login" required autofocus>
                    </div>
                </div>
                <div class="fg">
                    <label class="fl">Senha</label>
                    <div class="fi-wrap">
                        <i class="fas fa-lock ic"></i>
                        <input type="password" class="fi" name="password" id="pw" placeholder="Sua senha" required>
                        <button type="button" class="eye" onclick="const i=document.getElementById('pw');const p=i.type==='password';i.type=p?'text':'password';this.querySelector('i').className=p?'fas fa-eye-slash':'fas fa-eye'"><i class="fas fa-eye"></i></button>
                    </div>
                </div>

                <div class="captcha">
                    <span class="captcha-label">Verificação:</span>
                    <div class="captcha-nums">
                        <span><?=$_SESSION['captcha_num1']?></span>
                        <span class="op">+</span>
                        <span><?=$_SESSION['captcha_num2']?></span>
                        <span class="op">=</span>
                    </div>
                    <input type="number" class="captcha-input" name="captcha" placeholder="?" required>
                </div>

                <button type="submit" class="btn-login"><i class="fas fa-sign-in-alt"></i> Entrar</button>
            </form>

            <div class="link">
                <a href="cadastro_operador.php"><i class="fas fa-user-plus"></i> Não tenho acesso — Solicitar Cadastro</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
