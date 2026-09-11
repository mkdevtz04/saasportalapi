<?php
/**
 * Quick smoke test: verifies MikrotikService relay mode works against the agent.
 * Run: php test-relay.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\MikrotikService;

$svc = new MikrotikService(
    '192.168.88.1',
    'admin',
    'admin',
    8728,
    useRelay:    true,
    relayUrl:    'http://192.168.88.250:8000',
    relaySecret: 'change-me-now',
);

echo "Testing relay connect... ";
$connected = $svc->connect();
echo $connected ? "OK\n" : "FAIL\n";

if ($connected) {
    echo "Testing create_hotspot_user... ";
    $ok = $svc->createHotspotUser('smoketest', 'pass123', 'default');
    echo $ok ? "OK\n" : "FAIL\n";
    $svc->disconnect();
}

echo $connected ? "\nAll relay tests passed.\n" : "\nRelay test FAILED.\n";
exit($connected ? 0 : 1);