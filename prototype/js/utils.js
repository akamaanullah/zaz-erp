/* ZAZ ERP — shared utilities, icons and UI components */
(function(){
'use strict';
const $=(s,r=document)=>r.querySelector(s), $$=(s,r=document)=>[...r.querySelectorAll(s)];
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const money=n=>'Rs '+Number(n||0).toLocaleString('en-PK',{maximumFractionDigits:2});
const num=n=>Number(n||0).toLocaleString('en-PK',{maximumFractionDigits:2});
const uid=()=>Date.now().toString(36)+Math.random().toString(36).slice(2,6);
const today=()=>new Date().toISOString().slice(0,10);
const initials=n=>String(n||'?').split(/\s+/).map(w=>w[0]).slice(0,2).join('').toUpperCase();
const P={
 dashboard:'<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
 farm:'<path d="M3 21h18"/><path d="M5 21V10l7-5 7 5v11"/><path d="M9 21v-6h6v6"/>',
 users:'<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.8-3.5 3.4-5.5 6.5-5.5s5.7 2 6.5 5.5"/><circle cx="17" cy="9" r="2.5"/><path d="M17 14.5c2.2.2 3.9 1.8 4.5 4.5"/>',
 hari:'<path d="M12 21V11"/><path d="M12 11c0-4 3-7 7-7 0 4-3 7-7 7z"/><path d="M12 14c0-3-2.5-5.5-6-5.5 0 3.5 2.5 5.5 6 5.5z"/><path d="M6 21h12"/>',
 box:'<path d="M21 8l-9-5-9 5v8l9 5 9-5z"/><path d="M3 8l9 5 9-5"/><path d="M12 13v8"/>',
 truck:'<path d="M1 6h13v10H1z"/><path d="M14 9h4l3 3v4h-7"/><circle cx="5.5" cy="17.5" r="2"/><circle cx="17.5" cy="17.5" r="2"/>',
 sprout:'<path d="M7 20h10"/><path d="M12 20v-8"/><path d="M12 12C12 8 9 5 4 5c0 4 3 7 8 7z"/><path d="M12 10c0-3 2-6 7-6 0 4-3 6-7 6z"/>',
 receipt:'<path d="M5 3h14v18l-3-2-2 2-2-2-2 2-2-2-3 2z"/><path d="M9 8h6M9 12h6M9 16h3"/>',
 cash:'<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 10v4M18 10v4"/>',
 hand:'<path d="M3 14h3l4 3h5a2 2 0 0 0 0-4h-3"/><path d="M3 20h4l9-2 5-4a1.8 1.8 0 0 0-2.5-2.5L15 14"/><circle cx="15" cy="6" r="3"/>',
 book:'<path d="M4 4h6a3 3 0 0 1 3 3v14a2 2 0 0 0-2-2H4z"/><path d="M20 4h-6a3 3 0 0 0-1 .2"/><path d="M20 4v15h-7"/>',
 check:'<path d="M20 6L9 17l-5-5"/>',
 checks:'<path d="M3 12l4 4L16 7"/><path d="M11 15l1 1 9-9"/>',
 rules:'<path d="M4 6h16M4 12h10M4 18h6"/><circle cx="18" cy="16" r="3"/>',
 chart:'<path d="M3 21h18"/><rect x="5" y="11" width="3" height="7"/><rect x="10.5" y="6" width="3" height="12"/><rect x="16" y="13" width="3" height="5"/>',
 log:'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
 settings:'<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-2.7 1.1V21a2 2 0 1 1-4 0v-.1a1.6 1.6 0 0 0-2.7-1.1l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0-1.1-2.7H3a2 2 0 1 1 0-4h.1a1.6 1.6 0 0 0 1.1-2.7l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 2.7-1.1V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 2.7 1.1l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0 1.1 2.7H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1z"/>',
 clipboard:'<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V3h6v1"/><path d="M9 10h6M9 14h6M9 18h3"/>', wallet:'<rect x="3" y="6" width="18" height="14" rx="2"/><path d="M3 10h18"/><circle cx="16.5" cy="15" r="1.3"/><path d="M6 6l9-3 1 3"/>', tag:'<path d="M3 12V4h8l10 10-8 8z"/><circle cx="7.5" cy="8.5" r="1.5"/>', trash:'<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
 plus:'<path d="M12 5v14M5 12h14"/>', search:'<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>', x:'<path d="M18 6L6 18M6 6l12 12"/>',
 print:'<path d="M6 9V3h12v6"/><rect x="3" y="9" width="18" height="8" rx="2"/><path d="M6 14h12v7H6z"/>',
 moon:'<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>', sun:'<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
 logout:'<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>', menu:'<path d="M3 6h18M3 12h18M3 18h18"/>',
 alert:'<path d="M12 3l10 18H2z"/><path d="M12 10v4M12 17.5v.5"/>', info:'<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5v.5"/>',
 archive:'<rect x="3" y="4" width="18" height="4" rx="1"/><path d="M5 8v12h14V8M10 12h4"/>', edit:'<path d="M4 20h4L19 9l-4-4L4 16z"/>',
 download:'<path d="M12 3v12M7 10l5 5 5-5"/><path d="M4 21h16"/>', upload:'<path d="M12 21V9M7 14l5-5 5 5"/><path d="M4 3h16"/>', lock:'<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
 empty:'<path d="M3 13l3-8h12l3 8"/><path d="M3 13v6h18v-6h-6a3 3 0 0 1-6 0z"/>', shield:'<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/>'
};
const icon=(n,cls='')=>`<svg class="${cls}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${P[n]||P.info}</svg>`;

/* ---------- toasts ---------- */
function toast(msg,type='ok'){let w=$('.toasts');if(!w){w=document.createElement('div');w.className='toasts';w.setAttribute('role','status');w.setAttribute('aria-live','polite');document.body.appendChild(w)}
 const t=document.createElement('div');t.className='toast'+(type==='error'?' error':'');t.innerHTML=icon(type==='error'?'alert':'check')+`<span>${esc(msg)}</span>`;w.appendChild(t);setTimeout(()=>{t.style.opacity='0';t.style.transition='opacity .3s';setTimeout(()=>t.remove(),300)},3600)}

/* ---------- modal ---------- */
function modal({title,body,submit='Save',cancel='Cancel',danger=false,onSubmit,wide=false}){
 return new Promise(resolve=>{const back=document.createElement('div');back.className='modal-back';
  back.innerHTML=`<form class="modal" role="dialog" aria-modal="true" aria-label="${esc(title)}" style="${wide?'width:min(820px,100%)':''}" novalidate><header><h3>${esc(title)}</h3><button type="button" class="btn ghost icon" data-close aria-label="Close">${icon('x')}</button></header><div class="body">${body}</div><footer>${cancel?`<button type="button" class="btn secondary" data-close>${esc(cancel)}</button>`:''}${submit?`<button class="btn ${danger?'danger':''}">${esc(submit)}</button>`:''}</footer></form>`;
  document.body.appendChild(back);const f=back.querySelector('form');const prev=document.activeElement;
  const close=v=>{back.remove();document.removeEventListener('keydown',key);prev&&prev.focus&&prev.focus();resolve(v)};
  const key=e=>{if(e.key==='Escape')close(null)};document.addEventListener('keydown',key);
  back.addEventListener('mousedown',e=>{if(e.target===back)close(null)});$$('[data-close]',back).forEach(b=>b.onclick=()=>close(null));
  f.onsubmit=e=>{e.preventDefault();if(!f.checkValidity()){f.reportValidity();return}const data=Object.fromEntries(new FormData(f).entries());
   if(onSubmit){try{const r=onSubmit(data,f);if(r===false)return}catch(err){toast(err.message,'error');return}}close(data||true)};
  setTimeout(()=>{const el=f.querySelector('.body input,.body select,.body textarea')||f.querySelector('footer .btn:last-child');el&&el.focus()},30)})}
const confirmBox=(msg,{title='Please confirm',ok='Confirm',danger=true}={})=>modal({title,body:`<p style="margin:0">${esc(msg)}</p>`,submit:ok,danger}).then(Boolean);

/* ---------- form fields ---------- */
function field(o){const id='f_'+o.name.replace(/\W/g,'_')+Math.random().toString(36).slice(2,5);const req=o.required!==false;
 const lab=`<label for="${id}">${esc(o.label)}${req?' <span class="req" aria-hidden="true">*</span>':''}</label>`;
 let ctl;if(o.options){ctl=`<select id="${id}" name="${esc(o.name)}" ${req?'required':''}>${o.placeholder?`<option value="">${esc(o.placeholder)}</option>`:''}${o.options.map(op=>{const [v,l,dis]=Array.isArray(op)?op:[op,op];return `<option value="${esc(v)}" ${String(v)===String(o.value??'')?'selected':''} ${dis?'disabled':''}>${esc(l)}</option>`}).join('')}</select>`}
 else if(o.type==='textarea')ctl=`<textarea id="${id}" name="${esc(o.name)}" rows="3" ${req?'required':''}>${esc(o.value||'')}</textarea>`;
 else ctl=`<input id="${id}" name="${esc(o.name)}" type="${o.type||'text'}" value="${esc(o.value??'')}" ${req?'required':''} ${o.attrs||''} placeholder="${esc(o.placeholder||'')}">`;
 return `<div class="${o.cls||''}">${lab}${ctl}${o.hint?`<div class="hint">${esc(o.hint)}</div>`:''}</div>`}
const formGrid=fields=>`<div class="form-grid">${fields.map(field).join('')}</div>`;

/* ---------- empty state ---------- */
const empty=(title,text='',ic='empty')=>`<div class="empty">${icon(ic)}<b>${esc(title)}</b>${esc(text)}</div>`;

/* ---------- data table (search, filters, sort) ---------- */
function dataTable(el,{columns,rows,search=true,filters=[],emptyTitle='No records yet',emptyText='',footer=null,pageSize=0}){
 const st={q:'',sort:null,dir:1,f:{}};
 const val=(c,r)=>c.value?c.value(r):(c.key?r[c.key]:'');
 el.innerHTML=`<div class="toolbar">${search?`<div class="search">${icon('search')}<input type="search" placeholder="Search…" aria-label="Search table"></div>`:''}${filters.map((fl,i)=>`<select data-fi="${i}" aria-label="${esc(fl.label)}"><option value="">${esc(fl.label)}: All</option>${fl.options.map(o=>`<option>${esc(o)}</option>`).join('')}</select>`).join('')}</div><div class="table-wrap"></div><div class="table-meta"></div>`;
 const wrap=$('.table-wrap',el),meta=$('.table-meta',el);
 function draw(){let rs=rows.filter(r=>{if(st.q){const hay=columns.map(c=>val(c,r)).join(' ').toLowerCase();if(!hay.includes(st.q))return false}
   return filters.every((fl,i)=>!st.f[i]||String(fl.get(r))===st.f[i])});
  if(st.sort!=null){const c=columns[st.sort];rs=[...rs].sort((a,b)=>{const x=val(c,a),y=val(c,b);return (typeof x==='number'&&typeof y==='number'?x-y:String(x).localeCompare(String(y),undefined,{numeric:true}))*st.dir})}
  if(!rows.length){wrap.innerHTML=empty(emptyTitle,emptyText);meta.textContent='';return}
  wrap.innerHTML=`<table><thead><tr>${columns.map((c,i)=>`<th class="${c.sort===false?'':'sortable'} ${c.num?'num':''}" data-ci="${i}" ${c.sort===false?'':`tabindex="0" aria-sort="${st.sort===i?(st.dir>0?'ascending':'descending'):'none'}"`}>${esc(c.label)}${st.sort===i?`<span class="arr">${st.dir>0?'▲':'▼'}</span>`:''}</th>`).join('')}</tr></thead><tbody>${rs.length?rs.map(r=>`<tr>${columns.map(c=>`<td class="${c.num?'num':''}">${c.html?c.html(r):esc(val(c,r))}</td>`).join('')}</tr>`).join(''):`<tr><td colspan="${columns.length}">${empty('No matching records','Try a different search or filter.','search')}</td></tr>`}</tbody>${footer?`<tfoot><tr>${footer(rs).map((x,i)=>`<td class="${columns[i]?.num?'num':''}">${x}</td>`).join('')}</tr></tfoot>`:''}</table>`;
  meta.textContent=`Showing ${rs.length} of ${rows.length} record${rows.length===1?'':'s'}`;
  if(st.drawn)el.dispatchEvent(new CustomEvent('tabledraw',{bubbles:true}));st.drawn=true;
  $$('th.sortable',wrap).forEach(th=>{const go=()=>{const i=+th.dataset.ci;if(st.sort===i)st.dir*=-1;else{st.sort=i;st.dir=1}draw()};th.onclick=go;th.onkeydown=e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();go()}}})}
 const s=$('input[type=search]',el);if(s)s.oninput=()=>{st.q=s.value.trim().toLowerCase();draw()};
 $$('select[data-fi]',el).forEach(x=>x.onchange=()=>{st.f[x.dataset.fi]=x.value;draw()});
 draw();return {redraw:draw}}

/* ---------- SVG charts ---------- */
function barChart(series,{labels,colors=['var(--c1)','var(--c2)'],height=240,names=[]}={}){
 const W=640,H=height,pad={l:58,r:10,t:12,b:40};const max=Math.max(1,...series.flat());const n=labels.length,gw=(W-pad.l-pad.r)/Math.max(n,1),bw=Math.min(26,(gw-10)/series.length);
 const y=v=>H-pad.b-(v/max)*(H-pad.t-pad.b);let g='';
 for(let i=0;i<=4;i++){const v=max*i/4,yy=y(v);g+=`<line x1="${pad.l}" x2="${W-pad.r}" y1="${yy}" y2="${yy}" stroke="var(--border)"/><text x="${pad.l-8}" y="${yy+4}" text-anchor="end">${v>=1e6?(v/1e6).toFixed(1)+'M':v>=1e3?Math.round(v/1e3)+'k':Math.round(v)}</text>`}
 labels.forEach((l,i)=>{const cx=pad.l+gw*i+gw/2;series.forEach((s,k)=>{const x=cx-(bw*series.length)/2+k*bw,v=s[i]||0;g+=`<rect x="${x+1}" y="${y(v)}" width="${bw-2}" height="${Math.max(0,H-pad.b-y(v))}" rx="4" fill="${colors[k]}"><title>${esc(l)} — ${esc(names[k]||'')}: ${money(v)}</title></rect>`});
  g+=`<text x="${cx}" y="${H-pad.b+18}" text-anchor="middle">${esc(l.length>11?l.slice(0,10)+'…':l)}</text>`});
 const gid='bg'+Math.random().toString(36).slice(2,6);const defs=colors.map((c,k)=>`<linearGradient id="${gid}${k}" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="${c}" stop-opacity="1"/><stop offset="1" stop-color="${c}" stop-opacity=".55"/></linearGradient>`).join('');
 colors.forEach((c,k)=>{g=g.split(`fill="${c}"`).join(`fill="url(#${gid}${k})"`)});
 return `<svg class="chart" viewBox="0 0 ${W} ${H}" role="img" aria-label="Bar chart"><defs>${defs}</defs>${g}</svg>`+(names.length?`<div class="legend">${names.map((nm,k)=>`<span><i style="background:${colors[k]}"></i>${esc(nm)}</span>`).join('')}</div>`:'')}
function donut(parts,{size=180}={}){const tot=parts.reduce((s,p)=>s+p.value,0);if(!tot)return empty('No data yet','Entries will appear here.','chart');
 const r=60,c=2*Math.PI*r;let off=0,segs='';const cols=['var(--c1)','var(--c2)','var(--c3)','var(--c4)','var(--c5)','var(--c6)'];
 parts.forEach((p,i)=>{const len=c*p.value/tot;segs+=`<circle r="${r}" cx="90" cy="90" fill="none" stroke="${cols[i%6]}" stroke-width="26" stroke-dasharray="${len} ${c-len}" stroke-dashoffset="${-off}" transform="rotate(-90 90 90)"><title>${esc(p.label)}: ${money(p.value)}</title></circle>`;off+=len});
 return `<div class="row" style="gap:20px;flex-wrap:nowrap"><svg viewBox="0 0 180 180" width="${size}" height="${size}" role="img" aria-label="Donut chart" style="flex:none">${segs}<text x="90" y="86" text-anchor="middle" style="font:700 15px var(--font);fill:var(--text)">${money(tot).replace('Rs ','')}</text><text x="90" y="104" text-anchor="middle" style="font:12px var(--font);fill:var(--muted)">Rs total</text></svg><div class="legend" style="flex-direction:column;gap:6px">${parts.map((p,i)=>`<span><i style="background:${cols[i%6]}"></i>${esc(p.label)} — <b>${Math.round(p.value/tot*100)}%</b></span>`).join('')}</div></div>`}

const fieldArt=(cls='art')=>`<svg class="${cls}" viewBox="0 0 600 240" preserveAspectRatio="xMaxYMax slice" aria-hidden="true"><defs><linearGradient id="sunG" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ffe29a"/><stop offset="1" stop-color="#f2a93b"/></linearGradient></defs>
 <circle cx="470" cy="80" r="44" fill="url(#sunG)" opacity=".9"/><circle cx="470" cy="80" r="70" fill="#ffe29a" opacity=".12"/>
 <path d="M0 190 Q150 130 300 170 T600 150 V240 H0z" fill="#ffffff" opacity=".08"/><path d="M80 240 Q260 150 600 185 V240z" fill="#0b1530" opacity=".55"/>
 <path d="M200 240 Q400 175 600 205 V240z" fill="#e3a224" opacity=".35"/>
 ${Array.from({length:14},(_,i)=>{const x=330+i*20,h=40+(i%3)*12;return `<g stroke="#f7d27e" stroke-width="2" fill="none" opacity=".85"><path d="M${x} 240 V${240-h}"/>${[0,1,2].map(j=>`<path d="M${x} ${240-h+8+j*10} q-7 -3 -8 -11 q7 2 8 11z M${x} ${240-h+8+j*10} q7 -3 8 -11 q-7 2 -8 11z" fill="#f7d27e"/>`).join('')}</g>`}).join('')}
 <g fill="none" stroke="#fff" stroke-opacity=".25" stroke-width="1.5"><path d="M380 60 q8 -6 16 0 q8 -6 16 0"/><path d="M410 40 q6 -5 12 0 q6 -5 12 0"/></g></svg>`;
function animateCounters(root=document){$$('.kpi .val',root).forEach(el=>{const t=el.textContent.trim();const m=t.match(/^(\D*)([\d,\.]+)(.*)$/);if(!m)return;const target=parseFloat(m[2].replace(/,/g,''));if(!isFinite(target)||target===0)return;
 if(window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches)return;const st=performance.now(),dur=900;
 const step=now=>{const p=Math.min(1,(now-st)/dur),e=1-Math.pow(1-p,3);el.textContent=m[1]+(p<1?Math.round(target*e).toLocaleString('en-PK'):m[2])+m[3];if(p<1)requestAnimationFrame(step)};requestAnimationFrame(step)})}
/* bind handlers now and again whenever a data table inside root redraws (search/sort/filter) */
function on(root,sel,binder){const run=()=>$$(sel,root).forEach(binder);run();root.addEventListener('tabledraw',run)}
window.U={on,fieldArt,animateCounters,$, $$, esc, money, num, uid, today, initials, icon, toast, modal, confirmBox, field, formGrid, empty, dataTable, barChart, donut};
})();
