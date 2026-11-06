import { mkdtemp, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { api, chooseFarm, loadState, openSession, runSuite, shot } from '../lib.mjs';

const state = await loadState();
const originOf = (page) => new URL(page.url()).origin;

const supervisorDenied = [
  ['GET', '/farms'], ['GET', '/employees'], ['GET', '/salaries'], ['GET', '/godown'],
  ['GET', '/approvals'], ['GET', '/rates'], ['GET', '/rules'], ['GET', '/expenses'],
  ['GET', '/sales'], ['GET', '/advances'], ['GET', '/api/backup'],
  ['GET', '/api/table?resource=receipts'], ['GET', '/api/table?resource=approvals'], ['GET', '/api/table?resource=salaries'],
  ['POST', '/api/farms/save', { name: 'X', acres: 1, seri: 0 }],
  ['POST', '/api/employees/create', { name: 'X', username: 'e2e.nope', role: 'accountant', farm: 'E2E North', salary: 0, payType: 'Monthly salary' }],
  ['POST', '/api/employees/status', { id: 1 }],
  ['POST', '/api/godown/receive', { item: 'Urea', qty: 1, rate: 1, date: '2026-11-06' }],
  ['POST', '/api/transfers/send', { farm: 'E2E North', item: 'Urea', qty: 1 }],
  ['POST', '/api/expenses/create', { farm: 'E2E North', season: 'Kharif 2026', category: 'Tractor Preparation', target: 'seri', crop: 'X', qty: 1, rate: 1 }],
  ['POST', '/api/sales/create', { farm: 'E2E North', season: 'Kharif 2026', target: 'seri', crop: 'X', unit: 'kg', weight: 1, rate: 1 }],
  ['POST', '/api/advances/create', { farm: 'E2E North', season: 'Kharif 2026', hariId: 1, reason: 'X', amount: 1 }],
  ['POST', '/api/approvals/one', { type: 'expenses', id: 1 }],
  ['POST', '/api/approvals/bulk', { farm: 'E2E North' }],
  ['POST', '/api/rates/save', { name: 'Nope', unit: 'per acre', rate: 1, share: 50 }],
  ['POST', '/api/rules/update', { category: 'Tractor Preparation', share: 0 }],
  ['POST', '/api/settings/import', { format: 'zaz-erp-mvc-1', db: {} }],
  ['POST', '/api/salaries/pay', { userId: 1, month: '2026-11', kind: 'Salary', amount: 1, status: 'Paid', date: '2026-11-06' }],
  ['POST', '/api/hari/create', { farm: 'E2E North', name: 'X', address: 'X', acres: 1, crop: 'Wheat', season: 'Kharif 2026' }],
];

const accountantDenied = [
  ['GET', '/farms'], ['GET', '/employees'], ['GET', '/salaries'], ['GET', '/godown'],
  ['GET', '/approvals'], ['GET', '/rates'], ['GET', '/rules'], ['GET', '/api/backup'],
  ['GET', '/api/table?resource=receipts'],
  ['POST', '/api/godown/receive', { item: 'Urea', qty: 1, rate: 1, date: '2026-11-06' }],
  ['POST', '/api/transfers/send', { farm: 'E2E North', item: 'Urea', qty: 1 }],
  ['POST', '/api/approvals/bulk', { farm: 'E2E North' }],
  ['POST', '/api/rates/save', { name: 'Nope', unit: 'per acre', rate: 1, share: 50 }],
  ['POST', '/api/settings/import', { format: 'zaz-erp-mvc-1', db: {} }],
  ['POST', '/api/expenses/create', { farm: 'E2E South', season: 'Kharif 2026', category: 'Tractor Preparation', target: 'seri', crop: 'X', qty: 1, rate: 1 }],
];

await runSuite('04-access', async ({ check, browser, bucket }) => {
  async function matrix(session, label, rows) {
    for (const [method, apiPath, body] of rows) {
      await check(`${label} ${method} ${apiPath.split('?')[0]} is forbidden`, async () => {
        const res = await api(session.page, method, apiPath, body || {});
        if (res.status !== 403 && res.status !== 422) throw new Error('status ' + res.status);
        if (apiPath.includes('E2E South') && res.status !== 403) throw new Error('cross-farm status ' + res.status);
        if (!apiPath.includes('E2E South') && res.status !== 403) throw new Error('status ' + res.status);
      });
    }
  }

  const supervisor = await openSession(browser, state.supervisor.username, state.supervisor.password, bucket);
  await matrix(supervisor, 'supervisor', supervisorDenied);
  await check('supervisor has no backup or reset controls', async () => {
    await supervisor.page.goto(originOf(supervisor.page) + '/settings', { waitUntil: 'domcontentloaded' });
    if (await supervisor.page.locator('#exp, #rst').count()) throw new Error('supervisor sees backup controls');
  });
  await supervisor.close();

  const accountant = await openSession(browser, state.accountant.username, state.accountant.password, bucket);
  await matrix(accountant, 'accountant', accountantDenied);
  await accountant.close();

  const office = await openSession(browser, 'office', state.password, bucket);
  await check('office export downloads a backup without passwords, and a bad import is rejected', async () => {
    const origin = originOf(office.page);
    await office.page.goto(origin + '/settings', { waitUntil: 'domcontentloaded' });
    if (await office.page.locator('#rst').count()) throw new Error('office sees demo reset');
    const download = office.page.waitForEvent('download');
    await office.page.click('#exp');
    const file = await download;
    const saved = path.join(tmpdir(), 'zaz-e2e-backup.json');
    await file.saveAs(saved);
    const { readFile } = await import('node:fs/promises');
    const text = await readFile(saved, 'utf8');
    if (!text.includes('zaz-erp-mvc-1') || text.includes('password_hash') || text.includes(state.password)) throw new Error('backup contents were not safe');
    const dir = await mkdtemp(path.join(tmpdir(), 'zaz-e2e-'));
    const bad = path.join(dir, 'bad.json');
    await writeFile(bad, JSON.stringify({ format: 'nope', db: {} }));
    await office.page.setInputFiles('#imp', bad);
    await office.page.waitForSelector('.toast.error, .toast.error, .toast', { timeout: 10000 });
    const toast = await office.page.locator('.toast').last().innerText();
    if (!toast.toLowerCase().includes('backup') && !toast.toLowerCase().includes('not a zaz')) throw new Error(toast);
    const usersFile = path.join(dir, 'users.json');
    await writeFile(usersFile, JSON.stringify({ format: 'zaz-erp-mvc-1', db: { users: [{ username: 'x' }] } }));
    const imported = office.page.waitForResponse((res) => res.url().includes('/api/settings/import'));
    await office.page.setInputFiles('#imp', usersFile);
    await office.page.locator('form.modal').getByRole('button', { name: 'Import', exact: true }).click();
    const rejected = await imported;
    if (rejected.status() !== 422) throw new Error('import status ' + rejected.status());
  });
  await check('all-farms report, season control, and daily date filter', async () => {
    const origin = originOf(office.page);
    await office.page.goto(origin + '/reports', { waitUntil: 'domcontentloaded' });
    await chooseFarm(office.page, 'E2E North');
    await office.page.locator('[data-scope=all]').click();
    await office.page.locator('#sAll').waitFor({ timeout: 10000 });
    await office.page.locator('[data-scope=farm]').click();
    await office.page.selectOption('#ctxSeason', { label: 'Rabi 2026-27' });
    await office.page.waitForTimeout(500);
    await office.page.fill('#sw input[type=search]', 'KgCrop');
    await office.page.waitForTimeout(700);
    const rabi = await office.page.locator('#sw').innerText();
    if (rabi.includes('KgCrop')) throw new Error('Rabi report still lists the Kharif sale');
    await office.page.selectOption('#ctxSeason', { label: 'Kharif 2026' });
    await office.page.waitForTimeout(500);
    await office.page.fill('#sw input[type=search]', 'KgCrop');
    await office.page.waitForTimeout(700);
    const kharif = await office.page.locator('#sw').innerText();
    if (!kharif.includes('KgCrop')) throw new Error('Kharif report lost KgCrop');
    await shot(office.page, 'e2e-reports.png');
    await office.page.goto(origin + '/daily', { waitUntil: 'domcontentloaded' });
    await office.page.locator('[data-range=date]').click();
    await office.page.fill('#fDate', '2020-01-01');
    await office.page.waitForTimeout(400);
    const empty = await office.page.locator('#timeline').innerText();
    if (empty.includes('Watered the north block')) throw new Error('date filter did not hide today');
    await office.page.locator('[data-range=all]').click();
    await office.page.waitForTimeout(500);
    const back = await office.page.locator('#timeline').innerText();
    if (!back.includes('Watered the north block')) throw new Error('all-dates view hid the entry');
  });
  await office.close();

  const owner = await openSession(browser, 'owner', state.password, bucket);
  await check('theme and language persist, authenticator stays unconfigured, audit CSV downloads', async () => {
    const origin = originOf(owner.page);
    await owner.page.goto(origin + '/settings', { waitUntil: 'domcontentloaded' });
    const totp = await owner.page.locator('#totpBox').innerText();
    if (!totp.includes('ZAZ_APP_KEY')) throw new Error(totp);
    if (await owner.page.locator('#totpOn').count()) throw new Error('authenticator setup was offered without a key');
    await owner.page.selectOption('[name=theme]', 'dark');
    await owner.page.locator('html[data-theme=dark]').waitFor();
    await owner.page.reload({ waitUntil: 'domcontentloaded' });
    await owner.page.locator('html[data-theme=dark]').waitFor({ timeout: 15000 });
    await owner.page.selectOption('[name=lang]', 'ur');
    await owner.page.locator('.nav').getByText('ڈیش').waitFor();
    await owner.page.selectOption('[name=lang]', 'en');
    await owner.page.selectOption('[name=theme]', 'light');
    await owner.page.waitForTimeout(400);
    await owner.page.goto(origin + '/audit', { waitUntil: 'domcontentloaded' });
    const download = owner.page.waitForEvent('download');
    await owner.page.click('#csv');
    const file = await download;
    if (!file.suggestedFilename().endsWith('.csv')) throw new Error(file.suggestedFilename());
    await shot(owner.page, 'e2e-audit.png');
  });
  await owner.close();
});
