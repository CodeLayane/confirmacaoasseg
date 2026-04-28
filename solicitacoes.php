<?php
require_once 'layout.php';
if(!$evento_atual){header('Location: index.php');exit();}
$campos_extras=json_decode($evento_atual['campos_extras']??'[]',true)?:[];
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Pendentes - ASSEGO</title><?php renderCSS();?>
<style>.req-card{display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap;padding:16px;background:white;border:1px solid #dbeafe;border-radius:14px;margin-bottom:10px;transition:all .4s;animation:fadeInUp .4s ease}
.req-card.removing{opacity:0;transform:translateX(100px);height:0;padding:0;margin:0;overflow:hidden}
@keyframes fadeInUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
.req-avatar{width:70px;height:70px;border-radius:12px;overflow:hidden;background:#e0f2fe;flex-shrink:0;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:24px}
.req-avatar img{width:100%;height:100%;object-fit:cover}
.req-info{flex:1;min-width:200px}.req-name{color:var(--primary);font-size:16px;font-weight:700;margin-bottom:6px}
.req-meta{display:flex;gap:12px;flex-wrap:wrap;font-size:13px;color:var(--gray);margin-bottom:6px}
.req-tags{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:6px}
.req-tag{background:#f0f9ff;border:1px solid #dbeafe;padding:2px 8px;border-radius:8px;font-size:11px}
.req-time{font-size:11px;color:#94a3b8}
.req-actions{display:flex;gap:8px;flex-shrink:0;align-items:flex-start}
.req-details{width:100%;margin-top:8px;padding-top:10px;border-top:1px solid #e0f2fe;display:none}
.req-details.show{display:block}
.req-detail-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:8px;font-size:12px}
.req-detail-item{background:#f8fafc;border:1px solid #e0f2fe;border-radius:8px;padding:8px 10px}
.req-detail-item .lbl{font-size:10px;color:#64748b;text-transform:uppercase;font-weight:600;margin-bottom:2px}
.req-detail-item .val{color:#1e293b;font-weight:500}
.req-video-box{margin-top:8px;border-radius:10px;overflow:hidden;max-width:400px}
.req-video-box video{width:100%;max-height:200px;background:#000}
.btn-expand{background:#e0f2fe;color:var(--primary);border:none;border-radius:6px;padding:4px 10px;font-size:11px;font-weight:600;cursor:pointer;transition:.2s}
.btn-expand:hover{background:#dbeafe}
</style></head><body>
<?php renderHeader('solicitacoes');?>

<div class="page-header">
    <h1 class="page-title"><i class="fas fa-user-clock"></i> Pendentes — <?=htmlspecialchars($evento_atual['nome'])?> <span id="pendCount" style="background:#ef4444;color:white;border-radius:20px;padding:4px 12px;font-size:14px;margin-left:8px">0</span></h1>
    <button id="btnAprovarTodos" class="btn btn-success" style="display:none" onclick="aprovarTodos()"><i class="fas fa-check-double"></i> Aprovar Todos</button>
</div>

<div class="content-container" id="pendContainer">
    <div style="text-align:center;padding:40px;color:var(--gray)"><i class="fas fa-spinner fa-spin" style="font-size:24px;margin-bottom:12px"></i><br>Carregando...</div>
</div>

<?php renderScripts();?>
<script>
const EID = ASSEGO_RT.getEventoId();
const CAMPOS = <?=json_encode($campos_extras)?>;

async function loadPendentes(){
    try{
        const r=await fetch(`api_realtime.php?action=pendentes&evento_id=${EID}`,{cache:'no-store'});
        const d=await r.json();
        
        document.getElementById('pendCount').textContent=d.total;
        document.getElementById('btnAprovarTodos').style.display=d.total>0?'inline-flex':'none';
        
        const container=document.getElementById('pendContainer');
        
        if(d.total===0){
            container.innerHTML=`<div class="form-card text-center"><i class="fas fa-inbox" style="font-size:48px;color:#dbeafe;margin-bottom:16px"></i><p style="color:var(--gray);font-size:16px">Nenhum cadastro pendente.</p><p style="color:var(--gray);font-size:13px">Novos cadastros aparecerão aqui automaticamente.</p></div>`;
            return;
        }
        
        container.innerHTML=d.rows.map(p=>{
            const extras=JSON.parse(p.campos_extras||'{}');
            const foto=p.foto?`<img src="${p.foto}">`:`<i class="fas fa-user"></i>`;
            const insta=p.instagram?'@'+p.instagram.replace(/^@/,''):'';
            
            let tags='';
            CAMPOS.forEach(ce=>{if(extras[ce.nome])tags+=`<span class="req-tag"><strong>${ce.label}:</strong> ${extras[ce.nome]}</span>`;});
            
            const cidade=p.cidade?`<span><i class="fas fa-map-marker-alt"></i> ${p.cidade}${p.estado?' - '+p.estado:''}</span>`:'';
            const dt=new Date(p.created_at);
            const time=dt.toLocaleDateString('pt-BR')+' '+dt.toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit'});
            
            // Video
            let videoHtml='';
            if(p.video_url){
                const isLocal=!/^https?:\/\//.test(p.video_url);
                const ytMatch=p.video_url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([\w-]+)/);
                if(isLocal) videoHtml=`<div class="req-video-box"><video controls><source src="${p.video_url}"></video></div>`;
                else if(ytMatch) videoHtml=`<div class="req-video-box"><div style="position:relative;padding-bottom:56.25%;height:0"><iframe src="https://www.youtube.com/embed/${ytMatch[1]}" style="position:absolute;top:0;left:0;width:100%;height:100%;border-radius:8px" frameborder="0" allowfullscreen></iframe></div></div>`;
                else videoHtml=`<div style="margin-top:6px"><a href="${p.video_url}" target="_blank" style="color:#7c3aed;font-size:12px;font-weight:600"><i class="fas fa-external-link-alt"></i> Ver Vídeo</a></div>`;
            }
            
            // Acompanhantes
            let acompHtml='';
            try{
                const acomps=JSON.parse(p.acompanhantes||'[]');
                if(acomps.length>0){
                    acompHtml=`<div style="margin-top:10px;padding-top:10px;border-top:1px solid #dbeafe"><div style="font-size:12px;font-weight:700;color:#d97706;margin-bottom:8px"><i class="fas fa-user-friends"></i> Acompanhantes (${acomps.length})</div>`;
                    acomps.forEach((ac,i)=>{
                        acompHtml+=`<div style="background:#f0f9ff;padding:8px 10px;border-radius:8px;margin-bottom:4px;font-size:12px;border:1px solid #dbeafe"><strong>${i+1}. ${ac.nome||''}</strong>`;
                        if(ac.whatsapp)acompHtml+=` · <i class="fab fa-whatsapp" style="color:#25d366"></i> ${ac.whatsapp}`;
                        if(ac.cpf)acompHtml+=` · <i class="fas fa-id-card" style="color:#6366f1"></i> ${ac.cpf}`;
                        if(ac.instagram)acompHtml+=` · <i class="fab fa-instagram" style="color:#e1306c"></i> ${ac.instagram}`;
                        if(ac.endereco||ac.cidade)acompHtml+=`<br><i class="fas fa-map-marker-alt" style="color:#ef4444"></i> ${ac.endereco||''} ${ac.bairro?'- '+ac.bairro:''} ${ac.cidade?ac.cidade:''}${ac.estado?'/'+ac.estado:''} ${ac.cep?'CEP:'+ac.cep:''}`;
                        acompHtml+=`</div>`;
                    });
                    acompHtml+=`</div>`;
                }
            }catch(e){}
            
            // Details
            let details=`<div class="req-detail-grid">`;
            if(p.endereco) details+=`<div class="req-detail-item"><div class="lbl">Endereço</div><div class="val">${p.endereco}</div></div>`;
            if(p.cidade) details+=`<div class="req-detail-item"><div class="lbl">Cidade/UF</div><div class="val">${p.cidade}${p.estado?' - '+p.estado:''}</div></div>`;
            if(p.cep) details+=`<div class="req-detail-item"><div class="lbl">CEP</div><div class="val">${p.cep}</div></div>`;
            CAMPOS.forEach(ce=>{if(extras[ce.nome])details+=`<div class="req-detail-item"><div class="lbl">${ce.label}</div><div class="val">${extras[ce.nome]}</div></div>`;});
            details+=`</div>`;
            
            return `<div class="req-card" id="req-${p.id}">
                <div class="req-avatar">${foto}</div>
                <div class="req-info">
                    <div class="req-name">${p.nome}</div>
                    <div class="req-meta">
                        ${p.whatsapp?`<span><i class="fab fa-whatsapp" style="color:#25d366"></i> ${p.whatsapp}</span>`:''}
                        ${insta?`<span><i class="fab fa-instagram" style="color:#e1306c"></i> ${insta}</span>`:''}
                        ${cidade}
                    </div>
                    ${tags?`<div class="req-tags">${tags}</div>`:''}
                    <div class="req-time"><i class="fas fa-clock"></i> ${time}</div>
                </div>
                <div class="req-actions">
                    <button class="btn-expand" onclick="toggleDetails(${p.id})"><i class="fas fa-chevron-down"></i> Detalhes</button>
                    <button class="btn btn-success btn-sm" onclick="aprovar(${p.id},'${p.nome.replace(/'/g,"\\'")}')"><i class="fas fa-check"></i> Aprovar</button>
                    <button class="btn btn-danger btn-sm" onclick="rejeitar(${p.id},'${p.nome.replace(/'/g,"\\'")}')"><i class="fas fa-times"></i> Rejeitar</button>
                </div>
                <div class="req-details" id="det-${p.id}">
                    ${details}
                    ${videoHtml}
                    ${acompHtml}
                </div>
            </div>`;
        }).join('');
        
    }catch(e){console.error(e);}
}

async function aprovar(id,nome){
    const r=await Swal.fire({
        title:'Aprovar '+nome+'?',
        text:'O participante será adicionado à lista oficial do evento.',
        icon:'question',
        showCancelButton:true,
        confirmButtonText:'<i class="fas fa-check"></i> Aprovar',
        cancelButtonText:'Cancelar',
        confirmButtonColor:'#059669'
    });
    if(!r.isConfirmed)return;
    
    const card=document.getElementById('req-'+id);
    if(card)card.classList.add('removing');
    
    await fetch(`api_realtime.php?action=aprovar&evento_id=${EID}&id=${id}`);
    ASSEGO_RT.toast(nome+' aprovado!','✅');
    
    setTimeout(()=>{loadPendentes();ASSEGO_RT.refresh();},400);
}

async function rejeitar(id,nome){
    const r=await Swal.fire({title:'Rejeitar '+nome+'?',icon:'warning',showCancelButton:true,confirmButtonText:'Rejeitar',confirmButtonColor:'#dc2626'});
    if(!r.isConfirmed)return;
    
    const card=document.getElementById('req-'+id);
    if(card)card.classList.add('removing');
    
    await fetch(`api_realtime.php?action=rejeitar&evento_id=${EID}&id=${id}`);
    ASSEGO_RT.toast(nome+' rejeitado','❌');
    
    setTimeout(()=>{loadPendentes();ASSEGO_RT.refresh();},400);
}

async function aprovarTodos(){
    const r=await Swal.fire({title:'Aprovar todos?',icon:'question',showCancelButton:true,confirmButtonText:'Aprovar Todos',confirmButtonColor:'#059669'});
    if(!r.isConfirmed)return;
    
    await fetch(`api_realtime.php?action=aprovar_todos&evento_id=${EID}`);
    ASSEGO_RT.toast('Todos aprovados!','🎉');
    
    setTimeout(()=>{loadPendentes();ASSEGO_RT.refresh();},400);
}

// Carregar inicial
loadPendentes();

function toggleDetails(id){
    const det=document.getElementById('det-'+id);
    const btn=document.querySelector(`#req-${id} .btn-expand`);
    if(det.classList.contains('show')){
        det.classList.remove('show');
        btn.innerHTML='<i class="fas fa-chevron-down"></i> Detalhes';
    }else{
        det.classList.add('show');
        btn.innerHTML='<i class="fas fa-chevron-up"></i> Fechar';
    }
}

// Re-carregar quando houver mudança nos pendentes
let lastPend=-1;
ASSEGO_RT.onUpdate(function(data){
    if(lastPend>=0 && data.pendentes!==lastPend) loadPendentes();
    lastPend=data.pendentes;
});
</script>
</body></html>
