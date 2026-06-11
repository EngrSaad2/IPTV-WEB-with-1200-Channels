<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'LiveTV BD - Premium IPTV & Movies')</title>
    
    <!-- Bootstrap CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <!-- Custom Style -->
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <link rel="icon" type="image/png" href="{{ asset('images/fav.png') }}">
    
    <!-- HLS.js for IPTV Playback -->
    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
</head>
<body>

    <div class="app-container">
        <!-- Sidebar Navigation -->
        <aside class="app-sidebar">
            <div class="sidebar-logo">
                <i class="bi bi-tv-fill"></i>
            </div>
            
            <nav class="sidebar-menu">
                <a href="{{ route('home') }}" class="sidebar-item {{ Request::routeIs('home') ? 'active' : '' }}" title="Live TV">
                    <i class="bi bi-house-fill"></i>
                </a>
                <a href="{{ route('movies') }}" class="sidebar-item {{ Request::routeIs('movies') || Request::routeIs('movie.detail') ? 'active' : '' }}" title="Movies Explorer">
                    <i class="bi bi-grid-fill"></i>
                </a>
                <a href="{{ route('favorites') }}" class="sidebar-item {{ Request::routeIs('favorites') ? 'active' : '' }}" title="Favorites">
                    <i class="bi bi-heart-fill"></i>
                </a>
                <a href="{{ route('home') }}?category=movies" class="sidebar-item" title="Movies Streams">
                    <i class="bi bi-play-btn-fill"></i>
                </a>
            </nav>
            
            <div class="sidebar-footer">
                <a href="{{ route('profile') }}" class="sidebar-item {{ Request::routeIs('profile') ? 'active' : '' }}" title="Settings">
                    <i class="bi bi-gear-fill"></i>
                </a>
                <a href="{{ route('dev-info') }}" class="sidebar-item" title="Developer Info">
                    <i class="bi bi-box-arrow-right"></i>
                </a>
            </div>
        </aside>

        <!-- Main Content Wrapper -->
        <main class="app-content">
            <!-- Header -->
            <header class="app-header">
                <div class="user-greeting">
                    <h4>Welcome Back</h4>
                    <h1 id="global-username">Engr Saad</h1>
                </div>
                
                <div class="header-right">
                    <!-- Global Search -->
                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <input type="text" id="global-search-input" placeholder="Search movies..." onkeypress="handleGlobalSearch(event)">
                    </div>
                    
                    <!-- Avatar -->
                    <div class="user-avatar" onclick="window.location.href='{{ route('profile') }}'">
                        <img id="global-avatar-img" src="{{ asset('images/profile.jpg') }}" alt="Profile">
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            @yield('content')

            <!-- Footer -->
            <footer class="app-footer mt-auto pt-4 pb-2 text-center" style="border-top: 1px solid var(--border-glass); color: var(--text-secondary); font-size: 13px;">
                Copyright All Rights Reserved &copy; Triangle Technologies Ltd || Developed by 
                <a href="https://engr-saad.com/" target="_blank" style="color: #FFC107; text-decoration: none; font-weight: 600;">Engr Saad</a>
            </footer>
        </main>
    </div>

    <!-- Toast Element -->
    <div id="app-toast" class="toast-message"></div>

    <!-- Core App Logic -->
    <script>
        const APP_URL = "{{ url('/') }}";
        const API_BASE = "{{ url('api') }}";

        // Load User Profile Settings
        document.addEventListener('DOMContentLoaded', () => {
            loadUserProfile();
            initializeFavoritesDb();
        });

        function loadUserProfile() {
            const username = localStorage.getItem('user_name') || 'Engr Saad';
            const avatar = localStorage.getItem('user_avatar') || "{{ asset('images/profile.jpg') }}";
            
            document.getElementById('global-username').textContent = username;
            document.getElementById('global-avatar-img').src = avatar;
        }

        // Global Search Redirection
        function handleGlobalSearch(event) {
            if (event.key === 'Enter') {
                const query = event.target.value.trim();
                if (query) {
                    window.location.href = "{{ route('movies') }}?search=" + encodeURIComponent(query);
                }
            }
        }

        // Show toast helper
        function showToast(message) {
            const toast = document.getElementById('app-toast');
            toast.textContent = message;
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        // IndexedDB Favorites Handler
        let db;
        function initializeFavoritesDb() {
            const request = indexedDB.open('LiveTVdb', 1);
            request.onerror = (e) => console.error('IndexedDB open error:', e);
            request.onsuccess = (e) => {
                db = e.target.result;
            };
            request.onupgradeneeded = (e) => {
                const dbInstance = e.target.result;
                if (!dbInstance.objectStoreNames.contains('favorites')) {
                    dbInstance.createObjectStore('favorites', { keyPath: 'id' });
                }
                if (!dbInstance.objectStoreNames.contains('watch_history')) {
                    dbInstance.createObjectStore('watch_history', { keyPath: 'id' });
                }
            };
        }

        function toggleFavorite(item, callback) {
            if (!db) return;
            const transaction = db.transaction(['favorites'], 'readwrite');
            const store = transaction.objectStore('favorites');
            const checkRequest = store.get(item.id);
            
            checkRequest.onsuccess = () => {
                if (checkRequest.result) {
                    store.delete(item.id);
                    showToast(`${item.name || item.title} removed from favorites`);
                    if (callback) callback(false);
                } else {
                    store.add(item);
                    showToast(`${item.name || item.title} added to favorites`);
                    if (callback) callback(true);
                }
            };
        }

        function checkIsFavorite(itemId, callback) {
            if (!db) {
                setTimeout(() => checkIsFavorite(itemId, callback), 100);
                return;
            }
            const transaction = db.transaction(['favorites'], 'readonly');
            const store = transaction.objectStore('favorites');
            const getRequest = store.get(itemId);
            getRequest.onsuccess = () => {
                callback(!!getRequest.result);
            };
        }

        function getFavoritesList(callback) {
            if (!db) {
                setTimeout(() => getFavoritesList(callback), 100);
                return;
            }
            const transaction = db.transaction(['favorites'], 'readonly');
            const store = transaction.objectStore('favorites');
            const getAllRequest = store.getAll();
            getAllRequest.onsuccess = () => {
                callback(getAllRequest.result);
            };
        }
    </script>

    @yield('scripts')
</body>
</html>
