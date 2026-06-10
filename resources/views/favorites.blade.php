@extends('layouts.app')

@section('title', 'My Favorites - LiveTV BD')

@section('content')
<div class="movies-explorer">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <h2 class="section-title">My Favorites</h2>
        <!-- Tabs -->
        <div class="genre-chips">
            <span class="genre-chip active" id="tab-channels-btn" onclick="switchFavTab('channels')">Live Channels</span>
            <span class="genre-chip" id="tab-movies-btn" onclick="switchFavTab('movies')">Movies</span>
        </div>
    </div>

    <!-- Favorites Grid -->
    <div class="media-grid" id="favorites-grid">
        <!-- Will be populated dynamically from IndexedDB -->
        <div class="text-center w-100 p-5 text-muted">Loading your favorites...</div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    let currentTab = 'channels';
    let allFavorites = [];

    document.addEventListener('DOMContentLoaded', () => {
        // Delay to allow IndexedDB connection to establish in layout
        setTimeout(loadFavorites, 300);
    });

    function loadFavorites() {
        getFavoritesList((list) => {
            allFavorites = list;
            renderTabItems();
        });
    }

    function switchFavTab(tab) {
        currentTab = tab;
        document.getElementById('tab-channels-btn').classList.toggle('active', tab === 'channels');
        document.getElementById('tab-movies-btn').classList.toggle('active', tab === 'movies');
        renderTabItems();
    }

    function renderTabItems() {
        const grid = document.getElementById('favorites-grid');
        grid.innerHTML = '';

        const filtered = allFavorites.filter(item => {
            if (currentTab === 'channels') {
                return item.type === 'channel';
            } else {
                return item.type === 'movie';
            }
        });

        if (filtered.length === 0) {
            grid.innerHTML = `
                <div class="text-center w-100 p-5 text-muted">
                    <i class="bi bi-heartbreak" style="font-size: 48px; color: var(--text-muted)"></i>
                    <p class="mt-3">No favorite ${currentTab === 'channels' ? 'channels' : 'movies'} saved yet.</p>
                    <a href="${currentTab === 'channels' ? APP_URL : APP_URL + '/movies'}" class="btn-primary d-inline-flex mt-2">Browse ${currentTab === 'channels' ? 'TV' : 'Movies'}</a>
                </div>
            `;
            return;
        }

        filtered.forEach(item => {
            const card = document.createElement('div');
            card.className = 'movie-card';
            
            if (currentTab === 'channels') {
                card.onclick = () => {
                    localStorage.setItem('last_watched_channel', item.name);
                    window.location.href = APP_URL;
                };
            } else {
                card.onclick = () => {
                    window.location.href = `${APP_URL}/movies/${item.id}`;
                };
            }

            card.innerHTML = `
                <div class="rating-badge" onclick="handleRemoveClick(event, '${item.id}')" style="color: #FF4B4B;">
                    <i class="bi bi-trash-fill"></i>
                </div>
                <div class="movie-poster">
                    <img src="${item.logo}" alt="${item.name || item.title}" onerror="this.src='https://images.unsplash.com/photo-1440404653325-ab127d49abc1?w=300'">
                </div>
                <div class="movie-details-brief">
                    <h3 class="movie-title">${item.name || item.title}</h3>
                    <div class="movie-meta">
                        <span>${item.group || 'Category'}</span>
                        <span>HD</span>
                    </div>
                </div>
            `;
            grid.appendChild(card);
        });
    }

    function handleRemoveClick(event, itemId) {
        event.stopPropagation();
        const item = allFavorites.find(i => i.id === itemId);
        if (item) {
            toggleFavorite(item, () => {
                loadFavorites();
            });
        }
    }
</script>
@endsection
