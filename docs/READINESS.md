# Readiness notes

Local XAMPP app. Not a public production launch.

## What was measured

These are separate runs. The PHP files are not a browser end-to-end suite.

- `php tests/run.php`: 100 passed, 0 failed, database `zaz_agriculture_test` only. Includes a 30-row expense page: the API returns 25 rows and the footer amount equals `SUM(amount)` for every matching farm row. A supervisor who sends another farm name still receives only the assigned farm.
- `php tests/scenario.php`: 18 passed, 0 failed, same isolated database. Approved settlement on that fixture is 100 − 46.75 − 15 = 38.25. Operating cost is 94.00 and excludes the advance.
- `php tests/concurrent.php`: 4 passed, 0 failed. A thrown error inside a transaction rolled the receipt insert back. Two database connections used the same last Zinc quantity: the second waited 3038 ms on `SELECT … FOR UPDATE` and then got "Insufficient farm stock". The remaining quantity was not negative.
- Browser suites are separate Node processes, each with its own Edge process, on `127.0.0.1:8091` and `zaz_agriculture_e2e` only. Final green runs: `01-people` 5, `02-stock` 7, `03-ledger` 6, `04-access` 48, `05-paging` 2. These clicks cover farm create/edit/archive, staff create/salary/disable, rates and rules, godown receive, dispatch 20 / receive 18 / duplicate receive rejected, Hari and Seri usage, daily work, 26-acre rejection, expense rate snapshot, Maund and kilogram sales, advances, search/sort then single and bulk approval, approved Sindhi print, all-farm and date filters, backup rejection, audit CSV, theme/language, and forbidden API calls for Supervisor and Accountant. The season dropdown selects the season stored on new entries; report totals stay farm-scoped across seasons. A dialog's delayed focus used to move later keystrokes into the first field, so a rate edit could rename the category. Focus now stays where the user already clicked. Retested by typing `99` into the rate box: the category name stayed put, the saved rate became 99, and the older expense still showed 10. Suite 04 still logs a Playwright `TargetClosedError` while a context closes; that run's checks passed and the results file was written.
- Performance is a separate database, `zaz_agriculture_perf`: 8 farms, 2 seasons, 16 hari, 10,000 usages, 2,000 expenses, 2,000 sales, 1,000 advances, 400 transfers, 200 daily rows, 80 receipts (15,680 transaction rows), inserted in chunks of 500. Owner bootstrap was 10,675 bytes and did not embed those usage rows. A usage page is 25 rows, 4,757 bytes, about 56 ms all-farm and 5 ms for one farm; page 1 and page 2 both report amount 210,000 and count 10,000. The approved-usage sum for one farm used `idx_usage_farm_status` (`type=ref`, about 1,000 rows) with a median of 1.583 ms across five calls; the first call was 79.8 ms. On the older 402-row sample that same shape was `type=ALL` and slower after the index (0.86 ms then 2.14 ms). No new index was added. Approvals stopped looking up each farm row: 33 queries to 8, time stayed about 37 ms. This is not a production load test.
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
- The 15,680-row measurement is the largest local run. It does not say how the sums behave at hundreds of thousands of rows.
