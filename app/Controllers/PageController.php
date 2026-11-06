<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Access;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

final class PageController extends Controller
{
    public function page(Request $request): Response
    {
        $user = $this->user();
        $page = trim($request->path, '/');
        $page = preg_replace('/\.html$/', '', $page) ?: '';
        Access::assertPage($user, $page);
        $titles = [
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
        return $this->view('pages/app', [
            'page' => $page,
            'title' => $titles[$page] ?? 'ZAZ AGRICULTURE',
        ]);
    }
}
