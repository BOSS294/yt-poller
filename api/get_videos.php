<?php
// api/get_videos.php
// Hardened drop-in replacement to avoid 502 Bad Gateway and return JSON errors.

// DEV config: set to false in production to avoid exposing stack traces
define('YTPOLLER_DEV', true);

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../cache/ytpoller_errors.log');
error_reporting(E_ALL);

// helper: unified JSON response
function json_response($data, $http = 200) {
    http_response_code($http);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

// unified error handler & exception handler so PHP doesn't return HTML 502
set_exception_handler(function($e){
    $payload = ['error' => 'internal_server_error', 'message' => $e->getMessage()];
    if (YTPOLLER_DEV) {
        $payload['exception'] = [
            'class' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => explode("\n", $e->getTraceAsString())
        ];
    }
    error_log("Unhandled Exception: " . $e->getMessage());
    json_response($payload, 500);
});
set_error_handler(function($severity, $message, $file, $line) {
    // convert errors to exceptions so they are handled by exception handler
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Basic environment checks
if (!extension_loaded('curl')) {
    json_response(['error' => 'server_missing_extension', 'message' => 'cURL extension is required.'], 500);
}
if (!extension_loaded('simplexml')) {
    json_response(['error' => 'server_missing_extension', 'message' => 'SimpleXML extension is required.'], 500);
}

// Safe require: verify files exist first
$baseIncludes = realpath(__DIR__ . '/../includes');
if (!$baseIncludes || !is_dir($baseIncludes)) {
    json_response(['error' => 'includes_missing', 'message' => 'Includes directory not found.'], 500);
}

$cacheFile = $baseIncludes . '/cache.php';
$fetcherFile = $baseIncludes . '/fetcher.php';
$configFile = $baseIncludes . '/config.php';

foreach ([$cacheFile, $fetcherFile, $configFile] as $f) {
    if (!file_exists($f)) {
        json_response(['error' => 'missing_file', 'message' => basename($f) . ' is missing in includes/'], 500);
    }
}

// require files
require_once $cacheFile;
require_once $fetcherFile;
$cfg = include $configFile;

// CORS handling
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (is_array($cfg->allowed_origins) && (in_array('*', $cfg->allowed_origins) || in_array($origin, $cfg->allowed_origins))) {
    header("Access-Control-Allow-Origin: " . (in_array('*', $cfg->allowed_origins) ? '*' : $origin));
    header("Access-Control-Allow-Methods: GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Input parsing - sanitize
$channelRaw = trim((string)($_GET['channel'] ?? ''));
$force = isset($_GET['force']) && (($_GET['force'] === '1') || strtolower($_GET['force']) === 'true');
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : ($cfg->default_limit ?? 20);
$ttl = isset($_GET['ttl']) ? intval($_GET['ttl']) : ($cfg->default_ttl ?? 300);
$ttl = max(0, min($ttl, $cfg->max_ttl ?? 86400));

if ($channelRaw === '') {
    json_response(['error' => 'missing_channel', 'message' => 'Please provide channel parameter. Example: ?channel=UCxxxxx or channel URL.'], 400);
}

// parse channel using your fetcher helper (it must exist)
if (!function_exists('parse_channel_input')) {
    json_response(['error' => 'server_missing_function', 'message' => 'parse_channel_input not found in fetcher.php'], 500);
}

$channel = parse_channel_input($channelRaw);
if ($channel === false) {
    json_response(['error' => 'invalid_channel', 'message' => 'Could not parse channel id/URL. Provide valid channel id (UC...) or URL.'], 400);
}

try {
    $cachePath = cache_get_path($channel);
} catch (Throwable $e) {
    // If cache_get_path throws, respond gracefully
    error_log("cache_get_path error: " . $e->getMessage());
    json_response(['error' => 'cache_error', 'message' => 'Failed to build cache path. Check permissions.'], 500);
}

// read existing cache (if any)
$cached = null;
try {
    $cached = cache_read($channel);
} catch (Throwable $e) {
    // Log but continue (we can still try to fetch fresh)
    error_log("cache_read error: " . $e->getMessage());
    $cached = null;
}

$now = time();
$shouldFetch = $force ? true : false;
if ($cached) {
    $cached_age = $now - (int)($cached['fetched_at'] ?? 0);
    if ($cached_age > $ttl) $shouldFetch = true;
    if (!$shouldFetch && isset($cfg->min_fetch_interval) && $cached_age < $cfg->min_fetch_interval) {
        // prevent too frequent fetch; continue serving cache
        $shouldFetch = false;
    }
} else {
    $shouldFetch = true;
}

// Fetch logic (safe)
$feed = null;
if ($shouldFetch) {
    if (!function_exists('build_feed_url') || !function_exists('fetch_feed_xml') || !function_exists('parse_youtube_rss')) {
        json_response(['error' => 'server_missing_function', 'message' => 'fetcher helper functions missing (build_feed_url, fetch_feed_xml, parse_youtube_rss)'], 500);
    }

    $feedUrl = build_feed_url($channel);
    // Basic URL sanity check
    if (!filter_var($feedUrl, FILTER_VALIDATE_URL)) {
        error_log("Invalid feed URL generated: {$feedUrl}");
        // fallback to cached if present
        if ($cached) $feed = $cached;
    } else {
        $xml = null;
        try {
            $xml = fetch_feed_xml($feedUrl);
        } catch (Throwable $e) {
            error_log("fetch_feed_xml thrown: " . $e->getMessage());
            $xml = null;
        }

        if ($xml) {
            $parsed = null;
            try {
                $parsed = parse_youtube_rss($xml);
            } catch (Throwable $e) {
                error_log("parse_youtube_rss thrown: " . $e->getMessage());
                $parsed = null;
            }
            if ($parsed) {
                $feed = [
                    'channel' => $channel,
                    'feed_url' => $feedUrl,
                    'fetched_at' => $now,
                    'ttl' => $ttl,
                    'data' => $parsed,
                ];
                // write cache safely
                try {
                    cache_write($channel, $feed);
                } catch (Throwable $e) {
                    error_log("cache_write error: " . $e->getMessage());
                    // don't fail the response because of cache write error
                }
            } else {
                // parsing failed -> fallback to cache if available
                if ($cached) $feed = $cached;
            }
        } else {
            // fetch failed -> fallback to cache if available
            if ($cached) $feed = $cached;
        }
    }
}

// If still no feed, error out with reason
if (!$feed) {
    json_response(['error' => 'fetch_failed', 'message' => 'Could not fetch feed and no cached data available. Check feed URL and server network/curl.'], 502);
}

// Trim by limit
if (isset($feed['data']['entries']) && $limit > 0) {
    $feed['data']['entries'] = array_slice($feed['data']['entries'], 0, $limit);
}

// Return result
json_response($feed, 200);
