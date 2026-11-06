/* Progressive enhancement: open server-rendered dialogs, post their forms, and draw charts.
   Screen tables and forms are already in the PHP response. */
(function () {
  'use strict';
  const base = document.querySelector('meta[name="zaz-base"]')?.content || '';
  const csrf = document.querySelector('meta[name="zaz-csrf"]')?.content || '';

  function openTemplate(id) {
    const tpl = document.getElementById(id);
    const host = document.getElementById('dialog-host');
    if (!tpl || !host || tpl.tagName !== 'TEMPLATE') return false;
    host.replaceChildren(tpl.content.cloneNode(true));
    const form = host.querySelector('form');
    if (form) {
      form.classList.add('modal');
      if (!form.querySelector('[data-close]')) {
        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'btn secondary';
        close.setAttribute('data-close', '');
        close.textContent = 'Close';
        close.addEventListener('click', () => {
          host.hidden = true;
          host.replaceChildren();
        });
        form.appendChild(close);
      }
    }
    host.hidden = false;
    return true;
  }

  document.addEventListener('click', (event) => {
    const printBtn = event.target.closest('[data-print]');
    if (printBtn) {
      event.preventDefault();
      document.body.classList.add('printing');
      window.print();
      return;
    }
    const opener = event.target.closest('[data-open]');
    if (!opener) return;
    const id = opener.getAttribute('data-open');
    if (!id || !document.getElementById(id)) return;
    event.preventDefault();
    openTemplate(id);
  });

  document.addEventListener('submit', async (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    const action = form.getAttribute('action') || '';
    if (!action.includes('/api/')) return;
    event.preventDefault();
    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }
    const res = await fetch(action, {
      method: 'POST',
      body: new FormData(form),
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-CSRF-Token': csrf },
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
      window.U.toast(data.error || 'Request failed', 'error');
      return;
    }
    const msg = data.temporaryPassword
      ? 'Employee added. One-time password: ' + data.temporaryPassword
      : 'Saved';
    window.U.toast(msg);
    if (!data.temporaryPassword) {
      const next = new URL(window.location.href);
      next.searchParams.delete('q');
      next.searchParams.delete('page');
      window.location.assign(next.toString());
    }
  });

  document.addEventListener('change', (event) => {
    const el = event.target;
    if (!(el instanceof HTMLElement)) return;
    if (el.matches('#ctxFarm, #ctxSeason, #ctxLang, #fDate, [name=theme], [name=lang]') && el.form) {
      el.form.requestSubmit();
    }
  });

  let searchTimer = 0;
  document.addEventListener('input', (event) => {
    const el = event.target;
    if (!(el instanceof HTMLInputElement)) return;
    const form = el.form;
    if (el.type === 'search' && form && !(form.getAttribute('action') || '').includes('/api/')) {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(() => form.requestSubmit(), 120);
    }
    const tot = form && form.querySelector('#liveTot');
    if (!tot || !form) return;
    const weight = Number(form.querySelector('[name=Weight]')?.value || 0);
    const rate = Number(form.querySelector('[name=Rate]')?.value || 0);
    tot.textContent = 'Rs ' + (weight * rate).toLocaleString('en-PK', { maximumFractionDigits: 2 });
  });

  const menu = document.getElementById('menuBtn');
  if (menu) {
    menu.addEventListener('click', () => document.querySelector('.app')?.classList.toggle('nav-open'));
  }

  const barNode = document.getElementById('bar-data');
  const barHost = document.getElementById('dash-chart');
  if (barNode && barHost && window.U) {
    const rows = JSON.parse(barNode.textContent || '[]');
    if (rows.length) {
      barHost.innerHTML = window.U.barChart(
        [rows.map((row) => row.sales), rows.map((row) => row.costs)],
        { labels: rows.map((row) => row.farm), names: ['Sales', 'Costs'] }
      );
    }
  }
  const donutNode = document.getElementById('donut-data');
  const donutHost = document.getElementById('dash-donut');
  if (donutNode && donutHost && window.U) {
    const bag = JSON.parse(donutNode.textContent || '{}');
    const parts = Object.entries(bag).map(([label, value]) => ({ label, value: Number(value) }));
    donutHost.innerHTML = window.U.donut(parts);
  }

  const file = document.getElementById('imp');
  if (file) {
    file.addEventListener('change', async () => {
      const picked = file.files && file.files[0];
      if (!picked) return;
      let data;
      try {
        data = JSON.parse(await picked.text());
      } catch (err) {
        window.U.toast('Not a ZAZ ERP backup', 'error');
        return;
      }
      if (!data || data.format !== 'zaz-erp-mvc-1' || !data.db || typeof data.db !== 'object') {
        window.U.toast('Not a ZAZ ERP backup', 'error');
        return;
      }
      const send = async () => {
        const res = await fetch(base + '/api/settings/import', {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrf,
          },
          body: JSON.stringify(data),
        });
        const body = await res.json().catch(() => ({}));
        if (!res.ok) {
          window.U.toast(body.error || 'Not a ZAZ ERP backup', 'error');
          return false;
        }
        window.U.toast('Saved');
        window.location.reload();
        return false;
      };
      if (data.users || data.db.users || data.password || data.password_hash || data.db.password_hash) {
        await window.U.modal({
          title: 'Import backup',
          body: '<p style="margin:0">This file mentions accounts. The server will refuse it.</p>',
          submit: 'Import',
          onSubmit: send,
        });
        return;
      }
      await send();
    });
  }
})();
