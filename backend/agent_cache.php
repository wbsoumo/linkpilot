<?php
// backend/agent_cache.php
// Enterprise caching layer for LinkPilot AI Agent.
// Supports Redis when available, with automatic fallback to high-speed file-based caching.

class AgentCache {
    private static $redis = null;
    private static $redisAttempted = false;
    private static $cacheDir = null;
    private static $memoryCache = [];

    /**
     * Initializes Redis or file-based cache storage.
     */
    private static function init() {
        if (self::$cacheDir === null) {
            self::$cacheDir = sys_get_temp_dir() . '/linkpilot_agent_cache';
            if (!is_dir(self::$cacheDir)) {
                @mkdir(self::$cacheDir, 0777, true);
            }
        }

        if (!self::$redisAttempted) {
            self::$redisAttempted = true;
            if (class_exists('Redis')) {
                try {
                    $redisHost = getenv('REDIS_HOST') ?: '127.0.0.1';
                    $redisPort = (int)(getenv('REDIS_PORT') ?: 6379);
                    $redisPass = getenv('REDIS_PASSWORD') ?: null;

                    $r = new Redis();
                    // Connect with short 0.5s timeout to prevent blocking if Redis is not running
                    $connected = @$r->connect($redisHost, $redisPort, 0.5);
                    if ($connected) {
                        if (!empty($redisPass)) {
                            @$r->auth($redisPass);
                        }
                        @$r->ping();
                        self::$redis = $r;
                    }
                } catch (Throwable $e) {
                    self::$redis = null;
                }
            }
        }
    }

    /**
     * Checks if active storage is Redis.
     */
    public static function isRedis(): bool {
        self::init();
        return self::$redis !== null;
    }

    /**
     * Retrieves an item from cache.
     */
    public static function get($key, $default = null) {
        self::init();
        $safeKey = 'lp_agent_' . md5($key);

        // Check in-process static memory cache first
        if (isset(self::$memoryCache[$safeKey])) {
            $item = self::$memoryCache[$safeKey];
            if ($item['expires_at'] === 0 || $item['expires_at'] > time()) {
                return $item['value'];
            }
            unset(self::$memoryCache[$safeKey]);
        }

        // Redis cache
        if (self::$redis !== null) {
            try {
                $val = self::$redis->get($safeKey);
                if ($val !== false) {
                    $decoded = json_decode($val, true);
                    return ($decoded !== null || $val === 'null') ? $decoded : $val;
                }
            } catch (Throwable $e) {}
        }

        // File-based fallback
        $filePath = self::$cacheDir . '/' . $safeKey . '.cache';
        if (file_exists($filePath)) {
            $raw = @file_get_contents($filePath);
            if ($raw) {
                $data = @json_decode($raw, true);
                if (is_array($data) && isset($data['expires_at'], $data['value'])) {
                    if ($data['expires_at'] === 0 || $data['expires_at'] > time()) {
                        self::$memoryCache[$safeKey] = $data;
                        return $data['value'];
                    }
                    @unlink($filePath);
                }
            }
        }

        return $default;
    }

    /**
     * Stores an item in cache with a TTL (seconds).
     */
    public static function set($key, $value, int $ttl = 3600): bool {
        self::init();
        $safeKey = 'lp_agent_' . md5($key);
        $expiresAt = ($ttl > 0) ? time() + $ttl : 0;

        self::$memoryCache[$safeKey] = [
            'value' => $value,
            'expires_at' => $expiresAt
        ];

        if (self::$redis !== null) {
            try {
                $encoded = json_encode($value);
                if ($ttl > 0) {
                    return (bool)self::$redis->setex($safeKey, $ttl, $encoded);
                } else {
                    return (bool)self::$redis->set($safeKey, $encoded);
                }
            } catch (Throwable $e) {}
        }

        $filePath = self::$cacheDir . '/' . $safeKey . '.cache';
        $payload = json_encode([
            'expires_at' => $expiresAt,
            'value' => $value
        ]);
        return (bool)@file_put_contents($filePath, $payload, LOCK_EX);
    }

    /**
     * Removes an item from cache.
     */
    public static function delete($key): bool {
        self::init();
        $safeKey = 'lp_agent_' . md5($key);
        unset(self::$memoryCache[$safeKey]);

        if (self::$redis !== null) {
            try {
                self::$redis->del($safeKey);
            } catch (Throwable $e) {}
        }

        $filePath = self::$cacheDir . '/' . $safeKey . '.cache';
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
        return true;
    }

    /**
     * Remembers a computed value in cache.
     */
    public static function remember($key, int $ttl, callable $callback) {
        $val = self::get($key);
        if ($val !== null) {
            return $val;
        }
        $val = $callback();
        self::set($key, $val, $ttl);
        return $val;
    }

    /**
     * Clears all agent cached keys.
     */
    public static function flush(): bool {
        self::init();
        self::$memoryCache = [];
        if (self::$cacheDir && is_dir(self::$cacheDir)) {
            $files = glob(self::$cacheDir . '/*.cache');
            if ($files) {
                foreach ($files as $f) {
                    @unlink($f);
                }
            }
        }
        return true;
    }
}
