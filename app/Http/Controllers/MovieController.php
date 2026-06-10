<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class MovieController extends Controller
{
    private const BASE_URL = 'https://api.themoviedb.org/3';
    private const PAGES_TO_FETCH = 3; // 3 pages * 20 = 60 items is plenty for grid scrolling
    private const LANG = 'en-US';

    private const ADULT_KEYWORDS = [
        'nude', 'naked', 'erotic', 'erotica', 'sex', 'xxx', 'porn',
        'adult film', 'adult movie', 'softcore', 'hardcore', 'sensual',
        'seduction', 'naughty', 'explicit', 'uncensored', 'taboo',
        'milf', 'stepmom', 'stepsis', 'stepbrother', 'massage parlor'
    ];

    private function getHeaders(): array
    {
        $token = env('TMDB_READ_TOKEN');
        return [
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ];
    }

    public function trending()
    {
        $movies = $this->fetchConcurrentPages('/movie/popular');
        return response()->json($movies);
    }

    public function newReleases()
    {
        $movies = $this->fetchConcurrentPages('/movie/now_playing');
        return response()->json($movies);
    }

    public function topRated()
    {
        $movies = $this->fetchConcurrentPages('/movie/top_rated');
        return response()->json($movies);
    }

    public function byGenre(int $genreId)
    {
        $movies = $this->fetchConcurrentPages('/discover/movie', [
            'with_genres' => $genreId,
            'sort_by' => 'popularity.desc'
        ]);
        return response()->json($movies);
    }

    public function search(Request $request)
    {
        $query = $request->query('query', '');
        if (empty(trim($query))) {
            return response()->json([]);
        }

        $cacheKey = 'tmdb_search_' . md5($query);
        $movies = Cache::remember($cacheKey, 600, function () use ($query) {
            $response = Http::withHeaders($this->getHeaders())
                ->timeout(10)
                ->get(self::BASE_URL . '/search/movie', [
                    'query' => $query,
                    'language' => self::LANG,
                    'page' => 1
                ]);

            if ($response->successful()) {
                $results = $response->json()['results'] ?? [];
                return $this->processMovies($results);
            }
            return [];
        });

        return response()->json($movies);
    }

    public function detail(int $movieId)
    {
        $cacheKey = 'tmdb_movie_detail_' . $movieId;
        $detail = Cache::remember($cacheKey, 3600, function () use ($movieId) {
            $response = Http::withHeaders($this->getHeaders())
                ->timeout(10)
                ->get(self::BASE_URL . "/movie/{$movieId}", [
                    'append_to_response' => 'videos',
                    'language' => self::LANG
                ]);

            if ($response->successful()) {
                $movie = $response->json();
                if ($this->isAdultContent($movie)) {
                    return null;
                }

                // Extract trailer key
                $trailerKey = null;
                if (!empty($movie['videos']['results'])) {
                    foreach ($movie['videos']['results'] as $video) {
                        if (strtolower($video['site']) === 'youtube' && strtolower($video['type']) === 'trailer') {
                            $trailerKey = $video['key'];
                            break;
                        }
                    }
                    if (!$trailerKey && !empty($movie['videos']['results'])) {
                        $trailerKey = $movie['videos']['results'][0]['key'] ?? null;
                    }
                }

                // Fetch similar movies
                $similarResponse = Http::withHeaders($this->getHeaders())
                    ->timeout(10)
                    ->get(self::BASE_URL . "/movie/{$movieId}/similar", [
                        'language' => self::LANG,
                        'page' => 1
                    ]);

                $similar = [];
                if ($similarResponse->successful()) {
                    $similar = $this->processMovies($similarResponse->json()['results'] ?? []);
                }

                return [
                    'id' => $movie['id'],
                    'title' => $movie['title'] ?? 'Unknown',
                    'overview' => $movie['overview'] ?? '',
                    'poster_path' => $movie['poster_path'] ? "https://image.tmdb.org/t/p/w500{$movie['poster_path']}" : null,
                    'backdrop_path' => $movie['backdrop_path'] ? "https://image.tmdb.org/t/p/w780{$movie['backdrop_path']}" : null,
                    'release_date' => $movie['release_date'] ?? '',
                    'release_year' => $movie['release_date'] ? substr($movie['release_date'], 0, 4) : '',
                    'runtime' => $movie['runtime'] ?? 0,
                    'vote_average' => round($movie['vote_average'], 1),
                    'genres' => $movie['genres'] ?? [],
                    'trailer_key' => $trailerKey,
                    'similar' => $similar
                ];
            }
            return null;
        });

        if (!$detail) {
            return response()->json(['error' => 'Movie not found or filtered as adult content'], 404);
        }

        return response()->json($detail);
    }

    private function fetchConcurrentPages(string $endpoint, array $extraParams = []): array
    {
        $cacheKey = 'tmdb_' . str_replace('/', '_', $endpoint) . '_' . md5(serialize($extraParams));
        return Cache::remember($cacheKey, 1800, function () use ($endpoint, $extraParams) {
            $merged = [];
            for ($page = 1; $page <= self::PAGES_TO_FETCH; $page++) {
                try {
                    $params = array_merge([
                        'language' => self::LANG,
                        'page' => $page
                    ], $extraParams);

                    $response = Http::withHeaders($this->getHeaders())
                        ->timeout(10)
                        ->get(self::BASE_URL . $endpoint, $params);

                    if ($response->successful()) {
                        $results = $response->json()['results'] ?? [];
                        $processed = $this->processMovies($results);
                        $merged = array_merge($merged, $processed);
                    }
                } catch (\Exception $e) {
                    // Fail gracefully for this page
                }
            }

            // Remove duplicates by ID
            $unique = [];
            foreach ($merged as $m) {
                $unique[$m['id']] = $m;
            }

            return array_values($unique);
        });
    }

    private function processMovies(array $results): array
    {
        $processed = [];
        foreach ($results as $m) {
            if ($this->isAdultContent($m)) {
                continue;
            }

            if (empty($m['poster_path']) || empty($m['backdrop_path'])) {
                continue;
            }

            $year = '';
            if (!empty($m['release_date']) && strlen($m['release_date']) >= 4) {
                $year = substr($m['release_date'], 0, 4);
            }

            $processed[] = [
                'id' => $m['id'],
                'title' => $m['title'] ?? 'Unknown',
                'poster_path' => "https://image.tmdb.org/t/p/w500" . $m['poster_path'],
                'backdrop_path' => "https://image.tmdb.org/t/p/w780" . $m['backdrop_path'],
                'vote_average' => round($m['vote_average'] ?? 0, 1),
                'release_year' => $year
            ];
        }
        return $processed;
    }

    private function isAdultContent(array $m): bool
    {
        // 1) TMDB adult flag
        if (!empty($m['adult'])) {
            return true;
        }

        // 2) Romance genre (10749)
        if (!empty($m['genre_ids']) && in_array(10749, $m['genre_ids'])) {
            return true;
        }
        if (!empty($m['genres'])) {
            foreach ($m['genres'] as $genre) {
                if (($genre['id'] ?? null) === 10749 || strtolower($genre['name'] ?? '') === 'romance') {
                    return true;
                }
            }
        }

        // 3) Low vote count filter
        $voteCount = $m['vote_count'] ?? 0;
        if ($voteCount > 0 && $voteCount < 50) {
            return true;
        }

        // 4) Blacklist keyword filter
        $titleLower = strtolower($m['title'] ?? '');
        $overviewLower = strtolower($m['overview'] ?? '');
        foreach (self::ADULT_KEYWORDS as $kw) {
            if (str_contains($titleLower, $kw) || str_contains($overviewLower, $kw)) {
                return true;
            }
        }

        return false;
    }
}
