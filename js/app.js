/* ZAZ ERP — auth guard, app shell (sidebar/topbar), theme, language, context, print */
(function(){
'use strict';
const {icon,esc,$,$$,initials}=U;
const OFFICE=['owner','office'],ALL=['owner','office','supervisor','accountant'];
const NAV=[
 {group:'Overview'},
 {id:'dashboard',href:'dashboard.html',icon:'dashboard',l:['Dashboard','ڈیش بورڈ','ڊيش بورڊ'],roles:ALL},
 {group:'Administration'},
 {id:'farms',href:'farms.html',icon:'farm',l:['Farms','فارمز','فارم'],roles:OFFICE},
 {id:'employees',href:'employees.html',icon:'users',l:['Employees & Roles','ملازمین اور کردار','ملازم ۽ ڪردار'],roles:OFFICE},
 {id:'salaries',href:'salaries.html',icon:'wallet',l:['Staff Salaries','عملے کی تنخواہ','عملي جي پگهار'],roles:OFFICE},
 {group:'Field'},
 {id:'hari',href:'hari.html',icon:'hari',l:['Hari & Crop Acres','ہاری اور فصل کے ایکڑ','هاري ۽ فصل جا ايڪڙ'],roles:ALL},
 {id:'godown',href:'godown.html',icon:'box',l:['Main Godown','مین گودام','مکيه گودام'],roles:OFFICE},
 {id:'transfers',href:'transfers.html',icon:'truck',l:['Transfers & Receiving','منتقلی اور وصولی','منتقلي ۽ وصولي'],roles:ALL},
 {id:'usage',href:'usage.html',icon:'sprout',l:['Stock Usage','اسٹاک کا استعمال','اسٽاڪ جو استعمال'],roles:ALL},
 {id:'daily',href:'daily.html',icon:'clipboard',l:['Daily Work (24h)','روزانہ کام (24 گھنٹے)','روزانو ڪم (24 ڪلاڪ)'],roles:ALL},
 {group:'Accounts'},
 {id:'expenses',href:'expenses.html',icon:'receipt',l:['Other Expenses','دیگر اخراجات','ٻيا خرچ'],roles:ALL},
 {id:'sales',href:'sales.html',icon:'cash',l:['Crop Sales','فصل کی فروخت','فصل جو وڪرو'],roles:ALL},
 {id:'advances',href:'advances.html',icon:'hand',l:['Hari Advances','ہاری ایڈوانس','هاري اڳواٽ'],roles:ALL},
 {id:'settlements',href:'settlements.html',icon:'book',l:['Hari Ledger & Settlement','ہاری کھاتہ','هاري کاتو'],roles:ALL},
 {group:'Office'},
 {id:'approvals',href:'approvals.html',icon:'checks',l:['Office Bulk Approval','دفتر کی اجتماعی منظوری','آفيس جي گڏيل منظوري'],roles:OFFICE,count:true},
 {id:'rates',href:'rates.html',icon:'tag',l:['Rates & Categories','ریٹس اور زمرے','اگهه ۽ قسم'],roles:OFFICE},
 {id:'rules',href:'rules.html',icon:'rules',l:['Office Rules','دفتری اصول','آفيس جا قاعدا'],roles:OFFICE},
 {id:'reports',href:'reports.html',icon:'chart',l:['Reports & Print','رپورٹس','رپورٽون'],roles:ALL},
 {id:'audit',href:'audit.html',icon:'log',l:['Audit Log','آڈٹ لاگ','آڊٽ لاگ'],roles:ALL},
 {id:'settings',href:'settings.html',icon:'settings',l:['Settings','ترتیبات','سيٽنگون'],roles:ALL}];
const LANG=()=>Store.db.settings.language||'en';
const tr=item=>item.l[['en','ur','sd'].indexOf(LANG())]||item.l[0];
function applyTheme(){document.documentElement.dataset.theme=Store.db.settings.theme==='dark'?'dark':'light'}
applyTheme();
const user=()=>Store.sessionUser();
const isOffice=()=>!!user()&&OFFICE.includes(user().role);
const isOwner=()=>user()?.role==='owner';
function visibleFarms(){const u=user();if(!u)return[];const act=Store.db.farms.filter(f=>!f.archived).map(f=>f.name);return OFFICE.includes(u.role)?act:[u.farm].filter(Boolean)}
const CTX=Store.KEY+'_ctx';
function ctx(){let c={};try{c=JSON.parse(localStorage.getItem(CTX))||{}}catch(e){}const fs=visibleFarms();if(!fs.includes(c.farm))c.farm=fs[0]||'';if(!Store.SEASONS.includes(c.season))c.season=Store.SEASONS[0];return c}
const setCtx=c=>localStorage.setItem(CTX,JSON.stringify(c));
const farm=()=>ctx().farm, season=()=>ctx().season;
function login(id,pin){const u=Store.db.users.find(x=>x.id===id&&x.active);if(!u||pin!==u.pin)return false;localStorage.setItem(Store.KEY+'_session',u.id);Store.log('Signed in');return true}
function logout(){if(user())Store.log('Signed out');localStorage.removeItem(Store.KEY+'_session');location.href='index.html'}
let current=null;
function shell(page){const u=user(),c=ctx();const nav=NAV.filter(n=>n.group||n.roles.includes(u.role));
 const groups=[];nav.forEach((n,i)=>{if(n.group){const next=nav.slice(i+1);const firstGroup=next.findIndex(x=>x.group);const items=(firstGroup<0?next:next.slice(0,firstGroup));if(items.length)groups.push(`<div class="nav-group">${esc(n.group)}</div>`)}else{const cnt=n.count?Store.pending().length:0;groups.push(`<a href="${n.href}" class="${n.id===page.id?'active':''}" ${n.id===page.id?'aria-current="page"':''}>${icon(n.icon)}<span>${esc(tr(n))}</span>${cnt?`<span class="count">${cnt}</span>`:''}</a>`)}});
 const fs=visibleFarms();const navItem=NAV.find(n=>n.id===page.id);
const keepToasts=$('.toasts');
 document.body.innerHTML=`<a href="#content" class="skip">Skip to content</a><div class="app"><aside class="sidebar" aria-label="Main navigation"><div class="brand"><img src="assets/logo.svg" alt="ZAZ Agriculture logo"><div><b>ZAZ AGRICULTURE</b><small>Farm Control ERP</small></div></div><nav class="nav">${groups.join('')}</nav><div class="sidebar-foot">${icon('lock','').replace('<svg','<svg style="width:14px;vertical-align:-2px"')} Static demo • data stays in this browser</div></aside><div class="backdrop" data-close-nav></div>
 <div class="main"><header class="topbar"><button class="btn secondary icon menu-btn" id="menuBtn" aria-label="Open menu">${icon('menu')}</button><div class="title"><div class="crumb">ZAZ ERP / ${esc(navItem?tr(navItem):page.title)}</div><h1>${esc(page.title)}</h1></div>
 <div class="ctx no-print">${page.noCtx?'':`<select id="ctxFarm" aria-label="Farm" ${fs.length<2?'disabled':''}>${fs.map(f=>`<option ${f===c.farm?'selected':''}>${esc(f)}</option>`).join('')||'<option>No farm assigned</option>'}</select><select id="ctxSeason" aria-label="Season">${Store.SEASONS.map(s=>`<option ${s===c.season?'selected':''}>${s}</option>`).join('')}</select>`}
 <select id="ctxLang" aria-label="Menu language" style="min-width:auto"><option value="en">EN</option><option value="ur">اردو</option><option value="sd">سنڌي</option></select>
 <button class="btn secondary icon" id="themeBtn" aria-label="Toggle dark mode" title="Toggle dark / light">${icon(Store.db.settings.theme==='dark'?'sun':'moon')}</button>
 <div class="user-chip"><span class="avatar">${esc(initials(u.name))}</span><div><b>${esc(u.name)}</b><br><small>${esc(Store.ROLES[u.role])}${u.farm?' • '+esc(u.farm):''}</small></div></div><button class="btn secondary icon" id="logoutBtn" aria-label="Log out" title="Log out">${icon('logout')}</button></div></header>
 <main id="content" class="content" tabindex="-1"></main><footer class="footer">ZAZ AGRICULTURE • Internal-use ERP prototype • Static demo — not a secure production system</footer></div></div><section id="printview" aria-hidden="true"></section>`;
 if(keepToasts)document.body.appendChild(keepToasts);
 $('#ctxLang').value=LANG();
 $('#menuBtn').onclick=()=>document.body.classList.toggle('nav-open');$('[data-close-nav]').onclick=()=>document.body.classList.remove('nav-open');
 $('#themeBtn').onclick=()=>{Store.db.settings.theme=Store.db.settings.theme==='dark'?'light':'dark';Store.save();applyTheme();render()};
 $('#ctxLang').onchange=e=>{Store.db.settings.language=e.target.value;Store.save();render()};
 $('#logoutBtn').onclick=logout;
 const cf=$('#ctxFarm'),cs=$('#ctxSeason');if(cf)cf.onchange=()=>{setCtx({...ctx(),farm:cf.value});render()};if(cs)cs.onchange=()=>{setCtx({...ctx(),season:cs.value});render()};}
function render(){const p=current;shell(p);const el=$('#content');
 if(!p.roles.includes(user().role)){el.innerHTML=`<div class="card">${U.empty('Access restricted','This page is available to Owner and Office roles only. Use the menu to open pages for your role.','lock')}</div>`;return}
 if(!p.noCtx&&!farm()){el.innerHTML=`<div class="card">${U.empty('No farm assigned','Ask the office to assign you a farm.','farm')}</div>`;return}
 try{p.render(el);U.animateCounters(el)}catch(err){console.error(err);el.innerHTML=`<div class="alert">${icon('alert')} Something went wrong: ${esc(err.message)}</div>`}}
function boot(page){if(!user()){location.replace('index.html');return}const n=NAV.find(x=>x.id===page.id);current={roles:n?n.roles:ALL,...page};document.title=page.title+' | ZAZ AGRICULTURE ERP';render()}
function print(html,{rtl=false}={}){const pv=$('#printview');pv.innerHTML=`<div class="doc ${rtl?'rtl':''}" ${rtl?'dir="rtl" lang="sd"':''}>${html}</div>`;document.body.classList.add('printing');
 const done=()=>{document.body.classList.remove('printing');window.removeEventListener('afterprint',done)};window.addEventListener('afterprint',done);window.print();setTimeout(done,1500)}
const docHead=(title,sub='')=>`<div class="doc-head"><img src="assets/logo.svg" alt=""><div style="text-align:right"><b>ZAZ AGRICULTURE</b><br><small>${esc(sub)}</small></div></div><h2>${esc(title)}</h2>`;
/* common helpers used by pages */
const statusBadge=x=>x.approved?'<span class="badge ok">Approved</span>':'<span class="badge warn">Pending office approval</span>';
const targetName=x=>x.target==='seri'?'Seri':(Store.getHari(x.hariId)?.name||'—');
const hariOpts=()=>Store.db.haris.filter(h=>h.farm===farm()).map(h=>[h.id,h.name]);
const targetOpts=()=>[['seri','Seri — 100% Zamindar'],...hariOpts()];
const SCOPE='zaz_stock_scope';
function scopeBar(id){if(!isOffice())return '';const v=sessionStorage.getItem(SCOPE)||'farm';return `<div class="seg no-print" role="group" aria-label="Stock scope"><button type="button" data-scope="farm" class="${v==='farm'?'on':''}">This farm (${esc(farm())})</button><button type="button" data-scope="all" class="${v==='all'?'on':''}">All Farms</button></div>`}
function scope(){return isOffice()&&sessionStorage.getItem(SCOPE)==='all'?'all':'farm'}
function bindScope(el){el.querySelectorAll('[data-scope]').forEach(b=>b.onclick=()=>{sessionStorage.setItem(SCOPE,b.dataset.scope);render()})}
function editSalary(id){const u=Store.db.users.find(x=>x.id===id);if(!u)return;U.modal({title:'Salary — '+u.name,submit:'Save salary',body:U.formGrid([{name:'salary',label:'Monthly salary (PKR)',type:'number',value:u.salary??0,attrs:'min="0" step="1"'},{name:'payType',label:'Pay type',options:Store.PAY_TYPES,value:u.payType}]),onSubmit:d=>{const old=u.salary;u.salary=Number(d.salary);u.payType=d.payType;Store.log(`Salary ${u.username}: ${old} → ${u.salary} (${u.payType})`);U.toast('Salary updated');render()}})}
/* All Farms combined stock view (used by usage, transfers, reports) */
function stockSummary(host){const S=Store,{num,money}=U;const rows=S.ITEMS.map(it=>{const b=S.stockByFarm(it);const t=k=>b.reduce((s,x)=>s+x[k],0);return {it,sent:t('sent'),received:t('received'),used:t('used'),left:t('left'),cost:t('cost'),farms:b.filter(x=>x.sent||x.used).length}});
 U.dataTable(host,{search:false,rows,columns:[{label:'Item',value:r=>r.it,html:r=>`<b>${r.it}</b>`},{label:'Sent to farms',num:1,value:r=>r.sent,html:r=>num(r.sent)},{label:'Received',num:1,value:r=>r.received,html:r=>num(r.received)},{label:'Used (all farms)',num:1,value:r=>r.used,html:r=>`<b>${num(r.used)}</b>`},{label:'Remaining at farms',num:1,value:r=>r.left,html:r=>num(r.left)},{label:'Usage cost',num:1,value:r=>r.cost,html:r=>money(r.cost)},{label:'Godown',num:1,value:r=>S.godownQty(r.it),html:r=>num(S.godownQty(r.it))},{label:'Farms',num:1,value:r=>r.farms}]})}
function breakdown(host){const S=Store,{num,esc}=U;const farms=[...new Set(S.ITEMS.flatMap(it=>S.stockByFarm(it).filter(x=>x.sent||x.used).map(x=>x.farm)))];
 const cell=(f,it)=>S.stockByFarm(it).find(x=>x.farm===f);
 U.dataTable(host,{rows:farms,emptyTitle:'No farm stock yet',columns:[{label:'Farm',value:f=>f,html:f=>`<b>${esc(f)}</b>`}].concat(S.ITEMS.map(it=>({label:it+' used / left',num:1,value:f=>cell(f,it).used,html:f=>{const c=cell(f,it);return `<b>${num(c.used)}</b> <small>/ ${num(c.left)}</small>`}}))),footer:rs=>['All Farms'].concat(S.ITEMS.map(it=>`${num(rs.reduce((s,f)=>s+cell(f,it).used,0))} / ${num(rs.reduce((s,f)=>s+cell(f,it).left,0))}`))})}
function allFarmsStock(el){const S=Store,db=S.db,{icon,num,money,esc}=U;
 el.innerHTML=`<div class="page-head"><div><p><b>All Farms</b> — combined stock record. Example: total DAP used across every farm, with each farm’s share below.</p></div><div class="row">${scopeBar()}</div></div>
 <div class="grid g4">${S.ITEMS.map((it,i)=>{const b=S.stockByFarm(it);return `<div class="card kpi ${['','gold','blue','red'][i]}"><div class="ic">${icon('sprout')}</div><div><div class="lbl">${it} used — all farms</div><div class="val">${num(b.reduce((s,x)=>s+x.used,0))}</div><div class="sub">${num(b.reduce((s,x)=>s+x.left,0))} remaining at farms</div></div></div>`}).join('')}</div>
 <div class="card" style="margin-top:16px"><div class="card-head"><h3>Combined stock — all farms</h3></div><div id="sum"></div></div>
 <div class="card" style="margin-top:16px"><div class="card-head"><div><h3>Per-farm breakdown</h3><p>Used / remaining per item</p></div></div><div id="bd"></div></div>
 <div class="card" style="margin-top:16px"><div class="card-head"><h3>Usage history — all farms</h3></div><div id="h"></div></div>`;
 stockSummary(document.getElementById('sum'));breakdown(document.getElementById('bd'));
 U.dataTable(document.getElementById('h'),{rows:db.usage.slice().reverse(),filters:[{label:'Farm',options:[...new Set(db.usage.map(x=>x.farm))],get:x=>x.farm},{label:'Item',options:S.ITEMS,get:x=>x.item}],footer:rs=>['Total','','','',num(rs.reduce((s,x)=>s+x.qty,0)),money(rs.reduce((s,x)=>s+x.qty*x.rate,0)),''],columns:[{label:'Farm',value:x=>x.farm},{label:'Item',value:x=>x.item},{label:'Target',value:x=>targetName(x)},{label:'Crop',value:x=>x.crop},{label:'Qty',num:1,value:x=>x.qty},{label:'Cost',num:1,value:x=>x.qty*x.rate,html:x=>money(x.qty*x.rate)},{label:'Status',value:x=>x.approved?'Approved':'Pending',html:statusBadge}]});
 bindScope(el)}
window.App={stockSummary,breakdown,allFarmsStock,editSalary,scopeBar,scope,bindScope,NAV,OFFICE,ALL,user,isOffice,isOwner,visibleFarms,ctx,farm,season,login,logout,boot,refresh:render,print,docHead,statusBadge,targetName,hariOpts,targetOpts,applyTheme,tr};
})();
