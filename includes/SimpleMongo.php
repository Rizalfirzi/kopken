<?php
/**
 * Lightweight MongoDB Atlas Client for Kedai Kyuti
 * Direct integration with MongoDB Atlas database ('kyuti').
 * Supports:
 * 1. Native ext-mongodb (MongoDB\Driver\Manager) on Vercel Serverless Production
 * 2. Python Bridge (scripts/mongo_bridge.py) for local environment
 * 3. Local file cache fallback (data/ & temp cache)
 * 
 * Zero dependency on Redis / KV Cloud.
 */

class SimpleMongo {
    protected ?string $uri = null;
    protected string $database = 'kyuti';
    protected $manager = null;
    protected bool $connected = false;

    public function __construct() {
        $this->uri = getenv('MONGODB_URI') ?: (function_exists('config') ? config('mongodb.uri') : null);
        if (empty($this->uri)) {
            $this->uri = 'mongodb+srv://technologyswamp_db_user:EmmGSARebSpWS0Sw@cluster-utama.eyzthyo.mongodb.net/kyuti?retryWrites=true&w=majority';
        }
        if (function_exists('config') && config('mongodb.database')) {
            $this->database = config('mongodb.database');
        }

        if (extension_loaded('mongodb')) {
            try {
                $this->manager = new \MongoDB\Driver\Manager($this->uri, [], [
                    'serverSelectionTimeoutMS' => 4000,
                ]);
                $this->connected = true;
            } catch (\Throwable $e) {
                error_log("SimpleMongo Manager init error: " . $e->getMessage());
                $this->connected = false;
            }
        } else {
            // Local mode: python bridge is available
            $this->connected = true;
        }
    }

    public function isConnected(): bool {
        return $this->connected;
    }

    public function getProviderName(): string {
        return 'MongoDB Atlas (kyuti)';
    }

    /**
     * Get a string or JSON key from MongoDB Atlas 'settings' collection
     */
    public function get(string $key): ?string {
        // 1. Try native ext-mongodb if available (e.g. on Vercel)
        if ($this->manager !== null) {
            try {
                $query = new \MongoDB\Driver\Query(['_id' => $key]);
                $cursor = $this->manager->executeQuery("{$this->database}.settings", $query);
                $docs = $cursor->toArray();
                if (!empty($docs)) {
                    $doc = $docs[0];
                    if (isset($doc->raw_json) && is_string($doc->raw_json)) {
                        return $doc->raw_json;
                    }
                    if (isset($doc->data)) {
                        return json_encode($doc->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }
                    return json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            } catch (\Throwable $e) {
                error_log("SimpleMongo Driver GET error: " . $e->getMessage());
            }
        }

        // 2. Try Python Bridge (for local development)
        $pyResult = $this->runPythonBridge(['get', $key]);
        if ($pyResult !== null && $pyResult !== '') {
            return $pyResult;
        }

        return null;
    }

    /**
     * Set a string or JSON key into MongoDB Atlas 'settings' collection
     */
    public function set(string $key, string $value): bool {
        $saved = false;

        // 1. Try native ext-mongodb if available (e.g. on Vercel)
        if ($this->manager !== null) {
            try {
                $bulk = new \MongoDB\Driver\BulkWrite();
                $decoded = json_decode($value, true);
                $doc = [
                    '_id' => $key,
                    'data' => is_array($decoded) ? $decoded : $value,
                    'raw_json' => $value,
                    'updated_at' => new \MongoDB\BSON\UTCDateTime()
                ];
                $bulk->update(['_id' => $key], ['$set' => $doc], ['upsert' => true]);
                $this->manager->executeBulkWrite("{$this->database}.settings", $bulk);
                $saved = true;
            } catch (\Throwable $e) {
                error_log("SimpleMongo Driver SET error: " . $e->getMessage());
            }
        }

        // 2. Try Python Bridge (for local development)
        if (!$saved) {
            $pyResult = $this->runPythonBridge(['set', $key], $value);
            if ($pyResult !== null && trim($pyResult) === 'OK') {
                $saved = true;
            }
        }

        return $saved;
    }

    /**
     * Delete a key from MongoDB Atlas 'settings' collection
     */
    public function del(string $key): bool {
        if ($this->manager !== null) {
            try {
                $bulk = new \MongoDB\Driver\BulkWrite();
                $bulk->delete(['_id' => $key]);
                $this->manager->executeBulkWrite("{$this->database}.settings", $bulk);
                return true;
            } catch (\Throwable $e) {
                error_log("SimpleMongo Driver DEL error: " . $e->getMessage());
            }
        }

        $pyResult = $this->runPythonBridge(['del', $key]);
        return ($pyResult !== null && trim($pyResult) === 'OK');
    }

    /**
     * Helper to run python bridge script locally
     */
    protected function runPythonBridge(array $args, ?string $inputStdin = null): ?string {
        $scriptPath = dirname(__DIR__) . '/scripts/mongo_bridge.py';
        if (!file_exists($scriptPath)) {
            return null;
        }

        $cmdArgs = array_map('escapeshellarg', $args);
        $cmd = 'python ' . escapeshellarg($scriptPath) . ' ' . implode(' ', $cmdArgs);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];

        $process = proc_open($cmd, $descriptors, $pipes, dirname(__DIR__));
        if (!is_resource($process)) {
            return null;
        }

        if ($inputStdin !== null) {
            fwrite($pipes[0], $inputStdin);
        }
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        return ($exitCode === 0) ? $stdout : null;
    }
}

