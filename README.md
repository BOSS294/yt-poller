# YT Poller — Lightweight YouTube RSS Poller & Viewer

**One line:** A production-ready PHP + client-side project that polls YouTube RSS for a channel, caches results as JSON, and exposes a tiny API + embeddable UI — fast, configurable, and easy to self-host.

---

![License](https://img.shields.io/badge/license-MIT-green)
![Language](https://img.shields.io/badge/lang-PHP%20%2B%20JS-lightgrey)

---

# Table of contents

* [What is this](#what-is-this)
* [Key features](#key-features)
* [Why use it (SEO & performance)](#why-use-it-seo--performance)
* [Quickstart — run it locally / server](#quickstart---run-it-locally--server)
* [Configuration](#configuration)
* [Cron / Pre-warm cache](#cron--pre-warm-cache)
* [Client embed snippet](#client-embed-snippet)
* [API reference (short)](#api-reference-short)
* [Troubleshooting & logs](#troubleshooting--logs)


---

# What is this

YT Poller fetches a YouTube channel’s public feed (RSS), parses entries, caches the structured JSON on disk and exposes a safe HTTP API: `GET /api/get_videos.php?channel=...`. It ships with a themeable frontend (`index.php`) and a detailed integration/setup doc (`setup.php`) to generate copy-paste snippets for clients and cron.

---

# Key features

* Small, production-minded PHP API with robust error handling and cache fallback
* File-based JSON cache (atomic writes + flock) — simple and portable
* Channel resolver that accepts UC ids, channel URLs and handles (@handle)
* Client-side embeddable snippet and server-side cron snippet (recommended)
* Themeable UI (dark/light/glass) and mobile-first CSS
* Clear JSON errors (no HTML 502 pages) so frontends can handle failures gracefully

---

# Why use it (SEO & performance)

* Serving a cached JSON feed avoids on-demand YouTube fetches, reducing latency and network failures.
* Pre-warmed cache + static client UI -> very fast page loads which search engines prefer.
* Use canonical, indexable markup on your public pages (embed thumbnails, publish dates and structured data) to improve discoverability. See [Structured Data](#seo-helpers) below.

---

# Quickstart — run it locally / server

1. Clone repo:

   ```bash
   git clone https://github.com/<your-username>/yt-poller.git
   cd yt-poller
   ```
2. Ensure PHP (7.4+/8.x) with `curl` and `simplexml` extensions is installed:

   ```bash
   php -m | grep -E "curl|simplexml"
   ```
3. Create cache folder and set permissions:

   ```bash
   mkdir -p cache
   chown www-data:www-data cache   # adapt user to your host
   chmod 750 cache
   ```
4. Edit `includes/config.php` as needed (see next section).
5. Upload to any PHP-capable host (shared hosting, VPS, Render, DigitalOcean App Platform, etc.), or run with built-in PHP server for testing:

   ```bash
   php -S 0.0.0.0:8000
   # then open http://localhost:8000/index.php
   ```

---

# Configuration

Open `includes/config.php` and review these keys:

```php
return (object)[
  'default_ttl' => 300,            // default TTL in seconds
  'max_ttl' => 86400,              // maximum TTL allowed
  'cache_dir' => __DIR__ . '/../cache',
  'allowed_origins' => ['*'],      // restrict in production
  'user_agent' => 'YT-Poller/1.0 (+https://yourproject.example)',
  'min_fetch_interval' => 10,
  'default_limit' => 20,
  // optional:
  // 'default_channel' => 'UC_xxxxxxxxx', // uncomment to auto configure
];
```

**Auto-default channel**

* Option A (recommended): add `'default_channel' => 'UC_xxxxx'` in `includes/config.php`
* Option B (Docker): environment variable `YTPOLLER_DEFAULT_CHANNEL`
* Option C (simple file): `cache/default_channel.txt` (containing the UC id)

If no default channel is set, visitors can enter a channel in the UI or embed code can pass `?channel=...` in the API call.

---

# Cron / Pre-warm cache

To avoid live fetch failures and to reduce latency, run a cron that calls the API with `force=1`. Example script `cron/prime_cache.php`:

```php
<?php
$channel = "UC_xxxxxxxxx";
$api = "https://yourdomain.com/api/get_videos.php";
$url = $api.'?channel='.urlencode($channel).'&ttl=3600&limit=20&force=1';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_exec($ch);
curl_close($ch);
echo "Done\n";
```

Crontab:

```
# every 15 minutes
*/15 * * * * /usr/bin/php /var/www/yourrepo/cron/prime_cache.php >/dev/null 2>&1
```

---

# Client embed snippet

Copy-paste this into any page. The snippet calls your hosted API and renders minimal cards.

```html
<div id="ytpoller_embed"></div>
<script>
(async()=>{
  const channel = "UC_xxxxxxxxx";
  const res = await fetch("https://yourdomain.com/api/get_videos.php?channel="+encodeURIComponent(channel)+"&limit=10");
  const data = await res.json();
  const root = document.getElementById('ytpoller_embed');
  if(!data.data) { root.innerText = 'Error loading videos'; return; }
  root.innerHTML = data.data.entries.map(e=>`
    <div style="display:flex;gap:8px;margin-bottom:10px">
      <img src="${e.thumbnails?.[0] ?? 'https://i.ytimg.com/vi/'+e.id+'/hqdefault.jpg'}" width="140" style="border-radius:6px">
      <div><strong>${e.title}</strong><br>${new Date(e.published).toLocaleString()}</div>
    </div>`).join('');
})();
</script>
```

---

# API reference (short)

`GET /api/get_videos.php`

| Parameter |    Type | Description                                                 |
| --------- | ------: | ----------------------------------------------------------- |
| channel   |  string | channel id (UC...) or URL/handle — server resolves to UC id |
| limit     | integer | max videos to return                                        |
| ttl       | integer | cache TTL (seconds) — server enforces `max_ttl`             |
| force     | boolean | `1` to force fetch and write cache (use in cron)            |

Response: JSON structure with `channel`, `feed_url`, `fetched_at`, `ttl` and `data` (title, entries[]).

---

# Troubleshooting & logs

* `fetch_failed` or HTTP 502 — check: network/cURL, cache permissions, missing PHP extensions.
* Check `cache/ytpoller_errors.log` (if enabled) and server logs (PHP-FPM, Nginx/Apache).
* From the server:

  ```
  curl -s "https://yourdomain.com/api/get_videos.php?channel=UC_xxxx" | jq .
  ```
* Ensure `cache/` is writable and not publicly reachable (protect via `.htaccess` or nginx `deny`).


