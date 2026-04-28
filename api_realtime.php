<?php
if(session_status()===PHP_SESSION_NONE) session_start();
require_once 'config.php'; checkLogin();
header('Content-Type: application/json'); header('Cache-Control: no-cache,no-store');
$pdo=getConnection(); $action=$_GET['action']??'stats'; $eid=(int)($_GET['evento_id']??($_SESSION['evento_atual']??0));
if(!$eid){echo json_encode(['error'=>'no_event']);exit();}

// Verificar se colunas existem
$colsPart=array_column($pdo->query("SHOW COLUMNS FROM participantes")->fetchAll(),'Field');
$hasAprovadoEm=in_array('aprovado_em',$colsPart);
$hasAprovadoPor=in_array('aprovado_por',$colsPart);

try{
switch($action){

    case 'stats':
        // Titulares = aprovados que NÃO são avulsos
        $s=$pdo->prepare("SELECT COUNT(*) FROM participantes WHERE aprovado=1 AND evento_id=? AND campos_extras NOT LIKE '%acompanhante_avulso%'");$s->execute([$eid]);$total=(int)$s->fetchColumn();
        $s=$pdo->prepare("SELECT COUNT(*) FROM participantes WHERE aprovado=0 AND evento_id=?");$s->execute([$eid]);$pendentes=(int)$s->fetchColumn();
        $s=$pdo->prepare("SELECT COUNT(*) FROM participantes WHERE aprovado=1 AND evento_id=? AND DATE(created_at)=CURDATE() AND campos_extras NOT LIKE '%acompanhante_avulso%'");$s->execute([$eid]);$novos=(int)$s->fetchColumn();
        echo json_encode(['total'=>$total,'pendentes'=>$pendentes,'novos'=>$novos]);
        break;

    // ── NOVO: contagem de acompanhantes para o card do dashboard ──────────────
    case 'stats_acomps':
        $total_acomps = 0;
        // Acompanhantes aninhados
        $rows = $pdo->prepare("SELECT acompanhantes FROM participantes WHERE evento_id=? AND aprovado=1 AND acompanhantes IS NOT NULL AND acompanhantes!='' AND acompanhantes!='[]'");
        $rows->execute([$eid]);
        foreach($rows->fetchAll() as $row){
            $arr = json_decode($row['acompanhantes'], true);
            if(is_array($arr)) $total_acomps += count($arr);
        }
        // Avulsos
        try{
            $av=$pdo->prepare("SELECT COUNT(*) FROM participantes WHERE evento_id=? AND aprovado=1 AND campos_extras LIKE '%acompanhante_avulso%'");
            $av->execute([$eid]);
            $total_acomps += (int)$av->fetchColumn();
        }catch(Exception $e){}
        echo json_encode(['total_acomps' => $total_acomps]);
        break;

    case 'badge':
        $s=$pdo->prepare("SELECT COUNT(*) FROM participantes WHERE aprovado=0 AND evento_id=?");$s->execute([$eid]);
        echo json_encode(['pendentes'=>(int)$s->fetchColumn()]);
        break;

    case 'lista':
        $search=$_GET['search']??'';
        $tipo_f=$_GET['tipo']??'todos';
        $page=max(1,(int)($_GET['page']??1));$per_page=(int)($_GET['per_page']??10);$offset=($page-1)*$per_page;
        $hasAcompL=in_array('acompanhantes',$colsPart);$acompColL=$hasAcompL?',p.acompanhantes':'';
        $where="WHERE p.aprovado=1 AND p.evento_id=?";$params=[$eid];
        // tipo='titular' → todos os titulares (sem sub-rows de acompanhantes)
        // tipo='acomp'   → apenas titulares que têm acompanhantes embutidos no JSON
        if($tipo_f==='acomp'&&$hasAcompL){$where.=" AND acompanhantes IS NOT NULL AND acompanhantes!='' AND acompanhantes!='[]'";}
        // total real de acompanhantes (calculado depois se tipo=acomp)
        $calcAcompTotal=($tipo_f==='acomp'&&$hasAcompL);
        if($search){$searchW=" AND (p.nome LIKE ? OR p.whatsapp LIKE ? OR p.instagram LIKE ? OR p.campos_extras LIKE ?";$l="%$search%";$params=array_merge($params,[$l,$l,$l,$l]);if($hasAcompL){$searchW.=" OR p.acompanhantes LIKE ?";$params[]=$l;}$searchW.=")";$where.=$searchW;}
        $s=$pdo->prepare("SELECT COUNT(*) FROM participantes p $where");$s->execute($params);$total_rows=(int)$s->fetchColumn();
        $s=$pdo->prepare("SELECT p.id,p.nome,p.whatsapp,p.instagram,p.campos_extras,p.ativo,p.created_at{$acompColL},(SELECT 1 FROM fotos WHERE participante_id=p.id LIMIT 1) as has_foto FROM participantes p $where ORDER BY p.nome LIMIT $per_page OFFSET $offset");$s->execute($params);$rows=$s->fetchAll();
        // Para tipo='acomp', contar o total real de acompanhantes (não de titulares)
        if($calcAcompTotal){
            $sa=$pdo->prepare("SELECT acompanhantes FROM participantes p $where");$sa->execute($params);
            $total_rows=0;foreach($sa->fetchAll() as $row){$arr=json_decode($row['acompanhantes'],true);if(is_array($arr))$total_rows+=count($arr);}
        }
        echo json_encode(['rows'=>$rows,'total'=>$total_rows,'page'=>$page,'per_page'=>$per_page,'total_pages'=>ceil($total_rows/$per_page)]);
        break;

    case 'pendentes':
        $cols=array_column($pdo->query("SHOW COLUMNS FROM participantes")->fetchAll(),'Field');$hasVid=in_array('video_url',$cols);$hasAcomp=in_array('acompanhantes',$cols);
        $vidCol=$hasVid?',p.video_url':'';$acompCol=$hasAcomp?',p.acompanhantes':'';
        $s=$pdo->prepare("SELECT p.id,p.nome,p.whatsapp,p.instagram,p.endereco,p.cidade,p.estado,p.cep,p.campos_extras,p.created_at{$vidCol}{$acompCol},(SELECT dados FROM fotos WHERE participante_id=p.id LIMIT 1) as foto FROM participantes p WHERE p.evento_id=? AND p.aprovado=0 ORDER BY p.created_at DESC");
        $s->execute([$eid]);$rows=$s->fetchAll();
        echo json_encode(['total'=>count($rows),'rows'=>$rows]);
        break;

    case 'aprovar':
        $pid=(int)($_GET['id']??0);
        date_default_timezone_set('America/Sao_Paulo');$agora=date('Y-m-d H:i:s');
        $sql="UPDATE participantes SET aprovado=1";$p=[];
        if($hasAprovadoEm){$sql.=",aprovado_em=?";$p[]=$agora;}
        if($hasAprovadoPor){$sql.=",aprovado_por=?";$p[]=$_SESSION['user_nome']??'Admin';}
        $sql.=" WHERE id=? AND evento_id=?";$p[]=$pid;$p[]=$eid;
        $pdo->prepare($sql)->execute($p);
        $pNome=$pdo->prepare("SELECT nome FROM participantes WHERE id=?");$pNome->execute([$pid]);$nomePart=$pNome->fetchColumn()?:'';
        logAuditoria($pdo,'aprovar','participante',$pid,['nome'=>$nomePart],$eid);
        if(isset($_GET['redirect'])){header("Location: ".$_GET['redirect'].".php");exit();}
        echo json_encode(['ok'=>true]);
        break;

    case 'rejeitar':
        $pid=(int)($_GET['id']??0);
        $pNome=$pdo->prepare("SELECT nome FROM participantes WHERE id=?");$pNome->execute([$pid]);$nomePart=$pNome->fetchColumn()?:'';
        $pdo->prepare("DELETE FROM fotos WHERE participante_id=?")->execute([$pid]);
        $pdo->prepare("DELETE FROM participantes WHERE id=? AND evento_id=? AND aprovado=0")->execute([$pid,$eid]);
        logAuditoria($pdo,'rejeitar','participante',$pid,['nome'=>$nomePart],$eid);
        if(isset($_GET['redirect'])){header("Location: ".$_GET['redirect'].".php");exit();}
        echo json_encode(['ok'=>true]);
        break;

    case 'aprovar_todos':
        $cnt=$pdo->prepare("SELECT COUNT(*) FROM participantes WHERE evento_id=? AND aprovado=0");$cnt->execute([$eid]);$qtd=(int)$cnt->fetchColumn();
        date_default_timezone_set('America/Sao_Paulo');$agora2=date('Y-m-d H:i:s');
        $sql="UPDATE participantes SET aprovado=1";
        if($hasAprovadoEm)$sql.=",aprovado_em='".addslashes($agora2)."'";
        if($hasAprovadoPor)$sql.=",aprovado_por='".addslashes($_SESSION['user_nome']??'Admin')."'";
        $sql.=" WHERE evento_id=? AND aprovado=0";
        $pdo->prepare($sql)->execute([$eid]);
        logAuditoria($pdo,'aprovar_todos','participante',null,['quantidade'=>$qtd],$eid);
        echo json_encode(['ok'=>true]);
        break;

    case 'relatorio':
        $s=$pdo->prepare("SELECT COUNT(*) FROM participantes WHERE aprovado=1 AND evento_id=?");$s->execute([$eid]);$total=(int)$s->fetchColumn();
        $s=$pdo->prepare("SELECT COUNT(*) FROM participantes WHERE aprovado=0 AND evento_id=?");$s->execute([$eid]);$pendentes=(int)$s->fetchColumn();
        $cidades=$pdo->prepare("SELECT cidade,COUNT(*) as t FROM participantes WHERE aprovado=1 AND evento_id=? AND cidade!='' GROUP BY cidade ORDER BY t DESC LIMIT 10");$cidades->execute([$eid]);
        echo json_encode(['total'=>$total,'pendentes'=>$pendentes,'cidades'=>$cidades->fetchAll()]);
        break;

    default:
        echo json_encode(['error'=>'invalid_action']);
}
}catch(Exception $e){http_response_code(500);echo json_encode(['error'=>$e->getMessage()]);}
?>