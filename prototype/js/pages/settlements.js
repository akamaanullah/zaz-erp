/* Hari individual ledger (Sindhi RTL) + final settlement — logic identical to V6 printLedger */
App.boot({id:'settlements',title:'Hari Ledger & Settlement',render(el){const {esc,icon,money,num}=U,S=Store,db=S.db,fs=App.farm();const haris=db.haris.filter(h=>h.farm===fs);
 let selId=sessionStorage.getItem('zaz_sel_hari');if(!haris.some(h=>h.id===selId))selId=haris[0]?.id;
 el.innerHTML=`<div class="page-head"><div><p>Hari-wise settlement for <b>${esc(fs)}</b>: crop income (50% share), itemized expenses, personal advances and final balance.</p></div></div>
 <div class="card"><div class="card-head"><h3>Hari-wise settlement</h3></div><div id="t"></div><p class="hint">Pending entries are included in preview, but final statement must use office-approved records. No cash payment/recovery module in this demo.</p></div>
 <div class="card" style="margin-top:16px" id="detail"></div>`;
 U.dataTable(document.getElementById('t'),{rows:haris.map(h=>({h,l:S.ledger(h)})),emptyTitle:'No Hari on this farm',emptyText:'Add a Hari first.',footer:rs=>['Total',money(rs.reduce((s,r)=>s+r.l.gross,0)),money(rs.reduce((s,r)=>s+r.l.ded,0)),money(rs.reduce((s,r)=>s+r.l.advance,0)),money(rs.reduce((s,r)=>s+r.l.balance,0)),''],columns:[
  {label:'Hari',value:r=>r.h.name,html:r=>`<b>${esc(r.h.name)}</b>`},{label:'Gross 50% share',num:1,value:r=>r.l.gross,html:r=>money(r.l.gross)},{label:'Hari expenses',num:1,value:r=>r.l.ded,html:r=>money(r.l.ded)},{label:'Advances',num:1,value:r=>r.l.advance,html:r=>money(r.l.advance)},
  {label:'Net due',num:1,value:r=>r.l.balance,html:r=>`<b class="${r.l.balance<0?'neg':'pos'}">${money(r.l.balance)}</b>`},
  {label:'Actions',sort:false,html:r=>`<button class="btn secondary sm" data-view="${r.h.id}">View</button> <button class="btn sm" data-print="${r.h.id}">${icon('print')} Sindhi statement</button>`}]});
 function statement(h){const l=S.ledger(h);
  const expenseRows=l.cost.map(x=>{const pct=S.split(x),portion=x.amount*pct/100;return `<tr><td>${esc(x.category||x.item||'Other')}</td><td>${esc(x.crop||'—')}</td><td>${x.qty!=null?Number(x.qty).toLocaleString('en-PK'):'—'}</td><td>${x.rate!=null?money(x.rate):'—'}</td><td>${money(x.amount)}</td><td>${pct}%</td><td>${money(portion)}</td></tr>`}).join('');
  const saleRows=l.sales.map(x=>`<tr><td>${esc(x.crop||'—')}</td><td>${x.weight!=null?num(x.weight)+' '+(x.unit==='maund'?'مڻ':'ڪلو'):'—'}</td><td>${x.rate!=null?money(x.rate):'—'}</td><td>${money(x.amount)}</td><td>50%</td><td>${money(x.amount/2)}</td></tr>`).join('');
  const advRows=l.adv.map(x=>`<tr><td>${esc(x.reason||x.category||'Advance')}</td><td>${money(x.amount)}</td></tr>`).join('');
  const pending=[...l.sales,...l.cost,...l.adv].some(x=>!x.approved);
  return `<h2 style="text-align:center">هاري جو انفرادي حساب ڪتاب — تفصيلي بيان</h2>
  <p><b>فارم:</b> ${esc(h.farm)} | <b>هاري:</b> ${esc(h.name)} | <b>پتو:</b> ${esc(h.address||'—')}</p>
  <p><b>فصلون ۽ ايڪڙ:</b> ${h.alloc.map(a=>esc(a.crop)+' — '+Number(a.acres)+' ايڪڙ ('+esc(a.season||'')+')').join('، ')}</p>
  <h3>فصلن جي آمدني ۽ هاري جو حصو</h3><div class="table-wrap"><table><thead><tr><th>فصل</th><th>وزن</th><th>اگهه</th><th>ڪل وڪرو</th><th>هاري جو حصو</th><th>هاري جي آمدني</th></tr></thead><tbody>${saleRows||'<tr><td colspan="6">ڪابه آمدني درج نه آهي</td></tr>'}<tr><th>ڪل</th><th>${num(l.sales.reduce((a,x)=>a+(x.kg||0),0)/Store.MAUND_KG)} مڻ</th><th>—</th><th>${money(l.sales.reduce((a,x)=>a+x.amount,0))}</th><th>50%</th><th>${money(l.gross)}</th></tr></tbody></table></div>
  <h3>خرچن جو مڪمل تفصيل — هر شيءِ الڳ</h3><div class="table-wrap"><table><thead><tr><th>خرچ / شيءِ</th><th>فصل</th><th>مقدار</th><th>في يونٽ اگهه</th><th>ڪل خرچ</th><th>هاري ٪</th><th>هاري تي خرچ</th></tr></thead><tbody>${expenseRows||'<tr><td colspan="7">ڪوبه خرچ درج نه آهي</td></tr>'}<tr><th colspan="4">ڪل خرچ</th><th>${money(l.cost.reduce((a,x)=>a+x.amount,0))}</th><th>—</th><th>${money(l.ded)}</th></tr></tbody></table></div>
  <h3>ذاتي اڳواٽ رقم</h3><div class="table-wrap"><table><thead><tr><th>سبب</th><th>رقم</th></tr></thead><tbody>${advRows||'<tr><td colspan="2">ڪابه اڳواٽ رقم نه آهي</td></tr>'}<tr><th>ڪل اڳواٽ</th><th>${money(l.advance)}</th></tr></tbody></table></div>
  <h3>آخري حساب</h3><div class="table-wrap"><table><tbody><tr><td>هاري جي ڪل آمدني (50٪)</td><td>${money(l.gross)}</td></tr><tr><td>هاري جي حصي جا خرچ</td><td>− ${money(l.ded)}</td></tr><tr><td>ذاتي اڳواٽ</td><td>− ${money(l.advance)}</td></tr><tr><th>هاري کي ملندڙ باقي رقم</th><th>${money(l.balance)}</th></tr></tbody></table></div>
  <p style="font-size:13px">${pending?'خبردار: هن بيان ۾ آفيس جي منظوري لاءِ منتظر داخلائون به شامل آهن.':'ڊيمو حساب: حتمي تصديق آفيس ڪندي.'} هي صرف آزمائشي بيان آهي؛ ادائيگيون ۽ پراڻو بيلنس اڃا شامل نه آهن.</p>`}
 function show(id){const h=S.getHari(id),d=document.getElementById('detail');if(!h){d.innerHTML=U.empty('No Hari selected','Add a Hari to see the individual ledger.','book');return}
  sessionStorage.setItem('zaz_sel_hari',id);const l=S.ledger(h);
  d.innerHTML=`<div class="card-head"><div><h3>${esc(h.name)} — individual ledger</h3><p>${esc(h.address||'')} • ${num(S.hariAcres(h))} crop acres</p></div><button class="btn" id="pr">${icon('print')} Print Sindhi statement</button></div>
  <div class="grid g4" style="margin-bottom:16px">${[['Gross 50% share',l.gross,'cash',''],['Hari expenses',l.ded,'receipt','red'],['Advances',l.advance,'hand','gold'],['Net due',l.balance,'book','blue']].map(([a,b,c,k])=>`<div class="card kpi ${k}" style="box-shadow:none"><div class="ic">${icon(c)}</div><div><div class="lbl">${a}</div><div class="val" style="font-size:19px">${money(b)}</div></div></div>`).join('')}</div>
  <div class="rtl" dir="rtl" lang="sd">${statement(h)}</div><div class="sign no-print"></div>`;
  document.getElementById('pr').onclick=()=>printLedger(id)}
 function printLedger(id){const h=S.getHari(id);if(!h)return U.toast('Hari not found','error');App.print(App.docHead('','Hari Ledger • '+h.farm)+statement(h)+'<div class="sign"><div>هاري جي صحيح</div><div>آفيس جي صحيح</div></div>',{rtl:true})}
 U.on(el,'[data-view]',b=>b.onclick=()=>{show(b.dataset.view);document.getElementById('detail').scrollIntoView({behavior:'smooth'})});
 U.on(el,'[data-print]',b=>b.onclick=()=>printLedger(b.dataset.print));
 show(selId)}});
