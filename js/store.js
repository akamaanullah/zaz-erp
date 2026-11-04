/* ZAZ ERP — localStorage data store + domain logic (ported from Access Demo + V6 prototype) */
(function(){
'use strict';
const KEY='zaz_erp_static_v1';
const FARMS=['68 Mori','Juna Mori','Village','Sugar Mill','Jokho','Tando Adam','Zareen','Dalda Mill','Mehran','Koromal Dewan','Jahaniya','Other Farm'];
const ITEMS=['DAP','Urea','Zinc','Pesticide'];
const UNITS=['per acre','per hour','per bag','per trip','per litre','per day','lump sum'];
const DEFAULT_CATS=[['DAP','per bag',12000,50],['Urea','per bag',4500,50],['Zinc','per bag',2200,50],['Pesticide','per litre',3500,50],
 ['Tractor Preparation','per acre',3500,100],['Sugarcane Planting Labour','per acre',6000,100],['Harvest Labour','per acre',5000,100],
 ['Gobble','per acre',2500,100],['Harrow','per acre',2000,100],['Rotavator','per acre',3000,100],['Laser','per hour',4500,50],['Cultivator','per acre',1800,100],
 ['Tractor ploughing','per acre',2800,100],['Threshing','per hour',3500,50],['Diesel','per litre',290,50],['Water / Irrigation','per acre',1500,50],
 ['Pesticide spray','per acre',800,50],['Transport','per trip',6000,50],['Other','lump sum',0,50]];
const WORK_TYPES=['Land preparation','Ploughing / Gobble','Sowing / Planting','Irrigation','Fertilizer application','Pesticide spray','Weeding','Harvesting','Threshing','Transport','Maintenance / Repairs','Other'];
const PAY_TYPES=['Monthly salary','Daily wage','Contract'];
const MAUND_KG=40;
const SEASONS=['Kharif 2026','Rabi 2026-27'];
const ROLES={owner:'Owner',office:'Office',supervisor:'Farm Supervisor',accountant:'Farm Accountant'};
const DEFAULT_RULES={DAP:50,Urea:50,Zinc:50,Pesticide:50,'Tractor Preparation':100,'Sugarcane Planting Labour':100,'Harvest Labour':100,'Other':50};
const acreSeed={'68 Mori':[200,45,'Mori, Sanghar'],'Jahaniya':[150,30,'Jahaniya'],'Zareen':[120,20,'Tando Adam'],'Juna Mori':[90,15,'Mori']};
function seed(){
 const users=[
  {id:'owner',name:'ZAZ Owner',username:'owner',role:'owner',farm:'',active:true,pin:'1234'},
  {id:'office',name:'Main Office',username:'office',role:'office',farm:'',active:true,pin:'1234'},
  {id:'ja1',name:'Jahaniya Accountant 1',username:'jahaniya.a1',role:'accountant',farm:'Jahaniya',active:true,pin:'1234'},
  {id:'ja2',name:'Jahaniya Accountant 2',username:'jahaniya.a2',role:'accountant',farm:'Jahaniya',active:true,pin:'1234'},
  {id:'js1',name:'Jahaniya Supervisor',username:'jahaniya.sup',role:'supervisor',farm:'Jahaniya',active:true,pin:'1234'},
  {id:'zu1',name:'Zareen Supervisor',username:'zareen.sup',role:'supervisor',farm:'Zareen',active:true,pin:'1234'}];
 const farms=FARMS.map(n=>({name:n,acres:acreSeed[n]?.[0]||0,seri:acreSeed[n]?.[1]||0,location:acreSeed[n]?.[2]||'',purchase:'',archived:false}));
 const haris=[
  {id:'h1',farm:'68 Mori',name:'Shabbir Malik',address:'Sample address',alloc:[{crop:'Sugarcane',acres:10,season:'Kharif 2026'},{crop:'Wheat',acres:5,season:'Rabi 2026-27'},{crop:'Mustard',acres:5,season:'Rabi 2026-27'}]},
  {id:'h2',farm:'68 Mori',name:'Ghulam Rasool',address:'Deh Mori',alloc:[{crop:'Cotton',acres:12,season:'Kharif 2026'}]},
  {id:'h3',farm:'Jahaniya',name:'Allah Dino',address:'Goth Jahaniya',alloc:[{crop:'Sugarcane',acres:15,season:'Kharif 2026'}]},
  {id:'h4',farm:'Zareen',name:'Muhammad Hassan',address:'Zareen Village',alloc:[{crop:'Wheat',acres:8,season:'Rabi 2026-27'}]}];
 const tr=(id,farm,item,qty,received,date)=>({id,farm,item,qty,received,date,receivedBy:received!=null?'supervisor':undefined,receivedAt:received!=null?date:undefined});
 const S='Kharif 2026';
 return {version:1,users,farms,haris,rules:{...DEFAULT_RULES},
  receipts:[{id:'r1',item:'Urea',qty:500,rate:4500,date:'2026-11-02'},{id:'r2',item:'DAP',qty:500,rate:12000,date:'2026-11-02'},{id:'r3',item:'Zinc',qty:120,rate:2200,date:'2026-10-20'},{id:'r4',item:'Pesticide',qty:80,rate:3500,date:'2026-10-18'}],
  transfers:[tr('t1','68 Mori','Urea',100,98,'2026-10-25'),tr('t2','68 Mori','DAP',60,60,'2026-10-25'),tr('t3','Jahaniya','Urea',80,80,'2026-10-27'),tr('t4','Zareen','DAP',40,null,'2026-11-01')],
  usage:[{id:'u1',farm:'68 Mori',season:S,item:'Urea',qty:20,rate:4500,target:'h1',hariId:'h1',crop:'Sugarcane',share:50,by:'accountant',approved:true},
   {id:'u2',farm:'68 Mori',season:S,item:'DAP',qty:10,rate:12000,target:'h2',hariId:'h2',crop:'Cotton',share:50,by:'accountant',approved:false},
   {id:'u3',farm:'68 Mori',season:S,item:'Urea',qty:15,rate:4500,target:'seri',hariId:null,crop:'Seri',share:0,by:'supervisor',approved:true},
   {id:'u4',farm:'Jahaniya',season:S,item:'Urea',qty:25,rate:4500,target:'h3',hariId:'h3',crop:'Sugarcane',share:50,by:'accountant',approved:false}],
  expenses:[{id:'e1',farm:'68 Mori',season:S,category:'Tractor Preparation',target:'h1',hariId:'h1',crop:'Sugarcane',amount:35000,share:100,by:'accountant',approved:true},
   {id:'e2',farm:'68 Mori',season:S,category:'Harvest Labour',target:'h2',hariId:'h2',crop:'Cotton',amount:28000,share:100,by:'accountant',approved:false},
   {id:'e3',farm:'68 Mori',season:S,category:'Other',target:'seri',hariId:null,crop:'Seri',amount:18000,share:0,by:'office',approved:true},
   {id:'e4',farm:'Jahaniya',season:S,category:'Sugarcane Planting Labour',target:'h3',hariId:'h3',crop:'Sugarcane',amount:42000,share:100,by:'accountant',approved:true}],
  sales:[{id:'s1',farm:'68 Mori',season:S,target:'h1',hariId:'h1',crop:'Sugarcane',amount:850000,by:'accountant',approved:true},
   {id:'s2',farm:'68 Mori',season:S,target:'h2',hariId:'h2',crop:'Cotton',amount:520000,by:'accountant',approved:false},
   {id:'s3',farm:'68 Mori',season:S,target:'seri',hariId:null,crop:'Sugarcane',amount:1250000,by:'office',approved:true},
   {id:'s4',farm:'Jahaniya',season:S,target:'h3',hariId:'h3',crop:'Sugarcane',amount:960000,by:'accountant',approved:true},
   {id:'s5',farm:'Zareen',season:S,target:'seri',hariId:null,crop:'Wheat',amount:300000,by:'office',approved:true}],
  advances:[{id:'a1',farm:'68 Mori',season:S,hariId:'h1',reason:'Wedding expense',amount:50000,by:'accountant',approved:true},
   {id:'a2',farm:'68 Mori',season:S,hariId:'h2',reason:'Medical',amount:15000,by:'accountant',approved:false},
   {id:'a3',farm:'Jahaniya',season:S,hariId:'h3',reason:'Household',amount:30000,by:'accountant',approved:true}],
  audit:[{at:new Date().toLocaleString(),by:'System',msg:'Demo data seeded'}],
  settings:{theme:'light',language:'en'}};
}
let db;try{db=JSON.parse(localStorage.getItem(KEY))}catch(e){db=null}
if(!db||!Array.isArray(db.users)||!Array.isArray(db.farms))db=seed();
const base=seed();['haris','receipts','transfers','usage','expenses','sales','advances','audit'].forEach(k=>{if(!Array.isArray(db[k]))db[k]=base[k]});
db.rules=db.rules||{...DEFAULT_RULES};const _v=db.version||1;migrate(db);db.settings=db.settings||{theme:'light',language:'en'};
const save=()=>{try{localStorage.setItem(KEY,JSON.stringify(db))}catch(e){console.warn('Save failed',e)}};
if(_v<2)save();
const sessionUser=()=>{const id=localStorage.getItem(KEY+'_session');return db.users.find(u=>u.id===id&&u.active)||null};
function log(msg){const u=sessionUser();db.audit.unshift({at:new Date().toLocaleString(),by:u?u.username:'System',msg});db.audit=db.audit.slice(0,300);save()}
/* ---- v2 migration: adds sale units, categories with rates, daily work, salaries ---- */
function migrate(d){if((d.version||1)>=2)return d;const b=seed();const H=36e5,now=Date.now(),iso=h=>new Date(now-h*H).toISOString();
 const saleSeed={s1:['maund',250,3400],s2:['maund',65,8000],s3:['maund',500,2500],s4:['maund',300,3200],s5:['kg',3000,100]};
 d.sales.forEach(x=>{if(x.unit)return;const k=saleSeed[x.id],bs=b.sales.find(y=>y.id===x.id);if(k&&bs&&bs.amount===x.amount){x.unit=k[0];x.weight=k[1];x.rate=k[2]}else{x.unit=x.unit||null;x.weight=x.weight??null;x.rate=x.rate??null}x.kg=x.weight!=null?(x.unit==='maund'?x.weight*MAUND_KG:x.weight):null});
 const expSeed={e1:[10,3500,'per acre'],e2:[5.6,5000,'per acre'],e3:[1,18000,'lump sum'],e4:[7,6000,'per acre']};
 d.expenses.forEach(x=>{if(x.qty!=null)return;const k=expSeed[x.id];if(k&&k[0]*k[1]===x.amount){x.qty=k[0];x.rate=k[1];x.unit=k[2]}});
 if(!Array.isArray(d.categories)){d.categories=DEFAULT_CATS.map(([name,unit,rate,share])=>({name,unit,rate,share:d.rules[name]??share}));Object.keys(d.rules).forEach(k=>{if(!d.categories.some(c=>c.name===k))d.categories.push({name:k,unit:'lump sum',rate:0,share:d.rules[k]})})}
 d.categories.forEach(c=>d.rules[c.name]=c.share);
 const sal={owner:[0,'Monthly salary'],office:[85000,'Monthly salary'],ja1:[60000,'Monthly salary'],ja2:[55000,'Monthly salary'],js1:[45000,'Monthly salary'],zu1:[42000,'Monthly salary']};
 d.users.forEach(u=>{if(u.salary==null){u.salary=(sal[u.id]||[0])[0];u.payType=(sal[u.id]||[0,'Monthly salary'])[1]}});
 const m=new Date().toISOString().slice(0,7);
 if(!Array.isArray(d.salaryPayments))d.salaryPayments=[{id:'sp1',userId:'office',month:m,kind:'Salary',amount:85000,status:'Paid',date:iso(48).slice(0,10),note:'Bank transfer'},{id:'sp2',userId:'ja1',month:m,kind:'Advance',amount:10000,status:'Paid',date:iso(120).slice(0,10),note:'Medical'},{id:'sp3',userId:'js1',month:m,kind:'Salary',amount:45000,status:'Pending',date:iso(2).slice(0,10),note:''}];
 if(!Array.isArray(d.dailyWork))d.dailyWork=[
  {id:'w1',farm:'Jahaniya',date:iso(3).slice(0,10),at:iso(3),type:'Irrigation',desc:'Sugarcane block 3 & 4 watered from tubewell',resources:'2 labour, tubewell',acres:15,hours:6,notes:'',by:'jahaniya.sup'},
  {id:'w2',farm:'Jahaniya',date:iso(7).slice(0,10),at:iso(7),type:'Fertilizer application',desc:'Urea top dressing on sugarcane',resources:'4 labour',acres:10,hours:5,notes:'10 bags used',by:'jahaniya.sup'},
  {id:'w3',farm:'Zareen',date:iso(5).slice(0,10),at:iso(5),type:'Ploughing / Gobble',desc:'Gobble on wheat field before sowing',resources:'Tractor + gobble, 1 driver',acres:8,hours:7,notes:'',by:'zareen.sup'},
  {id:'w4',farm:'68 Mori',date:iso(10).slice(0,10),at:iso(10),type:'Pesticide spray',desc:'Cotton spray against whitefly',resources:'3 labour, spray machine',acres:12,hours:4,notes:'',by:'office'},
  {id:'w5',farm:'Zareen',date:iso(30).slice(0,10),at:iso(30),type:'Weeding',desc:'Manual weeding in mustard',resources:'6 labour',acres:4,hours:8,notes:'Older than 24h — for history',by:'zareen.sup'}];
 d.version=2;return d}
const kgOf=(unit,w)=>unit==='maund'?Number(w)*MAUND_KG:Number(w);
const category=name=>db.categories.find(c=>c.name===name);
function stockByFarm(item){return FARMS.concat(db.farms.map(f=>f.name).filter(n=>!FARMS.includes(n))).map(f=>{const rec=db.transfers.filter(x=>x.farm===f&&x.item===item&&x.received!=null).reduce((s,x)=>s+x.received,0),sent=db.transfers.filter(x=>x.farm===f&&x.item===item).reduce((s,x)=>s+x.qty,0),used=db.usage.filter(x=>x.farm===f&&x.item===item).reduce((s,x)=>s+x.qty,0);return {farm:f,sent,received:rec,used,left:rec-used,cost:db.usage.filter(x=>x.farm===f&&x.item===item).reduce((s,x)=>s+x.qty*x.rate,0)}})}
/* ---- domain logic (identical rules to V6) ---- */
const getHari=id=>db.haris.find(h=>h.id===id);
const godownQty=item=>db.receipts.filter(x=>x.item===item).reduce((s,x)=>s+x.qty,0)-db.transfers.filter(x=>x.item===item).reduce((s,x)=>s+x.qty,0);
const farmQty=(farm,item)=>db.transfers.filter(x=>x.farm===farm&&x.item===item&&x.received!=null).reduce((s,x)=>s+x.received,0)-db.usage.filter(x=>x.farm===farm&&x.item===item).reduce((s,x)=>s+x.qty,0);
const rate=item=>{const rs=db.receipts.filter(x=>x.item===item);return rs.length?rs[rs.length-1].rate:0};
const allExpenses=()=>[...db.usage.map(x=>({...x,category:x.item,amount:x.qty*x.rate,kind:'usage'})),...db.expenses.map(x=>({...x,kind:'expense'}))];
const split=x=>x.target==='seri'?0:Number(x.share);
function ledger(h){const sales=db.sales.filter(x=>x.hariId===h.id),cost=allExpenses().filter(x=>x.hariId===h.id),adv=db.advances.filter(x=>x.hariId===h.id),
 gross=sales.reduce((s,x)=>s+x.amount/2,0),ded=cost.reduce((s,x)=>s+x.amount*split(x)/100,0),advance=adv.reduce((s,x)=>s+x.amount,0);
 return {gross,ded,advance,balance:gross-ded-advance,sales,cost,adv}}
const hariAcres=h=>h.alloc.reduce((s,a)=>s+Number(a.acres),0);
const pending=farm=>[...db.usage.map(x=>({...x,_t:'usage'})),...db.expenses.map(x=>({...x,_t:'expenses'})),...db.sales.map(x=>({...x,_t:'sales'})),...db.advances.map(x=>({...x,_t:'advances'}))].filter(x=>!x.approved&&(!farm||x.farm===farm));
function reset(){db=seed();migrate(db);window.Store.db=db;save()}
window.Store={UNITS,DEFAULT_CATS,WORK_TYPES,PAY_TYPES,MAUND_KG,migrate,kgOf,category,stockByFarm,KEY,FARMS,ITEMS,SEASONS,ROLES,DEFAULT_RULES,get db(){return db},set db(v){db=v},save,log,sessionUser,getHari,godownQty,farmQty,rate,allExpenses,split,ledger,hariAcres,pending,reset,seed};
})();
