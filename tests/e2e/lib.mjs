import { spawn } from 'node:child_process';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { chromium } from 'playwright-core';

export const root = path.resolve(import.meta.dirname, '../..');
export const php = 'D:\\xampp\\php\\php.exe';
export const edge = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
export const base = 'http://127.0.0.1:8091';
export const art = path.join(process.env.LOCALAPPDATA || '', 'Cursor', 'AgentStores', 'cursor_agent_stores', 'bc-b62b4e5c-18b6-5eb6-87fb-eb5f355853b7', 'files', 'artifacts');
export const statePath = path.join(root, 'storage', 'e2e-run.json');

const secrets = new Set();
let closingBrowser = false;
process.on('uncaughtException', (err) => {
  const msg = String(err && err.message ? err.message : err);
  if (closingBrowser && msg.includes('Target page, context or browser has been closed')) return;
  console.error('UNCAUGHT ' + redact(msg));
  process.exitCode = 1;
});

export function remember(value) {
  if (value && String(value).length > 3) secrets.add(String(value));
}

export function redact(value) {
  let text = String(value ?? '');
  for (const secret of secrets) text = text.split(secret).join('[redacted]');
  return text.slice(0, 500);
}

export async function loadState() {
  const state = JSON.parse(await readFile(statePath, 'utf8'));
  remember(state.password);
  for (const key of ['supervisor', 'accountant', 'disabled']) {
    if (state[key]) remember(state[key].password);
  }
  return state;
}

export async function saveState(state) {
  await writeFile(statePath, JSON.stringify(state));
}

function attach(page, bucket) {
  page.on('pageerror', (err) => bucket.push('pageerror: ' + err.message));
  page.on('console', (msg) => {
    if (msg.type() === 'error') bucket.push('console: ' + msg.text().slice(0, 240));
  });
  page.on('requestfailed', (req) => {
    const url = req.url();
    if (url.includes('fonts.g') || url.includes('favicon')) return;
    bucket.push('network: ' + req.failure()?.errorText + ' ' + url.slice(0, 180));
  });
  page.on('response', (res) => {
    if (res.status() >= 500) bucket.push('http ' + res.status() + ' ' + res.url().slice(0, 180));
  });
  page.on('dialog', (dialog) => {
    bucket.push('dialog: ' + dialog.message());
    return dialog.dismiss();
  });
}

export async function launchBrowser() {
  return chromium.launch({
    executablePath: edge,
    headless: true,
    args: ['--disable-dev-shm-usage', '--no-first-run', '--no-default-browser-check'],
  });
}

export async function openSession(browser, username, password, bucket) {
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();
  attach(page, bucket);
  await page.goto(base + '/login', { waitUntil: 'domcontentloaded' });
  await page.fill('#username', username);
  await page.fill('#password', password);
  await page.click('form button.btn');
  await page.waitForURL('**/dashboard', { timeout: 20000 });
  await page.waitForSelector('h1');
  return {
    page,
    close: async () => {
    closingBrowser = true;
    for (const open of context.pages()) await open.close().catch(() => {});
    await context.close().catch(() => {});
  },
  };
}

export async function chooseFarm(page, name) {
  const select = page.locator('#ctxFarm');
  if (!(await select.count()) || await select.isDisabled()) return;
  await select.selectOption({ label: name });
  await page.waitForTimeout(300);
}

export async function shot(page, name) {
  await mkdir(art, { recursive: true });
  const file = path.join(art, name);
  await page.screenshot({ path: file, fullPage: false });
  return file;
}

export async function submitModal(page, label) {
  const form = page.locator('form.modal');
  const invalid = await form.evaluate((node) => [...node.elements]
    .filter((el) => el.willValidate && !el.checkValidity())
    .map((el) => (el.name || el.id) + ': ' + el.validationMessage));
  if (invalid.length) throw new Error('form invalid: ' + invalid.join('; '));
  await page.locator('.modal').getByRole('button', { name: label, exact: true }).click();
}

export async function submitAction(page, label, urlPart) {
  const response = page.waitForResponse((res) => res.url().includes(urlPart) && res.request().method() === 'POST', { timeout: 15000 }).catch((err) => err);
  await submitModal(page, label);
  const res = await response;
  if (res instanceof Error) throw res;
  if (res.status() >= 400) {
    const body = await res.json().catch(() => ({}));
    throw new Error(urlPart + ' ' + res.status() + ' ' + (body.error || ''));
  }
  await page.waitForSelector('.toast');
  return res;
}

export async function toastText(page) {
  await page.waitForSelector('.toast', { timeout: 15000 });
  return page.locator('.toast').last().innerText();
}

export async function api(page, method, apiPath, body) {
  return page.evaluate(async ({ method, apiPath, body }) => {
    const csrf = document.querySelector('meta[name="zaz-csrf"]')?.content || '';
    const res = await fetch(apiPath, {
      method,
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrf,
      },
      body: method === 'GET' ? undefined : JSON.stringify(body ?? {}),
    });
    return { status: res.status };
  }, { method, apiPath, body });
}

export async function runSuite(name, fn) {
  const results = [];
  const bucket = [];
  const file = path.join(art, `e2e-${name}.json`);
  await mkdir(art, { recursive: true });
  let browser;
  const flush = async () => {
    await writeFile(file, JSON.stringify({
      suite: name,
      results,
      browserErrors: bucket.map(redact).slice(-30),
    }, null, 2));
  };
  async function check(title, body) {
    const before = bucket.length;
    try {
      await body();
      const fresh = bucket.slice(before).filter((line) => line.startsWith('pageerror') || line.startsWith('http 5') || line.startsWith('dialog:'));
      if (fresh.length) throw new Error(fresh.join(' | '));
      results.push({ name: title, ok: true });
      console.log('PASS ' + title);
    } catch (err) {
      results.push({ name: title, ok: false, error: redact(err && err.message ? err.message : err) });
      console.log('FAIL ' + title + ' — ' + redact(err && err.message ? err.message : err));
    }
    await flush();
  }
  try {
    browser = await launchBrowser();
    await fn({ check, browser, bucket });
  } catch (err) {
    results.push({ name: name + ' setup', ok: false, error: redact(err && err.stack ? err.stack : err) });
    console.log('FAIL ' + name + ' setup — ' + redact(err && err.message ? err.message : err));
    await flush();
  } finally {
    closingBrowser = true;
    if (browser) await browser.close().catch(() => {});
    await flush();
  }
  const failed = results.filter((row) => !row.ok).length;
  console.log(`SUITE ${name} ${results.length - failed} passed, ${failed} failed`);
  process.exit(failed || process.exitCode ? 1 : 0);
}

export function spawnPhp(args, env, stdin) {
  return new Promise((resolve, reject) => {
    const child = spawn(php, args, { cwd: root, env: { ...process.env, ...env }, stdio: ['pipe', 'pipe', 'pipe'] });
    let out = '';
    let err = '';
    child.stdout.on('data', (buf) => { out += buf; });
    child.stderr.on('data', (buf) => { err += buf; });
    child.on('error', reject);
    child.on('close', (code) => (code === 0 ? resolve(out) : reject(new Error((err || out).slice(0, 500)))));
    child.stdin.end(stdin ?? '');
  });
}
