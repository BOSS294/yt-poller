<?php
// Safe esc helper
function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'utf-8'); }

// 1. Logic: Detect Configuration
$cfg = null;
$defaultChannel = '';
$configPath = __DIR__ . '/includes/config.php';

// Check config.php
if (file_exists($configPath)) {
    try {
        $cfg = include $configPath;
        if (is_object($cfg) && property_exists($cfg, 'default_channel') && $cfg->default_channel) {
            $defaultChannel = trim((string)$cfg->default_channel);
        }
    } catch (Throwable $e) { /* Ignore */ }
}

// Check file-based default
$fileBased = __DIR__ . '/cache/default_channel.txt';
if (!$defaultChannel && file_exists($fileBased)) {
    $c = trim(@file_get_contents($fileBased));
    if ($c) $defaultChannel = $c;
}

if (!$defaultChannel) {
    $env = getenv('YTPOLLER_DEFAULT_CHANNEL');
    if ($env) $defaultChannel = trim($env);
}

$selected = trim((string)($_GET['channel'] ?? ''));
if (!$selected && $defaultChannel) $selected = $defaultChannel;

$host = 'yourdomain.com'; // Dynamic host logic can go here if needed
$proto = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$apiBase = $proto . '://' . $host . '/api/get_videos.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    
    <title>YT Poller — Developer Setup Guide</title>
    <meta name="description" content="Integration snippets, configuration options and developer-level setup instructions for YT Poller by Mayank Chawdhari.">
    <meta name="author" content="Mayank Chawdhari">
    <meta property="og:title" content="YT Poller Integration Docs">
    <meta property="og:description" content="Drop-in snippets and server-side setup for YouTube Polling.">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

    <style>
        :root {
            --bg-body: #0d1117;
            --bg-sidebar: #010409;
            --bg-card: #161b22;
            --border: #30363d;
            --text-main: #c9d1d9;
            --text-heading: #ffffff;
            --accent: #58a6ff;
            --accent-hover: #79c0ff;
            --success: #238636;
            --code-bg: #0d1117;
            --font-main: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            --font-code: 'JetBrains Mono', monospace;
        }

        /* Syntax Highlighting Colors */
        .token-keyword { color: #ff7b72; } /* Red/Pink */
        .token-string { color: #a5d6ff; } /* Light Blue */
        .token-comment { color: #8b949e; font-style: italic; } /* Grey */
        .token-function { color: #d2a8ff; } /* Purple */
        .token-number { color: #79c0ff; } /* Blue */
        .token-tag { color: #7ee787; } /* Green */
        
        /* JSON Specific Tokens */
        .token-key { color: #7ee787; } /* Green for JSON Keys */
        .token-val-string { color: #a5d6ff; } /* Blue for JSON Values */

        html {
            scroll-behavior: smooth; /* Smooth Scroll Enabled */
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: var(--font-main);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        /* Layout Architecture */
        .doc-layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            max-width: 1500px;
            margin: 0 auto;
            min-height: 100vh;
        }

        /* Sidebar Navigation */
        .sidebar {
            background: var(--bg-sidebar);
            border-right: 1px solid var(--border);
            padding: 24px;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        .brand {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-heading);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .brand i { color: var(--accent); }
        .brand-meta { font-size: 0.8rem; color: #8b949e; margin-bottom: 32px; }

        .nav-link {
            display: flex; align-items: center; gap: 10px;
            color: var(--text-main);
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 6px;
            margin-bottom: 4px;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        .nav-link:hover { background: rgba(255,255,255,0.05); color: var(--accent); }
        .nav-link.active { background: rgba(88, 166, 255, 0.1); color: var(--accent); font-weight: 600; }
        .nav-link i { opacity: 0.7; }

        /* Social Media Icons */
        .social-media {
            display: flex;
            gap: 10px;
            margin-top: auto; /* Push to bottom */
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }
        .sm-icon {
            display: flex; align-items: center; justify-content: center;
            width: 36px; height: 36px;
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--text-main);
            text-decoration: none;
            font-size: 1.2rem;
            transition: all 0.2s;
        }
        .sm-icon:hover {
            color: var(--accent);
            border-color: var(--accent);
            background: rgba(88,166,255,0.1);
        }

        /* Main Content */
        .content { padding: 40px 60px; max-width: 1100px; }

        h1, h2, h3, h4, h5 { color: var(--text-heading); margin-bottom: 16px; font-weight: 600; letter-spacing: -0.02em; }
        h3 { margin-top: 60px; border-bottom: 1px solid var(--border); padding-bottom: 10px; font-size: 1.5rem; scroll-margin-top: 20px; }
        h4 { margin-top: 32px; font-size: 1.1rem; color: var(--text-heading); }
        p { margin-bottom: 16px; color: #8b949e; }
        ul { margin-bottom: 20px; padding-left: 20px; color: #8b949e; }
        li { margin-bottom: 8px; }

        /* Inputs & Forms */
        .input-group {
            display: flex; gap: 12px;
            background: var(--bg-card);
            padding: 20px;
            border-radius: 8px;
            border: 1px solid var(--border);
            margin-bottom: 30px;
        }
        
        .form-control {
            flex: 1;
            background: var(--bg-body);
            border: 1px solid var(--border);
            color: var(--text-main);
            padding: 12px 16px;
            border-radius: 6px;
            font-family: var(--font-code);
            font-size: 0.9rem;
            transition: border-color 0.2s;
        }
        .form-control:focus { outline: none; border-color: var(--accent); }

        .btn {
            background: var(--accent);
            color: white;
            border: none;
            padding: 0 20px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn:hover { background: var(--accent-hover); transform: translateY(-1px); }
        .btn-sm { padding: 6px 12px; font-size: 0.8rem; height: fit-content; }

        /* Code Blocks */
        .code-wrapper {
            position: relative;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 8px;
            margin: 16px 0 24px 0;
            overflow: hidden;
        }
        
        .code-header {
            display: flex; justify-content: space-between; align-items: center;
            padding: 8px 16px;
            background: rgba(255,255,255,0.02);
            border-bottom: 1px solid var(--border);
            font-size: 0.8rem;
            color: #8b949e;
        }
        
        pre {
            padding: 20px;
            overflow-x: auto;
            font-family: var(--font-code);
            font-size: 0.9rem;
            color: var(--text-main);
            background: var(--bg-card);
            margin: 0;
            white-space: pre;
        }

        code.inline {
            background: rgba(110, 118, 129, 0.4);
            padding: 2px 6px;
            border-radius: 4px;
            font-family: var(--font-code);
            font-size: 0.85em;
            color: var(--text-heading);
        }

        /* Tables for API Docs */
        .api-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
        }
        .api-table th {
            text-align: left;
            padding: 12px 16px;
            background: rgba(255,255,255,0.03);
            border-bottom: 1px solid var(--border);
            color: var(--text-heading);
            font-weight: 600;
        }
        .api-table td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            font-size: 0.9rem;
            vertical-align: top;
        }
        .api-table tr:last-child td { border-bottom: none; }
        .type-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-family: var(--font-code);
            background: rgba(121, 192, 255, 0.15);
            color: #79c0ff;
        }

        /* Callouts */
        .callout {
            padding: 16px;
            border-left: 4px solid var(--accent);
            background: rgba(56, 139, 253, 0.1);
            border-radius: 0 6px 6px 0;
            margin: 20px 0;
        }
        .callout strong { color: var(--text-heading); display: block; margin-bottom: 4px; }
        .callout.warning { border-left-color: #d29922; background: rgba(187, 128, 9, 0.1); }
        .callout.success { border-left-color: var(--success); background: rgba(46, 160, 67, 0.1); }

        /* Footer */
        footer {
            margin-top: 60px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
            font-size: 0.9rem;
            color: #8b949e;
            display: flex;
            justify-content: space-between;
        }

        /* Responsive */
        @media (max-width: 900px) {
            .doc-layout { grid-template-columns: 1fr; }
            .sidebar { display: none; } 
            .content { padding: 20px; }
        }
        .callout-compact { padding:10px; border-left:4px solid var(--accent); background:rgba(88,166,255,0.04); border-radius:6px; color:#9fb0c8; font-size:0.95rem; }
        .small-muted { color:#8b949e; font-size:0.92rem; margin-top:6px; }
        .result-box { background: #0b1220; border:1px solid var(--border); padding:12px; border-radius:6px; color:var(--text-main); font-family:var(--font-code); font-size:0.9rem; overflow:auto; }
        .kbd { background:#08111a; padding:2px 6px; border-radius:4px; font-family:var(--font-code); font-size:0.85rem; }
    </style>
</head>
<body>
<div class="doc-layout">
    <aside class="sidebar">
        <div>
            <div class="brand">
                <i class='bx bxs-terminal'></i> YT POLLER
            </div>
            <div class="brand-meta">Setup & Integration Docs</div>
            
            <nav>
                <a href="#quick-start" class="nav-link"><i class='bx bx-rocket'></i> Quick Start</a>
                <?php if($selected): ?>
                <a href="#client-snippet" class="nav-link"><i class='bx bxl-javascript'></i> Client Embed</a>
                <a href="#server-snippet" class="nav-link"><i class='bx bxl-php'></i> Server Cron</a>
                <?php endif; ?>
                <a href="#api-reference" class="nav-link active"><i class='bx bx-data'></i> API Reference</a>
                <a href="#config-guide" class="nav-link"><i class='bx bx-slider-alt'></i> Configuration</a>
                <a href="#file-map" class="nav-link"><i class='bx bx-map-alt'></i> File Map</a>
                <a href="#troubleshoot" class="nav-link"><i class='bx bx-support'></i> Troubleshooting</a>
            </nav>

            <div style="margin-top: 20px; padding: 15px; background: rgba(255,255,255,0.03); border-radius: 8px;">
                <div style="font-size:0.8rem; color:#8b949e; margin-bottom:8px">Built By</div>
                <strong style="color:var(--text-heading)">Mayank Chawdhari</strong>
            </div>
        </div>

        <div class="social-media">
            <a href="https://instagram.com/" target="_blank" class="sm-icon" title="Instagram"><i class='bx bxl-instagram'></i></a>
            <a href="https://linkedin.com/" target="_blank" class="sm-icon" title="LinkedIn"><i class='bx bxl-linkedin-square'></i></a>
            <a href="https://github.com/" target="_blank" class="sm-icon" title="GitHub"><i class='bx bxl-github'></i></a>
            <a href="https://dev.to/" target="_blank" class="sm-icon" title="Dev.to"><i class='bx bxl-dev-to'></i></a>
        </div>
    </aside>

    <main class="content">
        <header style="margin-bottom: 40px;">
            <h1 style="font-size: 2.5rem; margin-bottom: 10px;">Setup & Integration</h1>
            <p style="font-size: 1.1rem;">Practical integration snippets, configuration options and developer-level setup instructions.</p>
        </header>

        <section id="quick-start">
            <h3>Quick start — generate integration code</h3>
            <p>Enter a YouTube channel id, handle or URL — or leave blank to use a server-configured default channel (see "Auto-config" below).</p>

            <form method="get" class="input-group" style="margin-top:12px;">
                <input name="channel" value="<?=esc($selected)?>" class="form-control" placeholder="e.g. UC_xxx or @handle or https://youtube.com/..." />
                <div style="display:flex; gap:8px;">
                    <button type="submit" class="btn"><i class='bx bx-code-block'></i> Generate Code</button>
                    <?php if($selected): ?>
                        <a href="?channel=<?=urlencode($selected)?>&test_api=1" class="btn btn-sm" style="background:#2d2d2d; margin-left:6px;"><i class='bx bx-check'></i> Server API Test</a>
                    <?php endif; ?>
                </div>
            </form>

            <?php if($defaultChannel): ?>
                <div class="callout-compact" style="margin-top:12px">
                    <strong>Default channel detected:</strong>
                    <div class="small-muted" style="margin-top:6px"><?=esc($defaultChannel)?></div>
                    <div class="small-muted">This will be used automatically when no <code class="kbd">?channel=</code> parameter is provided.</div>
                </div>
            <?php else: ?>
                <div class="small-muted" style="margin-top:12px">No default channel found. You can configure one (instructions below) to avoid manual entry.</div>
            <?php endif; ?>
        </section>

        <?php if($selected): ?>
        
        <section id="snippets" style="margin-top:24px">
            <h3>Integration snippets for <code class="inline" style="font-size: 0.8em;"><?=esc($selected)?></code></h3>
            <p>Two recommended integration patterns — pick the one that fits your workflow.</p>

            <div id="client-snippet" style="margin-top:18px;" >
                <h4>1) Client-side JS embed (quick install)</h4>
                <p>Drop this into any page. It calls the local API and renders simple cards using your existing CSS. Ideal for quick demos and low-traffic sites.</p>
                <div class="code-wrapper" style="margin-top:8px;">
                    <div class="code-header">
                        <span><i class='bx bxl-html5'></i> HTML / JS</span>
                        <button class="btn btn-sm btn-copy" data-target="code-client"><i class='bx bx-copy'></i> Copy</button>
                    </div>
                    <pre id="code-client" class="syntax-highlight language-html">
&lt;div id="ytpoller_embed"&gt;&lt;/div&gt;
&lt;script&gt;
(async()=>{
  const channel = "<?=esc($selected)?>";
  const res = await fetch("<?=esc($apiBase)?>?channel="+encodeURIComponent(channel)+"&limit=10");
  const data = await res.json();
  const root = document.getElementById('ytpoller_embed');
  if(!data.data) { root.innerText = 'Error loading videos'; return; }
  root.innerHTML = data.data.entries.map(e=&gt;`
    &lt;div style="display:flex;gap:8px;margin-bottom:10px"&gt;
      &lt;img src="${e.thumbnails?.[0] ?? 'https://i.ytimg.com/vi/'+e.id+'/hqdefault.jpg'}" width="140" style="border-radius:6px"&gt;
      &lt;div&gt;&lt;strong&gt;${e.title}&lt;/strong&gt;&lt;br&gt;${new Date(e.published).toLocaleString()}&lt;/div&gt;
    &lt;/div&gt;`).join('');
})();
&lt;/script&gt;</pre>
                </div>
            </div>

            <div id="server-snippet" style="margin-top:18px;">
                <h4>2) Server-side PHP snippet (recommended — pre-warm cache)</h4>
                <p>Schedule this script with cron to force the API to fetch fresh data and write the JSON cache. This minimizes live fetches and keeps pages fast and reliable.</p>
                <div class="code-wrapper" style="margin-top:8px;">
                    <div class="code-header">
                        <span><i class='bx bxl-php'></i> cron/prime_cache.php</span>
                        <button class="btn btn-sm btn-copy" data-target="code-server"><i class='bx bx-copy'></i> Copy</button>
                    </div>
                    <pre id="code-server" class="syntax-highlight language-php">
&lt;?php
// cron/prime_cache.php
$channel = "<?=esc($selected)?>";
$api = "<?=esc($apiBase)?>";
$url = $api.'?channel='.urlencode($channel).'&ttl=3600&limit=20&force=1';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_exec($ch);
curl_close($ch);
echo "Done\n";
?&gt;</pre>
                </div>
            </div>

            <h4 style="margin-top:18px">Cron examples</h4>
            <div class="code-wrapper">
                <div class="code-header">
                    <span><i class='bx bx-terminal'></i> Crontab / Shell</span>
                    <button class="btn btn-sm btn-copy" data-target="code-cron"><i class='bx bx-copy'></i> Copy</button>
                </div>
                <pre id="code-cron" class="syntax-highlight language-bash">
# Run every 15 minutes (Linux crontab)
*/15 * * * * /usr/bin/php /path/to/cron/prime_cache.php >/dev/null 2>&1

# Or with curl from shell (no PHP installed on that host)
*/15 * * * * /usr/bin/curl -s "<?=esc($apiBase)?>?channel=<?=esc(urlencode($selected))?>&force=1" >/dev/null 2>&1</pre>
            </div>
        </section>

        <?php if ($apiTestResult): ?>
            <section id="api-test" style="margin-top:18px;">
                <h3>Server-side API Test</h3>
                <p class="small-muted">This test was executed from the web server (one quick request). It helps distinguish network/proxy vs. browser issues.</p>
                <div class="code-wrapper">
                    <div class="code-header"><span>Test summary</span></div>
                    <div style="padding:12px">
                        <div class="small-muted"><strong>URL:</strong> <span class="kbd"><?=esc($apiTestResult['url'])?></span></div>
                        <div class="small-muted" style="margin-top:6px"><strong>HTTP status:</strong> <?=esc($apiTestResult['http_code'])?></div>
                        <?php if($apiTestResult['curl_error']): ?>
                            <div class="small-muted" style="margin-top:6px"><strong>cURL error:</strong> <?=esc($apiTestResult['curl_error'])?></div>
                        <?php endif; ?>
                        <div style="margin-top:10px"><strong>Raw response (truncated):</strong></div>
                        <div class="result-box" style="margin-top:8px; max-height:320px"><?=esc(substr((string)$apiTestResult['body'],0,40000))?></div>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <?php endif; ?>

        <section id="api-reference" style="margin-top:26px;">
            <h3><i class='bx bx-data'></i> API Reference</h3>
            <p>The endpoint returns a structured JSON object containing cache metadata and the channel payload. Example response structure:</p>

            <div class="code-wrapper" style="margin-top:8px;">
                <div class="code-header"><span><i class='bx bxs-file-json'></i> JSON Sample</span></div>
                <pre class="syntax-highlight language-json">
{
    "channel": "UVCdRr...",
    "feed_url": "https://www.youtube.com/feeds/videos.xml?channel_id=UCdRr...",
    "fetched_at": 1769738245,
    "ttl": 300,
    "data": {
        "title": "Mayank Chawdhari (TheRealBoss)",
        "author": "Mayank Chawdhari",
        "entries": [
            {
                "id": "yiPJQgregNI",
                "title": "you forgot to call the function...",
                "link": "https://www.youtube.com/shorts/yiPJQgregNI",
                "published": "2024-07-21T18:17:08+00:00",
                "thumbnails": ["https://i.ytimg.com/vi/yiPJQgregNI/hqdefault.jpg"]
            }
        ]
    }
}</pre>
            </div>

            <h4>Query parameters</h4>
            <table class="api-table" style="margin-top:8px">
                <thead><tr><th>Parameter</th><th>Type</th><th>Description</th></tr></thead>
                <tbody>
                    <tr><td><code>channel</code></td><td><span class="type-badge">string</span></td><td>Channel id (UC...) or any channel URL/handle. Server resolves to canonical UC id.</td></tr>
                    <tr><td><code>limit</code></td><td><span class="type-badge">integer</span></td><td>Max videos returned (default: <?php echo esc($cfg->default_limit ?? 20); ?>).</td></tr>
                    <tr><td><code>ttl</code></td><td><span class="type-badge">integer</span></td><td>Cache TTL in seconds (server enforces max: <?php echo esc($cfg->max_ttl ?? 86400); ?>).</td></tr>
                    <tr><td><code>force</code></td><td><span class="type-badge">boolean</span></td><td>Set to <code>1</code> to force fetch and update cache (use in cron).</td></tr>
                </tbody>
            </table>
        </section>

        <section id="config-guide" style="margin-top:28px;">
            <h3>How to configure a default channel <span style="font-weight:400;color:#8b949e;font-size:1.1rem">(so site visitors never need to type an ID)</span></h3>
            <p style="margin-bottom:18px;color:#b3bed7;">Choose <strong>one</strong> of these three options to set your default YouTube channel. This ensures your integration works out-of-the-box for all users.</p>

            <div class="dcontainer config-options-grid">
                <div class="code-wrapper">
                    <div class="code-header"><span><i class='bx bx-cog'></i> Option A — <b>includes/config.php</b> <span class="type-badge" style="margin-left:8px;">Recommended</span></span></div>
                    <pre class="syntax-highlight language-php" style="padding:12px;">
// inside includes/config.php return object:
'default_channel' => 'UC_xxxxxxxxxxxxxxxxx', // ← add this line (replace with your UC id)
                    </pre>
                    <div class="small-muted" style="margin-top:6px">
                        <i class='bx bx-info-circle'></i> 
                        <b>Best for most setups.</b> After adding, <code>setup.php</code> and all generated snippets will use this channel by default.
                    </div>
                </div>

                <div class="code-wrapper">
                    <div class="code-header"><span><i class='bx bxl-docker'></i> Option B — <b>Environment variable</b> <span class="type-badge" style="margin-left:8px;">Docker/Cloud</span></span></div>
                    <pre class="syntax-highlight language-bash" style="padding:12px;">
# docker-compose example
environment:
  - YTPOLLER_DEFAULT_CHANNEL=UC_xxxxxxxxxxxxx
                    </pre>
                    <div class="small-muted" style="margin-top:6px">
                        <i class='bx bx-cloud'></i> 
                        <b>Best for Docker/cloud deployments.</b> Keeps config out of code and easy to automate.
                    </div>
                </div>

                <div class="code-wrapper">
                    <div class="code-header"><span><i class='bx bx-file'></i> Option C — <b>File-based</b> <span class="type-badge" style="margin-left:8px;">Simple</span></span></div>
                    <pre class="syntax-highlight language-bash" style="padding:12px;">echo 'UC_xxxxxxxxxxxxx' &gt; cache/default_channel.txt</pre>
                    <div class="small-muted" style="margin-top:6px">
                        <i class='bx bx-file'></i> 
                        <b>Quickest for local/dev.</b> Just drop your channel ID in a file, no code changes needed.
                    </div>
                </div>
            </div>
        </section>

        <style>
            #config-guide .dcontainer,
            #config-guide .config-options-grid {
                display: grid !important;
                grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
                gap: 18px;
                margin-top: 12px;
                max-height: 270px;
                min-height: 180px;
                overflow-y: auto;
                padding-bottom: 4px;
                scrollbar-width: thin;
                scrollbar-color: var(--accent) var(--bg-card);
                border-radius: 10px;
            }
            #config-guide .dcontainer::-webkit-scrollbar,
            #config-guide .config-options-grid::-webkit-scrollbar {
                width: 8px;
                background: var(--bg-card);
                border-radius: 6px;
            }
            #config-guide .dcontainer::-webkit-scrollbar-thumb,
            #config-guide .config-options-grid::-webkit-scrollbar-thumb {
                background: var(--accent);
                border-radius: 6px;
            }
            #config-guide .code-wrapper {
                min-height: 140px;
                display: flex;
                flex-direction: column;
                justify-content: flex-start;
            }
            #config-guide .code-header {
                font-size: 0.92rem;
                font-weight: 500;
                color: #b3bed7;
                background: rgba(255,255,255,0.02);
                border-bottom: 1px solid var(--border);
                padding: 8px 16px;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            #config-guide .type-badge {
                background: rgba(121, 192, 255, 0.18);
                color: #79c0ff;
                font-size: 0.75rem;
                border-radius: 4px;
                padding: 2px 7px;
                font-family: var(--font-code);
                margin-left: 4px;
            }
        </style>

        <section id="file-map" style="margin-top:28px;">
            <h3>Code & file map — what each file does</h3>
            <div style="margin-top:12px">
                <ul>
                    <li><strong>includes/config.php</strong> — global settings (TTL, cache_dir, allowed_origins). Add <code>default_channel</code> here to auto-configure.</li>
                    <li><strong>includes/cache.php</strong> — file-based JSON cache helper (atomic writes + locking).</li>
                    <li><strong>includes/fetcher.php</strong> — channel resolver, RSS builder, cURL fetcher, RSS → structured array parser.</li>
                    <li><strong>api/get_videos.php</strong> — API gateway, handles TTL, caching, safe fallback and JSON error responses.</li>
                    <li><strong>index.php</strong> — frontend viewer (themeable, polls API, stores user choices in localStorage).</li>
                    <li><strong>setup.php</strong> — this file: integration docs, snippets and test helper.</li>
                </ul>
            </div>
        </section>

        <section id="troubleshoot" style="margin-top:28px;">
            <h3>Troubleshooting & monitoring</h3>

            <div class="callout-compact">
                <strong>Common failure: <code>fetch_failed</code> or HTTP 502</strong>
                <div class="small-muted" style="margin-top:8px">
                    Root causes:
                    <ol style="margin-left:20px">
                        <li>Invalid channel input or unresolved handle → make sure the channel resolves to a UC id.</li>
                        <li>Server cannot reach YouTube (network / firewall / proxy).</li>
                        <li>Missing PHP extensions: <code>curl</code> or <code>simplexml</code>.</li>
                        <li>Cache directory not writable or protected incorrectly.</li>
                    </ol>
                </div>
            </div>

            <h4 style="margin-top:14px">Quick server checks</h4>
            <div class="code-wrapper">
                <div class="code-header"><span>Run on server shell</span></div>
                <pre class="syntax-highlight language-bash" id="diagnostic-curl">curl -s "<?=esc($apiBase)?>?channel=<?=esc(urlencode($selected ?: 'UC_xxxxxxx'))?>" | jq .</pre>
            </div>

            <h4 style="margin-top:12px">Logs & where to look</h4>
            <ul style="margin-top:8px">
                <li><code>cache/ytpoller_errors.log</code> — app-level PHP error log (if you enabled logging in get_videos.php).</li>
                <li>PHP-FPM / php-fpm logs — fatal errors, permission errors. E.g. <code>/var/log/php-fpm/www-error.log</code>.</li>
                <li>Webserver error logs (nginx/apache) — reverse proxy problems and upstream timeouts.</li>
            </ul>

            <h4 style="margin-top:12px">Monitoring suggestions</h4>
            <ul>
                <li>Add a lightweight health endpoint (e.g., <code>/health.php</code>) that checks cache write permissions and connectivity, then call it from uptime monitors.</li>
                <li>Rotate <code>ytpoller_errors.log</code> and alert on repeated fetch failures (> 3 times in 1 hour).</li>
            </ul>
        </section>

        <section style="margin-top:28px;">
            <h3>Advanced & optional: Docker Compose example</h3>
            <div class="code-wrapper">
                <div class="code-header"><span>docker-compose.yml (snippet)</span></div>
                <pre class="syntax-highlight language-yaml">
version: "3.8"
services:
  ytpoller:
    image: php:8.1-apache
    volumes:
      - ./:/var/www/html
    environment:
      - YTPOLLER_DEFAULT_CHANNEL=<?=esc($selected ?: 'UC_xxxxxxx')?>
    ports:
      - "8080:80"
                </pre>
            </div>

            <div class="small-muted" style="margin-top:8px">This example mounts the repo, sets a default channel, and exposes the app on port 8080. Adjust for production (use real image / php-fpm + nginx + supervisor, etc.).</div>
        </section>


    </main>
</div>
<script>
// 1. Copy Functionality
document.querySelectorAll('.btn-copy').forEach(btn => {
    btn.addEventListener('click', async () => {
        const targetId = btn.getAttribute('data-target');
        const el = document.getElementById(targetId);
        if (!el) return;
        
        try {
            await navigator.clipboard.writeText(el.innerText);
            const originalHTML = btn.innerHTML;
            btn.innerHTML = "<i class='bx bx-check'></i> Copied";
            btn.style.background = "var(--success)";
            setTimeout(() => {
                btn.innerHTML = originalHTML;
                btn.style.background = ""; // reset
            }, 2000);
        } catch (e) {
            alert('Copy failed');
        }
    });
});

// 2. Syntax Highlighting
(function highlightCode() {
    const rules = {
        'php': [
            { regex: /(&lt;\?php|\?&gt;)/g, class: 'token-tag' },
            { regex: /\b(echo|function|return|if|else|foreach|include|try|catch)\b/g, class: 'token-keyword' },
            { regex: /(\$[a-zA-Z_]\w*)/g, class: 'token-function' }, 
            { regex: /"(.*?)"|'(.*?)'/g, class: 'token-string' },
            { regex: /(\/\/.*|\/\*[\s\S]*?\*\/)/g, class: 'token-comment' },
        ],
        'html': [
             { regex: /(&lt;\/?[a-z][^&gt;]*&gt;)/gi, class: 'token-tag' },
             { regex: /"(.*?)"/g, class: 'token-string' },
             { regex: /\b(const|await|async|function|return|var|let)\b/g, class: 'token-keyword' },
             { regex: /(\/\/.*)/g, class: 'token-comment' }
        ],
        'bash': [
            { regex: /\b(curl|docker|php)\b/g, class: 'token-keyword' },
            { regex: /"(.*?)"/g, class: 'token-string' },
            { regex: /(#.*)/g, class: 'token-comment' }
        ],
        'json': [
            { regex: /"([a-zA-Z0-9_]+)":/g, class: 'token-key' },  // Keys
            { regex: /: "([^"]*)"/g, class: 'token-val-string' }, // String Values
            { regex: /: (\d+)/g, class: 'token-number' }          // Number Values
        ]
    };

    document.querySelectorAll('.syntax-highlight').forEach(block => {
        let html = block.innerHTML;
        let lang = 'php'; 
        if(block.classList.contains('language-html')) lang = 'html';
        if(block.classList.contains('language-bash')) lang = 'bash';
        if(block.classList.contains('language-json')) lang = 'json';

        // Apply rules specific to language
        if (lang === 'json') {
             // JSON requires a slightly different approach to avoid overriding itself
             html = html.replace(/"([a-zA-Z0-9_]+)":/g, '<span class="token-key">"$1":</span>');
             html = html.replace(/: "([^"]*)"/g, ': <span class="token-val-string">"$1"</span>');
             html = html.replace(/: (\d+)/g, ': <span class="token-number">$1</span>');
        } else {
             // Basic replacement for other langs
             html = html.replace(/(\/\/.*$)/gm, '<span class="token-comment">$1</span>');
             html = html.replace(/(".*?")/g, '<span class="token-string">$1</span>');
        }
        
        block.innerHTML = html;
    });
})();
</script>

</body>
</html>