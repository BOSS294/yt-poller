<!doctype html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>YT Poller — Premium</title>
    <link rel="stylesheet" href="assets/main.css">
</head>
<body>

    <header class="app-header">
        <div class="brand">
            <div class="play-icon"></div>
            YT POLLER
        </div>
        <div class="creator-tag">Created By Mayank Chawdhari</div>
        <select id="themeSelect" class="input-field" style="width: auto; padding: 5px 15px;">
            <option value="dark">Dark</option>
            <option value="light">Light</option>
            <option value="glass">Glass</option>
            <option value="neon">Cyber Neon</option>
            <option value="deepsea">Deep Sea</option>
        </select>
    </header>

    <main class="container">
        <section id="configPanel" class="panel config-panel">
            <h2 style="margin-bottom:20px">Feed Configuration</h2>
            <div style="display:flex; flex-direction:column; gap:20px">
                <div>
                    <label>Channel URL or ID</label>
                    <input id="channelInput" class="input-field" placeholder="https://youtube.com/@channelname" />
                </div>
                <div style="display:flex; gap:15px">
                    <div style="flex:1">
                        <label>Refresh Rate (Seconds)</label>
                        <input id="pollInterval" type="number" class="input-field" value="300" min="10" />
                    </div>
                    <button id="loadBtn" class="primary-btn" style="align-self: flex-end;">START POLLING</button>
                </div>
            </div>
        </section>

        <section id="feedSection">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px">
                <h2 id="channelTitle">Connect a channel...</h2>
                <div id="syncStatus" class="video-meta"></div>
            </div>
            <div id="videosGrid" class="videos-grid"></div>
        </section>
    </main>

    <div id="settingsFab" class="fab" title="Settings">⚙️</div>

    <div id="modalOverlay" class="modal-overlay">
        <div class="panel modal-content">
            <h3>Update Poller</h3>
            <p class="video-meta" style="margin-bottom:20px">Change channel or interval</p>
            <div style="display:flex; flex-direction:column; gap:15px">
                <input id="modalChannelInput" class="input-field" placeholder="Channel ID/URL" />
                <input id="modalPollInput" type="number" class="input-field" placeholder="Interval (sec)" />
                <div style="display:flex; gap:10px; margin-top:10px">
                    <button id="saveSettings" class="primary-btn" style="flex:1">Save & Reload</button>
                    <button id="closeModal" class="primary-btn" style="background:#444">Cancel</button>
                </div>
            </div>
        </div>
    </div>

<script>
(function(){
    const apiBase = 'https://yusufahmad.sbs/api/get_videos.php'; 
    const configPanel = document.getElementById('configPanel');
    const videosGrid = document.getElementById('videosGrid');
    const fab = document.getElementById('settingsFab');
    const modal = document.getElementById('modalOverlay');
    const syncStatus = document.getElementById('syncStatus');

    let pollTimer = null;

    // Theme Engine
    const themeSelect = document.getElementById('themeSelect');
    themeSelect.onchange = (e) => {
        document.documentElement.setAttribute('data-theme', e.target.value);
        localStorage.setItem('ytp_theme', e.target.value);
    };
    const savedTheme = localStorage.getItem('ytp_theme') || 'dark';
    document.documentElement.setAttribute('data-theme', savedTheme);
    themeSelect.value = savedTheme;

    function showSkeletons() {
        videosGrid.innerHTML = Array(8).fill(0).map(() => `
            <div class="video-card">
                <div class="thumb-wrap skeleton"></div>
                <div class="skeleton" style="height:20px; width:80%; margin-top:10px; border-radius:5px"></div>
                <div class="skeleton" style="height:15px; width:40%; margin-top:8px; border-radius:5px"></div>
            </div>
        `).join('');
    }

    async function loadData(channel) {
        if(!channel) return;
        
        configPanel.classList.add('collapsed');
        fab.classList.add('visible');
        
        showSkeletons();
        const ttl = localStorage.getItem('ytp_ttl') || 300;

        try {
            const res = await fetch(`${apiBase}?channel=${encodeURIComponent(channel)}&limit=12&ttl=${ttl}`);
            const json = await res.json();
            render(json);
        } catch (err) {
            videosGrid.innerHTML = `<div class="panel">Error fetching data. Check channel ID.</div>`;
        }
    }

    function render(feed) {
        document.getElementById('channelTitle').textContent = feed.data.title || 'Channel Feed';
        syncStatus.textContent = 'Syncing every ' + (localStorage.getItem('ytp_ttl') || 300) + 's • Last: ' + new Date().toLocaleTimeString();
        videosGrid.innerHTML = '';

        feed.data.entries.forEach(e => {
            const thumb = e.thumbnails?.[0] || `https://i.ytimg.com/vi/${e.id}/hqdefault.jpg`;
            const div = document.createElement('div');
            div.className = 'video-card';
            div.innerHTML = `
                <a href="${e.link}" target="_blank" style="text-decoration:none; color:inherit">
                    <div class="thumb-wrap"><img src="${thumb}" loading="lazy"></div>
                    <div class="video-title">${e.title}</div>
                    <div class="video-meta">${new Date(e.published).toLocaleDateString()}</div>
                </a>
            `;
            videosGrid.appendChild(div);
        });
    }

    function startPolling() {
        if(pollTimer) clearInterval(pollTimer);
        const interval = parseInt(localStorage.getItem('ytp_ttl') || 300) * 1000;
        pollTimer = setInterval(() => {
            const ch = localStorage.getItem('ytp_channel');
            if(ch) loadData(ch);
        }, interval);
    }

    // Event Listeners
    document.getElementById('loadBtn').onclick = () => {
        const ch = document.getElementById('channelInput').value.trim();
        const ttl = document.getElementById('pollInterval').value;
        if(ch) {
            localStorage.setItem('ytp_channel', ch);
            localStorage.setItem('ytp_ttl', ttl);
            loadData(ch);
            startPolling();
        }
    };

    fab.onclick = () => {
        document.getElementById('modalChannelInput').value = localStorage.getItem('ytp_channel');
        document.getElementById('modalPollInput').value = localStorage.getItem('ytp_ttl');
        modal.classList.add('open');
    };

    document.getElementById('closeModal').onclick = () => modal.classList.remove('open');
    
    document.getElementById('saveSettings').onclick = () => {
        const ch = document.getElementById('modalChannelInput').value.trim();
        const ttl = document.getElementById('modalPollInput').value;
        localStorage.setItem('ytp_channel', ch);
        localStorage.setItem('ytp_ttl', ttl);
        modal.classList.remove('open');
        loadData(ch);
        startPolling();
    };

    // Init
    const initCh = localStorage.getItem('ytp_channel');
    if(initCh) {
        document.getElementById('channelInput').value = initCh;
        loadData(initCh);
        startPolling();
    }

})();
</script>
</body>
</html>