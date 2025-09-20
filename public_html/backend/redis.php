<?php
// php/redis.php - Predis client (composer: predis/predis)
$config = require __DIR__ . '/../config.php';
$client = null;

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
    try {
        $parameters = [
            'scheme' => 'tcp',
            'host' => $config['REDIS_HOST'],
            'port' => $config['REDIS_PORT'],
        ];
        if (!empty($config['REDIS_AUTH'])) $parameters['password'] = $config['REDIS_AUTH'];
        $client = new Predis\Client($parameters);
    } catch (Exception $e) {
        error_log('Redis connection failed: ' . $e->getMessage());
        $client = null;
    }
} else {
    // fallback attempt using phpredis extension (if installed)
    if (extension_loaded('redis')) {
        try {
            $r = new Redis();
            $r->connect($config['REDIS_HOST'], $config['REDIS_PORT']);
            if (!empty($config['REDIS_AUTH'])) $r->auth($config['REDIS_AUTH']);
            $client = $r;
        } catch (Exception $e) {
            error_log('phpredis connection failed: ' . $e->getMessage());
            $client = null;
        }
    }
}

