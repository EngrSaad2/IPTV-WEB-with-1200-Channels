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
    const TMDB_TOKEN = "eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiJkZGI5ZTg4MGIwOGE5YzJjZDczZjg2OTQ2NDNkYmYxNyIsIm5iZiI6MTc4MDgzMzg4Mi40MjU5OTk5LCJzdWIiOiI2YTI1NWU1YWM4MmFkYWVkZDYxZTVjN2EiLCJzY29wZXMiOlsiYXBpX3JlYWQiXSwidmVyc2lvbiI6MX0.fmB7AdXKUzs3n37Q7oU7arLaqX3TSfnkS1cfU_2SrPY";
    const TMDB_BASE  = 'https://api.themoviedb.org/3';
    const TMDB_IMG   = 'https://image.tmdb.org/t/p/w500';
    const TMDB_BACK  = 'https://image.tmdb.org/t/p/w780';

    const ADULT_KW = ['nude','naked','erotic','erotica','sex','xxx','porn','adult film','softcore','hardcore'];

    document.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        const searchParam = urlParams.get('search');
        if (searchParam) {
            document.getElementById('global-search-input').value = searchParam;
            loadMovieSearch(searchParam);
        } else {
            loadMovieGenre(0, 'Popular');
        }
    });

    // ---------- TMDB direct fetch helpers ----------

    function tmdbHeaders() {
        return { 'Authorization': 'Bearer ' + TMDB_TOKEN, 'Accept': 'application/json' };
    }

    function isAdult(m) {
        if (m.adult) return true;
        const ids = m.genre_ids || [];
        if (ids.includes(10749)) return true;
        const title = (m.title || '').toLowerCase();
        const overview = (m.overview || '').toLowerCase();
        for (const kw of ADULT_KW) {
            if (title.includes(kw) || overview.includes(kw)) return true;
        }
        return false;
    }

    function processResults(results) {
        return results
            .filter(m => !isAdult(m) && m.poster_path && m.backdrop_path)
            .map(m => ({
                id: m.id,
                title: m.title || 'Unknown',
                poster_path: TMDB_IMG + m.poster_path,
                backdrop_path: TMDB_BACK + m.backdrop_path,
                vote_average: Math.round((m.vote_average || 0) * 10) / 10,
                release_year: (m.release_date || '').substring(0, 4)
            }));
    }

    async function tmdbGet(endpoint, params = {}) {
        const qs = new URLSearchParams({ language: 'en-US', ...params }).toString();
        const res = await fetch(`${TMDB_BASE}${endpoint}?${qs}`, { headers: tmdbHeaders() });
        if (!res.ok) throw new Error('TMDB error ' + res.status);
        return res.json();
    }

    async function fetchPages(endpoint, extraParams = {}, pages = 3) {
        const cacheKey = 'tmdb_' + endpoint + JSON.stringify(extraParams) + '_' + pages;
        const cached = sessionStorage.getItem(cacheKey);
        if (cached) return JSON.parse(cached);

        let all = [];
        // Load page 1 first for speed
        try {
            const data = await tmdbGet(endpoint, { page: 1, ...extraParams });
            all = processResults(data.results || []);
        } catch(e) { /* skip */ }

        const result = all;
        sessionStorage.setItem(cacheKey, JSON.stringify(result));

        // Load remaining pages in background
        (async () => {
            let more = [];
            for (let page = 2; page <= pages; page++) {
                try {
                    const data = await tmdbGet(endpoint, { page, ...extraParams });
                    more = more.concat(processResults(data.results || []));
                } catch(e) { break; }
            }
            if (more.length > 0) {
                const seen = {};
                const full = result.concat(more).filter(m => seen[m.id] ? false : (seen[m.id] = true));
                sessionStorage.setItem(cacheKey, JSON.stringify(full));
            }
        })();

        return result;
    }

    // ---------- Genre / Category loaders ----------

    async function loadMovieGenre(genreId, label, chipElement = null) {
        if (chipElement) {
            document.querySelectorAll('.genre-chip').forEach(c => c.classList.remove('active'));
            chipElement.classList.add('active');
        }
        document.getElementById('movies-grid-title').textContent = `${label} Movies`;
        showShimmer();

        try {
            let movies = [];
            if (genreId === 'islamic') {
                movies = await fetchPages('/discover/movie', { with_keywords: '187|789', sort_by: 'popularity.desc' }, 10);
            } else if (genreId > 0) {
                movies = await fetchPages('/discover/movie', { with_genres: genreId, sort_by: 'popularity.desc' });
            } else {
                movies = await fetchPages('/movie/popular');
            }
            renderMovies(movies);
        } catch (error) {
            console.error('Failed to fetch movies:', error);
            document.getElementById('movies-grid').innerHTML = '<div class="p-4 text-center text-danger">Failed to load movies list.</div>';
        }
    }

    async function loadMovieSearch(query) {
        document.getElementById('movies-grid-title').textContent = `Search Results: "${query}"`;
        showShimmer();
        document.querySelectorAll('.genre-chip').forEach(c => c.classList.remove('active'));

        try {
            const data  = await tmdbGet('/search/movie', { query });
            const movies = processResults(data.results || []);
            renderMovies(movies);
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
        if (!movies || movies.length === 0) {
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
