/* ZAZ ERP — server-backed store. The browser renders the same screens; MySQL is the source of truth. */
(function(){
'use strict';
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
const farmQty=(farm,item)=>(db.transfers||[]).filter(x=>x.farm===farm&&x.item===item&&x.received!=null).reduce((s,x)=>s+Number(x.received),0)-(db.usage||[]).filter(x=>x.farm===farm&&x.item===item).reduce((s,x)=>s+Number(x.qty),0);
function stockByFarm(item){return farmNames().map(f=>{const rec=(db.transfers||[]).filter(x=>x.farm===f&&x.item===item&&x.received!=null).reduce((s,x)=>s+Number(x.received),0),sent=(db.transfers||[]).filter(x=>x.farm===f&&x.item===item).reduce((s,x)=>s+Number(x.qty),0),used=(db.usage||[]).filter(x=>x.farm===f&&x.item===item).reduce((s,x)=>s+Number(x.qty),0);return {farm:f,sent,received:rec,used,left:rec-used,cost:(db.usage||[]).filter(x=>x.farm===f&&x.item===item).reduce((s,x)=>s+Number(x.qty)*Number(x.rate),0)}})}
const allExpenses=()=>[...(db.usage||[]).map(x=>({...x,category:x.item,amount:x.amount!=null?Number(x.amount):Number(x.qty)*Number(x.rate),kind:'usage'})),...(db.expenses||[]).map(x=>({...x,amount:Number(x.amount),kind:'expense'}))];
const split=x=>x.target==='seri'?0:Number(x.share);
function ledger(h,approvedOnly){const ok=x=>!approvedOnly||x.approved;const sales=(db.sales||[]).filter(x=>String(x.hariId)===String(h.id)&&ok(x)),cost=allExpenses().filter(x=>String(x.hariId)===String(h.id)&&ok(x)),adv=(db.advances||[]).filter(x=>String(x.hariId)===String(h.id)&&ok(x));
 const gross=sales.reduce((s,x)=>s+Number(x.amount)/2,0),ded=cost.reduce((s,x)=>s+Number(x.amount)*split(x)/100,0),advance=adv.reduce((s,x)=>s+Number(x.amount),0);
 return {gross,ded,advance,balance:gross-ded-advance,sales,cost,adv}}
const hariAcres=h=>h.physicalAcres!=null?Number(h.physicalAcres):(h.alloc||[]).reduce((s,a)=>s+Number(a.acres),0);
const pending=farm=>[...(db.usage||[]).map(x=>({...x,_t:'usage'})),...(db.expenses||[]).map(x=>({...x,_t:'expenses'})),...(db.sales||[]).map(x=>({...x,_t:'sales'})),...(db.advances||[]).map(x=>({...x,_t:'advances'}))].filter(x=>!x.approved&&(!farm||x.farm===farm));
const can=ability=>(db.abilities||[]).includes(ability);
window.Store={KEY:'zaz_erp_mvc',UNITS,WORK_TYPES,PAY_TYPES,MAUND_KG,ROLES,get SEASONS(){return seasons()},get ITEMS(){return items()},get FARMS(){return farmNames()},get db(){return db},api,ready,load,can,sessionUser,getHari,godownQty,godownReceived,godownSent,farmQty,rate,allExpenses,split,ledger,hariAcres,pending,category,stockByFarm,kgOf,save(){},log(){}};
load();
})();
