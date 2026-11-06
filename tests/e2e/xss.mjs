import { spawn } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import path from 'node:path';
import { chromium } from 'playwright-core';

const root = path.resolve(import.meta.dirname, '../..');
const php = 'D:\\xampp\\php\\php.exe';
const edge = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const base = 'http://127.0.0.1:8091';
const password = randomBytes(18).toString('base64url');

function phpSeed() {
  return new Promise((resolve, reject) => {
    const child = spawn(php, ['tests/e2e/seed.php'], {
      cwd: root,
      env: { ...process.env, ZAZ_DB_NAME: 'zaz_agriculture_e2e', ZAZ_APP_ENV: 'local', ZAZ_ALLOW_DEMO_RESET: '0' },
      stdio: ['pipe', 'pipe', 'pipe'],
    });
    let err = '';
    child.stderr.on('data', (b) => { err += b; });
    child.on('close', (code) => code === 0 ? resolve() : reject(new Error(err.slice(0, 400))));
    child.stdin.end(password);
  });
}

await phpSeed();
const server = spawn(php, ['-S', '127.0.0.1:8091', '-t', 'public', 'public/dev-router.php'], {
  cwd: root,
  env: { ...process.env, ZAZ_DB_NAME: 'zaz_agriculture_e2e', ZAZ_APP_ENV: 'local', ZAZ_ALLOW_DEMO_RESET: '0' },
  stdio: 'ignore',
});
try {
  for (let i = 0; i < 40; i++) {
    try { if ((await fetch(base + '/login')).status === 200) break; } catch {}
    await new Promise((r) => setTimeout(r, 250));
  }
  const browser = await chromium.launch({ executablePath: edge, headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  const dialogs = [];
  page.on('dialog', (d) => { dialogs.push(d.message()); return d.dismiss(); });
  await page.goto(base + '/login', { waitUntil: 'domcontentloaded' });
  await page.fill('#username', 'owner');
  await page.fill('#password', password);
  await page.click('form button.btn');
  await page.waitForURL('**/dashboard');
  await page.goto(base + '/hari', { waitUntil: 'domcontentloaded' });
  await page.selectOption('#ctxFarm', 'Jahaniya');
  await page.click('#add');
  await page.waitForSelector('.modal');
  const payload = '<img src=x onerror=alert(1)>';
  await page.fill('[name=Name]', payload);
  await page.fill('[name=Address]', 'e2e');
  await page.fill('[name=Acres]', '1');
  await page.fill('[name=Crop]', 'Wheat');
  await page.locator('.modal button', { hasText: 'Add Hari' }).click();
  await page.waitForSelector('.toast', { timeout: 10000 });
  const text = await page.locator('#t').innerText();
  const html = await page.locator('#t').innerHTML();
  if (dialogs.length) throw new Error('dialog: ' + dialogs.join(','));
  if (!text.includes(payload)) throw new Error('name was not shown as text');
  if (html.includes('<img src=x')) throw new Error('raw img tag in the table');
  if (!html.includes('&lt;img')) throw new Error('expected escaped img text');
  await browser.close();
  console.log('PASS stored markup is text and does not run');
} catch (err) {
  console.error('FAIL stored markup', err && err.message ? err.message : err);
  process.exitCode = 1;
} finally {
  server.kill();
}
