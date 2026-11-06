# Readiness notes

Local XAMPP app. Not a public production launch.

## What was measured

- `php tests/run.php`: 94 passed, 0 failed. `php tests/scenario.php`: 18 passed, 0 failed. Both use only `zaz_agriculture_test`.
- On MariaDB 10.4.32, inserting 400 usage rows one-by-one took 14.7 seconds (round trips, not a report). `SELECT SUM(amount) FROM stock_usages WHERE farm_id = ? AND status = ?` over 402 rows took 0.86 ms before a `(farm_id, status)` index and 2.14 ms after it. EXPLAIN stayed `type=ALL`, `rows=402`, `Using where` both times. The index is present for larger data; this sample is too small for the optimizer to prefer it.
- Browser click-through of every module was not available in this environment. Headless Edge can capture a page in a separate profile. That is a screenshot, not an end-to-end click test.
- The existing local database `zaz_agriculture` was not wiped. Its rows are the original demo seed (farms such as 68 Mori and Jahaniya, sample stock and ledgers). Ask before deleting them.

## Security fixed in this pass

- Prototype and `.git` are forbidden by the site `.htaccess`.
- Sessions expire after 8 idle hours, and end if the account is disabled or its role or farm changes.
- Login and authenticator failures use one generic message. Authenticator codes cannot be reused in the same time step. Recovery codes are stored hashed and work once.
- Backup import rejects account or secret fields and files over 2,000,000 bytes or 5,000 rows in one list.
- Demo reset stays available only when `ZAZ_APP_ENV=local` unless `ZAZ_ALLOW_DEMO_RESET=1` is set explicitly.
- Web responses do not display PHP errors.
- Audit CSV cells that start with `=`, `+`, `-`, or `@` are prefixed so a spreadsheet does not treat them as formulas.
- Farm roles no longer receive main-godown balances, and their list queries are filtered by `farm_id` in SQL.

## Still open

- Offline PWA sync is not implemented.
- Android APK is not implemented.
- Owner and Office still receive a full snapshot for their screens. There is no server pagination yet.
- Content-Security-Policy still allows `'unsafe-inline'` because the current pages use inline scripts.
- Local MariaDB is `root` with an empty password. That is not a production account. This change does not create a limited database user.
- HTTPS, hosted backups, and a real `ZAZ_APP_KEY` are deployment steps. Authenticator enrollment stays off until that key exists.
- No claim of perfect security or unlimited scale.
