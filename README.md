# ZAZ AGRICULTURE — Farm Control ERP (Static UI)

**Roman Urdu:** Ye ZAZ Agriculture ke farm ERP ka mukammal static front-end hai — sirf HTML, CSS aur JavaScript. Koi server, framework ya build nahi. `index.html` ko browser mein double-click kar ke kholein, account chunein aur PIN **1234** daalein. Saara data sirf isi browser (localStorage) mein save hota hai. **Ye sirf demo hai, production ke liye secure nahi.**

**English:** Complete static front-end for the ZAZ farm ERP — plain HTML/CSS/JS, no build. Open `index.html` (works from `file://`), pick a demo account, PIN **1234**. Data lives only in this browser's localStorage. **Static demo — NOT a secure production system** (no real authentication, no server-enforced permissions).

## Demo accounts (PIN 1234)
owner (Owner) • office (Office) • jahaniya.a1 / jahaniya.a2 (Accountant, Jahaniya) • jahaniya.sup (Supervisor, Jahaniya) • zareen.sup (Supervisor, Zareen)

## Structure
```
index.html            Login
dashboard.html        KPIs + SVG charts, Hari balances, farm stock
farms.html            Add / edit acreage / archive farms (Owner, Office)
employees.html        Employees, roles, farm assignment, enable/disable (Owner, Office)
hari.html             Hari accounts & crop acres (25-acre limit)
godown.html           Main godown receipts & balances (Owner, Office)
transfers.html        Send stock, farm receiving, printable receiving receipt
usage.html            Stock issue to Hari/Seri, farm balances, All Farms combined view (Owner/Office)
daily.html            Daily Work: supervisors submit work; Owner/Office see last 24 h across all farms
salaries.html         Staff salary sheet, salary payments/advances per month, paid/pending (Owner/Office)
rates.html            Rates & Categories: expense categories, unit, default rate, Hari share (Owner/Office)
expenses.html         Other expenses with office sharing rules
sales.html            Crop sales (50% Hari / Zamindar; Seri 100% Zamindar)
advances.html         Personal Hari advances
settlements.html      Hari individual ledger in Sindhi (RTL) + final settlement print
approvals.html        Office bulk approval (Owner, Office)
rules.html            Expense sharing rules (Owner, Office)
reports.html          Farm summary, crop sales, farm comparison, printable reports
audit.html            Audit log + CSV export
settings.html         Theme, menu language (EN/اردو/سنڌي), JSON backup export/import, reset
css/  variables.css (theme tokens, dark mode) • layout.css • components.css • print.css
js/   utils.js (icons, toasts, modals, data tables, charts) • store.js (localStorage + seed + business logic)
      app.js (auth guard, sidebar/topbar, context, print) • pages/*.js (one module per page)
assets/logo.png (compressed logo)   legacy/ (original files, unchanged for reference)
```
Fonts load from Google Fonts (Inter, Noto Naskh Arabic) when online, with system fallbacks offline.

## v2 changes (client feedback)
- Crop sales: weight in Maund (40 kg) or Kilogram, your own rate, total = weight × rate (live). Shown in sales, Sindhi ledger and reports.
- Daily Work (24h) page with timeline, per-farm counts, farm/date filters.
- Staff salary + pay type on employees; Staff Salaries page for payments/advances (paid/pending).
- "All Farms" toggle on Stock Usage, Transfers and Reports with per-farm breakdown.
- New expense categories (Gobble, Harrow, Rotavator, Laser, Cultivator, Tractor ploughing, Threshing, Diesel, Water/Irrigation, Pesticide spray, Transport…) with editable rates; expense = quantity × rate. Rate changes never alter past entries.
- Data auto-migrates from v1 (storage version 2). Use Settings → Reset demo data for a clean start.
