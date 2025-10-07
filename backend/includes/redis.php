<?php
// redis.php — robust Redis connection handler

$config = require __DIR__ . '/../config.php';
$client = null;

// Define your Redis socket path
$redis_socket = '/home/curevedd/tmp/redis.sock';

try {
    // First try Predis (Composer-based)
    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        require_once __DIR__ . '/vendor/autoload.php';

        $parameters = [
            'scheme' => 'unix',
            'path'   => $redis_socket,
        ];

        if (!empty($config['REDIS_AUTH'])) {
            $parameters['password'] = $config['REDIS_AUTH'];
        }

        $client = new Predis\Client($parameters);
        $client->ping(); // test connection
        error_log('Redis connected via UNIX socket using Predis.');
    }

    // Fallback to phpredis extension if Predis not available
    if ($client === null && extension_loaded('redis')) {
        $r = new Redis();
        $connected = $r->connect($redis_socket);
        if (!$connected) {
            throw new Exception('phpredis connection via socket failed.');
        }

        if (!empty($config['REDIS_AUTH'])) {
            $r->auth($config['REDIS_AUTH']);
        }

        $client = $r;
        error_log('Redis connected via UNIX socket using phpredis.');
    }

    // Last fallback: Try TCP if socket fails
    if ($client === null) {
        $r = new Redis();
        $connected = $r->connect($config['REDIS_HOST'] ?? '127.0.0.1', $config['REDIS_PORT'] ?? 6379);
        if (!$connected) {
            throw new Exception('Redis TCP connection failed.');
        }
        if (!empty($config['REDIS_AUTH'])) {
            $r->auth($config['REDIS_AUTH']);
        }
        $client = $r;
        error_log('Redis connected via TCP fallback.');
    }

} catch (Exception $e) {
    error_log('Redis initialization failed: ' . $e->getMessage());
    $client = null;
}

// Optional: expose for include files
return $client;

