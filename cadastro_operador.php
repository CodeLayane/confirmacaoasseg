<?php
session_start();
require_once 'config.php';
$pdo = getConnection();

$success = false;
$error = '';

// Verificar se coluna observacao existe
$cols = array_column($pdo->query("SHOW COLUMNS FROM usuarios")->fetchAll(), 'Field');
$hasObs = in_array('observacao', $cols);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $senha2 = $_POST['senha2'] ?? '';
    $obs = trim($_POST['observacao'] ?? '');

    if (strlen($nome) < 3) $error = "Nome deve ter pelo menos 3 caracteres.";
    elseif (strlen($email) < 3) $error = "Login é obrigatório.";
    elseif (strlen($senha) < 4) $error = "Senha deve ter pelo menos 4 caracteres.";
    elseif ($senha !== $senha2) $error = "As senhas não coincidem.";
    else {
        $chk = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
        $chk->execute([$email]);
        if ($chk->fetch()) {
            $error = "Este login já está em uso.";
        } else {
            $hash = password_hash($senha, PASSWORD_DEFAULT);
            $sqlC = "INSERT INTO usuarios (nome, email, senha, role, ativo".($hasObs ? ",observacao" : "").") VALUES (?, ?, ?, 'operador', 0".($hasObs ? ",?" : "").")";
            $paramsC = [$nome, $email, $hash];
            if ($hasObs) $paramsC[] = $obs;
            $pdo->prepare($sqlC)->execute($paramsC);
            $uid = $pdo->lastInsertId();
            logAuditoria($pdo, 'criar', 'usuario', $uid, ['nome' => $nome, 'auto_cadastro' => true, 'observacao' => $obs]);
            $success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Solicitar Acesso — ASSEGO Eventos</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f0f9ff;position:relative}
.bg{position:fixed;width:100%;height:100%;top:0;left:0;z-index:1;background:linear-gradient(135deg,#1e3a8a,#1e40af,#2563eb,#1e3a8a);background-size:400% 400%;animation:gs 15s ease infinite}
@keyframes gs{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}
.pat{position:fixed;width:100%;height:100%;top:0;left:0;z-index:2;opacity:.08;background-image:repeating-linear-gradient(45deg,transparent,transparent 35px,rgba(255,255,255,.1) 35px,rgba(255,255,255,.1) 70px)}
.container{position:relative;z-index:10;width:100%;max-width:520px;padding:20px;animation:su .8s ease-out}
@keyframes su{from{opacity:0;transform:translateY(50px)}to{opacity:1;transform:translateY(0)}}
.logo{text-align:center;margin-bottom:24px}
.logo img{width:120px;height:120px;object-fit:contain;filter:drop-shadow(0 8px 24px rgba(0,0,0,.35))}
.box{background:rgba(255,255,255,.98);backdrop-filter:blur(20px);border-radius:24px;overflow:hidden;box-shadow:0 20px 25px -5px rgba(0,0,0,.1);border:1px solid rgba(255,255,255,.3)}
.box-header{background:linear-gradient(135deg,#1e3a8a,#2563eb);padding:20px 30px;text-align:center}
.box-header h2{color:white;font-size:18px;margin:0}
.box-header small{color:rgba(255,255,255,.7);font-size:12px}
.box-body{padding:28px 32px}
.fg{margin-bottom:18px}
.fl{display:block;color:#1e3a8a;font-size:12px;font-weight:700;margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px}
.fi-wrap{position:relative;border-radius:12px;background:#f0f9ff;border:2px solid #dbeafe;transition:.3s}
.fi-wrap:focus-within{background:white;border-color:#1e3a8a}
.fi-wrap i.icon{position:absolute;left:16px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:14px}
.fi-wrap:focus-within i.icon{color:#1e3a8a}
.fi{width:100%;padding:12px 16px 12px 44px;border:none;background:transparent;font-size:14px;font-weight:500;color:#0f172a;outline:none;font-family:inherit}
.fi-wrap .eye{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;color:#94a3b8;cursor:pointer;font-size:16px;padding:4px}
.fi-wrap .eye:hover{color:#1e3a8a}
.fr{display:grid;grid-template-columns:1fr 1fr;gap:12px}
@media(max-width:500px){.fr{grid-template-columns:1fr}}
.btn-sub{width:100%;padding:14px;color:white;border:none;border-radius:12px;font-size:15px;font-weight:700;cursor:pointer;transition:.3s;background:linear-gradient(135deg,#1e3a8a,#2563eb);margin-top:8px}
.btn-sub:hover{transform:translateY(-2px);box-shadow:0 8px 30px rgba(30,64,175,.6)}
.err{background:#fee2e2;color:#991b1b;padding:12px;border-radius:12px;margin-bottom:16px;font-size:13px;font-weight:500;border:1px solid #fca5a5;display:flex;align-items:center;gap:8px}
.sbox{text-align:center;padding:40px 20px}
.sicon{width:80px;height:80px;background:linear-gradient(135deg,#059669,#10b981);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:40px;color:white;margin:0 auto 20px}
.info-box{background:#dbeafe;border:1px solid #93c5fd;border-radius:12px;padding:14px;margin-top:16px;font-size:13px;color:#1e3a8a;text-align:center}
.pw-strength{height:4px;border-radius:2px;margin-top:6px;transition:.3s;background:#e2e8f0}
.pw-bar{height:100%;border-radius:2px;transition:.3s}
</style>
</head>
<body>
<div class="bg"></div>
<div class="pat"></div>
<div class="container">
    <div class="logo"><img src="assets/img/logo_assego.png" alt="ASSEGO"></div>
    <div class="box">
        <div class="box-header">
            <h2><i class="fas fa-user-plus"></i> Solicitar Acesso</h2>
            <small>Preencha seus dados para solicitar acesso ao sistema</small>
        </div>
        <div class="box-body">
<?php if($success): ?>
            <div class="sbox">
                <div class="sicon"><i class="fas fa-check"></i></div>
                <h3 style="color:#059669;font-size:20px;margin-bottom:8px">Cadastro Realizado!</h3>
                <p style="color:#64748b;font-size:14px;margin-bottom:16px">Seu cadastro foi enviado com sucesso. O administrador irá revisar e ativar seu acesso em breve.</p>
                <div class="info-box"><i class="fas fa-info-circle"></i> Você receberá acesso assim que o administrador aprovar sua solicitação.</div>
                <div style="margin-top:16px"><a href="login.php" style="display:inline-flex;align-items:center;gap:8px;padding:12px 24px;background:linear-gradient(135deg,#1e3a8a,#2563eb);color:white;text-decoration:none;border-radius:12px;font-weight:600;font-size:14px;transition:.3s"><i class="fas fa-sign-in-alt"></i> Ir para o Login</a></div>
            </div>
<?php else: ?>
            <?php if($error): ?><div class="err"><i class="fas fa-exclamation-circle"></i> <?=htmlspecialchars($error)?></div><?php endif; ?>
            <form method="POST" autocomplete="off">
                <div class="fg">
                    <label class="fl">Nome Completo *</label>
                    <div class="fi-wrap"><i class="fas fa-user icon"></i><input type="text" class="fi" name="nome" required placeholder="Seu nome completo" value="<?=htmlspecialchars($_POST['nome']??'')?>"></div>
                </div>
                <div class="fg">
                    <label class="fl">Login de Acesso *</label>
                    <div class="fi-wrap"><i class="fas fa-at icon"></i><input type="text" class="fi" name="email" required placeholder="Escolha um login (ex: joao.silva)" value="<?=htmlspecialchars($_POST['email']??'')?>"></div>
                </div>
                <div class="fr">
                    <div class="fg">
                        <label class="fl">Senha *</label>
                        <div class="fi-wrap">
                            <i class="fas fa-lock icon"></i>
                            <input type="password" class="fi" name="senha" id="pw1" required placeholder="Criar senha" minlength="4">
                            <button type="button" class="eye" onclick="togglePw('pw1',this)"><i class="fas fa-eye"></i></button>
                        </div>
                        <div class="pw-strength"><div class="pw-bar" id="pwBar"></div></div>
                    </div>
                    <div class="fg">
                        <label class="fl">Confirmar Senha *</label>
                        <div class="fi-wrap">
                            <i class="fas fa-lock icon"></i>
                            <input type="password" class="fi" name="senha2" id="pw2" required placeholder="Repetir senha" minlength="4">
                            <button type="button" class="eye" onclick="togglePw('pw2',this)"><i class="fas fa-eye"></i></button>
                        </div>
                        <div id="pwMatch" style="font-size:11px;margin-top:4px;height:14px"></div>
                    </div>
                </div>
                <div class="fg">
                    <label class="fl"><i class="fas fa-comment-dots" style="margin-right:4px"></i> Evento / Observação</label>
                    <div class="fi-wrap"><i class="fas fa-pen icon"></i><input type="text" class="fi" name="observacao" placeholder="Ex: Quero gerenciar o evento Combate ASSEGO" value="<?=htmlspecialchars($_POST['observacao']??'')?>"></div>
                    <small style="color:#94a3b8;font-size:11px">Informe qual evento deseja acessar ou deixe uma observação</small>
                </div>
                <button type="submit" class="btn-sub"><i class="fas fa-paper-plane"></i> Solicitar Acesso</button>
            </form>
            <div class="info-box" style="margin-top:20px"><i class="fas fa-shield-alt"></i> Após o cadastro, o administrador precisará aprovar seu acesso.</div>
            <div style="text-align:center;margin-top:16px"><a href="login.php" style="color:#3b82f6;font-size:13px;font-weight:600;text-decoration:none"><i class="fas fa-sign-in-alt"></i> Já tenho acesso — Entrar</a></div>
<?php endif; ?>
        </div>
    </div>
</div>
<script>
function togglePw(id,btn){const inp=document.getElementById(id);const isP=inp.type==='password';inp.type=isP?'text':'password';btn.querySelector('i').className=isP?'fas fa-eye-slash':'fas fa-eye';}
document.getElementById('pw1')?.addEventListener('input',function(){const v=this.value;const bar=document.getElementById('pwBar');let s=0,c='#ef4444';if(v.length>=4)s=25;if(v.length>=6)s=50;if(/[A-Z]/.test(v)&&/[0-9]/.test(v))s=75;if(v.length>=8&&/[^a-zA-Z0-9]/.test(v))s=100;if(s>=50)c='#f59e0b';if(s>=75)c='#10b981';bar.style.width=s+'%';bar.style.background=c;checkMatch();});
document.getElementById('pw2')?.addEventListener('input',checkMatch);
function checkMatch(){const p1=document.getElementById('pw1').value;const p2=document.getElementById('pw2').value;const el=document.getElementById('pwMatch');if(!p2){el.innerHTML='';return;}el.innerHTML=p1===p2?'<span style="color:#059669"><i class="fas fa-check-circle"></i> Senhas coincidem</span>':'<span style="color:#dc2626"><i class="fas fa-times-circle"></i> Senhas não coincidem</span>';}
</script>
</body>
</html>
