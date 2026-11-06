<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Database;
use App\Models\AccountModel;
use App\Models\CatalogModel;
use App\Models\FarmModel;
use App\Models\HariModel;
use App\Models\SeasonModel;
use App\Models\StockModel;
use App\Models\UserModel;
use App\Models\WorkModel;

final class DemoSeeder
{
    public function seedIfEmpty(): bool
    {
        if (UserModel::count() > 0) {
            return false;
        }
        $creds = [];
        Database::transaction(function () use (&$creds): void {
            $this->seedFoundation();
            $creds = $this->seedUsers();
            $this->seedSample();
            Audit::add(UserModel::findByUsername('owner'), 'seed', 'Demo data seeded');
        });
        $this->writeCredentials($creds);
        return true;
    }

    public function seedFoundation(): void
    {
        foreach (['Kharif 2026', 'Rabi 2026-27'] as $i => $name) {
            if (!SeasonModel::findByName($name)) {
                SeasonModel::insert($name, $i);
            }
        }
        $meta = [
            '68 Mori' => [200, 45, 'Mori, Sanghar'],
            'Jahaniya' => [150, 30, 'Jahaniya'],
            'Zareen' => [120, 20, 'Tando Adam'],
            'Juna Mori' => [90, 15, 'Mori'],
        ];
        $names = ['68 Mori', 'Juna Mori', 'Village', 'Sugar Mill', 'Jokho', 'Tando Adam', 'Zareen', 'Dalda Mill', 'Mehran', 'Koromal Dewan', 'Jahaniya', 'Other Farm'];
        foreach ($names as $name) {
            $this->upsertFarm([
                'name' => $name,
                'acres' => $meta[$name][0] ?? 0,
                'seri' => $meta[$name][1] ?? 0,
                'location' => $meta[$name][2] ?? '',
                'purchase' => '',
                'archived' => false,
            ]);
        }
    }

    public function seedSample(): void
    {
        $this->seedCategories();
        $owner = UserModel::findByUsername('owner');
        $office = UserModel::findByUsername('office');
        $ja1 = UserModel::findByUsername('jahaniya.a1');
        $js1 = UserModel::findByUsername('jahaniya.sup');
        $zs = UserModel::findByUsername('zareen.sup');
        $actor = (int)($office['id'] ?? $owner['id']);
        $season = SeasonModel::findByName('Kharif 2026');
        $rabi = SeasonModel::findByName('Rabi 2026-27');
        $mori = FarmModel::findByName('68 Mori');
        $jah = FarmModel::findByName('Jahaniya');
        $zar = FarmModel::findByName('Zareen');

        $h1 = $this->hari($mori, 'Shabbir Malik', 'Sample address', [
            ['Sugarcane', 10, $season],
            ['Wheat', 5, $rabi],
            ['Mustard', 5, $rabi],
        ]);
        $h2 = $this->hari($mori, 'Ghulam Rasool', 'Deh Mori', [['Cotton', 12, $season]]);
        $h3 = $this->hari($jah, 'Allah Dino', 'Goth Jahaniya', [['Sugarcane', 15, $season]]);
        $h4 = $this->hari($zar, 'Muhammad Hassan', 'Zareen Village', [['Wheat', 8, $rabi]]);
        unset($h4);

        $this->receipt('Urea', 500, 4500, '2026-11-02', $actor);
        $this->receipt('DAP', 500, 12000, '2026-11-02', $actor);
        $this->receipt('Zinc', 120, 2200, '2026-10-20', $actor);
        $this->receipt('Pesticide', 80, 3500, '2026-10-18', $actor);

        $this->transfer($mori, 'Urea', 100, 98, '2026-10-25', $actor, (int)$js1['id']);
        $this->transfer($mori, 'DAP', 60, 60, '2026-10-25', $actor, (int)$js1['id']);
        $this->transfer($jah, 'Urea', 80, 80, '2026-10-27', $actor, (int)$js1['id']);
        $this->transfer($zar, 'DAP', 40, null, '2026-11-01', $actor, null);

        $this->usage($mori, $season, 'Urea', 20, 4500, $h1, 'Sugarcane', 50, true, 'accountant', $actor);
        $this->usage($mori, $season, 'DAP', 10, 12000, $h2, 'Cotton', 50, false, 'accountant', $actor);
        $this->usage($mori, $season, 'Urea', 15, 4500, null, 'Seri', 0, true, 'supervisor', $actor);
        $this->usage($jah, $season, 'Urea', 25, 4500, $h3, 'Sugarcane', 50, false, 'accountant', (int)($ja1['id'] ?? $actor));

        $this->expense($mori, $season, 'Tractor Preparation', 10, 3500, 'per acre', $h1, 'Sugarcane', 100, true, 'accountant', $actor);
        $this->expense($mori, $season, 'Harvest Labour', 5.6, 5000, 'per acre', $h2, 'Cotton', 100, false, 'accountant', $actor);
        $this->expense($mori, $season, 'Other', 1, 18000, 'lump sum', null, 'Seri', 0, true, 'office', $actor);
        $this->expense($jah, $season, 'Sugarcane Planting Labour', 7, 6000, 'per acre', $h3, 'Sugarcane', 100, true, 'accountant', (int)($ja1['id'] ?? $actor));

        $this->sale($mori, $season, $h1, 'Sugarcane', 'maund', 250, 3400, true, 'accountant', $actor);
        $this->sale($mori, $season, $h2, 'Cotton', 'maund', 65, 8000, false, 'accountant', $actor);
        $this->sale($mori, $season, null, 'Sugarcane', 'maund', 500, 2500, true, 'office', $actor);
        $this->sale($jah, $season, $h3, 'Sugarcane', 'maund', 300, 3200, true, 'accountant', (int)($ja1['id'] ?? $actor));
        $this->sale($zar, $season, null, 'Wheat', 'kg', 3000, 100, true, 'office', $actor);

        $this->advance($mori, $season, $h1, 'Wedding expense', 50000, true, 'accountant', $actor);
        $this->advance($mori, $season, $h2, 'Medical', 15000, false, 'accountant', $actor);
        $this->advance($jah, $season, $h3, 'Household', 30000, true, 'accountant', (int)($ja1['id'] ?? $actor));

        $month = date('Y-m');
        $this->pay((int)$office['id'], null, $month, 'Salary', 85000, 'Paid', date('Y-m-d', time() - 48 * 3600), 'Bank transfer', $actor);
        $this->pay((int)$ja1['id'], null, $month, 'Advance', 10000, 'Paid', date('Y-m-d', time() - 120 * 3600), 'Medical', $actor);
        $this->pay((int)$js1['id'], null, $month, 'Salary', 45000, 'Pending', date('Y-m-d', time() - 2 * 3600), '', $actor);

        $this->work($jah, 3, 'Irrigation', 'Sugarcane block 3 & 4 watered from tubewell', '2 labour, tubewell', 15, 6, '', (int)$js1['id']);
        $this->work($jah, 7, 'Fertilizer application', 'Urea top dressing on sugarcane', '4 labour', 10, 5, '10 bags used', (int)$js1['id']);
        $this->work($zar, 5, 'Ploughing / Gobble', 'Gobble on wheat field before sowing', 'Tractor + gobble, 1 driver', 8, 7, '', (int)$zs['id']);
        $this->work($mori, 10, 'Pesticide spray', 'Cotton spray against whitefly', '3 labour, spray machine', 12, 4, '', $actor);
        $this->work($zar, 30, 'Weeding', 'Manual weeding in mustard', '6 labour', 4, 8, 'Older than 24h — for history', (int)$zs['id']);
    }

    public function restoreBackup(array $db, int $actorId): void
    {
        foreach ($db['seasons'] ?? [] as $i => $name) {
            if (is_string($name) && !SeasonModel::findByName($name)) {
                SeasonModel::insert($name, (int)$i);
            }
        }
        foreach ($db['farms'] ?? [] as $farm) {
            if (is_array($farm) && !empty($farm['name'])) {
                $this->upsertFarm($farm);
            }
        }
        foreach ($db['categories'] ?? [] as $cat) {
            if (!is_array($cat) || empty($cat['name'])) {
                continue;
            }
            if (CatalogModel::findByName((string)$cat['name'])) {
                continue;
            }
            $stock = !empty($cat['stock']) || in_array($cat['name'], CatalogModel::ITEMS, true);
            CatalogModel::insert((string)$cat['name'], (string)($cat['unit'] ?? 'lump sum'), num($cat['rate'] ?? 0), (int)($cat['share'] ?? 50), $stock ? 1 : 0);
        }
        $hariMap = [];
        $plotMap = [];
        foreach ($db['haris'] ?? [] as $hari) {
            $farm = FarmModel::findByName((string)($hari['farm'] ?? ''));
            if (!$farm) {
                continue;
            }
            $hid = HariModel::insert((int)$farm['id'], (string)$hari['name'], (string)($hari['address'] ?? ''));
            $hariMap[(string)$hari['id']] = $hid;
            foreach ($hari['plots'] ?? [] as $plot) {
                $pid = HariModel::insertPlot((int)$farm['id'], $hid, (string)$plot['name'], num($plot['acres'] ?? 0), 'hari');
                $plotMap[(string)$plot['id']] = $pid;
            }
            foreach ($hari['alloc'] ?? [] as $alloc) {
                $season = SeasonModel::findByName((string)($alloc['season'] ?? ''));
                $pid = $plotMap[(string)($alloc['plotId'] ?? '')] ?? null;
                if ($season && $pid) {
                    HariModel::insertAllocation($pid, $hid, (int)$season['id'], (string)$alloc['crop']);
                }
            }
        }
        foreach ($db['receipts'] ?? [] as $row) {
            $this->receipt((string)$row['item'], num($row['qty']), num($row['rate']), (string)$row['date'], $actorId);
        }
        foreach ($db['transfers'] ?? [] as $row) {
            $farm = FarmModel::findByName((string)$row['farm']);
            if (!$farm) {
                continue;
            }
            $received = array_key_exists('received', $row) && $row['received'] !== null ? num($row['received']) : null;
            $this->transfer($farm, (string)$row['item'], num($row['qty']), $received, (string)$row['date'], $actorId, $received === null ? null : $actorId);
        }
        foreach ($db['usage'] ?? [] as $row) {
            $this->restoreMoney('usage', $row, $hariMap, $actorId);
        }
        foreach ($db['expenses'] ?? [] as $row) {
            $this->restoreMoney('expense', $row, $hariMap, $actorId);
        }
        foreach ($db['sales'] ?? [] as $row) {
            $farm = FarmModel::findByName((string)($row['farm'] ?? ''));
            $season = SeasonModel::findByName((string)($row['season'] ?? ''));
            if (!$farm || !$season) {
                continue;
            }
            $hariId = ($row['target'] ?? '') === 'seri' ? null : ($hariMap[(string)($row['hariId'] ?? '')] ?? null);
            $amount = num($row['amount'] ?? 0);
            $hariShare = $hariId ? num($amount / 2) : 0;
            $status = !empty($row['approved']) ? 'approved' : 'pending';
            AccountModel::insertSale([
                'farm_id' => (int)$farm['id'],
                'season_id' => (int)$season['id'],
                'crop' => (string)($row['crop'] ?? ''),
                'target_type' => $hariId ? 'hari' : 'seri',
                'hari_id' => $hariId,
                'weight_unit' => ($row['unit'] ?? '') === 'kg' ? 'kg' : 'maund',
                'weight' => num($row['weight'] ?? 0),
                'rate_snapshot' => num($row['rate'] ?? 0),
                'kg' => num($row['kg'] ?? 0),
                'amount' => $amount,
                'hari_share_amount' => $hariShare,
                'owner_share_amount' => num($amount - $hariShare),
                'status' => $status,
                'by_label' => (string)($row['by'] ?? 'import'),
                'created_by' => $actorId,
                'approved_by' => $status === 'approved' ? $actorId : null,
                'approved_at' => $status === 'approved' ? date('Y-m-d H:i:s') : null,
            ]);
        }
        foreach ($db['advances'] ?? [] as $row) {
            $farm = FarmModel::findByName((string)($row['farm'] ?? ''));
            $season = SeasonModel::findByName((string)($row['season'] ?? ''));
            $hariId = $hariMap[(string)($row['hariId'] ?? '')] ?? null;
            if (!$farm || !$season || !$hariId) {
                continue;
            }
            $status = !empty($row['approved']) ? 'approved' : 'pending';
            AccountModel::insertAdvance([
                'farm_id' => (int)$farm['id'],
                'season_id' => (int)$season['id'],
                'hari_id' => $hariId,
                'reason' => (string)($row['reason'] ?? ''),
                'amount' => num($row['amount'] ?? 0),
                'status' => $status,
                'by_label' => (string)($row['by'] ?? 'import'),
                'created_by' => $actorId,
                'approved_by' => $status === 'approved' ? $actorId : null,
                'approved_at' => $status === 'approved' ? date('Y-m-d H:i:s') : null,
            ]);
        }
        foreach ($db['dailyWork'] ?? [] as $row) {
            $farm = FarmModel::findByName((string)($row['farm'] ?? ''));
            if (!$farm) {
                continue;
            }
            WorkModel::insertDaily([
                'farm_id' => (int)$farm['id'],
                'work_date' => (string)($row['date'] ?? date('Y-m-d')),
                'occurred_at' => date('Y-m-d H:i:s', strtotime((string)($row['at'] ?? 'now'))),
                'work_type' => (string)($row['type'] ?? 'Other'),
                'description' => (string)($row['desc'] ?? ''),
                'resources' => (string)($row['resources'] ?? ''),
                'acres' => isset($row['acres']) ? num($row['acres']) : null,
                'hours' => isset($row['hours']) ? num($row['hours']) : null,
                'notes' => (string)($row['notes'] ?? ''),
                'created_by' => $actorId,
            ]);
        }
    }

    private function restoreMoney(string $kind, array $row, array $hariMap, int $actorId): void
    {
        $farm = FarmModel::findByName((string)($row['farm'] ?? ''));
        $season = SeasonModel::findByName((string)($row['season'] ?? ''));
        if (!$farm || !$season) {
            return;
        }
        $hariId = ($row['target'] ?? '') === 'seri' ? null : ($hariMap[(string)($row['hariId'] ?? '')] ?? null);
        $status = !empty($row['approved']) ? 'approved' : 'pending';
        $common = [
            'farm_id' => (int)$farm['id'],
            'season_id' => (int)$season['id'],
            'qty' => num($row['qty'] ?? 0),
            'rate_snapshot' => num($row['rate'] ?? 0),
            'amount' => num($row['amount'] ?? 0),
            'target_type' => $hariId ? 'hari' : 'seri',
            'hari_id' => $hariId,
            'crop' => (string)($row['crop'] ?? ''),
            'share_snapshot' => (int)($row['share'] ?? 0),
            'status' => $status,
            'by_label' => (string)($row['by'] ?? 'import'),
            'created_by' => $actorId,
            'approved_by' => $status === 'approved' ? $actorId : null,
            'approved_at' => $status === 'approved' ? date('Y-m-d H:i:s') : null,
        ];
        if ($kind === 'usage') {
            StockModel::insertUsage($common + ['item_name' => (string)($row['item'] ?? '')]);
            return;
        }
        AccountModel::insertExpense($common + [
            'category_name' => (string)($row['category'] ?? $row['item'] ?? ''),
            'unit_snapshot' => (string)($row['unit'] ?? ''),
        ]);
    }

    /** @return list<array{username:string,role:string,farm:string,password:string}> */
    private function seedUsers(): array
    {
        $rows = [
            ['ZAZ Owner', 'owner', 'owner', '', 0, 'Monthly salary'],
            ['Main Office', 'office', 'office', '', 85000, 'Monthly salary'],
            ['Jahaniya Accountant 1', 'jahaniya.a1', 'accountant', 'Jahaniya', 60000, 'Monthly salary'],
            ['Jahaniya Accountant 2', 'jahaniya.a2', 'accountant', 'Jahaniya', 55000, 'Monthly salary'],
            ['Jahaniya Supervisor', 'jahaniya.sup', 'supervisor', 'Jahaniya', 45000, 'Monthly salary'],
            ['Zareen Supervisor', 'zareen.sup', 'supervisor', 'Zareen', 42000, 'Monthly salary'],
        ];
        $out = [];
        foreach ($rows as [$name, $username, $role, $farmName, $salary, $pay]) {
            $password = bin2hex(random_bytes(4)) . 'Aa1';
            $farmId = $farmName !== '' ? (int)FarmModel::findByName($farmName)['id'] : null;
            UserModel::insert($name, $username, password_hash($password, PASSWORD_DEFAULT), $role, $farmId, (float)$salary, $pay);
            $out[] = ['username' => $username, 'role' => $role, 'farm' => $farmName, 'password' => $password];
        }
        return $out;
    }

    private function writeCredentials(array $creds): void
    {
        $path = project_root() . '/storage/dev-credentials.local.txt';
        $lines = [
            'ZAZ Agriculture local development accounts',
            'Generated ' . date('c'),
            'This file is gitignored. Do not commit it or share it.',
            '',
        ];
        foreach ($creds as $row) {
            $lines[] = $row['username'] . ' | ' . $row['role'] . ' | ' . ($row['farm'] !== '' ? $row['farm'] : 'all farms');
            $lines[] = 'password: ' . $row['password'];
            $lines[] = '';
        }
        file_put_contents($path, implode(PHP_EOL, $lines));
    }

    private function seedCategories(): void
    {
        $cats = [
            ['DAP', 'per bag', 12000, 50, 1], ['Urea', 'per bag', 4500, 50, 1], ['Zinc', 'per bag', 2200, 50, 1], ['Pesticide', 'per litre', 3500, 50, 1],
            ['Tractor Preparation', 'per acre', 3500, 100, 0], ['Sugarcane Planting Labour', 'per acre', 6000, 100, 0], ['Harvest Labour', 'per acre', 5000, 100, 0],
            ['Gobble', 'per acre', 2500, 100, 0], ['Harrow', 'per acre', 2000, 100, 0], ['Rotavator', 'per acre', 3000, 100, 0], ['Laser', 'per hour', 4500, 50, 0],
            ['Cultivator', 'per acre', 1800, 100, 0], ['Tractor ploughing', 'per acre', 2800, 100, 0], ['Threshing', 'per hour', 3500, 50, 0],
            ['Diesel', 'per litre', 290, 50, 0], ['Water / Irrigation', 'per acre', 1500, 50, 0], ['Pesticide spray', 'per acre', 800, 50, 0],
            ['Transport', 'per trip', 6000, 50, 0], ['Other', 'lump sum', 0, 50, 0],
        ];
        foreach ($cats as [$name, $unit, $rate, $share, $stock]) {
            if (!CatalogModel::findByName($name)) {
                CatalogModel::insert($name, $unit, (float)$rate, (int)$share, (int)$stock);
            }
        }
    }

    private function upsertFarm(array $farm): int
    {
        $purchase = trim((string)($farm['purchase'] ?? ''));
        $purchase = $purchase === '' ? null : $purchase;
        $existing = FarmModel::findByName((string)$farm['name']);
        if ($existing) {
            FarmModel::update((int)$existing['id'], num($farm['acres'] ?? 0), num($farm['seri'] ?? 0), (string)($farm['location'] ?? ''), $purchase);
            FarmModel::setArchived((int)$existing['id'], !empty($farm['archived']) ? 1 : 0);
            return (int)$existing['id'];
        }
        $id = FarmModel::insert((string)$farm['name'], num($farm['acres'] ?? 0), num($farm['seri'] ?? 0), (string)($farm['location'] ?? ''), $purchase);
        if (!empty($farm['archived'])) {
            FarmModel::setArchived($id, 1);
        }
        return $id;
    }

    private function hari(array $farm, string $name, string $address, array $crops): int
    {
        $id = HariModel::insert((int)$farm['id'], $name, $address);
        foreach ($crops as [$crop, $acres, $season]) {
            $plot = HariModel::insertPlot((int)$farm['id'], $id, $crop . ' plot', (float)$acres, 'hari');
            HariModel::insertAllocation($plot, $id, (int)$season['id'], $crop);
        }
        return $id;
    }

    private function receipt(string $item, float $qty, float $rate, string $date, int $userId): void
    {
        StockModel::insertReceipt($item, $qty, $rate, $date, $userId);
    }

    private function transfer(array $farm, string $item, float $qty, ?float $received, string $date, int $userId, ?int $receiver): void
    {
        $id = StockModel::insertTransfer((int)$farm['id'], $item, $qty, $date, $userId);
        if ($received !== null && $receiver) {
            StockModel::markReceived($id, $received, $receiver);
        }
    }

    private function usage(array $farm, array $season, string $item, float $qty, float $rate, ?int $hariId, string $crop, int $share, bool $approved, string $by, int $userId): void
    {
        StockModel::insertUsage([
            'farm_id' => (int)$farm['id'],
            'season_id' => (int)$season['id'],
            'item_name' => $item,
            'qty' => $qty,
            'rate_snapshot' => $rate,
            'amount' => money_mul($qty, $rate),
            'target_type' => $hariId ? 'hari' : 'seri',
            'hari_id' => $hariId,
            'crop' => $crop,
            'share_snapshot' => $hariId ? $share : 0,
            'status' => $approved ? 'approved' : 'pending',
            'by_label' => $by,
            'created_by' => $userId,
            'approved_by' => $approved ? $userId : null,
            'approved_at' => $approved ? date('Y-m-d H:i:s') : null,
        ]);
    }

    private function expense(array $farm, array $season, string $category, float $qty, float $rate, string $unit, ?int $hariId, string $crop, int $share, bool $approved, string $by, int $userId): void
    {
        AccountModel::insertExpense([
            'farm_id' => (int)$farm['id'],
            'season_id' => (int)$season['id'],
            'category_name' => $category,
            'qty' => $qty,
            'rate_snapshot' => $rate,
            'unit_snapshot' => $unit,
            'amount' => money_mul($qty, $rate),
            'target_type' => $hariId ? 'hari' : 'seri',
            'hari_id' => $hariId,
            'crop' => $crop,
            'share_snapshot' => $hariId ? $share : 0,
            'status' => $approved ? 'approved' : 'pending',
            'by_label' => $by,
            'created_by' => $userId,
            'approved_by' => $approved ? $userId : null,
            'approved_at' => $approved ? date('Y-m-d H:i:s') : null,
        ]);
    }

    private function sale(array $farm, array $season, ?int $hariId, string $crop, string $unit, float $weight, float $rate, bool $approved, string $by, int $userId): void
    {
        $amount = money_mul($weight, $rate);
        $kg = $unit === 'maund' ? num($weight * CatalogModel::MAUND_KG) : $weight;
        $hariShare = $hariId ? num($amount / 2) : 0;
        AccountModel::insertSale([
            'farm_id' => (int)$farm['id'],
            'season_id' => (int)$season['id'],
            'crop' => $crop,
            'target_type' => $hariId ? 'hari' : 'seri',
            'hari_id' => $hariId,
            'weight_unit' => $unit,
            'weight' => $weight,
            'rate_snapshot' => $rate,
            'kg' => $kg,
            'amount' => $amount,
            'hari_share_amount' => $hariShare,
            'owner_share_amount' => num($amount - $hariShare),
            'status' => $approved ? 'approved' : 'pending',
            'by_label' => $by,
            'created_by' => $userId,
            'approved_by' => $approved ? $userId : null,
            'approved_at' => $approved ? date('Y-m-d H:i:s') : null,
        ]);
    }

    private function advance(array $farm, array $season, int $hariId, string $reason, float $amount, bool $approved, string $by, int $userId): void
    {
        AccountModel::insertAdvance([
            'farm_id' => (int)$farm['id'],
            'season_id' => (int)$season['id'],
            'hari_id' => $hariId,
            'reason' => $reason,
            'amount' => $amount,
            'status' => $approved ? 'approved' : 'pending',
            'by_label' => $by,
            'created_by' => $userId,
            'approved_by' => $approved ? $userId : null,
            'approved_at' => $approved ? date('Y-m-d H:i:s') : null,
        ]);
    }

    private function pay(int $userId, ?int $staffId, string $month, string $kind, float $amount, string $status, string $date, string $note, int $actor): void
    {
        WorkModel::insertPayment([
            'user_id' => $userId,
            'staff_id' => $staffId,
            'pay_month' => $month,
            'kind' => $kind,
            'amount' => $amount,
            'status' => $status,
            'pay_date' => $date,
            'note' => $note,
            'created_by' => $actor,
        ]);
    }

    private function work(array $farm, int $hoursAgo, string $type, string $desc, string $resources, float $acres, float $hours, string $notes, int $userId): void
    {
        $stamp = time() - ($hoursAgo * 3600);
        WorkModel::insertDaily([
            'farm_id' => (int)$farm['id'],
            'work_date' => date('Y-m-d', $stamp),
            'occurred_at' => date('Y-m-d H:i:s', $stamp),
            'work_type' => $type,
            'description' => $desc,
            'resources' => $resources,
            'acres' => $acres,
            'hours' => $hours,
            'notes' => $notes,
            'created_by' => $userId,
        ]);
    }
}
