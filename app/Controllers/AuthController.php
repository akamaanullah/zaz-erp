<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\LoginAttemptModel;
use App\Models\UserModel;

final class AuthController extends Controller
{
    public const GENERIC_ERROR = 'Invalid username or password.';

    public function home(Request $request): Response
    {
        if ($this->user()) {
            return $this->redirect('/dashboard');
        }
        return $this->showLogin($request);
    }

    public function showLogin(Request $request): Response
    {
        return $this->view('auth/login', [
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function login(Request $request): Response
    {
        $username = UserModel::normalize((string)$request->input('username', ''));
        $password = (string)$request->input('password', '');
        $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        if (LoginAttemptModel::tooMany($ip)) {
            Session::flash('error', self::GENERIC_ERROR);
            return $this->redirect('/login');
        }
        $user = $username === '' ? null : UserModel::findByUsername($username);
        $hash = is_array($user) ? (string)$user['password_hash'] : UserModel::dummyHash();
        $verified = $password !== '' && password_verify($password, $hash);
        $active = is_array($user) && (int)$user['is_active'] === 1;
        if (!$verified || !$active) {
            LoginAttemptModel::add($ip);
            Session::flash('error', self::GENERIC_ERROR);
            return $this->redirect('/login');
        }
        LoginAttemptModel::clear($ip);
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
