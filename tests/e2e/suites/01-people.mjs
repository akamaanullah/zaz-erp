import { api, loadState, openSession, remember, runSuite, saveState, shot, submitAction, submitModal, toastText } from '../lib.mjs';

const state = await loadState();

await runSuite('01-people', async ({ check, browser }) => {
  const owner = await openSession(browser, 'owner', state.password, []);
  await check('owner adds, edits, and archives a farm from the form', async () => {
    await owner.page.goto(stateBase(owner.page) + '/farms', { waitUntil: 'domcontentloaded' });
    await owner.page.click('#add');
    await owner.page.waitForSelector('.modal');
    await owner.page.fill('[name=name]', 'E2E North');
    await owner.page.fill('[name=location]', 'Bench');
    await owner.page.fill('[name=acres]', '100');
    await owner.page.fill('[name=seri]', '20');
    await submitModal(owner.page, 'Add farm');
    await toastText(owner.page);
    await owner.page.click('#add');
    await owner.page.waitForSelector('.modal');
    await owner.page.fill('[name=name]', 'E2E South');
    await owner.page.fill('[name=acres]', '40');
    await owner.page.fill('[name=seri]', '5');
    await submitModal(owner.page, 'Add farm');
    await toastText(owner.page);
    const south = owner.page.locator('tr', { hasText: 'E2E South' });
    await south.locator('[data-edit]').click();
    await owner.page.waitForSelector('.modal');
    await owner.page.fill('[name=acres]', '42');
    await submitModal(owner.page, 'Save');
    await toastText(owner.page);
    await owner.page.locator('tr', { hasText: 'E2E South' }).locator('[data-arch]').click();
    await submitModal(owner.page, 'Archive');
    await toastText(owner.page);
    await owner.page.reload({ waitUntil: 'domcontentloaded' });
    await owner.page.waitForSelector('text=E2E North');
    const body = await owner.page.locator('#t').innerText();
    if (!body.includes('E2E North') || !body.includes('Archived')) throw new Error(body.slice(0, 180));
    await shot(owner.page, 'e2e-farms.png');
  });

  await check('owner adds a rate category and a sharing rule', async () => {
    await owner.page.goto(new URL('/rates', owner.page.url()).origin + '/rates', { waitUntil: 'domcontentloaded' });
    await owner.page.click('#add');
    await owner.page.fill('[name=name]', 'Urea');
    await owner.page.selectOption('[name=unit]', { label: 'per bag' });
    await owner.page.fill('[name=rate]', '10.5');
    await owner.page.selectOption('[name=share]', '50');
    await submitAction(owner.page, 'Add category', '/api/rates/save');
    await owner.page.waitForSelector('#add');
    await owner.page.click('#add');
    await owner.page.fill('[name=name]', 'Tractor Preparation');
    await owner.page.selectOption('[name=unit]', { label: 'per acre' });
    await owner.page.fill('[name=rate]', '10');
    await owner.page.selectOption('[name=share]', '100');
    await submitAction(owner.page, 'Add category', '/api/rates/save');
    await owner.page.goto(new URL('/rules', owner.page.url()).origin + '/rules', { waitUntil: 'domcontentloaded' });
    await owner.page.waitForSelector('[data-k="Tractor Preparation"]');
    await owner.page.locator('[data-k="Tractor Preparation"]').click();
    await owner.page.selectOption('[name=Share]', '50');
    await submitAction(owner.page, 'Update rule', '/api/rules/update');
    await owner.page.reload({ waitUntil: 'domcontentloaded' });
    const text = await owner.page.locator('#t').innerText();
    if (!text.includes('Tractor Preparation') || !text.includes('50%')) throw new Error(text.slice(0, 180));
  });

  await check('owner creates supervisor, accountant, and a disposable login', async () => {
    state.supervisor = await addEmployee(owner.page, 'E2E Supervisor', 'e2e.sup', 'supervisor', 'E2E North');
    state.accountant = await addEmployee(owner.page, 'E2E Accountant', 'e2e.acc', 'accountant', 'E2E North');
    state.disabled = await addEmployee(owner.page, 'E2E Temp', 'e2e.temp', 'supervisor', 'E2E North');
    remember(state.supervisor.password);
    remember(state.accountant.password);
    remember(state.disabled.password);
    await saveState(state);
    await owner.page.goto(new URL('/salaries', owner.page.url()).origin + '/salaries', { waitUntil: 'domcontentloaded' });
    await owner.page.locator('tr', { hasText: 'E2E Accountant' }).locator('[data-sal]').click();
    await owner.page.fill('[name=salary]', '25000');
    await submitModal(owner.page, 'Save salary');
    await toastText(owner.page);
    await owner.page.locator('tr', { hasText: 'E2E Accountant' }).locator('[data-payu]').click();
    await owner.page.fill('[name=amount]', '5000');
    await owner.page.selectOption('[name=kind]', 'Salary');
    await owner.page.selectOption('[name=status]', 'Paid');
    await submitModal(owner.page, 'Save payment');
    await toastText(owner.page);
    await owner.page.reload({ waitUntil: 'domcontentloaded' });
    const paid = await owner.page.locator('#p').innerText();
    if (!paid.includes('5,000') && !paid.includes('5000')) throw new Error(paid.slice(0, 180));
  });

  await check('disabling an account blocks its next sign-in', async () => {
    await owner.page.goto(new URL('/employees', owner.page.url()).origin + '/employees', { waitUntil: 'domcontentloaded' });
    await owner.page.locator('tr', { hasText: 'e2e.temp' }).locator('[data-tog]').click();
    await submitModal(owner.page, 'Disable');
    await toastText(owner.page);
    const blocked = await browser.newContext();
    const page = await blocked.newPage();
    await page.goto(new URL('/login', owner.page.url()).origin + '/login', { waitUntil: 'domcontentloaded' });
    await page.fill('#username', 'e2e.temp');
    await page.fill('#password', state.disabled.password);
    await page.click('form button.btn');
    await page.getByText('Invalid username or password').waitFor({ timeout: 8000 });
    const msg = await page.locator('#loginMsg').innerText();
    const url = page.url();
    await blocked.close();
    if (!msg.includes('Invalid username or password') || url.includes('dashboard')) throw new Error('disabled account was not refused');
  });
  await owner.close();

  const office = await openSession(browser, 'office', state.password, []);
  await check('office cannot create an Owner from the form or the API', async () => {
    await office.page.goto(new URL('/employees', office.page.url()).origin + '/employees', { waitUntil: 'domcontentloaded' });
    await office.page.click('#add');
    const disabled = await office.page.locator('select[name=role] option[value=owner]').getAttribute('disabled');
    if (disabled === null) throw new Error('Owner option is available to Office');
    await office.page.locator('.modal [data-close]').first().click();
    const res = await api(office.page, 'POST', '/api/employees/create', { name: 'Nope', username: 'e2e.owner2', role: 'owner', salary: 0, payType: 'Monthly salary' });
    if (res.status !== 403) throw new Error('status ' + res.status);
  });
  await office.close();
});

function stateBase(page) {
  return new URL(page.url()).origin;
}

async function addEmployee(page, name, username, role, farm) {
  await page.goto(new URL('/employees', page.url()).origin + '/employees', { waitUntil: 'domcontentloaded' });
  await page.click('#add');
  await page.fill('[name=name]', name);
  await page.fill('[name=username]', username);
  await page.selectOption('[name=role]', role);
  if (role === 'supervisor' || role === 'accountant') await page.selectOption('[name=farm]', { label: farm });
  const response = page.waitForResponse((res) => res.url().includes('/api/employees/create') && res.request().method() === 'POST');
  await submitModal(page, 'Add employee');
  const res = await response;
  if (res.status() >= 400) {
    const err = await res.json().catch(() => ({}));
    throw new Error('employee create ' + res.status() + ' ' + (err.error || ''));
  }
  const body = await res.json();
  if (!body.temporaryPassword) throw new Error('one-time password was not returned');
  await page.goto(new URL('/farms', page.url()).origin + '/farms', { waitUntil: 'domcontentloaded' });
  return { username, password: body.temporaryPassword };
}
