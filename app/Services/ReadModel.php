<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Access;
use App\Core\Database;
use App\Core\HttpException;
use App\Models\CatalogModel;
use App\Models\FarmModel;
use App\Models\HariModel;
use App\Models\SeasonModel;

/**
 * Aggregates and paged lists. Page payloads do not include every transaction.
 */
final class ReadModel
{
    public function summary(array $user, array $farmNames): array
    {
        $scope = $this->scope($user);
        $pdo = Database::pdo();
        $where = $scope === null ? '' : ' WHERE farm_id = ?';
        $params = $scope === null ? [] : [$scope];
        $byFarm = [];
        $add = function (string $sql, string $field) use ($pdo, $params, $farmNames, &$byFarm): void {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            foreach ($stmt->fetchAll() as $row) {
                $name = $farmNames[(int)$row['farm_id']] ?? '';
                if ($name === '') {
                    continue;
                }
                $byFarm[$name][$field] = ($byFarm[$name][$field] ?? 0) + (float)$row['v'];
            }
        };
        $add('SELECT farm_id, SUM(amount) v FROM sales' . $where . ' GROUP BY farm_id', 'sales');
        $add('SELECT farm_id, COUNT(*) v FROM sales' . $where . ' GROUP BY farm_id', 'saleCount');
        $add('SELECT farm_id, SUM(kg) v FROM sales' . $where . ' GROUP BY farm_id', 'saleKg');
        $add('SELECT farm_id, SUM(hari_share_amount) v FROM sales' . $where . ' GROUP BY farm_id', 'hariShare');
        $add('SELECT farm_id, SUM(amount) v FROM stock_usages' . $where . ' GROUP BY farm_id', 'costs');
        $add('SELECT farm_id, COUNT(*) v FROM stock_usages' . $where . ' GROUP BY farm_id', 'usageCount');
        $add('SELECT farm_id, SUM(amount) v FROM expenses' . $where . ' GROUP BY farm_id', 'costs');
        $add('SELECT farm_id, COUNT(*) v FROM expenses' . $where . ' GROUP BY farm_id', 'expenseCount');
        $add('SELECT farm_id, SUM(amount) v FROM advances' . $where . ' GROUP BY farm_id', 'advances');
        $add('SELECT farm_id, COUNT(*) v FROM advances' . $where . ' GROUP BY farm_id', 'advanceCount');
        foreach (['stock_usages', 'expenses', 'sales', 'advances'] as $table) {
            $add('SELECT farm_id, COUNT(*) v FROM ' . $table . ($scope === null ? " WHERE status = 'pending'" : " WHERE status = 'pending' AND farm_id = ?") . ' GROUP BY farm_id', 'pending');
        }
        $stock = [];
        $tWhere = $scope === null ? '' : ' WHERE t.farm_id = ?';
        $stmt = $pdo->prepare('SELECT t.farm_id, t.item_name, SUM(t.qty_sent) sent, SUM(CASE WHEN t.qty_received IS NULL THEN 0 ELSE t.qty_received END) received FROM transfers t' . $tWhere . ' GROUP BY t.farm_id, t.item_name');
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $row) {
            $name = $farmNames[(int)$row['farm_id']] ?? '';
            if ($name === '') {
                continue;
            }
            $stock[$name][$row['item_name']] = [
                'sent' => num($row['sent']),
                'received' => num($row['received']),
                'used' => 0,
                'left' => num($row['received']),
                'cost' => 0,
            ];
        }
        $uWhere = $scope === null ? '' : ' WHERE farm_id = ?';
        $stmt = $pdo->prepare('SELECT farm_id, item_name, SUM(qty) used, SUM(amount) cost FROM stock_usages' . $uWhere . ' GROUP BY farm_id, item_name');
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $row) {
            $name = $farmNames[(int)$row['farm_id']] ?? '';
            if ($name === '') {
                continue;
            }
            $cell = $stock[$name][$row['item_name']] ?? ['sent' => 0, 'received' => 0, 'used' => 0, 'left' => 0, 'cost' => 0];
            $cell['used'] = num($row['used']);
            $cell['cost'] = num($row['cost']);
            $cell['left'] = num($cell['received'] - $cell['used']);
            $stock[$name][$row['item_name']] = $cell;
        }
        $cats = [];
        $stmt = $pdo->prepare('SELECT farm_id, item_name label, SUM(amount) v FROM stock_usages' . $uWhere . ' GROUP BY farm_id, item_name');
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $row) {
            $name = $farmNames[(int)$row['farm_id']] ?? '';
            if ($name !== '') {
                $cats[$name][$row['label']] = num($row['v']);
            }
        }
        $expenseCats = [];
        $stmt = $pdo->prepare('SELECT farm_id, category_name label, SUM(amount) v, COUNT(*) n FROM expenses' . $uWhere . ' GROUP BY farm_id, category_name');
        $stmt->execute($params);
        $expenseCounts = [];
        foreach ($stmt->fetchAll() as $row) {
            $name = $farmNames[(int)$row['farm_id']] ?? '';
            if ($name !== '') {
                $cats[$name][$row['label']] = num(($cats[$name][$row['label']] ?? 0) + (float)$row['v']);
                $expenseCats[$name][$row['label']] = num($row['v']);
                $expenseCounts[$row['label']] = ($expenseCounts[$row['label']] ?? 0) + (int)$row['n'];
            }
        }
        $crops = [];
        $stmt = $pdo->prepare('SELECT farm_id, crop, SUM(amount) v FROM sales' . $uWhere . ' GROUP BY farm_id, crop');
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $row) {
            $name = $farmNames[(int)$row['farm_id']] ?? '';
            if ($name !== '') {
                $crops[$name][$row['crop']] = num($row['v']);
            }
        }
        return ['byFarm' => $byFarm, 'stock' => $stock, 'costCats' => $cats, 'expenseCats' => $expenseCats, 'expenseCounts' => $expenseCounts, 'salesByCrop' => $crops, 'hari' => $this->hariTotals($scope), 'daily24' => $this->daily24($scope)];
    }

    public function table(array $user, array $query): array
    {
        $resource = (string)($query['resource'] ?? '');
        $allowed = ['usage', 'expenses', 'sales', 'advances', 'transfers', 'receipts', 'daily', 'audit', 'approvals', 'salaries'];
        if (!in_array($resource, $allowed, true)) {
            throw new HttpException(404, 'Unknown list');
        }
        if ($resource === 'receipts' && !Access::can($user, 'admin.godown')) {
            throw new HttpException(403, 'Access restricted');
        }
        if ($resource === 'salaries' && !Access::can($user, 'admin.salaries')) {
            throw new HttpException(403, 'Access restricted');
        }
        if ($resource === 'approvals' && !Access::can($user, 'admin.approvals')) {
            throw new HttpException(403, 'Access restricted');
        }
        $page = max(1, (int)($query['page'] ?? 1));
        $per = min(50, max(5, (int)($query['per'] ?? 25)));
        $offset = ($page - 1) * $per;
        $farmId = $this->requestedFarm($user, (string)($query['farm'] ?? ''));
        $dir = strtolower((string)($query['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        [$sql, $countSql, $params, $map] = $this->listSql($resource, $farmId, $query, $dir);
        $pdo = Database::pdo();
        $count = $pdo->prepare($countSql);
        $count->execute($params);
        $total = (int)$count->fetch()['c'];
        $stmt = $pdo->prepare($sql . ' LIMIT ' . $per . ' OFFSET ' . $offset);
        $stmt->execute($params);
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[] = $map($row);
        }
        $totals = $this->listTotals($resource, $farmId, $query);
        if ($resource === 'transfers') {
            $awaitSql = 'SELECT COUNT(*) c FROM transfers WHERE qty_received IS NULL' . ($farmId === null ? '' : ' AND farm_id = ?');
            $await = $pdo->prepare($awaitSql);
            $await->execute($farmId === null ? [] : [$farmId]);
            $totals['awaiting'] = (int)$await->fetch()['c'];
        }
        if ($resource === 'approvals') {
            $totals['byKind'] = [];
            foreach (['stock_usages' => 'usage', 'expenses' => 'expenses', 'sales' => 'sales', 'advances' => 'advances'] as $table => $kind) {
                $sql = 'SELECT COUNT(*) c FROM ' . $table . " WHERE status = 'pending'" . ($farmId === null ? '' : ' AND farm_id = ?');
                $countKind = $pdo->prepare($sql);
                $countKind->execute($farmId === null ? [] : [$farmId]);
                $totals['byKind'][$kind] = (int)$countKind->fetch()['c'];
            }
        }
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'per' => $per, 'totals' => $totals];
    }

    public function ledger(array $user, int $hariId, bool $approvedOnly): array
    {
        $hari = HariModel::find($hariId);
        if (!$hari) {
            throw new HttpException(404, 'Hari not found');
        }
        Access::assertFarm($user, (int)$hari['farm_id']);
        $status = $approvedOnly ? " AND status = 'approved'" : '';
        $pdo = Database::pdo();
        $sales = $pdo->prepare('SELECT * FROM sales WHERE hari_id = ?' . $status . ' ORDER BY id');
        $sales->execute([$hariId]);
        $usage = $pdo->prepare('SELECT * FROM stock_usages WHERE hari_id = ?' . $status . ' ORDER BY id');
        $usage->execute([$hariId]);
        $expenses = $pdo->prepare('SELECT * FROM expenses WHERE hari_id = ?' . $status . ' ORDER BY id');
        $expenses->execute([$hariId]);
        $advances = $pdo->prepare('SELECT * FROM advances WHERE hari_id = ?' . $status . ' ORDER BY id');
        $advances->execute([$hariId]);
        $saleRows = [];
        $gross = 0.0;
        foreach ($sales->fetchAll() as $row) {
            $saleRows[] = [
                'crop' => $row['crop'],
                'weight' => num($row['weight']),
                'unit' => $row['weight_unit'],
                'rate' => num($row['rate_snapshot']),
                'kg' => num($row['kg']),
                'amount' => num($row['amount']),
                'approved' => $row['status'] === 'approved',
            ];
            $gross += (float)$row['hari_share_amount'];
        }
        $costs = [];
        $ded = 0.0;
        foreach (array_merge($usage->fetchAll(), $expenses->fetchAll()) as $row) {
            $portion = (float)$row['amount'] * (int)$row['share_snapshot'] / 100;
            $costs[] = [
                'category' => $row['item_name'] ?? $row['category_name'],
                'item' => $row['item_name'] ?? null,
                'crop' => $row['crop'],
                'qty' => num($row['qty']),
                'rate' => num($row['rate_snapshot']),
                'amount' => num($row['amount']),
                'share' => (int)$row['share_snapshot'],
                'approved' => $row['status'] === 'approved',
                'target' => $row['target_type'] === 'seri' ? 'seri' : (string)$row['hari_id'],
            ];
            $ded += $portion;
        }
        $advRows = [];
        $advance = 0.0;
        foreach ($advances->fetchAll() as $row) {
            $advRows[] = ['reason' => $row['reason'], 'amount' => num($row['amount']), 'approved' => $row['status'] === 'approved'];
            $advance += (float)$row['amount'];
        }
        return [
            'gross' => num($gross),
            'ded' => num($ded),
            'advance' => num($advance),
            'balance' => num($gross - $ded - $advance),
            'sales' => $saleRows,
            'cost' => $costs,
            'adv' => $advRows,
        ];
    }

    private function hariTotals(?int $scope): array
    {
        $pdo = Database::pdo();
        $where = $scope === null ? '' : ' AND h.farm_id = ?';
        $params = $scope === null ? [] : [$scope];
        $stmt = $pdo->prepare('SELECT h.id, h.name, f.name farm, COALESCE(SUM(p.acres),0) acres FROM haris h JOIN farms f ON f.id = h.farm_id LEFT JOIN plots p ON p.hari_id = h.id WHERE 1=1' . $where . ' GROUP BY h.id, h.name, f.name ORDER BY h.name');
        $stmt->execute($params);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(int)$row['id']] = [
                'id' => (string)$row['id'],
                'name' => $row['name'],
                'farm' => $row['farm'],
                'acres' => num($row['acres']),
                'preview' => ['gross' => 0, 'ded' => 0, 'advance' => 0, 'balance' => 0],
                'final' => ['gross' => 0, 'ded' => 0, 'advance' => 0, 'balance' => 0],
            ];
        }
        $this->foldHari($out, 'SELECT hari_id, status, SUM(hari_share_amount) v FROM sales WHERE hari_id IS NOT NULL' . ($scope === null ? '' : ' AND farm_id = ?') . ' GROUP BY hari_id, status', $params, 'gross');
        $this->foldHari($out, 'SELECT hari_id, status, SUM(amount * share_snapshot / 100) v FROM stock_usages WHERE hari_id IS NOT NULL' . ($scope === null ? '' : ' AND farm_id = ?') . ' GROUP BY hari_id, status', $params, 'ded');
        $this->foldHari($out, 'SELECT hari_id, status, SUM(amount * share_snapshot / 100) v FROM expenses WHERE hari_id IS NOT NULL' . ($scope === null ? '' : ' AND farm_id = ?') . ' GROUP BY hari_id, status', $params, 'ded');
        $this->foldHari($out, 'SELECT hari_id, status, SUM(amount) v FROM advances WHERE 1=1' . ($scope === null ? '' : ' AND farm_id = ?') . ' GROUP BY hari_id, status', $params, 'advance');
        foreach ($out as &$row) {
            foreach (['preview', 'final'] as $key) {
                $row[$key]['balance'] = num($row[$key]['gross'] - $row[$key]['ded'] - $row[$key]['advance']);
            }
        }
        return array_values($out);
    }

    private function foldHari(array &$out, string $sql, array $params, string $field): void
    {
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $row) {
            $id = (int)$row['hari_id'];
            if (!isset($out[$id])) {
                continue;
            }
            $out[$id]['preview'][$field] = num($out[$id]['preview'][$field] + (float)$row['v']);
            if ($row['status'] === 'approved') {
                $out[$id]['final'][$field] = num($out[$id]['final'][$field] + (float)$row['v']);
            }
        }
    }

    private function listTotals(string $resource, ?int $farmId, array $query): array
    {
        if (!in_array($resource, ['usage', 'expenses', 'sales', 'advances'], true)) {
            return [];
        }
        $table = $resource === 'usage' ? 'stock_usages' : $resource;
        $where = [];
        $params = [];
        if ($farmId !== null) {
            $where[] = 'farm_id = ?';
            $params[] = $farmId;
        }
        $seasonName = trim((string)($query['season'] ?? ''));
        if ($seasonName !== '' && $seasonName !== 'all') {
            $season = SeasonModel::findByName($seasonName);
            if (!$season) {
                throw new HttpException(422, 'Choose a valid season');
            }
            $where[] = 'season_id = ?';
            $params[] = (int)$season['id'];
        }
        $status = (string)($query['status'] ?? '');
        if (in_array($status, ['approved', 'pending'], true)) {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        $item = trim((string)($query['item'] ?? ''));
        if ($item !== '' && $resource === 'usage') {
            $where[] = 'item_name = ?';
            $params[] = $item;
        }
        $q = trim((string)($query['q'] ?? ''));
        if ($q !== '') {
            $column = $resource === 'advances' ? 'reason' : 'crop';
            $where[] = '(' . $column . ' LIKE ? OR by_label LIKE ?)';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
        $kg = $resource === 'sales' ? ', COALESCE(SUM(kg),0) kg, COALESCE(SUM(hari_share_amount),0) share' : '';
        $sql = 'SELECT COALESCE(SUM(amount),0) amount, COUNT(*) n' . $kg . ' FROM ' . $table;
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        $out = ['amount' => num($row['amount']), 'count' => (int)$row['n']];
        if ($resource === 'sales') {
            $out['kg'] = num($row['kg']);
            $out['share'] = num($row['share']);
        }
        return $out;
    }

    /** @return array{0:string,1:string,2:array,3:callable} */
    private function listSql(string $resource, ?int $farmId, array $query, string $dir): array
    {
        $q = trim((string)($query['q'] ?? ''));
        $params = [];
        $farmSql = '';
        if ($farmId !== null) {
            $farmSql = ' AND x.farm_id = ?';
            $params[] = $farmId;
        }
        $seasonSql = '';
        $extra = '';
        $seasonName = trim((string)($query['season'] ?? ''));
        if ($seasonName !== '' && $seasonName !== 'all' && in_array($resource, ['usage', 'expenses', 'sales', 'advances'], true)) {
            $season = SeasonModel::findByName($seasonName);
            if (!$season) {
                throw new HttpException(422, 'Choose a valid season');
            }
            $seasonSql = ' AND x.season_id = ?';
            $params[] = (int)$season['id'];
        }
        $status = (string)($query['status'] ?? '');
        if (in_array($status, ['approved', 'pending'], true) && in_array($resource, ['usage', 'expenses', 'sales', 'advances'], true)) {
            $extra .= ' AND x.status = ?';
            $params[] = $status;
        }
        if ($resource === 'transfers' && $status === 'awaiting') {
            $extra .= ' AND x.qty_received IS NULL';
        }
        if ($resource === 'transfers' && $status === 'received') {
            $extra .= ' AND x.qty_received IS NOT NULL';
        }
        $item = trim((string)($query['item'] ?? ''));
        if ($item !== '' && in_array($resource, ['usage', 'transfers'], true)) {
            $extra .= ' AND x.item_name = ?';
            $params[] = $item;
        }
        $ord = $this->orderBy($resource, (string)($query['sort'] ?? 'id'), $dir);
        $like = '%' . $q . '%';
        if ($resource === 'usage') {
            $search = $q === '' ? '' : ' AND (x.item_name LIKE ? OR x.crop LIKE ? OR x.by_label LIKE ?)';
            if ($q !== '') {
                array_push($params, $like, $like, $like);
            }
            $sql = 'SELECT x.*, f.name farm_name, s.name season_name FROM stock_usages x JOIN farms f ON f.id = x.farm_id LEFT JOIN seasons s ON s.id = x.season_id WHERE 1=1' . $farmSql . $seasonSql . $extra . $search . $ord;
            $count = 'SELECT COUNT(*) c FROM stock_usages x WHERE 1=1' . $farmSql . $seasonSql . $extra . $search;
            return [$sql, $count, $params, fn (array $row): array => $this->money($row, 'item_name')];
        }
        if ($resource === 'expenses') {
            $search = $q === '' ? '' : ' AND (x.category_name LIKE ? OR x.crop LIKE ? OR x.by_label LIKE ?)';
            if ($q !== '') {
                array_push($params, $like, $like, $like);
            }
            $sql = 'SELECT x.*, f.name farm_name, s.name season_name FROM expenses x JOIN farms f ON f.id = x.farm_id LEFT JOIN seasons s ON s.id = x.season_id WHERE 1=1' . $farmSql . $seasonSql . $extra . $search . $ord;
            $count = 'SELECT COUNT(*) c FROM expenses x WHERE 1=1' . $farmSql . $seasonSql . $extra . $search;
            return [$sql, $count, $params, function (array $row): array {
                $mapped = $this->money($row, 'category_name');
                $mapped['category'] = $row['category_name'];
                $mapped['unit'] = $row['unit_snapshot'];
                return $mapped;
            }];
        }
        if ($resource === 'sales') {
            $search = $q === '' ? '' : ' AND (x.crop LIKE ? OR x.by_label LIKE ?)';
            if ($q !== '') {
                array_push($params, $like, $like);
            }
            $sql = 'SELECT x.*, f.name farm_name, s.name season_name FROM sales x JOIN farms f ON f.id = x.farm_id LEFT JOIN seasons s ON s.id = x.season_id WHERE 1=1' . $farmSql . $seasonSql . $extra . $search . $ord;
            $count = 'SELECT COUNT(*) c FROM sales x WHERE 1=1' . $farmSql . $seasonSql . $extra . $search;
            return [$sql, $count, $params, fn (array $row): array => [
                'id' => (string)$row['id'], 'farm' => $row['farm_name'], 'season' => (string)($row['season_name'] ?? ''), 'crop' => $row['crop'],
                'target' => $row['target_type'] === 'seri' ? 'seri' : (string)$row['hari_id'],
                'hariId' => $row['hari_id'] ? (string)$row['hari_id'] : null,
                'unit' => $row['weight_unit'], 'weight' => num($row['weight']), 'rate' => num($row['rate_snapshot']),
                'kg' => num($row['kg']), 'amount' => num($row['amount']), 'by' => $row['by_label'],
                'approved' => $row['status'] === 'approved',
            ]];
        }
        if ($resource === 'advances') {
            $search = $q === '' ? '' : ' AND (x.reason LIKE ? OR x.by_label LIKE ?)';
            if ($q !== '') {
                array_push($params, $like, $like);
            }
            $sql = 'SELECT x.*, f.name farm_name, s.name season_name FROM advances x JOIN farms f ON f.id = x.farm_id LEFT JOIN seasons s ON s.id = x.season_id WHERE 1=1' . $farmSql . $seasonSql . $extra . $search . $ord;
            $count = 'SELECT COUNT(*) c FROM advances x WHERE 1=1' . $farmSql . $seasonSql . $extra . $search;
            return [$sql, $count, $params, fn (array $row): array => [
                'id' => (string)$row['id'], 'farm' => $row['farm_name'], 'hariId' => (string)$row['hari_id'],
                'reason' => $row['reason'], 'amount' => num($row['amount']), 'by' => $row['by_label'],
                'approved' => $row['status'] === 'approved', 'season' => (string)($row['season_name'] ?? ''),
            ]];
        }
        if ($resource === 'transfers') {
            $sql = 'SELECT x.*, f.name farm_name FROM transfers x JOIN farms f ON f.id = x.farm_id WHERE 1=1' . $farmSql . $extra . $ord;
            $count = 'SELECT COUNT(*) c FROM transfers x WHERE 1=1' . $farmSql;
            return [$sql, $count, $params, fn (array $row): array => [
                'id' => (string)$row['id'], 'farm' => $row['farm_name'], 'item' => $row['item_name'],
                'qty' => num($row['qty_sent']), 'received' => $row['qty_received'] === null ? null : num($row['qty_received']),
                'date' => $row['sent_on'], 'receivedBy' => '', 'receivedAt' => $row['received_at'] ? (string)$row['received_at'] : '',
            ]];
        }
        if ($resource === 'receipts') {
            $receiptWhere = '1=1';
            $receiptParams = [];
            if ($item !== '') {
                $receiptWhere .= ' AND item_name = ?';
                $receiptParams[] = $item;
            }
            $sql = 'SELECT * FROM godown_receipts WHERE ' . $receiptWhere . $ord;
            return [$sql, 'SELECT COUNT(*) c FROM godown_receipts WHERE ' . $receiptWhere, $receiptParams, fn (array $row): array => [
                'id' => (string)$row['id'], 'item' => $row['item_name'], 'qty' => num($row['qty']),
                'rate' => num($row['rate']), 'date' => $row['received_on'],
            ]];
        }
        if ($resource === 'daily') {
            $sql = 'SELECT x.*, f.name farm_name, u.username FROM daily_work x JOIN farms f ON f.id = x.farm_id JOIN users u ON u.id = x.created_by WHERE 1=1' . $farmSql . $ord;
            $count = 'SELECT COUNT(*) c FROM daily_work x WHERE 1=1' . $farmSql;
            return [$sql, $count, $params, fn (array $row): array => [
                'id' => (string)$row['id'], 'farm' => $row['farm_name'], 'date' => $row['work_date'],
                'at' => date('c', strtotime((string)$row['occurred_at'])), 'type' => $row['work_type'],
                'desc' => $row['description'], 'resources' => $row['resources'],
                'acres' => $row['acres'] === null ? null : num($row['acres']),
                'hours' => $row['hours'] === null ? null : num($row['hours']),
                'notes' => $row['notes'], 'by' => $row['username'],
            ]];
        }
        if ($resource === 'audit') {
            $auditWhere = $farmId === null ? '1=1' : 'farm_id = ?';
            $sql = 'SELECT * FROM audit_log WHERE ' . $auditWhere . $ord;
            $count = 'SELECT COUNT(*) c FROM audit_log WHERE ' . $auditWhere;
            return [$sql, $count, $params, fn (array $row): array => [
                'at' => (string)$row['created_at'], 'by' => $row['username'], 'msg' => $row['message'], 'action' => $row['action'],
            ]];
        }
        if ($resource === 'approvals') {
            $bits = [];
            $countBits = [];
            $all = [];
            foreach ([
                ['stock_usages', 'usage', 'item_name', 'qty', 'target_type'],
                ['expenses', 'expenses', 'category_name', 'qty', 'target_type'],
                ['sales', 'sales', 'crop', 'NULL', 'target_type'],
                ['advances', 'advances', 'reason', 'NULL', 'NULL'],
            ] as [$table, $kind, $label, $qty, $target]) {
                $wanted = (string)($query['kind'] ?? '');
                if ($wanted !== '' && $wanted !== $kind) {
                    continue;
                }
                $w = "status = 'pending'" . ($farmId === null ? '' : ' AND farm_id = ?');
                $searchSql = '';
                if ($q !== '') {
                    $searchSql = $kind === 'advances'
                        ? ' AND reason LIKE ?'
                        : ' AND (crop LIKE ? OR ' . $label . ' LIKE ?)';
                }
                $bits[] = "SELECT '$kind' kind, id, farm_id, amount, $qty qty, by_label, hari_id, $target target_type, $label label, " . ($kind === 'advances' ? 'reason' : 'crop') . " crop FROM $table WHERE $w" . $searchSql;
                $countBits[] = "SELECT id FROM $table WHERE $w" . $searchSql;
                if ($farmId !== null) {
                    $all[] = $farmId;
                }
                if ($q !== '') {
                    $all[] = $like;
                    if ($kind !== 'advances') {
                        $all[] = $like;
                    }
                }
            }
            if (!$bits) {
                return ['SELECT 1 WHERE 0', 'SELECT 0 c', [], fn (): array => []];
            }
            $sql = 'SELECT * FROM (' . implode(' UNION ALL ', $bits) . ') t' . $ord;
            $count = 'SELECT COUNT(*) c FROM (' . implode(' UNION ALL ', $countBits) . ') t';
            return [$sql, $count, $all, function (array $row): array {
                $farm = FarmModel::find((int)$row['farm_id']);
                return [
                    '_t' => $row['kind'], 'id' => (string)$row['id'], 'farm' => $farm['name'] ?? '',
                    'item' => $row['label'], 'category' => $row['label'], 'crop' => (string)($row['crop'] ?? $row['label']), 'reason' => $row['label'],
                    'amount' => $row['amount'] === null ? null : num($row['amount']),
                    'qty' => isset($row['qty']) ? num($row['qty']) : null,
                    'by' => $row['by_label'],
                    'target' => ($row['target_type'] ?? '') === 'seri' ? 'seri' : (string)($row['hari_id'] ?? ''),
                    'hariId' => $row['hari_id'] ? (string)$row['hari_id'] : null,
                    'approved' => false,
                ];
            }];
        }
        $sql = 'SELECT p.*, u.username, f.name farm_name FROM salary_payments p LEFT JOIN users u ON u.id = p.user_id LEFT JOIN farms f ON f.id = u.farm_id' . $ord;
        return [$sql, 'SELECT COUNT(*) c FROM salary_payments', [], fn (array $row): array => [
            'id' => (string)$row['id'],
            'userId' => $row['user_id'] ? (string)$row['user_id'] : 'k' . $row['staff_id'],
            'month' => $row['pay_month'], 'kind' => $row['kind'], 'amount' => num($row['amount']),
            'status' => $row['status'], 'date' => $row['pay_date'], 'note' => $row['note'],
        ]];
    }

    private function money(array $row, string $labelKey): array
    {
        return [
            'id' => (string)$row['id'],
            'farm' => $row['farm_name'],
            'season' => (string)($row['season_name'] ?? ''),
            'item' => $row[$labelKey] ?? '',
            'qty' => num($row['qty']),
            'rate' => num($row['rate_snapshot']),
            'amount' => num($row['amount']),
            'target' => $row['target_type'] === 'seri' ? 'seri' : (string)$row['hari_id'],
            'hariId' => $row['hari_id'] ? (string)$row['hari_id'] : null,
            'crop' => $row['crop'],
            'share' => (int)$row['share_snapshot'],
            'by' => $row['by_label'],
            'approved' => $row['status'] === 'approved',
        ];
    }

    private function daily24(?int $scope): array
    {
        $since = date('Y-m-d H:i:s', time() - 86400);
        $sql = 'SELECT f.name farm, COUNT(*) n, COALESCE(SUM(d.acres),0) acres, COALESCE(SUM(d.hours),0) hours FROM daily_work d JOIN farms f ON f.id = d.farm_id WHERE d.occurred_at >= ?';
        $params = [$since];
        if ($scope !== null) {
            $sql .= ' AND d.farm_id = ?';
            $params[] = $scope;
        }
        $sql .= ' GROUP BY f.name';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $byFarm = [];
        $n = 0;
        $acres = 0.0;
        $hours = 0.0;
        foreach ($stmt->fetchAll() as $row) {
            $byFarm[$row['farm']] = ['n' => (int)$row['n'], 'acres' => num($row['acres']), 'hours' => num($row['hours'])];
            $n += (int)$row['n'];
            $acres += (float)$row['acres'];
            $hours += (float)$row['hours'];
        }
        return ['n' => $n, 'acres' => num($acres), 'hours' => num($hours), 'byFarm' => $byFarm, 'farms' => count($byFarm)];
    }

    private function orderBy(string $resource, string $sort, string $dir): string
    {
        if ($sort === '' && $resource === 'daily') {
            $sort = 'date';
        }
        $maps = [
            'usage' => ['id' => 'x.id', 'amount' => 'x.amount', 'qty' => 'x.qty'],
            'expenses' => ['id' => 'x.id', 'amount' => 'x.amount', 'qty' => 'x.qty'],
            'sales' => ['id' => 'x.id', 'amount' => 'x.amount'],
            'advances' => ['id' => 'x.id', 'amount' => 'x.amount'],
            'transfers' => ['id' => 'x.id', 'qty' => 'x.qty_sent', 'date' => 'x.sent_on'],
            'receipts' => ['id' => 'id', 'qty' => 'qty', 'date' => 'received_on'],
            'daily' => ['id' => 'x.id', 'date' => 'x.occurred_at'],
            'audit' => ['id' => 'id', 'date' => 'created_at'],
            'approvals' => ['id' => 'id', 'amount' => 'amount'],
            'salaries' => ['id' => 'p.id', 'amount' => 'p.amount'],
        ];
        $col = $maps[$resource][$sort] ?? $maps[$resource]['id'];
        return ' ORDER BY ' . $col . ' ' . $dir;
    }

    private function scope(array $user): ?int
    {
        if (Access::isOffice($user)) {
            return null;
        }
        return (int)($user['farm_id'] ?? 0);
    }

    private function requestedFarm(array $user, string $farm): ?int
    {
        if (!Access::isOffice($user)) {
            return (int)($user['farm_id'] ?? 0);
        }
        if ($farm === '' || $farm === 'all') {
            return null;
        }
        $row = FarmModel::findByName($farm);
        if (!$row) {
            throw new HttpException(422, 'Choose a valid farm');
        }
        return (int)$row['id'];
    }
}
