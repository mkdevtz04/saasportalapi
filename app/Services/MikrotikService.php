<?php

namespace App\Services;

use App\Models\TenantRouter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MikrotikService
{
    private mixed $socket = null;

    public function __construct(
        private string $ip,
        private string $user,
        private string $pass,
        private int    $port = 8728,
        private bool   $useRelay = false,
        private string $relayUrl = '',
        private string $relaySecret = '',
    ) {}

    public static function forRouter(TenantRouter $router): self
    {
        return new self(
            $router->router_ip,
            $router->username,
            $router->password,
            $router->port,
            useRelay:    (bool) config('services.mikrotik.relay_enabled', false),
            relayUrl:    (string) config('services.mikrotik.relay_url', ''),
            relaySecret: (string) config('services.mikrotik.relay_secret', ''),
        );
    }

    public function connect(): bool
    {
        if ($this->useRelay) {
            return $this->relayConnect();
        }

        try {
            $this->socket = @fsockopen($this->ip, $this->port, $errno, $errstr, 10);

            if (! $this->socket) {
                Log::error("MikroTik connection failed to {$this->ip}:{$this->port} — $errstr ($errno)");
                return false;
            }

            return $this->login();
        } catch (\Exception $e) {
            Log::error('MikroTik connect error: ' . $e->getMessage());
            return false;
        }
    }

    public function createHotspotUser(string $username, string $password, string $profile): bool
    {
        if ($this->useRelay) {
            return $this->relayCreateHotspotUser($username, $password, $profile);
        }

        try {
            $this->writeWord('/ip/hotspot/user/add');
            $this->writeWord('=name='     . $username);
            $this->writeWord('=password=' . $password);
            $this->writeWord('=profile='  . $profile);
            $this->writeSentenceEnd();

            $response = $this->read();

            Log::info('MikroTik hotspot user created', [
                'username' => $username,
                'profile'  => $profile,
                'response' => $response,
            ]);

            return in_array('!done', $response);
        } catch (\Exception $e) {
            Log::error('MikroTik createHotspotUser: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Take a customer's login off the router and cut the session they are on.
     *
     * Both halves matter: removing the login alone leaves whoever is already connected online,
     * and cutting the session alone lets them log straight back in. Returns true when the login
     * is gone, including when it was never there.
     */
    public function removeHotspotUser(string $username): bool
    {
        if ($this->useRelay) {
            return $this->relayRemoveHotspotUser($username);
        }

        try {
            foreach ($this->findIds('/ip/hotspot/active/print', 'user', $username) as $id) {
                $this->writeWord('/ip/hotspot/active/remove');
                $this->writeWord('=.id=' . $id);
                $this->writeSentenceEnd();
                $this->read();
            }

            $removed = true;

            foreach ($this->findIds('/ip/hotspot/user/print', 'name', $username) as $id) {
                $this->writeWord('/ip/hotspot/user/remove');
                $this->writeWord('=.id=' . $id);
                $this->writeSentenceEnd();

                $removed = in_array('!done', $this->read(), true) && $removed;
            }

            return $removed;
        } catch (\Exception $e) {
            Log::error('MikroTik removeHotspotUser: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * The internal ids of the rows a print command matched on one property.
     *
     * @return list<string>
     */
    private function findIds(string $command, string $property, string $value): array
    {
        $this->writeWord($command);
        $this->writeWord('?' . $property . '=' . $value);
        $this->writeWord('=.proplist=.id');
        $this->writeSentenceEnd();

        $ids = [];

        foreach ($this->read() as $word) {
            if (str_starts_with($word, '=.id=')) {
                $ids[] = substr($word, 5);
            }
        }

        return $ids;
    }

    public function disconnect(): void
    {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    // ── Relay (HTTP agent) implementation ─────────────────────────────────────

    private function relayCall(string $action, array $extra = []): array
    {
        if (! $this->relayUrl) {
            Log::error('MikroTik relay enabled but no relay_url configured');
            return ['ok' => false, 'error' => 'No relay URL configured'];
        }

        $payload = array_merge([
            'action' => $action,
            'ip'     => $this->ip,
            'user'   => $this->user,
            'pass'   => $this->pass,
            'port'   => $this->port,
        ], $extra);

        try {
            $response = Http::withHeaders([
                'X-AGENT-SECRET' => $this->relaySecret,
                'Accept'         => 'application/json',
            ])->timeout(15)
              ->retry(2, 500)
              ->post($this->relayUrl, $payload);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('MikroTik relay HTTP error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return ['ok' => false, 'error' => "Relay returned HTTP {$response->status()}"];
        } catch (ConnectionException $e) {
            Log::error('MikroTik relay connection failed', ['error' => $e->getMessage()]);
            return ['ok' => false, 'error' => 'Relay unreachable: ' . $e->getMessage()];
        } catch (\Exception $e) {
            Log::error('MikroTik relay error', ['error' => $e->getMessage()]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function relayConnect(): bool
    {
        $result = $this->relayCall('connect');
        return $result['ok'] ?? false;
    }

    private function relayCreateHotspotUser(string $username, string $password, string $profile): bool
    {
        $result = $this->relayCall('create_hotspot_user', [
            'username' => $username,
            'password' => $password,
            'profile'  => $profile,
        ]);

        if (! ($result['ok'] ?? false)) {
            Log::warning('MikroTik relay hotspot user creation failed', [
                'username' => $username,
                'profile'  => $profile,
                'error'    => $result['error'] ?? 'unknown',
            ]);
        }

        return $result['ok'] ?? false;
    }

    private function relayRemoveHotspotUser(string $username): bool
    {
        $result = $this->relayCall('remove_hotspot_user', ['username' => $username]);

        if (! ($result['ok'] ?? false)) {
            Log::warning('MikroTik relay hotspot user removal failed', [
                'username' => $username,
                'error'    => $result['error'] ?? 'unknown',
            ]);
        }

        return $result['ok'] ?? false;
    }

    // ── Direct (socket) implementation ────────────────────────────────────────

    private function login(): bool
    {
        $this->writeWord('/login');
        $this->writeWord('=name='     . $this->user);
        $this->writeWord('=password=' . $this->pass);
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