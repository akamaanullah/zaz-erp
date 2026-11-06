(function(){'use strict';const {$,$$,esc,icon,initials}=U;App.applyTheme();
if(App.user()){location.replace('dashboard.html');return}
const users=Store.db.users.filter(u=>u.active);let sel=users[0]?.id;
const selEl=$('#loginUser'),hint=$('#roleHint');
selEl.innerHTML=users.map(u=>`<option value="${esc(u.id)}">${esc(u.name)} — ${esc(Store.ROLES[u.role])}${u.farm?' ('+esc(u.farm)+')':''}</option>`).join('');
const upd=()=>{const u=users.find(x=>x.id===selEl.value);sel=u?.id;hint.textContent=u?`@${u.username} • ${u.farm||'All farms'} access`:''};selEl.onchange=()=>{upd();$('#pin').focus()};upd();
$('#loginForm').onsubmit=e=>{e.preventDefault();const pin=$('#pin').value;if(!App.login(sel,pin)){$('#loginMsg').textContent='Incorrect demo PIN or inactive account. Hint: 1234';$('#pin').select();return}location.href='dashboard.html'};
$('#resetBtn').onclick=async()=>{if(await U.confirmBox('Reset all demo data (employees, farms, ledgers, audit history) to the original sample?',{ok:'Reset demo'})){Store.reset();location.reload()}};
})();
