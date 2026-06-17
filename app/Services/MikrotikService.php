<?php
namespace App\Services;

use Illuminate\Support\Facades\Log;

class MikrotikService
{
    protected $ip;
    protected $user;
    protected $pass;
    protected $port;
    protected $socket;

    public function __construct()
    {
        $this->ip   = config('services.mikrotik.ip');
        $this->user = config('services.mikrotik.user');
        $this->pass = config('services.mikrotik.password');
        $this->port = config('services.mikrotik.port', 8728);
    }

    public function connect(): bool
    {
        try {
            $this->socket = @fsockopen(
                $this->ip,
                $this->port,
                $errno,
                $errstr,
                10
            );

            if (!$this->socket) {
                Log::error("MikroTik connection failed: $errstr ($errno)");
                return false;
            }

            return $this->login();

        } catch (\Exception $e) {
            Log::error('MikroTik connect error: ' . $e->getMessage());
            return false;
        }
    }

    public function createHotspotUser(
        string $username,
        string $password,
        string $profile
    ): bool {
        try {
            $this->writeWord('/ip/hotspot/user/add');
            $this->writeWord('=name='     . $username);
            $this->writeWord('=password=' . $password);
            $this->writeWord('=profile='  . $profile);
            $this->writeSentenceEnd();

            $response = $this->read();

            Log::info('MikroTik user created', [
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

    public function disconnect(): void
    {
        if ($this->socket) {
            fclose($this->socket);
        }
    }

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