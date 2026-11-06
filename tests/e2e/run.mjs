import { spawn } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { art, launchBrowser, php, remember, root, spawnPhp, statePath } from './lib.mjs';

/**
 * The previous single-browser run died with TargetClosedError after dozens of
 * contexts, and the results file was left stale. Each suite below is its own
 * Node process and its own Edge process. A crash is recorded and the next
 * suite still runs.
 */
const suites = [
  'suites/01-people.mjs',
  'suites/02-stock.mjs',
  'suites/03-ledger.mjs',
  'suites/04-access.mjs',
  'suites/05-paging.mjs',
];
const password = randomBytes(18).toString('base64url');
remember(password);

const probe = await launchBrowser().catch((err) => err);
if (probe instanceof Error) {
  console.error('BROWSER_LAUNCH_FAILED');
  console.error(probe.message);
  process.exit(2);
}
await probe.close();

await mkdir(path.dirname(statePath), { recursive: true });
await writeFile(statePath, JSON.stringify({ password }));
await spawnPhp(['tests/e2e/seed-accounts.php'], {
  ZAZ_DB_NAME: 'zaz_agriculture_e2e',
  ZAZ_APP_ENV: 'local',
  ZAZ_ALLOW_DEMO_RESET: '0',
}, password);

const server = spawn(php, ['-S', '127.0.0.1:8091', '-t', 'public', 'public/dev-router.php'], {
  cwd: root,
  env: { ...process.env, ZAZ_DB_NAME: 'zaz_agriculture_e2e', ZAZ_APP_ENV: 'local', ZAZ_ALLOW_DEMO_RESET: '0' },
  stdio: 'ignore',
});
let stopped = false;
function stopServer() {
  if (!stopped) {
    stopped = true;
    server.kill();
  }
}
process.on('exit', stopServer);

async function waitUp() {
  for (let i = 0; i < 40; i++) {
    try {
      if ((await fetch('http://127.0.0.1:8091/login')).status === 200) return;
    } catch {}
    await new Promise((resolve) => setTimeout(resolve, 250));
  }
  throw new Error('PHP server did not answer on 127.0.0.1:8091');
}

function runChild(script) {
  return new Promise((resolve) => {
    const child = spawn(process.execPath, [path.join(root, 'tests/e2e', script)], {
      cwd: root,
      env: process.env,
      stdio: ['ignore', 'pipe', 'pipe'],
    });
    let out = '';
    child.stdout.on('data', (buf) => { out += buf; process.stdout.write(buf); });
    child.stderr.on('data', (buf) => { out += buf; process.stderr.write(buf); });
    child.on('close', (code) => resolve({ code: code ?? 1, out: out.slice(-2000) }));
  });
}

const summary = [];
try {
  await waitUp();
  for (const script of suites) {
    const result = await runChild(script);
    summary.push({ script, code: result.code, tail: result.out.slice(-500) });
  }
} catch (err) {
  summary.push({ script: 'runner', code: 1, error: String(err && err.message || err) });
  console.error(err && err.stack ? err.stack : err);
} finally {
  stopServer();
  await mkdir(art, { recursive: true });
  await writeFile(path.join(art, 'e2e-summary.json'), JSON.stringify({ summary }, null, 2));
}

const failed = summary.filter((row) => row.code !== 0);
console.log(`E2E suites ${summary.length - failed.length} green, ${failed.length} failed`);
process.exit(failed.length ? 1 : 0);
