/* ZAZ ERP — server-backed store. The browser renders the same screens; MySQL is the source of truth. */
(function(){
'use strict';
const meta=n=>{const el=document.querySelector('meta[name="'+n+'"]');return el?el.content:''};
window.ZAZ_BASE=meta('zaz-base');
window.ZAZ_CSRF=meta('zaz-csrf');
const base=window.ZAZ_BASE||'';
const csrf=window.ZAZ_CSRF||'';
const UNITS=['per acre','per hour','per bag','per trip','per litre','per day','lump sum'];
const WORK_TYPES=['Land preparation','Ploughing / Gobble','Sowing / Planting','Irrigation','Fertilizer application','Pesticide spray','Weeding','Harvesting','Threshing','Transport','Maintenance / Repairs','Other'];
const PAY_TYPES=['Monthly salary','Daily wage','Contract'];
const MAUND_KG=40;
const ROLES={owner:'Owner',office:'Office',supervisor:'Farm Supervisor',accountant:'Farm Accountant'};
let db={users:[],farms:[],haris:[],rules:{},categories:[],receipts:[],transfers:[],usage:[],expenses:[],sales:[],advances:[],audit:[],dailyWork:[],salaryPayments:[],settings:{theme:'light',language:'en'},seasons:['Kharif 2026','Rabi 2026-27'],items:['DAP','Urea','Zinc','Pesticide'],itemRates:{},godown:{},me:null,access:[],abilities:[]};
const queue=[];
let loaded=false;
const items=()=>db.items&&db.items.length?db.items:['DAP','Urea','Zinc','Pesticide'];
const seasons=()=>db.seasons&&db.seasons.length?db.seasons:['Kharif 2026','Rabi 2026-27'];
const farmNames=()=>(db.farms||[]).map(f=>f.name);
function ready(fn){if(loaded)fn();else queue.push(fn)}
async function load(){
 const res=await fetch(base+'/api/bootstrap',{headers:{'Accept':'application/json'},credentials:'same-origin'});
 if(res.status===401){location.replace(base+'/login');return}
 const data=await res.json().catch(()=>({}));
 if(!res.ok||!data.db){console.error(data);return}
 db=data.db;loaded=true;queue.splice(0).forEach(fn=>{try{fn()}catch(err){console.error(err)}});
}
async function api(action,payload){
 const res=await fetch(base+'/api/'+action,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-Token':csrf},body:JSON.stringify(payload||{})});
 const data=await res.json().catch(()=>({error:'Request failed'}));
 if(!res.ok||data.ok===false)throw Error(data.error||'Request failed');
 if(data.db)db=data.db;
 return data;
}
const sessionUser=()=>db.me||null;
const getHari=id=>(db.haris||[]).find(h=>String(h.id)===String(id));
const category=name=>(db.categories||[]).find(c=>c.name===name);
const kgOf=(unit,w)=>unit==='maund'?Number(w)*MAUND_KG:Number(w);
const rate=item=>{if(db.itemRates&&db.itemRates[item]!=null)return Number(db.itemRates[item]);const rs=(db.receipts||[]).filter(x=>x.item===item);return rs.length?Number(rs[rs.length-1].rate):0};
const godownReceived=item=>db.godown&&db.godown[item]?Number(db.godown[item].received):0;
const godownSent=item=>db.godown&&db.godown[item]?Number(db.godown[item].sent):0;
const godownQty=item=>db.godown&&db.godown[item]?Number(db.godown[item].available):0;
async function rows(query){
 const p=new URLSearchParams();
 Object.entries(query||{}).forEach(([k,v])=>{if(v!=null&&v!=='')p.set(k,v)});
 const res=await fetch(base+'/api/table?'+p.toString(),{credentials:'same-origin',headers:{Accept:'application/json'}});
 const data=await res.json().catch(()=>({error:'List failed'}));
 if(!res.ok||data.ok===false)throw Error(data.error||'List failed');
 return data;
}
async function allRows(query){
 let page=1,out=[],total=0,guard=0;
 do{
  const data=await rows(Object.assign({},query,{page,per:50}));
  out=out.concat(data.rows||[]);total=data.total||0;page++;guard++;
 }while(out.length<total&&guard<100);
 return out;
}
async function ledgerDetail(hariId,approvedOnly){
 const res=await fetch(base+'/api/ledger?hari='+encodeURIComponent(hariId)+'&approved='+(approvedOnly?'1':'0'),{credentials:'same-origin',headers:{Accept:'application/json'}});
 const data=await res.json().catch(()=>({error:'Ledger failed'}));
 if(!res.ok||data.ok===false)throw Error(data.error||'Ledger failed');
 return data.ledger;
}
const stat=farm=>{const b=db.summary&&db.summary.byFarm&&db.summary.byFarm[farm];return Object.assign({sales:0,saleCount:0,saleKg:0,hariShare:0,costs:0,usageCount:0,expenseCount:0,advances:0,advanceCount:0,pending:0},b||{})};
const stockCell=(farm,item)=>{const c=db.summary&&db.summary.stock&&db.summary.stock[farm]&&db.summary.stock[farm][item];return c||{sent:0,received:0,used:0,left:0,cost:0}};
const farmQty=(farm,item)=>Number(stockCell(farm,item).left)||0;
function stockByFarm(item){return farmNames().map(f=>{const c=stockCell(f,item);return {farm:f,sent:Number(c.sent)||0,received:Number(c.received)||0,used:Number(c.used)||0,left:Number(c.left)||0,cost:Number(c.cost)||0}})}
const allExpenses=()=>[];
const split=x=>x.target==='seri'?0:Number(x.share);
function ledger(h,approvedOnly){const row=(db.summary&&db.summary.hari||[]).find(x=>String(x.id)===String(h.id));const bag=row?(approvedOnly?row.final:row.preview):{gross:0,ded:0,advance:0,balance:0};return {gross:Number(bag.gross)||0,ded:Number(bag.ded)||0,advance:Number(bag.advance)||0,balance:Number(bag.balance)||0,sales:[],cost:[],adv:[]}}
const hariAcres=h=>h.physicalAcres!=null?Number(h.physicalAcres):(h.alloc||[]).reduce((s,a)=>s+Number(a.acres),0);
function pending(farm){const names=farm?[farm]:farmNames();const n=names.reduce((s,f)=>s+Number(stat(f).pending||0),0);return {length:n}}
const can=ability=>(db.abilities||[]).includes(ability);
window.Store={KEY:'zaz_erp_mvc',UNITS,WORK_TYPES,PAY_TYPES,MAUND_KG,ROLES,get SEASONS(){return seasons()},get ITEMS(){return items()},get FARMS(){return farmNames()},get db(){return db},api,ready,load,rows,allRows,ledgerDetail,can,sessionUser,getHari,godownQty,godownReceived,godownSent,farmQty,stockCell,stat,rate,allExpenses,split,ledger,hariAcres,pending,category,stockByFarm,kgOf,save(){},log(){}};
load();
})();
