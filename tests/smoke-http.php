<?php
declare(strict_types=1);

$lines = file(dirname(__DIR__) . '/storage/dev-credentials.local.txt', FILE_IGNORE_NEW_LINES);
$user = '';
$pass = '';
foreach ($lines as $i => $line) {
    if (str_starts_with($line, 'owner |')) {
        $user = 'owner';
        $pass = trim(substr($lines[$i + 1], strlen('password: ')));
        break;
    }
}
if ($user === '' || $pass === '') {
    fwrite(STDERR, "credentials file is missing an owner row\n");
    exit(1);
}
$cookie = tempnam(sys_get_temp_dir(), 'zaz');
$ch = curl_init('http://localhost/zaz-agriculture/public/login');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $cookie, CURLOPT_COOKIEFILE => $cookie]);
$html = (string)curl_exec($ch);
preg_match('/name="_csrf" value="([^"]+)"/', $html, $m);
curl_setopt_array($ch, [
    CURLOPT_URL => 'http://localhost/zaz-agriculture/public/login',
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query(['username' => $user, 'password' => $pass, '_csrf' => $m[1] ?? '']),
]);
curl_exec($ch);
$redir = (string)curl_getinfo($ch, CURLINFO_REDIRECT_URL);
echo 'login_status=' . curl_getinfo($ch, CURLINFO_HTTP_CODE) . PHP_EOL;
echo 'redirect_dashboard=' . (str_contains($redir, '/dashboard') ? 'yes' : 'no') . PHP_EOL;
curl_setopt_array($ch, [CURLOPT_POST => false, CURLOPT_HTTPGET => true, CURLOPT_URL => 'http://localhost/zaz-agriculture/public/dashboard']);
$dash = (string)curl_exec($ch);
echo 'dashboard_status=' . curl_getinfo($ch, CURLINFO_HTTP_CODE) . PHP_EOL;
echo 'dashboard_script=' . (str_contains($dash, 'assets/js/pages/dashboard.js') ? 'yes' : 'no') . PHP_EOL;
curl_setopt_array($ch, [
    CURLOPT_URL => 'http://localhost/zaz-agriculture/public/api/bootstrap',
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
]);
$boot = (string)curl_exec($ch);
$data = json_decode($boot, true);
echo 'bootstrap_status=' . curl_getinfo($ch, CURLINFO_HTTP_CODE) . PHP_EOL;
echo 'role=' . ($data['db']['me']['role'] ?? 'missing') . PHP_EOL;
echo 'farms=' . count($data['db']['farms'] ?? []) . PHP_EOL;
echo 'password_leak=' . (str_contains($boot, 'password') ? 'yes' : 'no') . PHP_EOL;
curl_close($ch);
@unlink($cookie);
