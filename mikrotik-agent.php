<?php
/**
 * MikroTik Local Relay Agent
 *
 * Runs on a machine that can reach the MikroTik (e.g., this Windows box on the LAN).
 * The VPS cannot reach 192.168.88.1 directly, so it calls this agent over HTTP,
 * and the agent forwards commands to the MikroTik API (port 8728).
 *
 * Usage:
 *   php mikrotik-agent.php
 *
 * Then on the VPS, set in .env:
 *   MIKROTIK_AGENT_URL=http://192.168.88.250:8000
 */

// ── Security: only allow requests with a shared secret ────────────────────────
$expectedSecret = getenv('MIKROTIK_AGENT_SECRET') ?: 'change-me-now';

// Prefer $_SERVER, but fall back to getallheaders() (PHP built-in server
// sometimes does not populate $_SERVER for HTTP_* keys).
$providedSecret = $_SERVER['HTTP_X-AGENT-SECRET']
    ?? ($_GET['secret'] ?? '');

if (! $providedSecret) {
    $allHeaders = function_exists('getallheaders') ? getallheaders() : [];
    foreach ($allHeaders as $key => $value) {
        if (strtolower($key) === 'x-agent-secret') {
            $providedSecret = $value;
            break;
        }
    }
}

if ($providedSecret !== $expectedSecret) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Invalid agent secret']);
    exit;
}

// ── Router protocol implementation (same as MikrotikService) ──────────────────
class MikrotikAgentProtocol
{
    private $socket = null;

    public function connect(string $ip, string $user, string $pass, int $port = 8728): array
    {
        $this->socket = @fsockopen($ip, $port, $errno, $errstr, 10);
        if (! $this->socket) {
            return ['ok' => false, 'error' => "Connection failed: $errstr ($errno)"];
        }
        if (! $this->login($user, $pass)) {
            fclose($this->socket);
            $this->socket = null;
            return ['ok' => false, 'error' => 'Login failed'];
        }
        return ['ok' => true];
    }

    public function createHotspotUser(string $username, string $password, string $profile): array
    {
        if (! $this->socket) {
            return ['ok' => false, 'error' => 'Not connected'];
        }
        $this->writeWord('/ip/hotspot/user/add');
        $this->writeWord('=name='     . $username);
        $this->writeWord('=password=' . $password);
        $this->writeWord('=profile='  . $profile);
        $this->writeSentenceEnd();
        $response = $this->read();
        return ['ok' => in_array('!done', $response), 'response' => $response];
    }

    public function disconnect(): array
    {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }
        return ['ok' => true];
    }

    private function login(string $user, string $pass): bool
    {
        $this->writeWord('/login');
        $this->writeWord('=name='     . $user);
        $this->writeWord('=password=' . $pass);
        $this->writeSentenceEnd();
        $response = $this->read();
        return in_array('!done', $response);
    }

    private function writeWord(string $word): void
    {
        $len = strlen($word);
        if ($len < 0x80) {
            fwrite($this->socket, chr($len));
        } elseif ($len < 0x4000) {
            $len |= 0x8000;
            fwrite($this->socket, chr(($len >> 8) & 0xFF));
            fwrite($this->socket, chr($len & 0xFF));
        } else {
            $len |= 0xC00000;
            fwrite($this->socket, chr(($len >> 16) & 0xFF));
            fwrite($this->socket, chr(($len >> 8)  & 0xFF));
            fwrite($this->socket, chr($len & 0xFF));
        }
        fwrite($this->socket, $word);
    }

    private function writeSentenceEnd(): void
    {
        fwrite($this->socket, chr(0));
    }

    private function read(): array
    {
        $response = [];
        while (true) {
            $lenByte = ord(fread($this->socket, 1));
            if ($lenByte === 0) break;

            if ($lenByte & 0x80) {
                $lenByte2 = ord(fread($this->socket, 1));
                $len = (($lenByte & 0x3F) << 8) | $lenByte2;
            } else {
                $len = $lenByte;
            }

            $word = '';
            while (strlen($word) < $len) {
                $word .= fread($this->socket, $len - strlen($word));
            }
            $response[] = $word;
        }
        return $response;
    }
}

// ── Request handling ──────────────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$input  = file_get_contents('php://input');
$data   = $input ? json_decode($input, true) : [];

// Also accept GET params for simple health checks
if ($method === 'GET' && empty($data)) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'agent' => 'mikrotik-relay', 'time' => date('c')]);
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

// Validate required fields
$ip   = $data['ip']   ?? null;
$user = $data['user'] ?? null;
$pass = $data['pass'] ?? null;
$port = (int) ($data['port'] ?? 8728);
$action = $data['action'] ?? null;

if (! $ip || ! $user || ! $pass) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Missing ip, user, or pass']);
    exit;
}

$proto = new MikrotikAgentProtocol();

try {
    switch ($action) {
        case 'connect':
            $result = $proto->connect($ip, $user, $pass, $port);
            break;

        case 'create_hotspot_user':
            $username = $data['username'] ?? null;
            $password = $data['password'] ?? '';
            $profile  = $data['profile'] ?? null;

            if (! $username || ! $profile) {
                $result = ['ok' => false, 'error' => 'Missing username or profile'];
                break;
            }
            $conn = $proto->connect($ip, $user, $pass, $port);
            if (! $conn['ok']) {
                $result = $conn;
                break;
            }
            $result = $proto->createHotspotUser($username, $password, $profile);
            $proto->disconnect();
            break;

        case 'disconnect':
            $result = $proto->disconnect();
            break;

        default:
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Unknown action: ' . ($action ?? 'null')]);
            exit;
    }
} catch (\Throwable $e) {
    $result = ['ok' => false, 'error' => $e->getMessage()];
} finally {
    $proto->disconnect();
}

header('Content-Type: application/json');
echo json_encode($result);