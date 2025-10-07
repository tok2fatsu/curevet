<?php
// backend/includes/redis.php
// Safe, CSP-compliant Redis connection helper

$config = require __DIR__ . '/../config.php';
$client = null;

try {
    // Prefer Unix socket (fast and local)
    $socketPath = '/home/curevedd/tmp/redis.sock';
    if (extension_loaded('redis')) {
        $r = new Redis();
        $r->connect($socketPath);
        if (!empty($config['REDIS_AUTH'])) $r->auth($config['REDIS_AUTH']);
        $client = $r;
    } else {
        require_once __DIR__ . '/../vendor/autoload.php';
        $client = new Predis\Client(['scheme' => 'unix', 'path' => $socketPath]);
    }
} catch (Exception $e) {
    error_log('Redis connection failed: ' . $e->getMessage());
    $client = null;
}

