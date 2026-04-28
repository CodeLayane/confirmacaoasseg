<?php
require_once 'layout.php';
if(!isAdmin()){header('Location: index.php');exit();}

$pdo = getConnection();
$eventos = $pdo->query("SELECT id,nome FROM eventos ORDER BY nome")->fetchAll();

function gerarToken($id, $tipo='titular', $idx=0){
    $secret=DB_PASS.DB_NAME;
    $data=$id.':'.$tipo.':'.$idx;
    return base64_encode($data.':'.substr(hash_hmac('sha256',$data,$secret),0,12));
}

$baseUrl=(isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on'?'https':'http').'://'.$_SERVER['HTTP_HOST'].rtrim(dirname($_SERVER['SCRIPT_NAME']),'/').'/';

// Nomes para cadastro
$nomes_lista = [
    'Ronivon Pereira Pinto',
    'Paulo Lemos Brito',
    'Maria Eduarda Soares Brito',
    'Wanessa Pereira Faria',
    'Adalicio Francisco de Almeida Júnior',
    'Rafael Silva Martins Gidrão',
    'Jucelia Aline da Cunha',
    'Victor Ferreira de Andrade',
    'Vaneide Corrêa',
    'Luiz Cláudio Corrêa',
    'Mário Corrêa',
    'Wellington Marcos',
    'Victor Hugo Oliveira',
    'Heloisio dos Reis Pinto Ferreira',
    'Onizia Marques Pereira',
    'Marco Aurélio Gonçalves de Souza',
    'Elvis Paulo Barbalho Soares',
];

$resultado = [];
$evento_selecionado = null;
$modo = $_GET['modo'] ?? 'form';

// Processar inserção
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['evento_id'])){
    $eid = (int)$_POST['evento_id'];
    $evento_selecionado = $pdo->prepare("SELECT * FROM eventos WHERE id=?")->execute([$eid]) ? $pdo->query("SELECT * FROM eventos WHERE id=$eid")->fetch() : null;
    date_default_timezone_set('America/Sao_Paulo');
    $agora = date('Y-m-d H:i:s');

    $cols = array_column($pdo->query("SHOW COLUMNS FROM participantes")->fetchAll(),'Field');
    $hasAprovadoEm = in_array('aprovado_em',$cols);
    $hasAprovadoPor = in_array('aprovado_por',$cols);

    foreach($nomes_lista as $nome){
        // Verificar se já existe (por nome + evento)
        $chk = $pdo->prepare("SELECT id FROM participantes WHERE nome=? AND evento_id=?");
        $chk->execute([$nome, $eid]);
        $existe = $chk->fetchColumn();

        if($existe){
            $resultado[] = ['nome'=>$nome,'id'=>$existe,'status'=>'ja_existia'];
            continue;
        }

        // Inserir
        $sql_cols = "evento_id,nome,whatsapp,aprovado,campos_extras";
        $sql_vals = "?,?,?,1,?";
        $params = [$eid, $nome, '', json_encode(['_tipo'=>'acompanhante_avulso'],JSON_UNESCAPED_UNICODE)];

        if($hasAprovadoEm){ $sql_cols.=",aprovado_em"; $sql_vals.=",'$agora'"; }
        if($hasAprovadoPor){ $sql_cols.=",aprovado_por"; $sql_vals.=",'Admin (Cadastro em massa)'"; }

        try{
            $pdo->prepare("INSERT INTO participantes ($sql_cols) VALUES ($sql_vals)")->execute($params);
            $pid = (int)$pdo->lastInsertId();
            $resultado[] = ['nome'=>$nome,'id'=>$pid,'status'=>'criado'];
        }catch(Exception $e){
            $resultado[] = ['nome'=>$nome,'id'=>0,'status'=>'erro','msg'=>$e->getMessage()];
        }
    }
    $modo = 'resultado';
}
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Cadastro em Massa — ASSEGO</title><?php renderCSS();?>
<style>
.qr-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:20px;margin-top:24px}
.qr-card{background:white;border-radius:16px;box-shadow:var(--shadow-md);border:1px solid #dbeafe;overflow:hidden;text-align:center}
.qr-card-header{background:linear-gradient(135deg,#1e40af,#3b82f6);padding:14px;color:white}
.qr-card-nome{font-size:13px;font-weight:700;line-height:1.3}
.qr-card-body{padding:16px}
.qr-card img{width:160px;height:160px;border-radius:8px;border:2px solid #e2e8f0}
.btn-dl{display:flex;align-items:center;justify-content:center;gap:6px;width:100%;padding:8px;margin-top:8px;background:linear-gradient(135deg,#059669,#10b981);color:white;border:none;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;text-decoration:none}
.btn-print{display:flex;align-items:center;justify-content:center;gap:6px;width:100%;padding:8px;margin-top:6px;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:white;border:none;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer}
.badge-criado{background:#dcfce7;color:#166534;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700}
.badge-ja{background:#fef3c7;color:#92400e;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700}
@media print{
    .no-print{display:none!important}
    body{background:white!important}
    .qr-grid{grid-template-columns:repeat(3,1fr);gap:12px}
    .qr-card{break-inside:avoid;box-shadow:none;border:1px solid #ccc}
}
</style>
</head><body>
<?php renderHeader('participantes');?>

<?php if($modo==='form'): ?>
<div class="page-header">
    <h1 class="page-title"><i class="fas fa-users-gear"></i> Cadastro em Massa</h1>
</div>
<div class="content-container">
<div class="form-card" style="max-width:600px;margin:0 auto">
    <h3 style="color:var(--primary);margin-bottom:16px"><i class="fas fa-list"></i> Participantes a cadastrar</h3>
    <div style="background:#f0f9ff;border:1px solid #dbeafe;border-radius:12px;padding:16px;margin-bottom:24px">
        <?php foreach($nomes_lista as $i=>$n): ?>
        <div style="padding:6px 0;border-bottom:1px solid #e0f2fe;display:flex;align-items:center;gap:10px">
            <span style="background:#1e40af;color:white;width:22px;height:22px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0"><?=$i+1?></span>
            <span style="font-weight:500"><?=htmlspecialchars($n)?></span>
        </div>
        <?php endforeach;?>
        <div style="margin-top:10px;font-size:12px;color:var(--gray)"><strong><?=count($nomes_lista)?></strong> participantes serão cadastrados como <strong>aprovados</strong></div>
    </div>
    <form method="POST">
        <label class="form-label">Selecione o Evento *</label>
        <select name="evento_id" class="form-select mb-4" required style="font-size:14px;padding:12px">
            <option value="">Selecione...</option>
            <?php foreach($eventos as $ev): ?>
            <option value="<?=$ev['id']?>"><?=htmlspecialchars($ev['nome'])?></option>
            <?php endforeach;?>
        </select>
        <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:12px;margin-bottom:20px;font-size:13px;color:#92400e">
            <i class="fas fa-info-circle"></i> Os participantes serão inseridos como <strong>aprovados automaticamente</strong>. Se algum nome já existir no evento, será ignorado.
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%"><i class="fas fa-bolt"></i> Cadastrar e Gerar QR Codes</button>
    </form>
</div>
</div>

<?php else: // resultado + QR codes ?>
<div class="page-header no-print">
    <h1 class="page-title"><i class="fas fa-qrcode"></i> QR Codes Gerados</h1>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> Imprimir Todos</button>
        <a href="cadastro_massa.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
    </div>
</div>
<div class="content-container">
<div style="background:#f0f9ff;border:1px solid #dbeafe;border-radius:12px;padding:14px 20px;margin-bottom:20px;display:flex;gap:20px;flex-wrap:wrap;align-items:center" class="no-print">
    <?php
    $criados = count(array_filter($resultado,fn($r)=>$r['status']==='criado'));
    $jaExistiam = count(array_filter($resultado,fn($r)=>$r['status']==='ja_existia'));
    ?>
    <span><i class="fas fa-check-circle" style="color:#059669"></i> <strong><?=$criados?></strong> cadastrados agora</span>
    <?php if($jaExistiam):?><span><i class="fas fa-clock" style="color:#d97706"></i> <strong><?=$jaExistiam?></strong> já existiam</span><?php endif;?>
    <span style="color:var(--gray);font-size:13px">Evento: <strong><?=htmlspecialchars($evento_selecionado['nome']??'')?></strong></span>
</div>

<div class="qr-grid">
<?php foreach($resultado as $r):
    if(!$r['id']) continue;
    $token = gerarToken($r['id']);
    $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&data='.urlencode($baseUrl.'api_presenca.php?action=confirmar&token='.urlencode($token)).'&bgcolor=ffffff&color=000000&format=png&margin=10&ecc=M';
    $ingressoUrl = $baseUrl.'meu_ingresso.php?id='.$r['id'].'&tk='.urlencode($token);
?>
<div class="qr-card">
    <div class="qr-card-header">
        <div class="qr-card-nome"><?=htmlspecialchars($r['nome'])?></div>
        <div style="margin-top:4px">
            <span class="<?=$r['status']==='criado'?'badge-criado':'badge-ja'?>"><?=$r['status']==='criado'?'✓ Cadastrado':'Já existia'?></span>
        </div>
    </div>
    <div class="qr-card-body">
        <img src="<?=$qrUrl?>" alt="QR <?=htmlspecialchars($r['nome'])?>">
        <div style="font-size:10px;color:var(--gray);margin-top:6px">ID #<?=$r['id']?></div>
        <a href="<?=$qrUrl?>" download="qr-<?=urlencode(substr($r['nome'],0,20))?>.png" class="btn-dl no-print">
            <i class="fas fa-download"></i> Baixar QR
        </a>
        <a href="<?=$ingressoUrl?>" target="_blank" class="btn-dl no-print" style="background:linear-gradient(135deg,#1e40af,#3b82f6);margin-top:6px">
            <i class="fas fa-ticket"></i> Ver Ingresso
        </a>
    </div>
</div>
<?php endforeach;?>
</div>
</div>
<?php endif;?>

<?php renderScripts();?>
</body></html>