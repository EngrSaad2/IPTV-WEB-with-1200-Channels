@extends('layouts.app')

@section('title', 'Movie Details - LiveTV BD')

@section('content')
<div class="detail-container" id="movie-detail-content">
    <!-- Left: Movie Trailer & Info -->
    <div class="detail-main">
        <!-- Video Player or Banner -->
        <div class="video-container" id="player-container">
            <div class="d-flex justify-content-center align-items-center h-100 shimmer shimmer-row"></div>
        </div>
        
        <!-- Info Details -->
        <div class="detail-info-block mt-3">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <h1 class="detail-title" id="movie-title">Loading...</h1>
                <button class="btn-secondary" id="movie-fav-btn" onclick="toggleMovieFavorite()">
                    <i class="bi bi-heart"></i> Favorite
                </button>
            </div>
            
            <div class="detail-meta-row">
                <span class="detail-rating" id="movie-rating"><i class="bi bi-star-fill"></i> 0.0</span>
                <span id="movie-year">0000</span>
                <span id="movie-runtime">0m</span>
                <span id="movie-genres-list"></span>
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
            <!-- Shimmers -->
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
    const TMDB_TOKEN = "eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiJkZGI5ZTg4MGIwOGE5YzJjZDczZjg2OTQ2NDNkYmYxNyIsIm5iZiI6MTc4MDgzMzg4Mi40MjU5OTk5LCJzdWIiOiI2YTI1NWU1YWM4MmFkYWVkZDYxZTVjN2EiLCJzY29wZXMiOlsiYXBpX3JlYWQiXSwidmVyc2lvbiI6MX0.fmB7AdXKUzs3n37Q7oU7arLaqX3TSfnkS1cfU_2SrPY";
    const TMDB_BASE  = 'https://api.themoviedb.org/3';
    const TMDB_IMG   = 'https://image.tmdb.org/t/p/w500';
    const TMDB_BACK  = 'https://image.tmdb.org/t/p/w780';

    let activeMovie = null;

    document.addEventListener('DOMContentLoaded', () => {
        loadMovieDetails();
    });

    function tmdbHeaders() {
        return { 'Authorization': 'Bearer ' + TMDB_TOKEN, 'Accept': 'application/json' };
    }

    async function tmdbGet(endpoint, params = {}) {
        const qs = new URLSearchParams({ language: 'en-US', ...params }).toString();
        const res = await fetch(`${TMDB_BASE}${endpoint}?${qs}`, { headers: tmdbHeaders() });
        if (!res.ok) throw new Error('TMDB error ' + res.status);
        return res.json();
    }

    async function loadMovieDetails() {
        try {
            const [movie, videosData, similarData] = await Promise.all([
                tmdbGet(`/movie/${movieId}`, { append_to_response: 'videos' }),
                Promise.resolve(null), // included in movie above via append_to_response
                tmdbGet(`/movie/${movieId}/similar`)
            ]);

            activeMovie = movie;

            // Extract trailer
            let trailerKey = null;
            const videos = movie.videos?.results || [];
            for (const v of videos) {
                if (v.site?.toLowerCase() === 'youtube' && v.type?.toLowerCase() === 'trailer') {
                    trailerKey = v.key;
                    break;
                }
            }
            if (!trailerKey && videos.length > 0) trailerKey = videos[0]?.key;

            // Process similar
            const similar = (similarData.results || [])
                .filter(m => m.poster_path && m.backdrop_path)
                .slice(0, 20)
                .map(m => ({
                    id: m.id,
                    title: m.title || 'Unknown',
                    poster_path: TMDB_IMG + m.poster_path,
                    backdrop_path: TMDB_BACK + m.backdrop_path,
                    vote_average: Math.round((m.vote_average || 0) * 10) / 10,
                    release_year: (m.release_date || '').substring(0, 4)
                }));

            const detail = {
                id: movie.id,
                title: movie.title || 'Unknown',
                overview: movie.overview || '',
                poster_path: movie.poster_path ? TMDB_IMG + movie.poster_path : null,
                backdrop_path: movie.backdrop_path ? TMDB_BACK + movie.backdrop_path : null,
                release_date: movie.release_date || '',
                release_year: (movie.release_date || '').substring(0, 4),
                runtime: movie.runtime || 0,
                vote_average: Math.round((movie.vote_average || 0) * 10) / 10,
                genres: movie.genres || [],
                trailer_key: trailerKey,
                similar
            };

            renderDetails(detail);
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
                badge.className = 'badge bg-secondary me-1';
                badge.textContent = g.name;
                genreContainer.appendChild(badge);
            });
        }

        // Render Player (YouTube or Banner Image)
        const playerContainer = document.getElementById('player-container');
        if (movie.trailer_key) {
            playerContainer.innerHTML = `
                <iframe class="video-element" 
                        src="https://www.youtube.com/embed/${movie.trailer_key}?autoplay=1&mute=0&rel=0" 
                        title="YouTube video player" 
                        frameborder="0" 
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" 
                        allowfullscreen>
                </iframe>
            `;
        } else if (movie.backdrop_path) {
            playerContainer.innerHTML = `
                <img src="${movie.backdrop_path}" alt="${movie.title}" class="video-element">
                <div class="video-overlay">
                    <span class="video-tag">No Trailer</span>
                    <h2 class="video-title">${movie.title}</h2>
                </div>
            `;
        } else {
            playerContainer.innerHTML = `
                <div class="d-flex justify-content-center align-items-center h-100 text-muted">
                    <span>No visual media available</span>
                </div>
            `;
        }

        // Sync Favorite Button State
        checkIsFavorite(movie.id.toString(), (isFav) => {
            const favBtn = document.getElementById('movie-fav-btn');
            if (isFav) {
                favBtn.className = 'btn-primary';
                favBtn.innerHTML = '<i class="bi bi-heart-fill"></i> Favorited';
            } else {
                favBtn.className = 'btn-secondary';
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
            id: (activeMovie.id || '').toString(),
            name: activeMovie.title,
            title: activeMovie.title,
            logo: activeMovie.poster_path,
            group: activeMovie.release_year,
            type: 'movie'
        };
        toggleFavorite(item, (isFav) => {
            const favBtn = document.getElementById('movie-fav-btn');
            if (isFav) {
                favBtn.className = 'btn-primary';
                favBtn.innerHTML = '<i class="bi bi-heart-fill"></i> Favorited';
            } else {
                favBtn.className = 'btn-secondary';
                favBtn.innerHTML = '<i class="bi bi-heart"></i> Favorite';
            }
        });
    }
</script>
@endsection
