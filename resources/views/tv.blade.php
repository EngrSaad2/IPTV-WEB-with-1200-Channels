@extends('layouts.app')

@section('title', 'Live TV Dashboard - LiveTV BD')

@section('content')
<div class="dashboard-grid">
    <!-- Left: Video Player Panel -->
    <div class="hero-player-panel">
        <div class="video-container">
            <!-- HTML5 Video Player -->
            <video id="hls-video" class="video-element" controls preload="auto"></video>
            
            <!-- Floating Quality Selector -->
            <div id="video-quality-selector" class="video-quality-selector" style="display: none;">
                <button class="quality-btn" id="quality-trigger" onclick="toggleQualityDropdown(event)">
                    <i class="bi bi-three-dots-vertical"></i> <span id="current-quality-label">Auto</span>
                </button>
                <div class="quality-dropdown" id="quality-dropdown-menu">
                    <!-- Dynamic level list -->
                </div>
            </div>
            
            <!-- Video Overlay for Hero Info -->
            <div id="video-overlay" class="video-overlay">
                <span class="video-tag" id="player-category">Premier League</span>
                <h2 class="video-title" id="player-title">Chelsea vs Arsenal</h2>
                <div class="video-controls">
                    <button class="btn-primary" id="player-action-btn" onclick="startDefaultStream()">
                        <i class="bi bi-play-fill"></i> Watch Now
                    </button>
                    <button class="btn-secondary" id="player-fav-btn" onclick="toggleCurrentFavorite()">
                        <i class="bi bi-record-circle"></i> Record
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Bottom Category Selectors -->
        <div class="categories-container mt-3">
            <h2 class="section-title">Categories</h2>
            <div class="categories-grid">
                <div class="category-card active" onclick="switchCategory('sports', this)">
                    <div class="category-icon"><i class="bi bi-dribbble"></i></div>
                    <h3>Sports</h3>
                </div>
                <div class="category-card" onclick="switchCategory('bangladeshi', this)">
                    <div class="category-icon"><i class="bi bi-flag-fill"></i></div>
                    <h3>Bangladeshi</h3>
                </div>
                <div class="category-card" onclick="switchCategory('movies', this)">
                    <div class="category-icon"><i class="bi bi-film"></i></div>
                    <h3>Movies</h3>
                </div>
                <div class="category-card" onclick="switchCategory('news', this)">
                    <div class="category-icon"><i class="bi bi-newspaper"></i></div>
                    <h3>News</h3>
                </div>
                <div class="category-card" onclick="switchCategory('islamic', this)">
                    <div class="category-icon"><i class="bi bi-moon-stars-fill"></i></div>
                    <h3>Islamic</h3>
                </div>
                <div class="category-card" onclick="switchCategory('kids', this)">
                    <div class="category-icon"><i class="bi bi-controller"></i></div>
                    <h3>Kids</h3>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Right: Channels List -->
    <div class="recommended-panel">
        <h2 class="section-title">
            Channels
            <span class="badge" id="channels-count">0 Channels</span>
        </h2>
        
        <!-- Channels Container -->
        <div class="media-list-vertical" id="channels-list">
            <!-- Shimmer Loaders -->
            <div class="media-item-row shimmer-row shimmer"></div>
            <div class="media-item-row shimmer-row shimmer"></div>
            <div class="media-item-row shimmer-row shimmer"></div>
            <div class="media-item-row shimmer-row shimmer"></div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    let currentChannels = [];
    let activeChannel = null;
    let hls = null;
    let wakeLock = null;

    function safeBtoa(str) {
        try {
            return btoa(unescape(encodeURIComponent(str))).replace(/[^a-zA-Z0-9]/g, '');
        } catch (e) {
            return str.replace(/[^a-zA-Z0-9]/g, '_');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        // Load default category
        const urlParams = new URLSearchParams(window.location.search);
        const categoryParam = urlParams.get('category') || 'sports';
        
        // Highlight active category tab
        const cards = document.querySelectorAll('.category-card');
        cards.forEach(card => {
            const heading = card.querySelector('h3').textContent.toLowerCase();
            if (heading === categoryParam) {
                cards.forEach(c => c.classList.remove('active'));
                card.classList.add('active');
            }
        });

        loadChannels(categoryParam);
        setupScreenWakeLock();
    });

    async function loadChannels(category) {
        const listContainer = document.getElementById('channels-list');

        listContainer.innerHTML = `
            <div class="media-item-row shimmer-row shimmer"></div>
            <div class="media-item-row shimmer-row shimmer"></div>
            <div class="media-item-row shimmer-row shimmer"></div>
        `;

        try {
            // Map web categories to controllers
            let apiCategory = 'all';
            if (category === 'bangladeshi') apiCategory = 'bangladeshi';
            if (category === 'islamic') apiCategory = 'islamic';
            if (category === 'movies') apiCategory = 'movies';
            if (category === 'news') apiCategory = 'global';

            const response = await fetch(`${API_BASE}/channels?category=${apiCategory}`);
            const data = await response.json();
            
            // Client-side filtering for sub-categories
            let filtered = data;
            if (category === 'sports') {
                filtered = data.filter(c => c.group.toLowerCase().includes('sports') || c.name.toLowerCase().includes('sports') || c.name.toLowerCase().includes('ten'));
            } else if (category === 'news') {
                filtered = data.filter(c => c.group.toLowerCase().includes('news') || c.name.toLowerCase().includes('news') || c.name.toLowerCase().includes('al jazeera') || c.name.toLowerCase().includes('cnn'));
            } else if (category === 'kids') {
                filtered = data.filter(c => c.name.toLowerCase().includes('kids') || c.name.toLowerCase().includes('cartoon') || c.name.toLowerCase().includes('disney') || c.name.toLowerCase().includes('nickelodeon') || c.group.toLowerCase().includes('kids'));
            }

            currentChannels = filtered;
            document.getElementById('channels-count').textContent = `${filtered.length} Channels`;
            renderChannels(filtered);
            
            // Auto-play T Sports HD if present, otherwise last watched channel, otherwise first channel
            if (filtered.length > 0) {
                const tSports = filtered.find(c => {
                    const norm = c.name.toLowerCase().replace(/[^a-z0-9]/g, '');
                    return norm === 'tsports' || norm === 'tsportshd';
                });
                if (tSports) {
                    playChannel(tSports);
                } else {
                    const lastChannelName = localStorage.getItem('last_watched_channel');
                    const found = lastChannelName ? filtered.find(c => c.name === lastChannelName) : null;
                    playChannel(found || filtered[0]);
                }
            }

        } catch (error) {
            console.error('Failed to load channels:', error);
            listContainer.innerHTML = '<div class="p-3 text-center text-danger">Failed to load channels</div>';
        }
    }

    function renderChannels(channels) {
        const listContainer = document.getElementById('channels-list');
        if (channels.length === 0) {
            listContainer.innerHTML = '<div class="p-3 text-center text-muted">No channels found</div>';
            return;
        }

        listContainer.innerHTML = '';
        channels.forEach(channel => {
            const isPlaying = activeChannel && activeChannel.name === channel.name;
            const row = document.createElement('div');
            row.className = `media-item-row ${isPlaying ? 'active' : ''}`;
            row.onclick = () => playChannel(channel);
            
            row.innerHTML = `
                <div class="media-thumbnail">
                    <img src="${channel.logo}" alt="${channel.name}" onerror="this.src='https://tstatic.akash-go.com/cms-ui/images/custom-content/1770377900139.png'">
                </div>
                <div class="media-info">
                    <h3 class="media-name">${channel.name}</h3>
                    <p class="media-group">${channel.group}</p>
                </div>
                <button class="favorite-btn" onclick="handleFavClick(event, '${channel.name}', '${channel.logo}', '${channel.url}', '${channel.group}')">
                    <i class="bi bi-heart" id="fav-icon-${safeBtoa(channel.name)}"></i>
                </button>
            `;
            listContainer.appendChild(row);

            // Sync favorite icon state
            checkIsFavorite(channel.name, (isFav) => {
                const icon = document.getElementById(`fav-icon-${safeBtoa(channel.name)}`);
                if (icon) {
                    if (isFav) {
                        icon.className = 'bi bi-heart-fill active';
                        icon.style.color = '#FF4B4B';
                    } else {
                        icon.className = 'bi bi-heart';
                        icon.style.color = '';
                    }
                }
            });
        });
    }

    function setHeroDetails(channel, isAutoplay) {
        document.getElementById('player-category').textContent = channel.group;
        document.getElementById('player-title').textContent = channel.name;
        
        // Update hero buttons
        const actionBtn = document.getElementById('player-action-btn');
        actionBtn.onclick = () => playChannel(channel);
        actionBtn.innerHTML = '<i class="bi bi-play-fill"></i> Watch Now';
        
        // Update background visual
        const container = document.querySelector('.video-container');
        if (!isAutoplay) {
            container.style.backgroundImage = `linear-gradient(to top, rgba(0,0,0,0.8) 0%, rgba(0,0,0,0.2) 100%), url('https://images.unsplash.com/photo-1508098682722-e99c43a406b2?w=1000')`;
            container.style.backgroundSize = 'cover';
            container.style.backgroundPosition = 'center';
        } else {
            container.style.backgroundImage = 'none';
        }

        // Update favorite record button in hero
        checkIsFavorite(channel.name, (isFav) => {
            const favBtn = document.getElementById('player-fav-btn');
            if (isFav) {
                favBtn.innerHTML = '<i class="bi bi-record-fill text-danger"></i> Favorited';
            } else {
                favBtn.innerHTML = '<i class="bi bi-record-circle"></i> Add Favorite';
            }
        });
    }

    function playChannel(channel) {
        activeChannel = channel;
        setHeroDetails(channel, true);
        localStorage.setItem('last_watched_channel', channel.name);

        // Highlight in the list
        const rows = document.querySelectorAll('.media-item-row');
        rows.forEach(row => {
            const nameEl = row.querySelector('.media-name');
            if (nameEl && nameEl.textContent === channel.name) {
                rows.forEach(r => r.classList.remove('active'));
                row.classList.add('active');
            }
        });

        // Hide overlay and start video playback
        document.getElementById('video-overlay').style.display = 'none';
        const video = document.getElementById('hls-video');
        video.style.display = 'block';
        document.getElementById('video-quality-selector').style.display = 'block';

        if (hls) {
            hls.destroy();
        }

        // Initialize alternate links tracking
        let channelUrls = [channel.url];
        if (channel.alternates && Array.isArray(channel.alternates)) {
            channel.alternates.forEach(alt => {
                if (alt !== channel.url && !channelUrls.includes(alt)) {
                    channelUrls.push(alt);
                }
            });
        }
        let currentUrlIndex = 0;

        if (Hls.isSupported()) {
            hls = new Hls({
                enableWorker: true,
                lowLatencyMode: true,
                maxBufferLength: 10,
                maxMaxBufferLength: 15,
                maxBufferSize: 30 * 1024 * 1024,
                liveBackBufferLength: 5,
                liveSyncPosition: 3,
                initialLiveManifestSize: 3,
                liveDurationInfinity: true,
                abrEwmaDefaultEstimate: 4000000, // Bias initial load estimate to 4 Mbps to favor HD
                abrBandwidthFactor: 0.95,
                abrBandwidthLimit: 0,
                testBandwidth: true,
                fragLoadPolicy: {
                    default: {
                        maxTimeToFirstByteMs: 5000,
                        maxLoadTimeMs: 10000,
                        timeoutRetry: { maxNumRetry: 4, retryDelayMs: 500, maxRetryDelayMs: 4000 },
                        errorRetry: { maxNumRetry: 3, retryDelayMs: 1000, maxRetryDelayMs: 4000 }
                    }
                }
            });
            hls.loadSource(channelUrls[currentUrlIndex]);
            hls.attachMedia(video);
            
            hls.on(Hls.Events.MANIFEST_PARSED, () => {
                video.play().catch(e => {
                    // Autoplay blocked fallback
                    document.getElementById('video-overlay').style.display = 'flex';
                    const actionBtn = document.getElementById('player-action-btn');
                    actionBtn.innerHTML = '<i class="bi bi-play-fill"></i> Click to Play';
                });
                applyPreferredResolution();
                buildQualitySelector();
            });

            hls.on(Hls.Events.LEVEL_SWITCHED, (event, data) => {
                if (hls.autoLevelEnabled) {
                    const activeLevel = hls.levels[data.level];
                    if (activeLevel) {
                        const height = activeLevel.height;
                        const label = height ? `${height}p` : 'Auto';
                        document.getElementById('current-quality-label').textContent = `Auto (${label})`;
                    }
                }
            });

            hls.on(Hls.Events.ERROR, (event, data) => {
                if (data.fatal) {
                    switch (data.type) {
                        case Hls.ErrorTypes.NETWORK_ERROR:
                            if (currentUrlIndex < channelUrls.length - 1) {
                                currentUrlIndex++;
                                console.warn(`Switching to alternate URL index ${currentUrlIndex} due to network error.`);
                                showToast(`Stream failed. Trying alternate link ${currentUrlIndex + 1}...`);
                                hls.loadSource(channelUrls[currentUrlIndex]);
                                hls.startLoad();
                            } else {
                                console.error("HLS network error, all alternates failed.");
                                showToast("Stream unavailable. Links may be expired or geo-blocked.");
                                document.getElementById('video-quality-selector').style.display = 'none';
                                hls.destroy();
                                hls = null;
                                
                                const overlay = document.getElementById('video-overlay');
                                overlay.style.display = 'flex';
                                const actionBtn = document.getElementById('player-action-btn');
                                actionBtn.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Stream Unavailable';
                                actionBtn.onclick = null;
                            }
                            break;
                        case Hls.ErrorTypes.MEDIA_ERROR:
                            console.warn("HLS media error, attempting recovery...");
                            hls.recoverMediaError();
                            break;
                        default:
                            if (currentUrlIndex < channelUrls.length - 1) {
                                currentUrlIndex++;
                                console.warn(`Switching to alternate URL index ${currentUrlIndex} due to fatal error.`);
                                showToast(`Stream failed. Trying alternate link ${currentUrlIndex + 1}...`);
                                hls.loadSource(channelUrls[currentUrlIndex]);
                                hls.startLoad();
                            } else {
                                console.error("HLS unrecoverable playback error:", data);
                                showToast("Error playing stream. All links failed.");
                                document.getElementById('video-quality-selector').style.display = 'none';
                                hls.destroy();
                            }
                            break;
                    }
                }
            });
        } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
            // Safari fallback
            video.src = channelUrls[currentUrlIndex];
            video.play();

            video.onerror = () => {
                if (currentUrlIndex < channelUrls.length - 1) {
                    currentUrlIndex++;
                    console.warn(`Safari fallback: switching to alternate URL index ${currentUrlIndex}`);
                    showToast(`Stream failed. Trying alternate link ${currentUrlIndex + 1}...`);
                    video.src = channelUrls[currentUrlIndex];
                    video.play();
                } else {
                    showToast("Error playing stream. All links failed.");
                }
            };
        } else {
            showToast("HLS playback not supported on this browser.");
        }
    }

    function startDefaultStream() {
        if (activeChannel) {
            playChannel(activeChannel);
        } else if (currentChannels.length > 0) {
            playChannel(currentChannels[0]);
        }
    }

    function switchCategory(category, element) {
        const cards = document.querySelectorAll('.category-card');
        cards.forEach(c => c.classList.remove('active'));
        element.classList.add('active');
        
        // Stop video when switching category
        const video = document.getElementById('hls-video');
        video.pause();
        video.style.display = 'none';
        document.getElementById('video-quality-selector').style.display = 'none';
        document.getElementById('video-overlay').style.display = 'flex';
        if (hls) {
            hls.destroy();
            hls = null;
        }

        activeChannel = null;
        loadChannels(category);
    }

    // Quality Selector Helpers
    function applyPreferredResolution() {
        if (!hls) return;
        const preferred = localStorage.getItem('user_streaming_quality') || 'auto';
        if (preferred === 'auto') {
            hls.currentLevel = -1;
            return;
        }

        const targetHeight = parseInt(preferred, 10);
        const levels = hls.levels;
        let bestIndex = -1;
        let minDiff = Infinity;

        // Find closest resolution
        for (let i = 0; i < levels.length; i++) {
            if (levels[i].height) {
                const diff = Math.abs(levels[i].height - targetHeight);
                if (diff < minDiff) {
                    minDiff = diff;
                    bestIndex = i;
                }
            }
        }

        if (bestIndex !== -1) {
            hls.currentLevel = bestIndex;
            const height = levels[bestIndex].height;
            document.getElementById('current-quality-label').textContent = height ? `${height}p` : 'HD';
        }
    }

    function buildQualitySelector() {
        const selector = document.getElementById('video-quality-selector');
        const menu = document.getElementById('quality-dropdown-menu');
        if (!hls || !menu) return;

        const levels = hls.levels;
        if (!levels || levels.length <= 1) {
            selector.style.display = 'none';
            return;
        }

        selector.style.display = 'block';
        menu.innerHTML = '';

        // Auto Level Option
        const autoOpt = document.createElement('button');
        autoOpt.className = `quality-option ${hls.loadLevel === -1 ? 'active' : ''}`;
        autoOpt.onclick = (e) => {
            e.stopPropagation();
            setQualityLevel(-1, 'Auto');
        };
        autoOpt.innerHTML = `Auto <i class="bi bi-check2 ${hls.loadLevel === -1 ? '' : 'd-none'}"></i>`;
        menu.appendChild(autoOpt);

        // Quality levels reversed (highest resolution first)
        for (let i = levels.length - 1; i >= 0; i--) {
            const level = levels[i];
            const height = level.height;
            const bitrate = (level.bitrate / 1000000).toFixed(1);
            let label = height ? `${height}p` : `${bitrate} Mbps`;
            
            if (height >= 720) {
                label += ' <span class="badge bg-danger ms-1" style="font-size: 8px; padding: 2px 4px;">HD</span>';
            }

            const opt = document.createElement('button');
            opt.className = `quality-option ${hls.loadLevel === i ? 'active' : ''}`;
            opt.onclick = (e) => {
                e.stopPropagation();
                setQualityLevel(i, height ? `${height}p` : `${bitrate} Mbps`);
            };
            opt.innerHTML = `${label} <i class="bi bi-check2 ${hls.loadLevel === i ? '' : 'd-none'}"></i>`;
            menu.appendChild(opt);
        }
    }

    function setQualityLevel(levelIndex, label) {
        if (!hls) return;
        
        hls.currentLevel = levelIndex;
        document.getElementById('current-quality-label').textContent = levelIndex === -1 ? 'Auto' : label;
        
        buildQualitySelector();
        document.getElementById('quality-dropdown-menu').classList.remove('show');
        showToast(`Quality set to ${levelIndex === -1 ? 'Auto' : label}`);
    }

    function toggleQualityDropdown(event) {
        event.stopPropagation();
        const menu = document.getElementById('quality-dropdown-menu');
        if (menu) {
            menu.classList.toggle('show');
        }
    }

    document.addEventListener('click', (e) => {
        const menu = document.getElementById('quality-dropdown-menu');
        const trigger = document.getElementById('quality-trigger');
        if (menu && menu.classList.contains('show') && !menu.contains(e.target) && e.target !== trigger) {
            menu.classList.remove('show');
        }
    });

    function handleFavClick(event, name, logo, url, group) {
        event.stopPropagation();
        const item = { id: name, name: name, logo: logo, url: url, group: group, type: 'channel' };
        toggleFavorite(item, (isFav) => {
            const icon = document.getElementById(`fav-icon-${safeBtoa(name)}`);
            if (icon) {
                if (isFav) {
                    icon.className = 'bi bi-heart-fill active';
                    icon.style.color = '#FF4B4B';
                } else {
                    icon.className = 'bi bi-heart';
                    icon.style.color = '';
                }
            }
            // Update hero fav state if current channel
            if (activeChannel && activeChannel.name === name) {
                const favBtn = document.getElementById('player-fav-btn');
                favBtn.innerHTML = isFav ? '<i class="bi bi-record-fill text-danger"></i> Favorited' : '<i class="bi bi-record-circle"></i> Add Favorite';
            }
        });
    }

    function toggleCurrentFavorite() {
        const target = activeChannel || (currentChannels.length > 0 ? currentChannels[0] : null);
        if (!target) return;
        
        const item = { id: target.name, name: target.name, logo: target.logo, url: target.url, group: target.group, type: 'channel' };
        toggleFavorite(item, (isFav) => {
            const favBtn = document.getElementById('player-fav-btn');
            favBtn.innerHTML = isFav ? '<i class="bi bi-record-fill text-danger"></i> Favorited' : '<i class="bi bi-record-circle"></i> Add Favorite';
            
            // Sync side list row icon if visible
            const icon = document.getElementById(`fav-icon-${safeBtoa(target.name)}`);
            if (icon) {
                if (isFav) {
                    icon.className = 'bi bi-heart-fill active';
                    icon.style.color = '#FF4B4B';
                } else {
                    icon.className = 'bi bi-heart';
                    icon.style.color = '';
                }
            }
        });
    }

    // WakeLock API to keep screen active during streaming
    async function setupScreenWakeLock() {
        try {
            if ('wakeLock' in navigator) {
                document.addEventListener('visibilitychange', async () => {
                    if (wakeLock !== null && document.visibilityState === 'visible') {
                        wakeLock = await navigator.wakeLock.request('screen');
                    }
                });
                // Request wake lock initially
                wakeLock = await navigator.wakeLock.request('screen');
            }
        } catch (err) {
            console.warn('Wake Lock request failed:', err.message);
        }
    }
</script>
@endsection
