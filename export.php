<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'config.php';
checkLogin();
$pdo = getConnection();

$evento_id = $_SESSION['evento_atual'] ?? 0;
if (!$evento_id) { die('Nenhum evento selecionado.'); }

$ev = $pdo->prepare("SELECT * FROM eventos WHERE id=?"); $ev->execute([$evento_id]); $evento = $ev->fetch();
if (!$evento) die('Evento não encontrado.');

$campos_extras = json_decode($evento['campos_extras'] ?? '[]', true) ?: [];
$form_config = json_decode($evento['form_config'] ?? '{}', true) ?: [];
$format = $_GET['format'] ?? 'excel';
$search = $_GET['search'] ?? '';
logAuditoria($pdo, 'exportar', 'participante', null, ['formato' => $format, 'evento' => $evento['nome']], $evento_id);

// Verificar colunas
$cols = array_column($pdo->query("SHOW COLUMNS FROM participantes")->fetchAll(), 'Field');
$hasVideo = in_array('video_url', $cols);
$hasAcomp = in_array('acompanhantes', $cols);

$sql = "SELECT * FROM participantes WHERE aprovado=1 AND evento_id=?";
$params = [$evento_id];
if ($search) { $sql .= " AND (nome LIKE ? OR whatsapp LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
$sql .= " ORDER BY nome";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$participantes = $stmt->fetchAll();

$total_ativos = 0; $total_inativos = 0; $total_acomp = 0;
foreach ($participantes as $i) {
    if ($i['ativo'] ?? 1) $total_ativos++; else $total_inativos++;
    if ($hasAcomp && !empty($i['acompanhantes'])) {
        $ac = json_decode($i['acompanhantes'], true) ?: [];
        $total_acomp += count($ac);
    }
}

// Campos do acompanhante habilitados
$acWa = ($form_config['acomp_whatsapp'] ?? '1') === '1';
$acCpf = ($form_config['acomp_cpf'] ?? '0') === '1';
$acIg = ($form_config['acomp_instagram'] ?? '0') === '1';
$acEnd = ($form_config['acomp_endereco'] ?? '0') === '1';

if ($format == 'excel') {
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"participantes_".preg_replace('/[^a-z0-9]/','_',strtolower($evento['nome']))."_".date('Y-m-d').".xls\"");
    echo "\xEF\xBB\xBF"; // UTF-8 BOM
    echo "<table border='1' cellpadding='4' cellspacing='0' style='border-collapse:collapse;font-family:Arial;font-size:11px'>";
    
    // Cabeçalho
    echo "<tr style='background:#1e40af;color:white;font-weight:bold;font-size:12px'>";
    echo "<th>#</th><th>Tipo</th><th>Nome</th><th>WhatsApp</th><th>Instagram</th>";
    if ($acCpf) echo "<th>CPF</th>";
    foreach ($campos_extras as $ce) echo "<th>".htmlspecialchars($ce['label'])."</th>";
    echo "<th>Endereço</th><th>Bairro</th><th>Cidade</th><th>UF</th><th>CEP</th>";
    echo "<th>Status</th><th>Cadastro</th><th>Acompanhante de</th></tr>";
    
    $n = 1;
    foreach ($participantes as $i) {
        $extras = json_decode($i['campos_extras'] ?? '{}', true) ?: [];
        $status = ($i['ativo'] ?? 1) ? 'Ativo' : 'Inativo';
        $bg = ($i['ativo'] ?? 1) ? '' : 'style="background:#fff1f2;"';
        
        // Linha do participante
        echo "<tr $bg>";
        echo "<td>$n</td>";
        echo "<td style='background:#dbeafe;font-weight:bold;color:#1e40af'>TITULAR</td>";
        echo "<td style='font-weight:bold'>".htmlspecialchars($i['nome'])."</td>";
        echo "<td>".htmlspecialchars($i['whatsapp'] ?? '-')."</td>";
        echo "<td>".(!empty($i['instagram']) ? '@'.htmlspecialchars(ltrim($i['instagram'], '@')) : '-')."</td>";
        if ($acCpf) echo "<td>-</td>";
        foreach ($campos_extras as $ce) echo "<td>".htmlspecialchars($extras[$ce['nome']] ?? '-')."</td>";
        echo "<td>".htmlspecialchars($i['endereco'] ?? '-')."</td>";
        echo "<td>-</td>";
        echo "<td>".htmlspecialchars($i['cidade'] ?? '-')."</td>";
        echo "<td>".htmlspecialchars($i['estado'] ?? '-')."</td>";
        echo "<td>".htmlspecialchars($i['cep'] ?? '-')."</td>";
        echo "<td>$status</td>";
        echo "<td>".date('d/m/Y', strtotime($i['created_at']))."</td>";
        echo "<td>-</td>";
        echo "</tr>";
        
        // Linhas dos acompanhantes
        if ($hasAcomp && !empty($i['acompanhantes'])) {
            $acomps = json_decode($i['acompanhantes'], true) ?: [];
            $ac_num = 1;
            foreach ($acomps as $ac) {
                echo "<tr style='background:#fffbeb;'>";
                echo "<td style='color:#92400e;font-size:10px'>$n.$ac_num</td>";
                echo "<td style='background:#fef3c7;color:#92400e;font-weight:bold;font-size:10px'>ACOMPANHANTE</td>";
                echo "<td style='color:#92400e'>".htmlspecialchars($ac['nome'] ?? '')."</td>";
                echo "<td>".(!empty($ac['whatsapp']) ? htmlspecialchars(formatPhone($ac['whatsapp'])) : '-')."</td>";
                echo "<td>".(!empty($ac['instagram']) ? htmlspecialchars($ac['instagram']) : '-')."</td>";
                if ($acCpf) echo "<td>".(!empty($ac['cpf']) ? htmlspecialchars(formatCPF($ac['cpf'])) : '-')."</td>";
                foreach ($campos_extras as $ce) echo "<td>-</td>";
                echo "<td>".(!empty($ac['endereco']) ? htmlspecialchars($ac['endereco']) : '-')."</td>";
                echo "<td>".(!empty($ac['bairro']) ? htmlspecialchars($ac['bairro']) : '-')."</td>";
                echo "<td>".(!empty($ac['cidade']) ? htmlspecialchars($ac['cidade']) : '-')."</td>";
                echo "<td>".(!empty($ac['estado']) ? htmlspecialchars($ac['estado']) : '-')."</td>";
                echo "<td>".(!empty($ac['cep']) ? htmlspecialchars($ac['cep']) : '-')."</td>";
                echo "<td>-</td>";
                echo "<td>-</td>";
                echo "<td style='font-weight:bold;color:#92400e'>".htmlspecialchars($i['nome'])."</td>";
                echo "</tr>";
                $ac_num++;
            }
        }
        $n++;
    }
    echo "</table>";
    
    // Resumo
    echo "<br><table border='1' cellpadding='4' cellspacing='0' style='border-collapse:collapse;font-family:Arial;font-size:11px'>";
    echo "<tr style='background:#1e40af;color:white;'><td colspan='2'><strong>RESUMO — ".htmlspecialchars($evento['nome'])."</strong></td></tr>";
    echo "<tr><td><strong>Total Titulares:</strong></td><td>".count($participantes)."</td></tr>";
    echo "<tr><td><strong>Ativos:</strong></td><td>$total_ativos</td></tr>";
    echo "<tr><td><strong>Inativos:</strong></td><td>$total_inativos</td></tr>";
    echo "<tr style='background:#fffbeb;'><td><strong>Total Acompanhantes:</strong></td><td>$total_acomp</td></tr>";
    echo "<tr style='background:#dbeafe;'><td><strong>TOTAL GERAL (Titulares + Acompanhantes):</strong></td><td><strong>".(count($participantes) + $total_acomp)."</strong></td></tr>";
    echo "<tr><td><strong>Gerado em:</strong></td><td>".date('d/m/Y H:i:s')."</td></tr>";
    echo "</table>";

} elseif ($format == 'pdf') { ?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">
<title>Relatório - <?php echo htmlspecialchars($evento['nome']); ?></title>
<style>
@page{size:A4 landscape;margin:1cm}
body{font-family:Arial,sans-serif;font-size:9pt;color:#333;margin:0}
.header{text-align:center;margin-bottom:16px;padding-bottom:10px;border-bottom:2px solid #1e40af}
.header h1{color:#1e40af;font-size:16pt;margin:0 0 4px}
.header p{color:#666;margin:0;font-size:9pt}
.summary{background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:10px 16px;margin-bottom:16px;display:flex;gap:30px}
.summary p{margin:0}
table{width:100%;border-collapse:collapse;margin-bottom:20px}
th{background:#1e40af;color:white;padding:5px 6px;text-align:left;font-size:7.5pt}
td{padding:4px 6px;border-bottom:1px solid #e5e7eb;font-size:7.5pt}
tr:nth-child(even){background:#f8faff}
tr.acomp{background:#fffbeb !important}
tr.acomp td{color:#92400e;font-size:7pt}
.badge-titular{background:#dbeafe;color:#1e40af;padding:1px 6px;border-radius:8px;font-weight:700;font-size:7pt}
.badge-acomp{background:#fef3c7;color:#92400e;padding:1px 6px;border-radius:8px;font-weight:700;font-size:7pt}
.badge-ativo{background:#dcfce7;color:#16a34a;padding:2px 7px;border-radius:10px;font-weight:700;font-size:7pt}
.badge-inativo{background:#fee2e2;color:#dc2626;padding:2px 7px;border-radius:10px;font-weight:700;font-size:7pt}
.no-print{margin:16px;text-align:center}
.btn{background:#1e40af;color:white;padding:8px 18px;text-decoration:none;border-radius:5px;display:inline-block;margin:4px;font-size:10pt;border:none;cursor:pointer}
@media print{.no-print{display:none}}
</style></head><body>
<div class="no-print">
    <button onclick="window.print()" class="btn"><i class="fas fa-print"></i> Imprimir / Salvar PDF</button>
    <a href="index.php" class="btn" style="background:#6b7280;">Voltar</a>
</div>
<div class="header">
    <h1>ASSEGO — <?php echo htmlspecialchars($evento['nome']); ?></h1>
    <p>Relatório de Participantes — Gerado em <?php echo date('d/m/Y H:i'); ?></p>
</div>
<div class="summary">
    <p><strong>Titulares:</strong> <?php echo count($participantes); ?></p>
    <p><strong>Ativos:</strong> <?php echo $total_ativos; ?></p>
    <p><strong>Inativos:</strong> <?php echo $total_inativos; ?></p>
    <p><strong>Acompanhantes:</strong> <?php echo $total_acomp; ?></p>
    <p><strong>Total Geral:</strong> <?php echo count($participantes) + $total_acomp; ?></p>
</div>
<table>
<thead><tr>
<th>#</th><th>Tipo</th><th>Nome</th><th>WhatsApp</th><th>Instagram</th>
<?php if($acCpf):?><th>CPF</th><?php endif;?>
<?php foreach($campos_extras as $ce): ?><th><?php echo htmlspecialchars($ce['label']); ?></th><?php endforeach; ?>
<th>Cidade/UF</th><th>Cadastro</th><th>Status</th><th>Acompanhante de</th>
</tr></thead>
<tbody>
<?php $n=1; foreach($participantes as $i): $extras=json_decode($i['campos_extras']??'{}',true)?:[]; $status=($i['ativo']??1)?'Ativo':'Inativo'; $cls=($i['ativo']??1)?'badge-ativo':'badge-inativo'; ?>
<tr>
<td><?php echo $n; ?></td>
<td><span class="badge-titular">TITULAR</span></td>
<td><strong><?php echo htmlspecialchars($i['nome']); ?></strong></td>
<td><?php echo htmlspecialchars($i['whatsapp']??'-'); ?></td>
<td><?php echo !empty($i['instagram'])?'@'.htmlspecialchars(ltrim($i['instagram'],'@')):'-'; ?></td>
<?php if($acCpf):?><td>-</td><?php endif;?>
<?php foreach($campos_extras as $ce): ?><td><?php echo htmlspecialchars($extras[$ce['nome']]??'-'); ?></td><?php endforeach; ?>
<td><?php echo htmlspecialchars(($i['cidade']??'').($i['estado']?'/'.$i['estado']:'')); ?></td>
<td><?php echo date('d/m/Y',strtotime($i['created_at'])); ?></td>
<td><span class="<?php echo $cls; ?>"><?php echo $status; ?></span></td>
<td>-</td>
</tr>
<?php
if ($hasAcomp && !empty($i['acompanhantes'])) {
    $acomps = json_decode($i['acompanhantes'], true) ?: [];
    $ac_num = 1;
    foreach ($acomps as $ac): ?>
<tr class="acomp">
<td><?php echo "$n.$ac_num"; ?></td>
<td><span class="badge-acomp">ACOMP.</span></td>
<td><?php echo htmlspecialchars($ac['nome']??''); ?></td>
<td><?php echo !empty($ac['whatsapp'])?htmlspecialchars(formatPhone($ac['whatsapp'])):'-'; ?></td>
<td><?php echo !empty($ac['instagram'])?htmlspecialchars($ac['instagram']):'-'; ?></td>
<?php if($acCpf):?><td><?php echo !empty($ac['cpf'])?htmlspecialchars(formatCPF($ac['cpf'])):'-'; ?></td><?php endif;?>
<?php foreach($campos_extras as $ce): ?><td>-</td><?php endforeach; ?>
<td><?php echo htmlspecialchars(($ac['cidade']??'').(!empty($ac['estado'])?'/'.$ac['estado']:'')); ?></td>
<td>-</td>
<td>-</td>
<td style="font-weight:bold"><?php echo htmlspecialchars($i['nome']); ?></td>
</tr>
<?php $ac_num++; endforeach;
} ?>
<?php $n++; endforeach; ?>
</tbody></table>
<div style="text-align:center;font-size:8pt;color:#999;border-top:1px solid #e5e7eb;padding-top:8px;">
ASSEGO Eventos | <?php echo htmlspecialchars($evento['nome']); ?> | Gerado em <?php echo date('d/m/Y H:i:s'); ?>
</div>
</body></html>
<?php } ?>
