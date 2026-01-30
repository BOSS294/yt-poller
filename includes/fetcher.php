<?php
// includes/fetcher.php

function parse_channel_input($input) {
    $input = trim($input);
    if (preg_match('#(UC[0-9A-Za-z_-]{22,})#', $input, $m)) {
        return $m[1];
    }
    // channel URL like https://www.youtube.com/@handle or /user/username
    if (preg_match('#youtube\.com/(?:channel/|c/|user/|@)?([A-Za-z0-9_\-]+)#', $input, $m)) {
        // If it looks like a handle (starts with @) return as-is (we'll try feed by username not guaranteed)
        $val = $m[1];
        if (strpos($val, '@') === 0) return $val;
        if (preg_match('/^UC[0-9A-Za-z_-]{22,}$/', $val)) return $val;
        return $val;
    }
    return false;
}

function build_feed_url($channel) {
    // If it's an exact channel id starting with UC
    if (preg_match('/^UC[0-9A-Za-z_-]{22,}$/', $channel)) {
        return "https://www.youtube.com/feeds/videos.xml?channel_id={$channel}";
    }
    // If it's a handle starting with @
    if (strpos($channel, '@') === 0) {
        // YouTube supports '@handle' in web UI; RSS by handle is not supported directly.
        // Try the channel page feed via the channel's page (best-effort - may redirect)
        $encoded = urlencode($channel);
        return "https://www.youtube.com/feeds/videos.xml?user={$encoded}";
    }

    // Falling back to user param or attempt to use as user name
    return "https://www.youtube.com/feeds/videos.xml?user=" . urlencode($channel);
}

function fetch_feed_xml($url, $timeout = 10) {
    $cfg = include __DIR__ . '/config.php';
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_USERAGENT => $cfg->user_agent,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($err || $code >= 400) return null;
    return $resp;
}

function parse_youtube_rss($xmlStr) {
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xmlStr, 'SimpleXMLElement', LIBXML_NOCDATA);
    if (!$xml) return null;
    $nsMedia = $xml->getNamespaces(true);
    $entries = [];
    foreach ($xml->entry as $entry) {
        $e = [];
        $e['id'] = (string)$entry->children('yt', true)->videoId ?: (string)$entry->id;
        $e['title'] = (string)$entry->title;
        $e['link'] = (string)$entry->link['href'];
        $e['published'] = (string)$entry->published;
        $e['updated'] = (string)$entry->updated;
        $media = $entry->children($nsMedia['media'] ?? 'media', true);
        if ($media) {
            $group = $media->group;
            $e['description'] = (string)$group->description;
            // thumbnails
            $thumbs = [];
            foreach ($group->thumbnail as $t) {
                $attrs = $t->attributes();
                $thumbs[] = (string)$attrs['url'];
            }
            $e['thumbnails'] = $thumbs;
        }
        $entries[] = $e;
    }
    $feed = [];
    $feed['title'] = (string)$xml->title;
    $feed['link'] = (string)$xml->link['href'] ?? null;
    $feed['author'] = (string)$xml->author->name ?? null;
    $feed['updated'] = (string)$xml->updated ?? null;
    $feed['entries'] = $entries;
    return $feed;
}
