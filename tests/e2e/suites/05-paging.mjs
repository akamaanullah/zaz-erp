import { loadState, openSession, runSuite, shot, submitModal, toastText } from '../lib.mjs';

const state = await loadState();

await runSuite('05-paging', async ({ check, browser, bucket }) => {
  const accountant = await openSession(browser, state.accountant.username, state.accountant.password, bucket);
  await check('more than one page of expenses is created from the form', async () => {
    const origin = new URL(accountant.page.url()).origin;
    await accountant.page.goto(origin + '/expenses', { waitUntil: 'domcontentloaded' });
    for (let i = 1; i <= 26; i++) {
      await accountant.page.click('#add');
      const category = await accountant.page.locator('[name=Category] option').evaluateAll((opts) => (opts.find((opt) => opt.textContent.includes('Tractor')) || {}).value || '');
      if (!category) throw new Error('tractor category missing');
      await accountant.page.selectOption('[name=Category]', category);
      await accountant.page.selectOption('[name=Target]', 'seri');
      await accountant.page.fill('[name=Crop]', 'PageRow ' + i);
      await accountant.page.fill('[name=Qty]', '1');
      await accountant.page.fill('[name=Rate]', '1');
      await submitModal(accountant.page, 'Add expense');
      await toastText(accountant.page);
    }
  });
  await check('next and previous pages keep one footer total for every matching row', async () => {
    await accountant.page.reload({ waitUntil: 'domcontentloaded' });
    await accountant.page.waitForSelector('#t tfoot', { timeout: 15000 });
    const page1 = await accountant.page.locator('#t tfoot').innerText();
    const meta1 = await accountant.page.locator('#t .table-meta').innerText();
    if (!meta1.includes(' of ') && !/matching/.test(meta1)) throw new Error(meta1);
    await accountant.page.locator('#t [data-pg=next]').click();
    await accountant.page.waitForTimeout(700);
    const page2 = await accountant.page.locator('#t tfoot').innerText();
    const meta2 = await accountant.page.locator('#t .table-meta').innerText();
    if (page1 !== page2) throw new Error('footer changed from ' + page1 + ' to ' + page2);
    if (meta2 === meta1) throw new Error('page did not change: ' + meta2);
    await accountant.page.locator('#t [data-pg=prev]').click();
    await accountant.page.waitForTimeout(700);
    await accountant.page.fill('#t input[type=search]', 'PageRow 1');
    await accountant.page.waitForTimeout(600);
    const found = await accountant.page.locator('#t').innerText();
    if (!found.includes('PageRow 1')) throw new Error(found.slice(0, 180));
    await accountant.page.locator('#t th.sortable').nth(0).click();
    await accountant.page.waitForTimeout(500);
    if (!(await accountant.page.locator('#t tbody tr').count())) throw new Error('sort removed the rows');
    await shot(accountant.page, 'e2e-paging.png');
  });
  await accountant.close();
});
