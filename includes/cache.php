<?php
// includes/cache.php

function cache_get_path($channelId) {
    $cfg = include __DIR__ . '/config.php';
    $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $channelId);
    return rtrim($cfg->cache_dir, '/')."/{$safe}.json";
}

function cache_read($channelId) {
    $path = cache_get_path($channelId);
    if (!file_exists($path)) return null;
    $json = @file_get_contents($path);
    if (!$json) return null;
    $data = json_decode($json, true);
    return $data ?: null;
}

function cache_write($channelId, $data) {
    $path = cache_get_path($channelId);
    $dir = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0750, true);
    $temp = $path . '.tmp';
    $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    $fp = fopen($temp, 'c');
    if (!$fp) return false;
    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        fwrite($fp, $json);
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
    rename($temp, $path);
    return true;
}
