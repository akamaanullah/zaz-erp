import { api, loadState, openSession, runSuite, shot, submitModal, toastText } from '../lib.mjs';

const state = await loadState();

async function recordSale(page, crop, weight, rate) {
  await page.click('#add');
  await page.selectOption('[name=Target]', 'seri');
  await page.fill('[name=Crop]', crop);
  await page.selectOption('[name=Unit]', 'kg');
  await page.fill('[name=Weight]', String(weight));
  await page.fill('[name=Rate]', String(rate));
  await submitModal(page, 'Add sale');
  await toastText(page);
}

await runSuite('06-season', async ({ check, browser, bucket }) => {
  const accountant = await openSession(browser, state.accountant.username, state.accountant.password, bucket);
  await check('two seasons on one farm stay separate in the sales list and its total', async () => {
    const origin = new URL(accountant.page.url()).origin;
    await accountant.page.goto(origin + '/sales', { waitUntil: 'domcontentloaded' });
    await accountant.page.selectOption('#ctxSeason', { label: 'Kharif 2026' });
    await accountant.page.waitForTimeout(400);
    await recordSale(accountant.page, 'SeasonK', 3, 10);
    await accountant.page.selectOption('#ctxSeason', { label: 'Rabi 2026-27' });
    await accountant.page.waitForTimeout(400);
    await recordSale(accountant.page, 'SeasonR', 4, 10);
    await accountant.page.fill('input[type=search]', 'SeasonK');
    await accountant.page.waitForTimeout(700);
    const rabi = await accountant.page.locator('#t').innerText();
    if (rabi.includes('SeasonK')) throw new Error('Rabi list includes the Kharif sale');
    await shot(accountant.page, 'e2e-season-rabi.png');
    await accountant.page.selectOption('#ctxSeason', { label: 'Kharif 2026' });
    await accountant.page.waitForTimeout(500);
    await accountant.page.fill('input[type=search]', 'SeasonR');
    await accountant.page.waitForTimeout(700);
    const kharif = await accountant.page.locator('#t').innerText();
    if (kharif.includes('SeasonR')) throw new Error('Kharif list includes the Rabi sale');
    await accountant.page.fill('input[type=search]', 'SeasonK');
    await accountant.page.waitForTimeout(700);
    const back = await accountant.page.locator('#t tbody').innerText();
    if (!back.includes('SeasonK') || !back.includes('30')) throw new Error(back.slice(0, 180));
    await shot(accountant.page, 'e2e-season-kharif.png');
  });
  await check('changing season does not reveal another farm', async () => {
    const res = await api(accountant.page, 'GET', '/api/table?resource=sales&farm=' + encodeURIComponent('E2E South') + '&season=' + encodeURIComponent('Rabi 2026-27') + '&page=1');
    if (res.status !== 200) throw new Error('status ' + res.status);
    const rows = await accountant.page.evaluate(async () => {
      const r = await fetch('/api/table?resource=sales&farm=' + encodeURIComponent('E2E South') + '&season=' + encodeURIComponent('Rabi 2026-27') + '&page=1', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      return r.json();
    });
    const farms = [...new Set((rows.rows || []).map((row) => row.farm))];
    if (farms.some((name) => name && name !== 'E2E North')) throw new Error('cross-farm rows ' + farms.join(','));
  });
  await accountant.close();
});
