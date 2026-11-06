Store.ready(function () {
 App.boot({id: 'settings', title: 'Settings', noCtx: true, render(el) {
  const {esc, icon} = U, S = Store, db = S.db, u = App.user();
  el.innerHTML = `<div class="grid g2"><div class="card"><div class="card-head"><h3>Appearance & language</h3></div><div class="form-grid">${U.field({name:'theme',label:'Theme',options:[['light','Light'],['dark','Dark']],value:db.settings.theme})}${U.field({name:'lang',label:'Menu language',options:[['en','English'],['ur','اردو'],['sd','سنڌي']],value:db.settings.language})}</div><p class="hint">English / Urdu / Sindhi menu labels. Hari ledger statements are always printed in Sindhi.</p></div>
 <div class="card"><div class="card-head"><h3>My account</h3></div><ul class="list"><li><b style="width:110px">Name</b>${esc(u.name)}</li><li><b style="width:110px">Username</b>@${esc(u.username)}</li><li><b style="width:110px">Role</b>${esc(S.ROLES[u.role])}</li><li><b style="width:110px">Farm</b>${esc(u.farm||'All farms')}</li></ul></div>
 <div class="card"><div class="card-head"><h3>Access</h3></div><p>Owner and Office see every farm. Supervisor and Accountant stay on their assigned farm. Hari and Kamdar do not sign in.</p><p><b>Approval:</b> entries made by farm staff stay pending until Owner or Office approves them. Owner and Office entries are approved when they are saved. Approving an entry again does not move stock a second time.</p><div class="alert">${icon('info')}<span>Records are stored in the farm database for this server. Offline sync and the Android app are later milestones.</span></div></div>
 ${['owner','office'].includes(u.role)?`<div class="card"><div class="card-head"><h3>Authenticator</h3></div><p id="totpBox" class="muted"></p><div class="row" id="totpActions"></div></div>`:''}
 ${S.can('admin.backup')?`<div class="card"><div class="card-head"><h3>Data backup</h3></div><p class="muted">Export business records to JSON, or restore a backup made by this application. Passwords are not included. The audit log is kept.</p><div class="row"><button class="btn" id="exp">${icon('download')} Export JSON backup</button><label class="btn secondary" style="margin:0">${icon('upload')} Import backup<input type="file" id="imp" accept="application/json" class="hide"></label>${App.isOwner()?`<button class="btn danger" id="rst">Reset demo data</button>`:''}</div></div>`:''}</div>`;
  el.querySelector('[name=theme]').onchange = async e => {
   try { await S.api('settings/prefs', {theme: e.target.value, language: db.settings.language || 'en'}); }
   catch (err) { U.toast(err.message, 'error'); return; }
   App.applyTheme(); App.refresh();
  };
  el.querySelector('[name=lang]').onchange = async e => {
   try { await S.api('settings/prefs', {theme: db.settings.theme || 'light', language: e.target.value}); }
   catch (err) { U.toast(err.message, 'error'); return; }
   App.refresh();
  };
  const exp = document.getElementById('exp');
  if (exp) exp.onclick = () => { window.location = App.url('api/backup'); };
  const imp = document.getElementById('imp');
  if (imp) imp.onchange = e => {
   const f = e.target.files[0]; if (!f) return;
   const r = new FileReader();
   r.onload = async () => {
    try {
     const d = JSON.parse(r.result);
     if (d.format !== 'zaz-erp-mvc-1') throw Error('Not a ZAZ ERP backup');
     if (!await U.confirmBox('Replace current business records with this backup? Login accounts are kept.', {ok: 'Import'})) return;
     await S.api('settings/import', d); U.toast('Backup imported'); App.refresh();
    } catch (err) { U.toast(err.message, 'error'); }
   };
   r.readAsText(f);
  };
  const rs = document.getElementById('rst');
  if (rs) rs.onclick = async () => {
   if (!await U.confirmBox('Reset demo farms, ledgers and stock to the sample records? Login accounts stay as they are.', {ok: 'Reset demo'})) return;
   await S.api('settings/reset', {}); U.toast('Demo business data reset'); App.refresh();
  };
  const totpBox = document.getElementById('totpBox');
  if (totpBox) {
   const actions = document.getElementById('totpActions');
   if (!db.totpAvailable) {
    totpBox.textContent = 'Authenticator stays off until the server has ZAZ_APP_KEY set. No codes are generated without it.';
   } else if (db.me.totpEnabled) {
    totpBox.textContent = 'Authenticator is on for this account. A code is required after the password.';
    actions.innerHTML = '<button class="btn secondary" id="totpOff" type="button">Turn off</button>';
    document.getElementById('totpOff').onclick = () => U.modal({
     title: 'Turn off authenticator', submit: 'Turn off',
     body: U.formGrid([{name: 'code', label: 'Current authenticator code', attrs: 'inputmode="numeric" maxlength="6" autocomplete="one-time-code"'}]),
     onSubmit: async d => { await S.api('totp/disable', {code: d.code}); U.toast('Authenticator turned off'); App.refresh(); }
    });
   } else {
    totpBox.textContent = 'Optional for Owner and Office. Farm staff are not asked for a code.';
    actions.innerHTML = '<button class="btn" id="totpOn" type="button">Set up authenticator</button>';
    document.getElementById('totpOn').onclick = async () => {
     const started = await S.api('totp/start', {});
     await U.modal({
      title: 'Confirm authenticator', submit: 'Confirm code',
      body: '<p>Add this key to your authenticator app, then enter the 6-digit code. The key is shown only now.</p><p><code>' + U.esc(started.secret) + '</code></p>' + U.formGrid([{name: 'code', label: '6-digit code', attrs: 'inputmode="numeric" maxlength="6" autocomplete="one-time-code"'}]),
      onSubmit: async d => {
       const done = await S.api('totp/confirm', {code: d.code});
       await U.modal({title: 'Recovery codes', submit: 'I have saved them', cancel: '', body: '<p>Save these codes somewhere private. Each code works once. They will not be shown again.</p><pre>' + U.esc((done.recoveryCodes || []).join('\n')) + '</pre>'});
       App.refresh();
      }
     });
    };
   }
  }
 }});
});
