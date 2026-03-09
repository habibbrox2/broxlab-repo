<?php
// Path: /app/Modules/AISystem/Cache.php

class Cache {
    private $cacheDir;

    public function __construct() {
        // Mapping system/cache/chat/ to h:\Web\broxbhai\system\cache\chat
        $this->cacheDir = realpath(__DIR__ . '/../../../system/cache');
        if (!$this->cacheDir) {
            $this->cacheDir = __DIR__ . '/../../../system/cache';
        }
        $this->cacheDir .= DIRECTORY_SEPARATOR . 'chat' . DIRECTORY_SEPARATOR;

        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0777, true);
        }
    }

    public function get($key) {
        $file = $this->cacheDir . $key . '.json';
        if (file_exists($file) && (time() - filemtime($file)) < 3600) { // 1 hour cache
            $content = file_get_contents($file);
            return $content ? json_decode($content, true) : false;
        }
        return false;
    }

    public function set($key, $data, $ttl = 3600) {
        $file = $this->cacheDir . $key . '.json';
        @file_put_contents($file, json_encode($data));
    }
}
