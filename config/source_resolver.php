<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Source Resolver — Embed Provider URLs
    |--------------------------------------------------------------------------
    |
    | Each server entry is an embed URL template containing a {tmdb_id} placeholder.
    | The SourceResolverService replaces {tmdb_id} at runtime with the actual
    | TMDB movie ID. Providers are tried in order (1 → 2 → 3) for fallback.
    |
    | To change providers, update .env — no code changes needed.
    |
    */

    'servers' => [
        [
            'name'  => env('SOURCE_SERVER_1_NAME', 'Server 1'),
            'url'   => env('SOURCE_SERVER_1', 'https://vidsrc.xyz/embed/movie/{tmdb_id}'),
        ],
        [
            'name'  => env('SOURCE_SERVER_2_NAME', 'Server 2'),
            'url'   => env('SOURCE_SERVER_2', 'https://vidsrc.to/embed/movie/{tmdb_id}'),
        ],
        [
            'name'  => env('SOURCE_SERVER_3_NAME', 'Server 3'),
            'url'   => env('SOURCE_SERVER_3', 'https://multiembed.mov/?video_id={tmdb_id}&tmdb=1'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Embed Timeout (seconds)
    |--------------------------------------------------------------------------
    |
    | How long to wait for an embed iframe to load before attempting fallback.
    |
    */
    'embed_timeout' => (int) env('SOURCE_EMBED_TIMEOUT', 8),

];
