<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Access;
use App\Core\Session;
use App\Models\FarmModel;
use App\Models\SeasonModel;

/**
 * Farm and season chosen in the top bar. Farm roles cannot widen their farm.
 */
final class ScreenContext
{
    public function resolve(array $user, array $query): array
    {
        $seasons = array_column(SeasonModel::all(), 'name');
        $season = (string)($query['season'] ?? Session::get('zaz_season') ?? ($seasons[0] ?? ''));
        if (!in_array($season, $seasons, true) && $season !== 'No season') {
            $season = $seasons[0] ?? '';
        }
        Session::put('zaz_season', $season);

        $office = Access::isOffice($user);
        $farms = [];
        foreach (FarmModel::all() as $farm) {
            if ((int)$farm['is_archived'] === 1) {
                continue;
            }
            if (!$office && (int)$farm['id'] !== (int)($user['farm_id'] ?? 0)) {
                continue;
            }
            $farms[] = $farm['name'];
        }
        $farm = $office
            ? (string)($query['farm'] ?? Session::get('zaz_farm') ?? ($farms[0] ?? ''))
            : (string)($user['farm_name'] ?? '');
        if ($office && !in_array($farm, $farms, true)) {
            $farm = $farms[0] ?? '';
        }
        Session::put('zaz_farm', $farm);

        $scope = 'farm';
        if ($office && (string)($query['scope'] ?? Session::get('zaz_scope') ?? '') === 'all') {
            $scope = 'all';
        }
        if (isset($query['scope'])) {
            Session::put('zaz_scope', $scope);
        }
        return [
            'farm' => $farm,
            'farms' => $farms,
            'season' => $season,
            'seasons' => $seasons,
            'scope' => $scope,
            'office' => $office,
        ];
    }
}
