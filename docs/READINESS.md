# Readiness notes

Local XAMPP app. Not a public production launch.

## What was measured

These are separate runs. The PHP files are not a browser end-to-end suite.

- `php tests/run.php`: 100 passed, 0 failed, database `zaz_agriculture_test` only. Includes a 30-row expense page: the API returns 25 rows and the footer amount equals `SUM(amount)` for every matching farm row. A supervisor who sends another farm name still receives only the assigned farm.
- `php tests/scenario.php`: 18 passed, 0 failed, same isolated database. Approved settlement on that fixture is 100 − 46.75 − 15 = 38.25. Operating cost is 94.00 and excludes the advance.
- `php tests/concurrent.php`: 4 passed, 0 failed. A thrown error inside a transaction rolled the receipt insert back. Two database connections used the same last Zinc quantity: the second waited 3038 ms on `SELECT … FOR UPDATE` and then got "Insufficient farm stock". The remaining quantity was not negative.
- Browser, Edge via Playwright, headless, separate profile, app on `127.0.0.1:8091` with database `zaz_agriculture_e2e` only: 66 screen checks passed (every menu page for Owner, Office, Supervisor, and Accountant, plus a direct `/employees` refusal for Supervisor and Accountant). A later run repeated the action checks: invalid expense stays open, a real expense is created, search and column sort still leave Approve clickable, the row stays approved after reload, the dashboard chart renders, the ledger print handler runs, and audit CSV downloads. A follow-up browser run stored a Hari name containing an `<img onerror>` payload and the table showed it as escaped text with no dialog. One full process then hit `TargetClosedError` inside Playwright after those passes; the markup check was repeated on its own and passed. Screenshots from the passing clicks are in the agent artifacts folder: `e2e-login-empty.png`, `e2e-dashboard-owner.png`, `e2e-expense-created.png`, `e2e-approved-after-sort.png`, `e2e-ledger.png`. They do not contain passwords.
- MariaDB 10.4.32. On 402 usage rows, `SUM(amount) … WHERE farm_id = ? AND status = ?` was `type=ALL`, 0.86 ms before a `(farm_id, status)` index and 2.14 ms after it. The optimizer did not use that index. On the small e2e seed (4 usage, 4 expenses, 5 sales) the grouped sums used `type=index` on `idx_expenses_farm` / `idx_sales_farm` and a paged usage read used `PRIMARY`, measured 0.51–0.82 ms. That is an index scan of a tiny table, not evidence that the index speeds a large report. Inserting 400 rows one-by-one earlier took 14.7 seconds of round trips.
- `php tests/scenario.php` EXPLAIN of the approved-usage sum on 2 rows: `type=ALL`, `rows=2`, 1269 microseconds.
- The existing local database `zaz_agriculture` was not wiped and its passwords were not changed. Demo rows stay until the owner says to delete them.

## Security checked in this pass

- Prototype and `.git` are forbidden by the site `.htaccess`.
- Sessions end after 8 idle hours, when the account is disabled, and when the stored role or farm no longer matches the session.
- Login and authenticator failures use one generic message. Authenticator codes cannot be reused in the same time step. Recovery codes are stored hashed and work once. Real accounts stay unenrolled. No app key was generated for the live app.
- Backup import rejects account or secret fields and files over 2,000,000 bytes or 5,000 rows in one list. Export reads transactions in pages of 50, up to 5,000, separate from the page snapshot.
- Page scripts no longer use an inline script. The response CSP is `script-src 'self'`. Style attributes still need `style-src 'unsafe-inline'`.
- Guest table and ledger routes return 401. Farm roles cannot request another farm's page by changing the farm query.
- Demo reset stays available only when `ZAZ_APP_ENV=local` unless `ZAZ_ALLOW_DEMO_RESET=1` is set explicitly.
- Web responses do not display PHP errors. Audit rows cannot be deleted. CSV formula prefixes remain.

## Still required before a real deployment

- HTTPS in front of the app. The session cookie is Secure only on HTTPS.
- A least-privilege database user. Local MariaDB `root` with an empty password is not production-ready. This work does not change that account.
- Backups and a tested restore, outside this machine's demo seed.
- `ZAZ_APP_KEY` of at least 16 characters if Owner or Office will enroll an authenticator. Until that key is set, authenticator enrollment stays off. Do not invent a key in a report.
- Offline PWA sync is not implemented. Android APK is not part of this web task. Both remain open.
- The e2e seed is a few dozen rows. It does not prove how the grouped sums behave at hundreds of thousands of rows. The 402-row EXPLAIN above is the largest measurement so far, and it was a full scan.
