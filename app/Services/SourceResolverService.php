<?php

namespace App\Services;

class SourceResolverService
{
    /**
     * Resolve all available embed source URLs for a given TMDB movie ID.
     *
     * Returns an ordered array of providers. The frontend iterates through
     * these for graceful fallback if the primary embed fails.
     *
     * @param  int|string  $tmdbId
     * @return array<int, array{name: string, url: string}>
     */
    public function resolve(int|string $tmdbId): array
    {
        $servers = config('source_resolver.servers', []);
        $sources = [];

        foreach ($servers as $index => $server) {
            $urlTemplate = $server['url'] ?? '';
            if (empty($urlTemplate)) {
                continue;
            }

            $sources[] = [
                'name' => $server['name'] ?? ('Server ' . ($index + 1)),
                'url'  => str_replace('{tmdb_id}', (string) $tmdbId, $urlTemplate),
            ];
        }

        return $sources;
    }

    /**
     * Get the configured embed timeout in seconds.
     */
    public function getTimeout(): int
    {
        return config('source_resolver.embed_timeout', 8);
    }
}
