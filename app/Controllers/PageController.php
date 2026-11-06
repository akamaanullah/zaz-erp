<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Access;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\ReadModel;
use App\Services\ScreenContext;
use App\Services\Snapshot;

final class PageController extends Controller
{
    private const TITLES = [
        'dashboard' => 'Dashboard',
        'farms' => 'Farm Management',
        'employees' => 'Employees & Roles',
        'salaries' => 'Staff Salaries',
        'hari' => 'Hari & Crop Acres',
        'godown' => 'Main Godown',
        'transfers' => 'Transfers & Receiving',
        'usage' => 'Stock Usage',
        'daily' => 'Daily Work (Last 24 Hours)',
        'expenses' => 'Other Expenses',
        'sales' => 'Crop Sales',
        'advances' => 'Hari Advances',
        'settlements' => 'Hari Ledger & Settlement',
        'approvals' => 'Office Bulk Approval',
        'rates' => 'Rates & Categories',
        'rules' => 'Office Rules',
        'reports' => 'Reports & Print',
        'audit' => 'Audit Log',
        'settings' => 'Settings',
    ];

    /** @var array<string, array{0:string,1:bool}> resource, season scoped */
    private const LISTS = [
        'usage' => ['usage', true],
        'expenses' => ['expenses', true],
        'sales' => ['sales', true],
        'advances' => ['advances', true],
        'approvals' => ['approvals', true],
        'reports' => ['sales', true],
        'transfers' => ['transfers', false],
        'godown' => ['receipts', false],
        'daily' => ['daily', false],
        'audit' => ['audit', false],
    ];

    public function page(Request $request): Response
    {
        $user = $this->user();
        $page = preg_replace('/\.html$/', '', trim($request->path, '/')) ?: '';
        Access::assertPage($user, $page);
        $ctx = (new ScreenContext())->resolve($user, $request->query);
        $snap = (new Snapshot())->forUser($user);
        if (($request->query['export'] ?? '') === 'csv' && $page === 'audit') {
            return $this->auditCsv($user, $ctx);
        }
        $list = null;
        if (isset(self::LISTS[$page])) {
            [$resource, $seasoned] = self::LISTS[$page];
            $farm = $ctx['scope'] === 'all' ? '' : $ctx['farm'];
            $list = (new ReadModel())->table($user, [
                'resource' => $resource,
                'farm' => $farm,
                'season' => $seasoned ? $ctx['season'] : '',
                'page' => (int)($request->query['page'] ?? 1),
                'q' => (string)($request->query['q'] ?? ''),
                'sort' => (string)($request->query['sort'] ?? ''),
                'dir' => (string)($request->query['dir'] ?? ''),
                'status' => (string)($request->query['status'] ?? ''),
                'item' => (string)($request->query['item'] ?? ''),
                'kind' => (string)($request->query['kind'] ?? ''),
                'range' => (string)($request->query['range'] ?? ''),
                'date' => (string)($request->query['date'] ?? ''),
            ]);
        }
        $lang = (string)($snap['settings']['language'] ?? 'en');
        return $this->view('layouts/app', [
            'screen' => $page,
            'title' => self::TITLES[$page] ?? 'ZAZ AGRICULTURE',
            'ctx' => $ctx,
            'snap' => $snap,
            'list' => $list,
            'query' => $request->query,
            'nav' => $this->nav((string)$user['role'], $page, $lang, $snap, $ctx),
            'lang' => $lang,
            'me' => $snap['me'],
            'account' => $user,
            'flash' => Session::pullFlash('ok'),
            'formError' => Session::pullFlash('error'),
            'showForm' => (string)($request->query['form'] ?? '') === 'add',
        ]);
    }

    private function auditCsv(array $user, array $ctx): Response
    {
        $pack = (new ReadModel())->table($user, [
            'resource' => 'audit',
            'farm' => $ctx['office'] ? '' : $ctx['farm'],
            'page' => 1,
            'per' => 50,
        ]);
        $lines = ["When,User,Event"];
        $page = 1;
        do {
            if ($page > 1) {
                $pack = (new ReadModel())->table($user, [
                    'resource' => 'audit',
                    'farm' => $ctx['office'] ? '' : $ctx['farm'],
                    'page' => $page,
                    'per' => 50,
                ]);
            }
            foreach ($pack['rows'] as $row) {
                $cells = [];
                foreach ([$row['at'], $row['by'], $row['msg']] as $value) {
                    $text = (string)$value;
                    if (preg_match('/^[=+\-@]/', $text)) {
                        $text = "'" . $text;
                    }
                    $cells[] = '"' . str_replace('"', '""', $text) . '"';
                }
                $lines[] = implode(',', $cells);
            }
            $page++;
        } while (($page - 1) * 50 < (int)$pack['total'] && $page < 40);
        return new Response(200, implode("\n", $lines), [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="zaz-audit-log.csv"',
        ]);
    }

    private function nav(string $role, string $active, string $lang, array $snap, array $ctx): array
    {
        $idx = ['en' => 0, 'ur' => 1, 'sd' => 2][$lang] ?? 0;
        $items = [
            ['group' => 'Overview'],
            ['id' => 'dashboard', 'l' => ['Dashboard', 'ڈیش بورڈ', 'ڊيش بورڊ']],
            ['group' => 'Administration'],
            ['id' => 'farms', 'l' => ['Farms', 'فارمز', 'فارم']],
            ['id' => 'employees', 'l' => ['Employees & Roles', 'ملازمین اور کردار', 'ملازم ۽ ڪردار']],
            ['id' => 'salaries', 'l' => ['Staff Salaries', 'عملے کی تنخواہ', 'عملي جي پگهار']],
            ['group' => 'Field'],
            ['id' => 'hari', 'l' => ['Hari & Crop Acres', 'ہاری اور فصل کے ایکڑ', 'هاري ۽ فصل جا ايڪڙ']],
            ['id' => 'godown', 'l' => ['Main Godown', 'مین گودام', 'مکيه گودام']],
            ['id' => 'transfers', 'l' => ['Transfers & Receiving', 'منتقلی اور وصولی', 'منتقلي ۽ وصولي']],
            ['id' => 'usage', 'l' => ['Stock Usage', 'اسٹاک کا استعمال', 'اسٽاڪ جو استعمال']],
            ['id' => 'daily', 'l' => ['Daily Work (24h)', 'روزانہ کام (24 گھنٹے)', 'روزانو ڪم (24 ڪلاڪ)']],
            ['group' => 'Accounts'],
            ['id' => 'expenses', 'l' => ['Other Expenses', 'دیگر اخراجات', 'ٻيا خرچ']],
            ['id' => 'sales', 'l' => ['Crop Sales', 'فصل کی فروخت', 'فصل جو وڪرو']],
            ['id' => 'advances', 'l' => ['Hari Advances', 'ہاری ایڈوانس', 'هاري اڳواٽ']],
            ['id' => 'settlements', 'l' => ['Hari Ledger & Settlement', 'ہاری کھاتہ', 'هاري کاتو']],
            ['group' => 'Office'],
            ['id' => 'approvals', 'l' => ['Office Bulk Approval', 'دفتر کی اجتماعی منظوری', 'آفيس جي گڏيل منظوري']],
            ['id' => 'rates', 'l' => ['Rates & Categories', 'ریٹس اور زمرے', 'اگهه ۽ قسم']],
            ['id' => 'rules', 'l' => ['Office Rules', 'دفتری اصول', 'آفيس جا قاعدا']],
            ['id' => 'reports', 'l' => ['Reports & Print', 'رپورٹس', 'رپورٽون']],
            ['id' => 'audit', 'l' => ['Audit Log', 'آڈٹ لاگ', 'آڊٽ لاگ']],
            ['id' => 'settings', 'l' => ['Settings', 'ترتیبات', 'سيٽنگون']],
        ];
        $allowed = array_flip($snap['access'] ?? []);
        $pending = 0;
        $bag = $snap['summary']['seasons'][$ctx['season']]['byFarm'][$ctx['farm']] ?? [];
        $pending = (int)($bag['pending'] ?? 0);
        $nav = [];
        foreach ($items as $item) {
            if (isset($item['group'])) {
                $nav[] = $item;
                continue;
            }
            if (!isset($allowed[$item['id']])) {
                continue;
            }
            $nav[] = [
                'id' => $item['id'],
                'label' => $item['l'][$idx] ?? $item['l'][0],
                'active' => $item['id'] === $active,
                'count' => $item['id'] === 'approvals' ? $pending : 0,
            ];
        }
        return $nav;
    }
}
