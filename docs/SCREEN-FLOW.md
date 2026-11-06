# Screen-by-screen flow

Yeh local app ka asal flow hai. Numbers neeche **example** hain, live database ki current balances nahi.

Har screen ka table aur form pehle PHP response mein aata hai. `PageController` farm, season, aur page data tayyar karta hai. `views/layouts/app.php` sidebar aur top bar rakhta hai, aur `views/screens/` wali file us screen ka HTML likhti hai. Save ab bhi `POST /api/...` par hai. JavaScript sirf dialog kholta hai, chart banata hai, aur form ko JSON ke sath bhejta hai. Purana `views/pages/app.php` shell, jo poori screen `public/assets/js/pages/*.js` se banata tha, hata diya gaya hai. Woh page scripts response mein load nahi hoti.

Sign-in: `http://localhost/zaz-agriculture/public/login`  
Username aur password. Koi account list nahi. Role aur farm server account se aata hai. Sab roles `/dashboard` par jaate hain, lekin data apni scope ka hota hai.

Upar farm aur season ka selector hai. Sales, usage history, expenses, advances, approvals, Hari ledger, settlement print aur report totals **us season** ke hain. SQL farm ke sath season bhi lagati hai. Doosra season select karne se doosre farm ka data nahi khulta. Godown, transfers, farm stock remaining, salaries aur audit season se nahi kat-te: stock asal quantity hai, salary month ki hai, audit waqai ki list hai. Jis purani row par season na ho, woh "No season" ke naam se alag rehti hai. Koi row delete nahi hoti.

## Pehle setup (Owner ya Office)

1. **Farms** (`/farms`) — farm ka naam, total acres, Seri acres. Hari land = total − Seri. Example: 100 acres, Seri 20, Hari land 80.
2. **Employees & Roles** (`/employees`) — username unique hota hai, lowercase save hota hai. Supervisor aur Accountant ko ek farm do. Kamdar ka login nahi hota. **Sirf Owner, Owner account bana, badal, ya band kar sakta hai.** Office Owner ka record nahi chhu sakta: na naya Owner, na username, na disable.
3. **Staff Salaries** (`/salaries`) — Office aur Owner non-owner staff ki salary dekhte hain. Owner ki salary sirf Owner dekhta hai.
4. **Hari & Crop Acres** (`/hari`) — Accountant (ya Owner/Office) Hari banata hai. Ek Hari ki physical plots 25 acres se zyada nahi. Wahi plot doosri season mein dobara use ho to acres dobara nahi juchte.
5. **Rates & Categories** (`/rates`) aur **Office Rules** (`/rules`) — unit, default rate, Hari share 0, 50, ya 100. Purani entries par asar nahi hota.
6. **Main Godown** (`/godown`) — item, qty, office rate. Example: 100 Urea @ 10.50.

## Roz ka stock

1. **Transfers & Receiving** (`/transfers`) — Office 20 bhejta hai. Yeh expense nahi. Supervisor asal qty receive karta hai, maslan 18. Farq 2 record rehta hai. Dobara receive nahi hota.
2. **Stock Usage** (`/usage`) — farm stock se Hari ya Seri ko qty. Cost = qty × us waqt ka office rate, ek dafa. Example: 5 × 10.50 = 52.50. Hari share 50% ho to Hari par 26.25. Seri par Hari share hamesha 0.
3. **Daily Work** (`/daily`) — Supervisor apni farm ka kaam likhta hai. Accountant sirf dekh sakta hai. Owner/Office kisi farm ka likh sakte hain.

## Hisaab

1. **Other Expenses** (`/expenses`) — qty × rate. Share snapshot. Seri par Hari share 0.
2. **Crop Sales** (`/sales`) — Maund (40 kg) ya Kg. Total = weight × usi unit ka rate. Doosri conversion nahi. Hari sale 50/50. Example: 2 maund × 100 = 200, Hari share 100. Seri sale 100% Owner.
3. **Hari Advances** (`/advances`) — Hari ke ledger se kat ta hai. Farm operating expense nahi.
4. **Office Bulk Approval** (`/approvals`) — Owner/Office. Farm staff ki entries pending rehti hain. Approve dobara stock nahi kat ta. Owner/Office ki apni entries save hote hi approved.
5. **Hari Ledger & Settlement** (`/settlements`) — screen par pending bhi nazar aa sakti hai. Print/PDF sirf approved. Formula: Hari sale share − Hari cost share − advances. Example: 100 − 26.25 − 20.50 − 15 = 38.25.
6. **Staff Salaries** (`/salaries`) — staff ki salary/advance. Hari advance se alag.
7. **Reports & Print** (`/reports`) aur **Audit Log** (`/audit`) — dashboard, report, aur ledger ke totals SQL se saari matching rows par aate hain, sirf is page par nahi. Lambi lists 25-25 server page par aati hain. Audit sirf append hota hai. CSV mein formula characters escape hote hain.

## Role matrix

| Screen | Owner | Office | Supervisor | Accountant |
|---|---|---|---|---|
| Dashboard | all farms | all farms | own farm | own farm |
| Farms | edit | edit | no | no |
| Employees | Owner records included | non-owner staff only; cannot create or change an Owner | no | no |
| Salaries | all staff | non-owner staff | no | no |
| Hari | edit | edit | view | edit own farm |
| Godown | yes | yes | no | no |
| Transfers | dispatch and receive | dispatch only | receive own farm | view |
| Stock Usage | yes | yes | own farm | own farm |
| Daily Work | submit any farm | submit any farm | submit own | view |
| Expenses, Sales, Advances | yes | yes | no | own farm |
| Ledger | yes | yes | view | yes |
| Approvals, Rates, Rules | yes | yes | no | no |
| Reports, Audit | all | all | own farm | own farm |
| Settings | yes, authenticator optional | yes, authenticator optional | theme/language | theme/language |

Authenticator tabhi chalta hai jab server par `ZAZ_APP_KEY` set ho. Purane accounts khud enroll nahi hote.
