@extends('layouts.app')

@section('title', 'Movie Details - LiveTV BD')

@section('content')
<div class="detail-container" id="movie-detail-content">
    <!-- Left: Movie Player & Info -->
    <div class="detail-main">
        <!-- Video Player Container -->
        <div class="video-container" id="player-container">
            <div class="d-flex justify-content-center align-items-center h-100 shimmer shimmer-row"></div>
        </div>

        <!-- Info Details -->
        <div class="detail-info-block mt-3">
            <div class="detail-title-row">
                <div class="detail-title-left">
                    <h1 class="detail-title" id="movie-title">Loading...</h1>
                    <div class="detail-meta-row">
                        <span class="detail-rating" id="movie-rating"><i class="bi bi-star-fill"></i> 0.0</span>
                        <span id="movie-year">0000</span>
                        <span id="movie-runtime">0m</span>
                        <span id="movie-genres-list"></span>
                    </div>
                </div>
                <div class="detail-actions">
                    <button class="btn-secondary btn-fav" id="movie-fav-btn" onclick="toggleMovieFavorite()">
                        <i class="bi bi-heart"></i> Favorite
                    </button>
                </div>
            </div>
            
            <p class="detail-overview" id="movie-overview">
                Loading details...
            </p>
        </div>
    </div>
    
    <!-- Right: Similar Movies List -->
    <div class="detail-sidebar">
        <h2 class="section-title">Similar Movies</h2>
        <div class="media-list-vertical" id="similar-movies-list">
            <div class="media-item-row shimmer-row shimmer"></div>
            <div class="media-item-row shimmer-row shimmer"></div>
            <div class="media-item-row shimmer-row shimmer"></div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const movieId = "{{ $id }}";
    let activeMovie = null;

    document.addEventListener('DOMContentLoaded', () => {
        loadMovieDetails();
    });

    async function loadMovieDetails() {
        try {
            const response = await fetch(`${API_BASE}/movies/detail/${movieId}`);
            if (!response.ok) throw new Error('Movie details load failed');
            
            const data = await response.json();
            activeMovie = data;
            renderDetails(data);
        } catch (error) {
            console.error('Failed to load movie details:', error);
            document.getElementById('movie-detail-content').innerHTML = `
                <div class="col-12 p-5 text-center">
                    <h3 class="text-danger">Failed to load movie details.</h3>
                    <a href="{{ route('movies') }}" class="btn-primary d-inline-flex mt-3">Back to Movies</a>
                </div>
            `;
        }
    }

    function renderDetails(movie) {
        document.getElementById('movie-title').textContent = movie.title;
        document.getElementById('movie-rating').innerHTML = `<i class="bi bi-star-fill"></i> ${movie.vote_average}`;
        document.getElementById('movie-year').textContent = movie.release_year || 'Unknown';
        document.getElementById('movie-runtime').textContent = `${movie.runtime} mins`;
        document.getElementById('movie-overview').textContent = movie.overview || 'No description available.';

        // Render Genres
        const genreContainer = document.getElementById('movie-genres-list');
        genreContainer.innerHTML = '';
        if (movie.genres && movie.genres.length > 0) {
            movie.genres.forEach(g => {
                const badge = document.createElement('span');
                badge.className = 'genre-badge';
                badge.textContent = g.name;
                genreContainer.appendChild(badge);
            });
        }

        // Play YouTube trailer by default
        const playerContainer = document.getElementById('player-container');
        if (movie.trailer_key) {
            playerContainer.innerHTML = `
                <iframe class="video-element" id="player-iframe"
                        src="https://www.youtube.com/embed/${movie.trailer_key}?autoplay=1&mute=0&rel=0&modestbranding=1" 
                        title="${movie.title} - Trailer" 
                        frameborder="0" 
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" 
                        referrerpolicy="strict-origin-when-cross-origin"
                        allowfullscreen>
                </iframe>
                <div class="player-badge trailer-badge">
                    <i class="bi bi-youtube"></i> Trailer
                </div>
            `;
        } else if (movie.backdrop_path) {
            playerContainer.innerHTML = `
                <img src="${movie.backdrop_path}" alt="${movie.title}" class="video-element" style="object-fit: cover;">
                <div class="video-overlay">
                    <span class="video-tag">No Trailer Available</span>
                    <h2 class="video-title">${movie.title}</h2>
                </div>
            `;
        } else {
            playerContainer.innerHTML = `
                <div class="player-empty">
                    <i class="bi bi-film"></i>
                    <span>No preview available</span>
                </div>
            `;
        }

        // Sync Favorite Button State
        checkIsFavorite(movie.id.toString(), (isFav) => {
            const favBtn = document.getElementById('movie-fav-btn');
            if (isFav) {
                favBtn.className = 'btn-primary btn-fav';
                favBtn.innerHTML = '<i class="bi bi-heart-fill"></i> Favorited';
            } else {
                favBtn.className = 'btn-secondary btn-fav';
                favBtn.innerHTML = '<i class="bi bi-heart"></i> Favorite';
            }
        });

        // Render Similar Movies
        renderSimilar(movie.similar || []);
    }

    function renderSimilar(similarList) {
        const sidebar = document.getElementById('similar-movies-list');
        if (similarList.length === 0) {
            sidebar.innerHTML = '<div class="p-3 text-center text-muted">No similar movies found.</div>';
            return;
        }

        sidebar.innerHTML = '';
        similarList.forEach(m => {
            const row = document.createElement('div');
            row.className = 'media-item-row';
            row.onclick = () => window.location.href = `${APP_URL}/movies/${m.id}`;
            row.innerHTML = `
                <div class="media-thumbnail">
                    <img src="${m.poster_path}" alt="${m.title}" onerror="this.src='https://images.unsplash.com/photo-1440404653325-ab127d49abc1?w=300'">
                </div>
                <div class="media-info">
                    <h3 class="media-name">${m.title}</h3>
                    <p class="media-group">${m.release_year || 'Unknown'} &bull; <i class="bi bi-star-fill text-warning"></i> ${m.vote_average}</p>
                </div>
            `;
            sidebar.appendChild(row);
        });
    }

    function toggleMovieFavorite() {
        if (!activeMovie) return;
        
        const item = {
            id: activeMovie.id.toString(),
            name: activeMovie.title,
            title: activeMovie.title,
            logo: activeMovie.poster_path,
            group: activeMovie.release_year,
            type: 'movie'
        };

        toggleFavorite(item, (isFav) => {
            const favBtn = document.getElementById('movie-fav-btn');
            if (isFav) {
                favBtn.className = 'btn-primary btn-fav';
                favBtn.innerHTML = '<i class="bi bi-heart-fill"></i> Favorited';
            } else {
                favBtn.className = 'btn-secondary btn-fav';
                favBtn.innerHTML = '<i class="bi bi-heart"></i> Favorite';
            }
        });
    }
</script>
@endsection
