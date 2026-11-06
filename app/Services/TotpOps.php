<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Access;
use App\Core\Audit;
use App\Core\HttpException;
use App\Models\TotpRecoveryModel;
use App\Models\UserModel;

final class TotpOps
{
    public function start(array $user): array
    {
        $this->guard($user);
        if (!Totp::available()) {
            throw new HttpException(503, 'Authenticator is not configured on this server');
        }
        $secret = Totp::generateSecret();
        UserModel::setTotpPending((int)$user['id'], Totp::encrypt($secret));
        Audit::add($user, 'totp.start', 'Authenticator enrollment started');
        return [
            'secret' => $secret,
            'uri' => Totp::provisioningUri((string)$user['username'], $secret),
        ];
    }

    /** @return list<string> */
    public function confirm(array $user, string $code): array
    {
        $this->guard($user);
        $fresh = UserModel::find((int)$user['id']);
        if (!$fresh || $fresh['totp_secret'] === null) {
            throw new HttpException(422, 'Start enrollment first');
        }
        $secret = Totp::decrypt((string)$fresh['totp_secret']);
        $step = Totp::verify($secret, $code, 0);
        if ($step === false) {
            throw new HttpException(422, 'Authenticator code was not accepted');
        }
        UserModel::setTotpStep((int)$user['id'], $step);
        UserModel::enableTotp((int)$user['id']);
        $codes = [];
        $hashes = [];
        for ($i = 0; $i < 8; $i++) {
            $plain = strtoupper(bin2hex(random_bytes(4)));
            $codes[] = $plain;
            $hashes[] = password_hash($plain, PASSWORD_DEFAULT);
        }
        TotpRecoveryModel::replace((int)$user['id'], $hashes);
        Audit::add($user, 'totp.enable', 'Authenticator enabled');
        return $codes;
    }

    public function disable(array $user, string $code): void
    {
        $this->guard($user);
        $fresh = UserModel::find((int)$user['id']);
        if (!$fresh || (int)$fresh['totp_enabled'] !== 1) {
            throw new HttpException(422, 'Authenticator is not enabled');
        }
        $secret = Totp::decrypt((string)$fresh['totp_secret']);
        $step = Totp::verify($secret, $code, (int)$fresh['totp_last_step']);
        if ($step === false) {
            throw new HttpException(422, 'Authenticator code was not accepted');
        }
        UserModel::clearTotp((int)$user['id']);
        TotpRecoveryModel::replace((int)$user['id'], []);
        Audit::add($user, 'totp.disable', 'Authenticator disabled');
    }

    private function guard(array $user): void
    {
        if (!in_array($user['role'] ?? '', ['owner', 'office'], true)) {
            throw new HttpException(403, 'Access restricted');
        }
        Access::assert($user, 'settings.prefs');
    }
}
