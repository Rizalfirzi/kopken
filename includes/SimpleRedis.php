<?php
/**
 * Lightweight Pure PHP Redis Client
 * Supports both Redis TCP/TLS Socket Protocol (REDIS_URL) & REST API (Upstash/Vercel KV).
 * Zero external composer dependencies.
 */
class SimpleRedis {
    protected ?string $redisUrl = null;
    protected ?string $restUrl = null;
    protected ?string $restToken = null;

    public function __construct() {
        // 1. Check REST environment variables
        $this->restUrl = getenv('KV_REST_API_URL') ?: getenv('UPSTASH_REDIS_REST_URL') ?: (function_exists('config') ? config('database.kv_url') : null);
        $this->restToken = getenv('KV_REST_API_TOKEN') ?: getenv('UPSTASH_REDIS_REST_TOKEN') ?: (function_exists('config') ? config('database.kv_token') : null);

        // 2. Check TCP REDIS_URL environment variables
        $this->redisUrl = getenv('REDIS_URL') ?: getenv('KV_URL') ?: null;

        // Auto-derive REST URL from REDIS_URL if not directly set
        if (!empty($this->redisUrl) && (empty($this->restUrl) || empty($this->restToken))) {
            $parsed = parse_url($this->redisUrl);
            if (!empty($parsed['host']) && !empty($parsed['pass'])) {
                $this->restUrl = 'https://' . $parsed['host'];
                $this->restToken = $parsed['pass'];
            }
        }
    }

    public function isConnected(): bool {
        return !empty($this->redisUrl) || (!empty($this->restUrl) && !empty($this->restToken));
    }

    public function getProviderName(): string {
        return $this->isConnected() ? 'Vercel KV / Upstash Redis' : 'Lokal / Serverless Temp';
    }

    /**
     * Set a string or JSON key
     */
    public function set(string $key, string $value): bool {
        // 1. Try TCP/TLS Socket first if REDIS_URL is present
        if (!empty($this->redisUrl)) {
            $ok = $this->socketCommand('SET', $key, $value);
            if ($ok !== null && ($ok === 'OK' || $ok === '+OK' || $ok === true)) {
                return true;
            }
        }

        // 2. Fallback to REST API
        if (!empty($this->restUrl) && !empty($this->restToken)) {
            return $this->restSet($key, $value);
        }

        return false;
    }

    /**
     * Get a string or JSON key
     */
    public function get(string $key): ?string {
        // 1. Try TCP/TLS Socket first if REDIS_URL is present
        if (!empty($this->redisUrl)) {
            $val = $this->socketCommand('GET', $key);
            if ($val !== null) {
                return $val;
            }
        }

        // 2. Fallback to REST API
        if (!empty($this->restUrl) && !empty($this->restToken)) {
            return $this->restGet($key);
        }

        return null;
    }

    /**
     * Pure PHP Socket Implementation (RESP Protocol)
     */
    protected function socketCommand(string $command, ...$args) {
        if (empty($this->redisUrl)) return null;

        $parsed = parse_url($this->redisUrl);
        $host = $parsed['host'] ?? '';
        $port = (int)($parsed['port'] ?? 6379);
        $pass = $parsed['pass'] ?? '';
        $isTls = ($parsed['scheme'] ?? '') === 'rediss';

        if (empty($host)) return null;

        $transport = $isTls ? 'tls://' : 'tcp://';
        $ctx = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]);

        $fp = @stream_socket_client($transport . $host . ':' . $port, $errno, $errstr, 2.5, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) return null;
        @stream_set_timeout($fp, 2);

        // AUTH if password provided
        if (!empty($pass)) {
            $authCmd = "*2\r\n$4\r\nAUTH\r\n$" . strlen($pass) . "\r\n$pass\r\n";
            @fwrite($fp, $authCmd);
            $authRes = @fgets($fp);
            if ($authRes === false || $authRes[0] === '-') {
                @fclose($fp);
                return null;
            }
        }

        // BUILD RESP COMMAND
        $parts = array_merge([$command], $args);
        $req = "*" . count($parts) . "\r\n";
        foreach ($parts as $p) {
            $s = (string)$p;
            $req .= "$" . strlen($s) . "\r\n" . $s . "\r\n";
        }
        @fwrite($fp, $req);

        // READ RESP RESPONSE
        $reply = @fgets($fp);
        $result = null;
        if ($reply !== false) {
            $type = $reply[0];
            $payload = rtrim(substr($reply, 1), "\r\n");
            if ($type === '+') { // Simple string (e.g. +OK)
                $result = $payload;
            } elseif ($type === '$') { // Bulk string
                $len = (int)$payload;
                if ($len >= 0) {
                    $result = '';
                    $remaining = $len;
                    while ($remaining > 0 && !feof($fp)) {
                        $chunk = @fread($fp, $remaining);
                        if ($chunk === false) break;
                        $result .= $chunk;
                        $remaining -= strlen($chunk);
                    }
                    @fgets($fp); // Read trailing \r\n
                }
            }
        }

        @fclose($fp);
        return $result;
    }

    /**
     * REST API Set Implementation
     */
    protected function restSet(string $key, string $value): bool {
        $postData = json_encode(['SET', $key, $value]);
        $headers = [
            "Authorization: Bearer {$this->restToken}",
            "Content-Type: application/json"
        ];

        // 1. Try file_get_contents with stream context
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers) . "\r\n",
                'content' => $postData,
                'timeout' => 3,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]);
        $res = @file_get_contents(rtrim($this->restUrl, '/') . '/', false, $ctx);
        if ($res) {
            $dec = json_decode($res, true);
            if (!empty($dec['result'])) return true;
        }

        // 2. Try cURL fallback
        if (function_exists('curl_init')) {
            $ch = curl_init(rtrim($this->restUrl, '/') . '/');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postData,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => 3,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
            ]);
            $res = curl_exec($ch);
            curl_close($ch);
            if ($res) {
                $dec = json_decode($res, true);
                if (!empty($dec['result'])) return true;
            }
        }

        return false;
    }

    /**
     * REST API Get Implementation
     */
    protected function restGet(string $key): ?string {
        $url = rtrim($this->restUrl, '/') . '/get/' . urlencode($key);
        $headers = ["Authorization: Bearer {$this->restToken}"];

        // 1. Try file_get_contents
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers) . "\r\n",
                'timeout' => 3,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]);
        $res = @file_get_contents($url, false, $ctx);
        if ($res) {
            $dec = json_decode($res, true);
            if (isset($dec['result']) && $dec['result'] !== null) {
                return is_string($dec['result']) ? $dec['result'] : json_encode($dec['result']);
            }
        }

        // 2. Try cURL fallback
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => 3,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
            ]);
            $res = curl_exec($ch);
            curl_close($ch);
            if ($res) {
                $dec = json_decode($res, true);
                if (isset($dec['result']) && $dec['result'] !== null) {
                    return is_string($dec['result']) ? $dec['result'] : json_encode($dec['result']);
                }
            }
        }

        return null;
    }
}
