<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\CatalogModel;
use App\Models\FarmModel;
use App\Models\UserModel;

final class AuthController extends Controller
{
    public function home(Request $request): Response
    {
        if ($this->user()) {
            return $this->redirect('/dashboard');
        }
        return $this->showLogin($request);
    }

    public function showLogin(Request $request): Response
    {
        $farmNames = [];
        foreach (FarmModel::all() as $farm) {
            $farmNames[(int)$farm['id']] = $farm['name'];
        }
        $accounts = [];
        foreach (UserModel::all() as $row) {
            if (!(int)$row['is_active']) {
                continue;
            }
            $row['farm_name'] = $farmNames[(int)$row['farm_id']] ?? '';
            $accounts[] = $row;
        }
        return $this->view('auth/login', [
            'accounts' => $accounts,
            'roles' => CatalogModel::ROLES,
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function login(Request $request): Response
    {
        $username = trim((string)$request->input('username', ''));
        $password = (string)$request->input('password', '');
        $user = UserModel::findByUsername($username);
        $hash = $user['password_hash'] ?? '$2y$10$invalidinvalidinvalidinvalidinvalidinvalidinvalidinv';
        $ok = $user && (int)$user['is_active'] === 1 && password_verify($password, (string)$hash);
        if (!$ok) {
            Session::flash('error', 'Incorrect password or inactive account.');
            return $this->redirect('/login');
        }
        Session::regenerate();
        Session::put('user_id', (int)$user['id']);
        Session::forget('_csrf');
        Audit::add($user, 'auth.login', 'Signed in');
        return $this->redirect('/dashboard');
    }

    public function logout(Request $request): Response
    {
        $user = $this->user();
        if ($user) {
            Audit::add($user, 'auth.logout', 'Signed out');
        }
        Session::destroy();
        if ($request->wantsJson()) {
            return $this->json(['ok' => true]);
        }
        return $this->redirect('/login');
    }
}
