import { spawn } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { chromium } from 'playwright-core';

const root = path.resolve(import.meta.dirname, '../..');
const php = 'D:\\xampp\\php\\php.exe';
const edge = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const base = 'http://127.0.0.1:8091';
const password = randomBytes(18).toString('base64url');
const art = path.join(process.env.LOCALAPPDATA || '', 'Cursor', 'AgentStores', 'cursor_agent_stores', 'bc-b62b4e5c-18b6-5eb6-87fb-eb5f355853b7', 'files', 'artifacts');
const results = [];

function record(name, ok, error = '') {
  results.push({ name, ok, error: String(error || '').slice(0, 400) });
  console.log(`${ok ? 'PASS' : 'FAIL'} ${name}${error ? ' — ' + String(error).slice(0, 220) : ''}`);
}

async function check(name, fn) {
  try {
    await fn();
    record(name, true);
  } catch (err) {
    record(name, false, err && err.message ? err.message : err);
  }
}

function runPhp(args, env, stdin) {
  return new Promise((resolve, reject) => {
    const child = spawn(php, args, { cwd: root, env: { ...process.env, ...env }, stdio: ['pipe', 'pipe', 'pipe'] });
    let out = '';
    let err = '';
    child.stdout.on('data', (b) => { out += b; });
    child.stderr.on('data', (b) => { err += b; });
    child.on('error', reject);
    child.on('close', (code) => {
      if (code === 0) resolve(out);
      else reject(new Error((err || out || 'php failed').slice(0, 500)));
    });
    if (stdin != null) child.stdin.end(stdin);
    else child.stdin.end();
  });
}

async function launchEdge() {
  return chromium.launch({ executablePath: edge, headless: true });
}

const browserProbe = await launchEdge().catch((err) => err);
if (browserProbe instanceof Error) {
  console.error('BROWSER_LAUNCH_FAILED');
  console.error(browserProbe.stack || browserProbe.message);
  process.exit(2);
}
await browserProbe.close();

await runPhp(['tests/e2e/seed.php'], {
  ZAZ_DB_NAME: 'zaz_agriculture_e2e',
  ZAZ_APP_ENV: 'local',
  ZAZ_ALLOW_DEMO_RESET: '0',
}, password);

const server = spawn(php, ['-S', '127.0.0.1:8091', '-t', 'public', 'public/dev-router.php'], {
  cwd: root,
  env: { ...process.env, ZAZ_DB_NAME: 'zaz_agriculture_e2e', ZAZ_APP_ENV: 'local', ZAZ_ALLOW_DEMO_RESET: '0' },
  stdio: ['ignore', 'pipe', 'pipe'],
});
let serverLog = '';
server.stdout.on('data', (b) => { serverLog += b; });
server.stderr.on('data', (b) => { serverLog += b; });

async function waitUp() {
  for (let i = 0; i < 40; i++) {
    try {
      const res = await fetch(base + '/login');
      if (res.status === 200) return;
    } catch {}
    await new Promise((r) => setTimeout(r, 250));
  }
  throw new Error('PHP server did not answer on 127.0.0.1:8091. ' + serverLog.slice(-400));
}

const pages = {
  owner: ['dashboard', 'farms', 'employees', 'salaries', 'hari', 'godown', 'transfers', 'usage', 'daily', 'expenses', 'sales', 'advances', 'settlements', 'approvals', 'rates', 'rules', 'reports', 'audit', 'settings'],
  office: ['dashboard', 'farms', 'employees', 'salaries', 'hari', 'godown', 'transfers', 'usage', 'daily', 'expenses', 'sales', 'advances', 'settlements', 'approvals', 'rates', 'rules', 'reports', 'audit', 'settings'],
  supervisor: ['dashboard', 'hari', 'transfers', 'usage', 'daily', 'settlements', 'reports', 'audit', 'settings'],
  accountant: ['dashboard', 'hari', 'transfers', 'usage', 'daily', 'expenses', 'sales', 'advances', 'settlements', 'reports', 'audit', 'settings'],
};
const users = {
  owner: 'owner',
  office: 'office',
  supervisor: 'jahaniya.sup',
  accountant: 'jahaniya.a1',
};

try {
  await waitUp();
  const browser = await launchEdge();
  const shots = [];
  async function shot(page, name) {
    await mkdir(art, { recursive: true });
    const file = path.join(art, name);
    await page.screenshot({ path: file, fullPage: false });
    shots.push(file);
  }
  async function login(role) {
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await context.newPage();
    const dialogs = [];
    page.on('dialog', (d) => { dialogs.push(d.message()); return d.dismiss(); });
    page.on('pageerror', (err) => { page._pageError = err.message; });
    await page.goto(base + '/login', { waitUntil: 'domcontentloaded' });
    if (role === 'owner') await shot(page, 'e2e-login-empty.png');
    await page.fill('#username', users[role]);
    await page.fill('#password', password);
    await page.click('form button.btn');
    await page.waitForURL('**/dashboard', { timeout: 15000 });
    await page.waitForSelector('h1', { timeout: 15000 });
    if (page._pageError) throw new Error(page._pageError);
    page._dialogs = dialogs;
    return { context, page };
  }

  await check('login page has username and password and no account dropdown', async () => {
    const context = await browser.newContext();
    const page = await context.newPage();
    await page.goto(base + '/login', { waitUntil: 'domcontentloaded' });
    const select = await page.locator('select').count();
    const user = await page.locator('#username').count();
    const pass = await page.locator('#password').count();
    await context.close();
    if (select !== 0 || user !== 1 || pass !== 1) throw new Error('login controls ' + JSON.stringify({ select, user, pass }));
  });

  for (const role of Object.keys(pages)) {
    const session = await login(role);
    if (role === 'owner') await shot(session.page, 'e2e-dashboard-owner.png');
    for (const id of pages[role]) {
      await check(`${role} opens ${id}`, async () => {
        await session.page.goto(base + '/' + id, { waitUntil: 'domcontentloaded' });
        await session.page.waitForSelector('h1', { timeout: 15000 });
        session.page._pageError = '';
        const title = await session.page.locator('h1').innerText();
        const denied = await session.page.getByText('Access restricted').count();
        if (!title || denied) throw new Error('missing heading or access restricted');
        if (session.page._pageError) throw new Error(session.page._pageError);
      });
    }
    await check(`${role} is refused on a page outside the role`, async () => {
      const blocked = role === 'supervisor' || role === 'accountant' ? '/employees' : '/login';
      if (blocked === '/login') return;
      const res = await session.page.goto(base + blocked, { waitUntil: 'domcontentloaded' });
      const text = await session.page.locator('body').innerText();
      if (res && res.status() < 400 && !text.includes('Access restricted') && !text.includes('Invalid')) {
        throw new Error('outside page was not rejected, status ' + (res ? res.status() : ''));
      }
    });
    await session.context.close();
  }

  await check('accountant validation, create, office sort-then-approve, persistence', async () => {
    const acc = await login('accountant');
    await acc.page.goto(base + '/expenses', { waitUntil: 'domcontentloaded' });
    await acc.page.click('#add');
    await acc.page.waitForSelector('.modal');
    await acc.page.fill('[name=Crop]', '');
    await acc.page.fill('[name=Qty]', '');
    await acc.page.locator('.modal button', { hasText: 'Add expense' }).click();
    if (await acc.page.locator('.modal').count() !== 1) throw new Error('invalid expense closed the dialog');
    const marker = 'E2E crop ' + Date.now().toString().slice(-6);
    await acc.page.fill('[name=Crop]', marker);
    await acc.page.selectOption('[name=Target]', { index: 1 });
    await acc.page.fill('[name=Qty]', '2');
    await acc.page.fill('[name=Rate]', '10');
    await acc.page.locator('.modal button', { hasText: 'Add expense' }).click();
    await acc.page.waitForSelector('.toast', { timeout: 10000 });
    await acc.page.goto(base + '/expenses', { waitUntil: 'domcontentloaded' });
    await acc.page.fill('input[type=search]', marker);
    await acc.page.waitForTimeout(500);
    if (!(await acc.page.locator('tbody').innerText()).includes(marker)) throw new Error('created expense is not in the paged table');
    await shot(acc.page, 'e2e-expense-created.png');
    await acc.context.close();

    const off = await login('office');
    await off.page.goto(base + '/approvals', { waitUntil: 'domcontentloaded' });
    await off.page.selectOption('#ctxFarm', 'Jahaniya');
    await off.page.waitForSelector('#t table, #t .empty', { timeout: 10000 });
    await off.page.fill('#t input[type=search]', marker);
    await off.page.waitForTimeout(400);
    const header = off.page.locator('#t th.sortable').first();
    await header.click();
    await off.page.waitForTimeout(400);
    const approve = off.page.locator('#t button[data-ok]').first();
    if (await approve.count() !== 1) throw new Error('approve button missing after search and sort');
    await approve.click();
    await off.page.waitForSelector('.toast', { timeout: 10000 });
    await shot(off.page, 'e2e-approved-after-sort.png');
    await off.context.close();

    const again = await login('accountant');
    await again.page.goto(base + '/expenses', { waitUntil: 'domcontentloaded' });
    await again.page.fill('input[type=search]', marker);
    await again.page.waitForTimeout(500);
    const body = await again.page.locator('#t').innerText();
    if (!body.includes(marker) || !body.includes('Approved')) throw new Error('approved expense did not persist: ' + body.slice(0, 180));
    await again.context.close();
  });

  await check('dashboard chart, ledger print handler, audit CSV download', async () => {
    const owner = await login('owner');
    await owner.page.goto(base + '/dashboard', { waitUntil: 'domcontentloaded' });
    await owner.page.waitForSelector('text=Sales vs costs', { timeout: 15000 });
    const charts = await owner.page.locator('svg.chart, svg[role="img"]').count();
    if (charts < 1) {
      const note = await owner.page.locator('.content').innerText();
      throw new Error('no chart svg: ' + note.slice(0, 300));
    }
    await owner.page.goto(base + '/settlements', { waitUntil: 'domcontentloaded' });
    await owner.page.selectOption('#ctxFarm', 'Jahaniya');
    await owner.page.waitForSelector('[data-print]', { timeout: 10000 });
    await shot(owner.page, 'e2e-ledger.png');
    await owner.page.evaluate(() => { window.__printed = false; window.print = () => { window.__printed = true; }; });
    await owner.page.click('[data-print]');
    await owner.page.waitForTimeout(800);
    const printed = await owner.page.evaluate(() => window.__printed === true);
    if (!printed) throw new Error('print handler did not run');
    await owner.page.goto(base + '/audit', { waitUntil: 'domcontentloaded' });
    const download = owner.page.waitForEvent('download', { timeout: 15000 });
    await owner.page.click('#csv');
    const file = await download;
    const name = file.suggestedFilename();
    if (!name.endsWith('.csv')) throw new Error('csv filename ' + name);
    await owner.context.close();
  });

  await check('stored markup is text and does not run', async () => {
    const owner = await login('owner');
    await owner.page.goto(base + '/hari', { waitUntil: 'domcontentloaded' });
    await owner.page.selectOption('#ctxFarm', 'Jahaniya');
    const dialogs = owner.page._dialogs;
    await owner.page.click('#add');
    await owner.page.waitForSelector('.modal');
    const payload = '<img src=x onerror=alert(1)>';
    await owner.page.fill('[name=Name]', payload);
    await owner.page.fill('[name=Address]', 'e2e');
    const acres = owner.page.locator('.modal input[type=number]').first();
    if (await acres.count()) await acres.fill('1');
    await owner.page.locator('.modal button', { hasText: 'Add Hari' }).click();
    await owner.page.waitForSelector('.toast', { timeout: 10000 });
    if (dialogs.length) throw new Error('dialog fired: ' + dialogs.join(','));
    const cell = await owner.page.locator('#t').innerText();
    const html = await owner.page.locator('#t').innerHTML();
    if (!cell.includes(payload)) throw new Error('markup was not stored as text');
    if (html.includes('<img src=x')) throw new Error('markup was injected as HTML');
    await owner.context.close();
  });

  await check('expense footer total covers every matching row', async () => {
    const owner = await login('owner');
    await owner.page.goto(base + '/expenses', { waitUntil: 'domcontentloaded' });
    await owner.page.selectOption('#ctxFarm', 'Jahaniya');
    await owner.page.waitForSelector('#t tfoot, #t .empty', { timeout: 10000 });
    const data = await owner.page.evaluate(async () => {
      const res = await fetch('/api/table?resource=expenses&farm=Jahaniya&page=1&per=25', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      return res.json();
    });
    if (!data || data.ok !== true) throw new Error('table API failed');
    if ((data.rows || []).length > 25) throw new Error('page longer than 25');
    if (Number(data.total) < (data.rows || []).length) throw new Error('total smaller than page');
    const foot = await owner.page.locator('#t tfoot').innerText();
    const amount = Number(data.totals.amount).toLocaleString('en-PK');
    if (!foot.includes(amount) && !foot.includes(String(data.totals.amount))) throw new Error('footer ' + foot + ' != ' + data.totals.amount);
    await owner.context.close();
  });

  await browser.close();
  await writeFile(path.join(art, 'e2e-results.json'), JSON.stringify({ results, shots }, null, 2));
} catch (err) {
  console.error('E2E_BLOCKER');
  console.error(err && err.stack ? err.stack : err);
  record('suite', false, err && err.message ? err.message : err);
  try { await mkdir(art, { recursive: true }); await writeFile(path.join(art, 'e2e-results.json'), JSON.stringify({ results, blocker: String(err && err.stack || err) }, null, 2)); } catch {}
} finally {
  server.kill();
}

const failed = results.filter((r) => !r.ok);
console.log(`E2E ${results.length - failed.length} passed, ${failed.length} failed`);
process.exit(failed.length ? 1 : 0);
