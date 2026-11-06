<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\LoginAttemptModel;
use App\Models\TotpRecoveryModel;
use App\Models\UserModel;
use App\Services\Totp;

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
        if ((int)($user['totp_enabled'] ?? 0) === 1) {
            Session::regenerate();
            Session::forget('user_id');
            Session::put('pending_totp_user', (int)$user['id']);
            Session::forget('_csrf');
            return $this->redirect('/login/totp');
        }
        $this->completeLogin($user);
        return $this->redirect('/dashboard');
    }

    public function showTotp(Request $request): Response
    {
        if (!Session::get('pending_totp_user')) {
            return $this->redirect('/login');
        }
        return $this->view('auth/totp', [
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function verifyTotp(Request $request): Response
    {
        $pending = (int)Session::get('pending_totp_user');
        $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        if ($pending < 1 || LoginAttemptModel::tooMany($ip)) {
            Session::flash('error', self::GENERIC_ERROR);
            return $this->redirect('/login');
        }
        $user = UserModel::find($pending);
        $code = (string)$request->input('code', '');
        $recovery = strtoupper(preg_replace('/\s+/', '', (string)$request->input('recovery', '')) ?? '');
        $ok = false;
        if ($user && (int)$user['totp_enabled'] === 1 && $code !== '') {
            try {
                $secret = Totp::decrypt((string)$user['totp_secret']);
            } catch (\Throwable) {
                $secret = '';
            }
            $step = $secret === '' ? false : Totp::verify($secret, $code, (int)$user['totp_last_step']);
            if ($step !== false) {
                UserModel::setTotpStep((int)$user['id'], $step);
                $ok = true;
            }
        }
        if (!$ok && $user && $recovery !== '' && TotpRecoveryModel::consume((int)$user['id'], $recovery)) {
            $ok = true;
        }
        if (!$ok || !$user || !(int)$user['is_active']) {
            LoginAttemptModel::add($ip);
            Session::flash('error', self::GENERIC_ERROR);
            return $this->redirect('/login/totp');
        }
        LoginAttemptModel::clear($ip);
        Session::forget('pending_totp_user');
        $this->completeLogin($user);
        return $this->redirect('/dashboard');
    }

    private function completeLogin(array $user): void
    {
        Session::regenerate();
        Session::put('user_id', (int)$user['id']);
        Session::put('auth_role', (string)$user['role']);
        Session::put('auth_farm_id', (int)($user['farm_id'] ?? 0));
        Session::put('auth_seen', time());
        Session::forget('_csrf');
        Session::forget('pending_totp_user');
        Audit::add($user, 'auth.login', 'Signed in');
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
