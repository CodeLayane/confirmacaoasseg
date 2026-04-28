<?php
require_once 'layout.php';
if(!$evento_atual){/* no event */}
$campos_extras=[];
if($evento_atual&&!empty($evento_atual['campos_extras']))$campos_extras=json_decode($evento_atual['campos_extras'],true)?:[];
$slug_ev=$evento_atual['slug']??null;
$base=(isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on'?"https":"http")."://".$_SERVER['HTTP_HOST'].rtrim(dirname($_SERVER['SCRIPT_NAME']),'/').'/';
$link_cadastro=$base."inscricao.php?evento=".($slug_ev?:($evento_atual['id']??''));

// Acompanhantes = aninhados + avulsos
$total_acomps = 0;
if($evento_atual){
    try{
        $sa=$pdo->prepare("SELECT acompanhantes FROM participantes WHERE evento_id=? AND aprovado=1 AND acompanhantes IS NOT NULL AND acompanhantes!='' AND acompanhantes!='[]'");
        $sa->execute([$evento_atual['id']]);
        foreach($sa->fetchAll() as $row){
            $arr=json_decode($row['acompanhantes'],true);
            if(is_array($arr))$total_acomps+=count($arr);
        }
        $av=$pdo->prepare("SELECT COUNT(*) FROM participantes WHERE evento_id=? AND aprovado=1 AND campos_extras LIKE '%acompanhante_avulso%'");
        $av->execute([$evento_atual['id']]);
        $total_acomps+=(int)$av->fetchColumn();
    }catch(Exception $e){}
}
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>ASSEGO Eventos</title><?php renderCSS();?>
<style>
.rt-updating{position:relative}.rt-updating::after{content:'';position:absolute;top:8px;right:8px;width:6px;height:6px;border-radius:50%;background:#10b981;animation:rtPulse 1.5s infinite}
.stat-acomp{border-left:4px solid #d97706!important}
</style>
</head><body>
<?php renderHeader('index');?>

<?php if(!$evento_atual):?>
<div class="no-evento-alert">
<i class="fas fa-calendar-times" style="font-size:48px;color:#dbeafe;display:block;margin-bottom:16px"></i>
<h3 style="color:var(--primary)">Nenhum evento</h3>
<p style="color:var(--gray)"><?=isAdmin()?'<a href="eventos.php?action=add" class="btn btn-primary mt-3"><i class="fas fa-plus-circle"></i> Criar Evento</a>':'Solicite acesso ao administrador.'?></p>
</div>
<?php else:?>

<!-- Stats -->
<div class="stats-container" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr))">

    <div class="stat-card rt-updating">
        <div class="stat-header">
            <div><div class="stat-value" id="rt-total">0</div><div class="stat-label">Cadastrados</div></div>
            <div class="stat-icon"><i class="fas fa-users"></i></div>
        </div>
    </div>

    <div class="stat-card stat-acomp">
        <div class="stat-header">
            <div>
                <div class="stat-value" id="rt-acomps"><?=$total_acomps?></div>
                <div class="stat-label">Acompanhantes</div>
            </div>
            <div class="stat-icon" style="background:linear-gradient(135deg,#d97706,#f59e0b)"><i class="fas fa-user-friends"></i></div>
        </div>

    </div>

    <div class="stat-card">
        <div class="stat-header">
            <div><div class="stat-value" id="rt-novos">0</div><div class="stat-label">Novos Hoje</div></div>
            <div class="stat-icon" style="background:linear-gradient(135deg,#0284c7,#0ea5e9)"><i class="fas fa-user-plus"></i></div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <div><div class="stat-value" id="rt-pendentes">0</div><div class="stat-label">Pendentes</div></div>
            <div class="stat-icon" style="background:linear-gradient(135deg,#dc2626,#ef4444)"><i class="fas fa-user-clock"></i></div>
        </div>
    </div>

    <div class="stat-card" style="border-left:4px solid #6366f1">
        <div class="stat-header">
            <div>
                <div class="stat-value" id="rt-total-evento" style="color:#6366f1">—</div>
                <div class="stat-label">Total do Evento</div>
            </div>
            <div class="stat-icon" style="background:linear-gradient(135deg,#6366f1,#8b5cf6)"><i class="fas fa-users-line"></i></div>
        </div>
        <div style="margin-top:10px;padding-top:10px;border-top:1px solid #f1f5f9;font-size:12px;color:var(--gray)">
            Titulares + Acompanhantes
        </div>
    </div>

</div>

<!-- Filtros -->
<div style="background:white;padding:20px 24px;margin:0 24px 20px;border-radius:16px;box-shadow:var(--shadow-md);border:1px solid #dbeafe">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px">
        <h3 style="color:var(--primary);font-size:18px;font-weight:600;margin:0">
            <i class="fas fa-bolt" style="color:#f59e0b"></i> <?=htmlspecialchars($evento_atual['nome'])?>
            <small style="color:var(--gray);font-weight:400;font-size:12px">— tempo real</small>
        </h3>
        <div class="d-flex gap-2 flex-wrap">
            <a href="participantes.php?action=add" class="btn btn-success btn-sm"><i class="fas fa-plus-circle"></i> Novo</a>
            <button class="btn btn-sm" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);color:white;border:none" data-bs-toggle="modal" data-bs-target="#modalLink"><i class="fas fa-share-alt"></i> Link</button>
            <a href="export.php?format=excel" class="btn btn-info btn-sm"><i class="fas fa-download"></i> Excel</a>
        </div>
    </div>
    <div style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap">
        <button onclick="setTipo('todos')" id="btnTipoTodos" class="btn btn-primary btn-sm" style="border-radius:20px"><i class="fas fa-users"></i> Todos</button>
        <button onclick="setTipo('titular')" id="btnTipoTitular" class="btn btn-secondary btn-sm" style="border-radius:20px"><i class="fas fa-user"></i> Cadastrados <span id="cntTitular" style="background:rgba(0,0,0,.15);padding:1px 7px;border-radius:10px;font-size:11px"><?=$total_aprov??''?></span></button>
        <button onclick="setTipo('acomp')" id="btnTipoAcomp" class="btn btn-secondary btn-sm" style="border-radius:20px;background:linear-gradient(135deg,#d97706,#f59e0b);color:white;border:none"><i class="fas fa-user-friends"></i> Acompanhantes <span id="cntAcomp" style="background:rgba(0,0,0,.15);padding:1px 7px;border-radius:10px;font-size:11px"><?=$total_acomps??''?></span></button>
    </div>
    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:end">
        <div style="flex:1;min-width:200px"><input type="text" id="rtSearch" class="form-control" placeholder="Buscar nome, WhatsApp, CPF... (busca em tempo real)" autocomplete="off"></div>
        <select id="rtPerPage" class="form-select" style="width:auto">
            <option value="10">10</option><option value="25" selected>25</option><option value="50">50</option><option value="100">100</option>
        </select>
    </div>
</div>

<!-- Tabela -->
<div class="table-container" id="rtTableContainer">
    <div class="table-header">
        <h3 class="table-title">Cadastrados</h3>
        <span style="color:var(--gray)" id="rtTotalRows">carregando...</span>
    </div>
    <div style="overflow-x:auto">
        <table class="table" style="table-layout:auto"><thead><tr>
            <th style="min-width:200px">Participante</th>
            <th style="min-width:120px">WhatsApp</th>
            <th style="min-width:100px">Instagram</th>
            <?php foreach(array_slice($campos_extras,0,2) as $ce):?><th><?=htmlspecialchars($ce['label'])?></th><?php endforeach;?>
            <th class="text-center" style="width:130px">Ações</th>
        </tr></thead>
        <tbody id="rtTableBody">
            <tr><td colspan="<?=4+count(array_slice($campos_extras,0,2))?>" style="text-align:center;padding:40px;color:var(--gray)"><i class="fas fa-spinner fa-spin"></i> Carregando...</td></tr>
        </tbody></table>
    </div>
    <div class="pagination-container" id="rtPagination"></div>
</div>

<!-- Modal Link + QR -->
<?php $qrUrl='https://api.qrserver.com/v1/create-qr-code/?size=250x250&data='.urlencode($link_cadastro).'&bgcolor=ffffff&color=000000&format=png&margin=10';?>
<div class="modal fade" id="modalLink" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content" style="border-radius:16px;border:none">
<div class="modal-header" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);color:white;border-radius:16px 16px 0 0">
<h5 class="modal-title"><i class="fas fa-link"></i> Link de Cadastro</h5>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
<div class="modal-body p-4 text-center">
<img src="<?=$qrUrl?>" width="200" height="200" alt="QR Code" style="border-radius:12px;border:3px solid #e0f2fe;margin-bottom:16px" id="indexQrImg">
<p class="text-muted small mb-2">Escaneie ou envie o link para as pessoas se cadastrarem</p>
<div class="input-group mb-3">
<input type="text" class="form-control bg-light" id="linkInput" value="<?=$link_cadastro?>" readonly>
<button class="btn btn-primary" onclick="navigator.clipboard.writeText(document.getElementById('linkInput').value).then(()=>Swal.fire({icon:'success',title:'Copiado!',timer:1500,showConfirmButton:false}))"><i class="fas fa-copy"></i></button>
</div>
<div class="d-flex gap-2 justify-content-center flex-wrap">
<button onclick="(function(){const a=document.createElement('a');a.href=document.getElementById('indexQrImg').src;a.download='qrcode-evento.png';a.click()})()" class="btn btn-sm btn-outline-primary"><i class="fas fa-download"></i> Baixar QR</button>
<button onclick="(function(){const img=document.getElementById('indexQrImg');const w=window.open('','_blank','width=500,height=600');w.document.write('<html><head><title>QR Code</title><style>body{text-align:center;font-family:Inter,Arial,sans-serif;padding:40px}h2{color:#1e3a8a;margin-bottom:8px}p{color:#64748b;font-size:14px;word-break:break-all}img{margin:20px 0}</style></head><body><h2><?=htmlspecialchars(addslashes($evento_atual['nome']??''))?></h2><p>Escaneie para se cadastrar</p><img src='+img.src+' width=300 height=300><p style=margin-top:16px><?=addslashes($link_cadastro)?></p></body></html>');w.document.close();w.onload=function(){w.print()}})()" class="btn btn-sm btn-outline-success"><i class="fas fa-print"></i> Imprimir</button>
</div>
</div></div></div></div>

<?php endif;?>


<!-- Modal Preview Participante -->
<div class="modal fade" id="modalPreview" tabindex="-1"><div class="modal-dialog modal-dialog-centered" style="max-width:420px"><div class="modal-content" style="border-radius:20px;border:none;overflow:hidden">
<div class="modal-body" style="padding:0">
    <div id="pvModalBody" style="min-height:80px;display:flex;align-items:center;justify-content:center;padding:24px">
        <i class="fas fa-spinner fa-spin" style="color:#94a3b8;font-size:24px"></i>
    </div>
</div>
<div class="modal-footer" style="border-top:1px solid #f1f5f9;padding:10px 16px;background:#f8fafc;gap:8px">
    <a id="pvModalLink" href="#" class="btn btn-primary btn-sm"><i class="fas fa-eye"></i> Ficha completa</a>
    <a id="pvModalEdit" href="#" class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i> Editar</a>
    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
</div>
</div></div></div>

<?php renderScripts();?>
<script>
<?php if($evento_atual):?>
const CAMPOS_EXTRAS = <?=json_encode(array_slice($campos_extras,0,2))?>;
let currentPage = 1;
let currentSearch = '';
let currentTipo = 'todos';

function setTipo(t){
    currentTipo=t;
    ['todos','titular','acomp'].forEach(x=>{
        const btn=document.getElementById('btnTipo'+x.charAt(0).toUpperCase()+x.slice(1));
        if(btn) btn.className=btn.className.replace(/btn-primary|btn-secondary/g,'')+(x===t?' btn-primary':' btn-secondary');
    });
    const titles={todos:'Todos',titular:'Cadastrados',acomp:'Acompanhantes'};
    const th=document.querySelector('#rtTableContainer .table-title');
    if(th) th.textContent=titles[t]||'';
    loadTable(1,currentSearch);
}

async function loadTable(page, search){
    page = page || currentPage;
    search = search !== undefined ? search : currentSearch;
    const perPage = document.getElementById('rtPerPage').value;
    const eid = ASSEGO_RT.getEventoId();
    try{
        const r = await fetch(`api_realtime.php?action=lista&evento_id=${eid}&page=${page}&per_page=${perPage}&search=${encodeURIComponent(search)}&tipo=${currentTipo}`,{cache:'no-store'});
        const d = await r.json();
        currentPage = d.page;
        currentSearch = search;
        document.getElementById('rtTotalRows').textContent = d.total + ' registros';
        const tbody = document.getElementById('rtTableBody');
        if(d.rows.length === 0){
            tbody.innerHTML = `<tr><td colspan="${4+CAMPOS_EXTRAS.length}" style="text-align:center;padding:40px;color:var(--gray)"><i class="fas fa-users-slash" style="font-size:32px;color:#dbeafe;margin-bottom:8px;display:block"></i>Nenhum cadastrado encontrado</td></tr>`;
        } else {
            tbody.innerHTML = d.rows.map(p => {
                const extras = JSON.parse(p.campos_extras || '{}');
                const insta = p.instagram ? '@'+p.instagram.replace(/^@/,'') : '-';
                let extraCols = '';
                CAMPOS_EXTRAS.forEach(ce => { extraCols += `<td>${extras[ce.nome] || '-'}</td>`; });
                let acomps = [];
                try { acomps = JSON.parse(p.acompanhantes || '[]'); } catch(e){}
                const avatarInner = p.has_foto
                    ? `<img src="foto.php?id=${p.id}" style="width:100%;height:100%;object-fit:cover" loading="lazy" onerror="this.parentElement.innerHTML='<i class=\\'fas fa-user\\' style=\\'color:#94a3b8;font-size:14px\\'></i>'">`
                    : `<i class="fas fa-user" style="color:#94a3b8;font-size:14px"></i>`;
                const avatarBg = p.has_foto ? '#f0fdf4' : '#e0f2fe';
                const acompBadge = acomps.length > 0
                    ? `<span style="background:#fff7ed;color:#d97706;border:1px solid #fed7aa;padding:1px 7px;border-radius:10px;font-size:10px;font-weight:700;margin-left:6px"><i class="fas fa-user-friends"></i> +${acomps.length}</span>`
                    : '';

                let html = '';
                // Linha do titular — não exibe quando filtro = acompanhantes
                if(currentTipo !== 'acomp'){
                    html = `<tr style="cursor:pointer" onclick="showPreview(${p.id},'${(p.nome||'').replace(/'/g,"\\'")}')">
                    <td><div style="display:flex;align-items:center;gap:10px">
                        <div style="width:36px;height:36px;min-width:36px;border-radius:50%;overflow:hidden;background:${avatarBg};display:flex;align-items:center;justify-content:center;border:2px solid ${p.has_foto?'#bbf7d0':'#dbeafe'}">${avatarInner}</div>
                        <div><span class="fw-semibold">${(p.nome||'').toUpperCase()}</span>${acompBadge}</div>
                    </div></td>
                    <td style="white-space:nowrap">${p.whatsapp||'-'}</td>
                    <td>${insta}</td>
                    ${extraCols}
                    <td><div class="action-buttons">
                        <a href="participante_view.php?id=${p.id}" class="btn-action view"><i class="fas fa-eye"></i></a>
                        <a href="participantes.php?action=edit&id=${p.id}" class="btn-action edit"><i class="fas fa-edit"></i></a>
                        <button onclick="delPart(${p.id})" class="btn-action delete"><i class="fas fa-trash"></i></button>
                    </div></td></tr>`;
                }
                // Sub-linhas de acompanhantes — não exibe quando filtro = titular
                if(currentTipo !== 'titular'){
                    acomps.forEach(ac => {
                        let acExtraCols = '';
                        CAMPOS_EXTRAS.forEach(() => { acExtraCols += '<td>-</td>'; });
                        html += `<tr style="background:#fffbeb">
                            <td style="padding:6px 12px 6px 48px"><div style="display:flex;align-items:center;gap:8px">
                                <i class="fas fa-level-up-alt fa-rotate-90" style="color:#d97706;font-size:10px"></i>
                                <span style="color:#92400e;font-size:12px;font-weight:600">${(ac.nome||'').toUpperCase()}</span>
                                <span style="font-size:10px;color:#d97706;background:#fef3c7;padding:1px 6px;border-radius:4px">acomp. de ${(p.nome||'').split(' ')[0]}</span>
                            </div></td>
                            <td style="color:#92400e;font-size:12px;padding:6px 12px">${ac.whatsapp||'-'}</td>
                            <td style="color:#92400e;font-size:12px;padding:6px 12px">${ac.instagram||'-'}</td>
                            ${acExtraCols}<td></td></tr>`;
                    });
                }
                return html;
            }).join('');
        }
        const pag = document.getElementById('rtPagination');
        if(d.total_pages > 1){
            let html = `<div style="color:var(--gray);font-size:14px">Pág ${d.page} de ${d.total_pages} (${d.total})</div><nav><ul class="pagination">`;
            for(let i=Math.max(1,d.page-2);i<=Math.min(d.total_pages,d.page+2);i++){
                html += `<li class="page-item ${i===d.page?'active':''}"><a class="page-link" href="#" onclick="loadTable(${i});return false">${i}</a></li>`;
            }
            html += '</ul></nav>';
            pag.innerHTML = html; pag.style.display='flex';
        } else { pag.style.display='none'; }
    }catch(e){ console.error('loadTable error',e); }
}

function delPart(id){
    Swal.fire({title:'Excluir?',icon:'warning',showCancelButton:true,confirmButtonColor:'#dc2626',confirmButtonText:'Excluir'}).then(r=>{
        if(r.isConfirmed){
            fetch(`participantes.php?action=delete&id=${id}&ajax=1`).then(()=>{
                ASSEGO_RT.toast('Participante excluído','🗑️');
                loadTable(); ASSEGO_RT.refresh();
            });
        }
    });
}

let searchTimer;
document.getElementById('rtSearch')?.addEventListener('input',function(){
    clearTimeout(searchTimer);
    searchTimer=setTimeout(()=>loadTable(1,this.value),400);
});
document.getElementById('rtPerPage')?.addEventListener('change',()=>loadTable(1));
loadTable(1,'');

let lastKnownTotal=-1;
ASSEGO_RT.onUpdate(function(data){
    if(lastKnownTotal>=0&&data.total!==lastKnownTotal){loadTable();}
    lastKnownTotal=data.total;
    // Atualizar card de acompanhantes e total evento
    fetch(`api_realtime.php?action=stats_acomps&evento_id=${ASSEGO_RT.getEventoId()}`,{cache:'no-store'})
        .then(r=>r.json()).then(d=>{
            const acomps=d.total_acomps||0;
            const titulares=data.total||0;
            const totalEvento=titulares+acomps;
            const elAcomps=document.getElementById('rt-acomps');
            if(elAcomps) elAcomps.textContent=acomps;
            const elTev=document.getElementById('rt-total-evento');
            if(elTev){elTev.style.transform='scale(1.1)';elTev.style.color='#6366f1';setTimeout(()=>{elTev.textContent=totalEvento;elTev.style.transform='scale(1)';},150);}
        }).catch(()=>{});
});


// Preview rápido do participante
async function showPreview(id, nome){
    // Abrir modal
    const modal = new bootstrap.Modal(document.getElementById('modalPreview'));
    document.getElementById('pvModalBody').style.padding='24px';
    document.getElementById('pvModalBody').innerHTML = '<i class="fas fa-spinner fa-spin" style="color:#94a3b8;font-size:24px"></i>';
    document.getElementById('pvModalLink').href = `participante_view.php?id=${id}`;
    document.getElementById('pvModalEdit').href = `participantes.php?action=edit&id=${id}`;
    modal.show();

    // Buscar dados
    try{
        const r = await fetch(`preview_participante.php?id=${id}`, {cache:'no-store'});
        const d = await r.json();

        // subtítulo removido — info já está no corpo do modal

        // Corpo do modal — compacto e sem duplicação
        const acompsCount = d.acomps ? d.acomps.length : 0;

        const fotoHtml = d.foto
            ? `<img src="foto.php?id=${id}" style="width:100%;height:auto;max-height:340px;object-fit:contain;background:#f1f5f9;display:block">`
            : `<div style="width:100%;height:120px;background:linear-gradient(135deg,#1e3a8a,#3b82f6);display:flex;align-items:center;justify-content:center"><i class="fas fa-user" style="font-size:48px;color:rgba(255,255,255,.4)"></i></div>`;

        const statusHtml = d.aprovado_em_fmt
            ? `<span style="background:#dcfce7;color:#16a34a;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700"><i class="fas fa-check-circle"></i> Aprovado ${d.aprovado_em_fmt}</span>`
            : `<span style="background:#fef3c7;color:#92400e;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700"><i class="fas fa-clock"></i> Pendente</span>`;

        let html = `
        <div>
            ${fotoHtml}
            <div style="padding:16px 18px">
                <div style="font-size:18px;font-weight:800;color:#1e293b;margin-bottom:2px">${d.nome}</div>
                <div style="font-size:13px;color:#64748b;margin-bottom:12px">
                    <i class="fab fa-whatsapp" style="color:#25d366"></i> ${d.whatsapp||'—'}
                    ${d.instagram?`&nbsp;·&nbsp;<i class="fab fa-instagram" style="color:#e1306c"></i> @${d.instagram.replace(/^@/,'')}`:''}
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:12px">
                    ${statusHtml}
                    ${acompsCount>0?`<span style="background:#fff7ed;color:#d97706;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;border:1px solid #fed7aa"><i class="fas fa-user-friends"></i> ${acompsCount} acomp.</span>`:''}
                </div>
                <div style="display:flex;gap:16px;font-size:11px;color:#94a3b8;border-top:1px solid #f1f5f9;padding-top:10px">
                    <span><i class="fas fa-calendar-plus" style="color:#3b82f6"></i> Cadastro: <strong style="color:#475569">${d.created_at_fmt||'—'}</strong></span>
                    ${d.aprovado_por?`<span><i class="fas fa-user-check" style="color:#16a34a"></i> por <strong style="color:#475569">${d.aprovado_por}</strong></span>`:''}
                </div>
            </div>
        </div>`;

        document.getElementById('pvModalBody').style.padding = '0';
        document.getElementById('pvModalBody').innerHTML = html;
    }catch(e){
        document.getElementById('pvModalBody').innerHTML = '<div style="text-align:center;color:#dc2626"><i class="fas fa-exclamation-triangle"></i> Erro ao carregar dados</div>';
    }
}
<?php endif;?>
</script>
</body></html>