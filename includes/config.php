<?php
// includes/config.php

return (object)[
    // Default cache TTL in seconds when not specified by client (e.g., 300 = 5 minutes)
    'default_ttl' => 300,

    // Max allowed TTL (so clients can't request extremely long caching)
    'max_ttl' => 86400, // 24 hours

    // Path to cache directory - must be writable by PHP
    'cache_dir' => __DIR__ . '/../cache',

    // Allowed CORS origins - set to your site or * for public. For production set exact host.
    'allowed_origins' => ['*'],

    // User agent used when fetching remote feeds
    'user_agent' => 'YT-Poller/1.0 (+https://yourproject.example)',

    // Rate limit: minimal seconds between fetches per channel by server-side protection
    'min_fetch_interval' => 10, // seconds

    // Maximum items to return by default
    'default_limit' => 20,
];
