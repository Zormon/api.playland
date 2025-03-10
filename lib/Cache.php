<?php

namespace Lib;
use \Predis\Client;

//TODO: usar la libreria de leafphp para redis en lugar de predis
class Cache {
    private static $redis;

    private static function connect() {
        if (self::$redis === null) {
            self::$redis = new Client([
                'scheme' => 'tcp',
                'host'   => '127.0.0.1',
                'port'   => 6379,
            ]);
        }
    }

    public static function get($key) {
        self::connect();
        $data = self::$redis->get($key);
        if ($data !== null) {
            return unserialize($data);
        }
        return null;
    }
    
    public static function set($key, $data, $ttl = 3600) {
        self::connect();
        $data = serialize($data);
        self::$redis->setex($key, $ttl, $data);
    }
    
    public static function delete(array|string $keys) {
        self::connect();
        if (!is_array($keys)) {
            $keys = [$keys];
        }
        foreach ($keys as $key) {
            self::$redis->del($key);
        }
    }
}
