# ZAZ AGRICULTURE — Farm Control ERP

Custom PHP MVC application for the ZAZ farm ERP. The static prototype is preserved unchanged in `prototype/` for reference. The running application uses server sessions, a MySQL database, and role checks on every page and API call.

## Requirements

- PHP 8.2 or newer (this machine: PHP 8.4)
- MySQL or MariaDB with PDO (this machine: MariaDB 10.4 via XAMPP)
- Apache with `mod_rewrite` and `AllowOverride All` for `htdocs` (standard XAMPP)

No Composer packages and no Laravel or Symfony.

## Locations

- Checkout: `D:\xampp\htdocs\zaz-agriculture`
- Application URL: `http://localhost/zaz-agriculture/public/`
- Static prototype (reference only): `http://localhost/zaz-agriculture/prototype/index.html`

`public/` is the document root for the application. `app/`, `config/`, `database/`, `storage/`, and `tests/` are not meant to be requested directly.

## Database setup

The app reads `ZAZ_DB_HOST`, `ZAZ_DB_PORT`, `ZAZ_DB_NAME`, `ZAZ_DB_USER`, and `ZAZ_DB_PASS`. If those are unset, it uses `127.0.0.1`, port `3306`, database `zaz_agriculture`, user `root`, and an empty password, which matches a default local XAMPP install. Copy `config/config.example.php` to `config/config.local.php` to override this. `config.local.php` is gitignored.

Migrations refuse any database name other than `zaz_agriculture` or `zaz_agriculture_test`. They do not drop existing databases.

```bat
D:\xampp\php\php.exe database\migrate.php
D:\xampp\php\php.exe database\seed.php
```

`seed.php` loads sample farms and entries only when the user table is empty. It writes one-time local passwords to `storage/dev-credentials.local.txt`. That file is gitignored. Do not commit it.

## Tests

```bat
D:\xampp\php\php.exe tests\run.php
```

Tests create and drop only `zaz_agriculture_test`.

## Roles

Owner sees every farm and is the only role that can create or change Owner accounts. The last active Owner cannot be removed. Office manages farms, non-owner staff, salaries, the main godown, dispatch, rates, rules, approvals, and reports, and cannot change Owner records. A supervisor works on the assigned farm: dashboard, Hari view, receiving, stock usage, daily work, ledger view, and limited reports. An accountant on the assigned farm maintains Hari accounts, stock usage, expenses, crop sales, advances, and the ledger. Receiving and daily work are view-only for the accountant. Hari and Kamdar have records and no login.

Farm staff entries stay pending until Owner or Office approves them. Owner and Office entries are approved when saved. Approval does not move stock a second time. The on-screen ledger can include pending rows; the Sindhi print is approved records only.

## Not in this milestone

- Authenticator (TOTP)
- Offline PWA sync and conflict handling
- Android APK
- Rejection workflow, cash payment, and opening-balance carry-forward
- This build is not a production launch
