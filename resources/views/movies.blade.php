@extends('layouts.app')

@section('title', 'Movies Explorer - LiveTV BD')

@section('content')
<div class="movies-explorer">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <h2 class="section-title" id="movies-grid-title">Popular Movies</h2>
        <!-- Genre Chips -->
        <div class="genre-chips" id="genre-chips-container">
            <span class="genre-chip active" onclick="loadMovieGenre(0, 'Popular', this)">Popular</span>
            <span class="genre-chip" onclick="loadMovieGenre('islamic', 'Islamic', this)">Islamic</span>
            <span class="genre-chip" onclick="loadMovieGenre(28, 'Action', this)">Action</span>
            <span class="genre-chip" onclick="loadMovieGenre(53, 'Thriller', this)">Thriller</span>
            <span class="genre-chip" onclick="loadMovieGenre(16, 'Animation', this)">Animation</span>
            <span class="genre-chip" onclick="loadMovieGenre(27, 'Horror', this)">Horror</span>
            <span class="genre-chip" onclick="loadMovieGenre(35, 'Comedy', this)">Comedy</span>
        </div>
    </div>

    <!-- Movie Grid -->
    <div class="media-grid" id="movies-grid">
        <!-- Shimmer items -->
        <div class="movie-card shimmer shimmer-card"></div>
        <div class="movie-card shimmer shimmer-card"></div>
        <div class="movie-card shimmer shimmer-card"></div>
        <div class="movie-card shimmer shimmer-card"></div>
        <div class="movie-card shimmer shimmer-card"></div>
        <div class="movie-card shimmer shimmer-card"></div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        const searchParam = urlParams.get('search');
        
        if (searchParam) {
            // Set input value and load search results
            document.getElementById('global-search-input').value = searchParam;
            loadMovieSearch(searchParam);
        } else {
            // Load popular movies by default
            loadMovieGenre(0, 'Popular');
        }
    });

    async function loadMovieGenre(genreId, label, chipElement = null) {
        // Highlight chip
        if (chipElement) {
            const chips = document.querySelectorAll('.genre-chip');
            chips.forEach(c => c.classList.remove('active'));
            chipElement.classList.add('active');
        }

        // Set header
        document.getElementById('movies-grid-title').textContent = `${label} Movies`;
        
        // Show shimmer
        showShimmer();

        try {
            let url = `${API_BASE}/movies/trending`;
            if (genreId === 'islamic') {
                url = `${API_BASE}/movies/islamic`;
            } else if (genreId > 0) {
                url = `${API_BASE}/movies/genre/${genreId}`;
            }

            const response = await fetch(url);
            const data = await response.json();
            renderMovies(data);
        } catch (error) {
            console.error('Failed to fetch movies:', error);
            document.getElementById('movies-grid').innerHTML = '<div class="p-4 text-center text-danger">Failed to load movies list.</div>';
        }
    }

    async function loadMovieSearch(query) {
        document.getElementById('movies-grid-title').textContent = `Search Results: "${query}"`;
        showShimmer();

        // De-select all genre chips
        const chips = document.querySelectorAll('.genre-chip');
        chips.forEach(c => c.classList.remove('active'));

        try {
            const response = await fetch(`${API_BASE}/movies/search?query=${encodeURIComponent(query)}`);
            const data = await response.json();
            renderMovies(data);
        } catch (error) {
            console.error('Failed to search movies:', error);
            document.getElementById('movies-grid').innerHTML = '<div class="p-4 text-center text-danger">Search query failed.</div>';
        }
    }

    function showShimmer() {
        const grid = document.getElementById('movies-grid');
        grid.innerHTML = '';
        for (let i = 0; i < 12; i++) {
            const shim = document.createElement('div');
            shim.className = 'movie-card shimmer shimmer-card';
            grid.appendChild(shim);
        }
    }

    function renderMovies(movies) {
        const grid = document.getElementById('movies-grid');
        if (movies.length === 0) {
            grid.innerHTML = '<div class="p-4 text-center text-muted">No media items found matching criteria.</div>';
            return;
        }

        grid.innerHTML = '';
        movies.forEach(movie => {
            const card = document.createElement('div');
            card.className = 'movie-card';
            card.onclick = () => window.location.href = `${APP_URL}/movies/${movie.id}`;
            
            card.innerHTML = `
                <div class="rating-badge">
                    <i class="bi bi-star-fill"></i> ${movie.vote_average || '0.0'}
                </div>
                <div class="movie-poster">
                    <img src="${movie.poster_path}" alt="${movie.title}" onerror="this.src='https://images.unsplash.com/photo-1440404653325-ab127d49abc1?w=300'">
                </div>
                <div class="movie-details-brief">
                    <h3 class="movie-title">${movie.title}</h3>
                    <div class="movie-meta">
                        <span>${movie.release_year || 'Unknown'}</span>
                        <span>HD</span>
                    </div>
                </div>
            `;
            grid.appendChild(card);
        });
    }
</script>
@endsection
