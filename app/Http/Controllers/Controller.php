<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

abstract class Controller
{
    protected function getIpResolveMode(string $url, array $headers = []): string
    {
        $parsed = parse_url($url);
        $host = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
        $cacheKey = 'ip_resolve_mode_' . md5($host);
        
        return Cache::remember($cacheKey, 86400, function () use ($host, $headers) {
            // Probe IPv6 first
            try {
                $response = Http::withHeaders($headers)
                    ->withOptions(['force_ip_resolve' => 'v6'])
                    ->timeout(2)
                    ->get($host);
                if ($response->status() > 0) {
                    return 'v6';
                }
            } catch (\Exception $e) {
                // Ignore
            }

            // Probe default
            try {
                $response = Http::withHeaders($headers)
                    ->timeout(2)
                    ->get($host);
                if ($response->status() > 0) {
                    return 'default';
                }
            } catch (\Exception $e) {
                // Ignore
            }

            return 'default';
        });
    }

    protected function adaptiveGet(string $url, array $params = [], array $headers = []): \Illuminate\Http\Client\Response
    {
        $resolveMode = $this->getIpResolveMode($url, $headers);
        
        $options = [];
        if ($resolveMode === 'v6') {
            $options['force_ip_resolve'] = 'v6';
        }
        
        try {
            $response = Http::withHeaders($headers)
                ->withOptions($options)
                ->timeout(6)
                ->get($url, $params);
            
            if ($response->successful()) {
                return $response;
            }
        } catch (\Exception $e) {
            // Emergency fallback
            $fallbackOptions = [];
            if ($resolveMode !== 'v6') {
                $fallbackOptions['force_ip_resolve'] = 'v6';
            }
            try {
                return Http::withHeaders($headers)
                    ->withOptions($fallbackOptions)
                    ->timeout(6)
                    ->get($url, $params);
            } catch (\Exception $ex) {
                // Ignore and fallthrough
            }
        }

        return Http::withHeaders($headers)
            ->timeout(6)
            ->get($url, $params);
    }
}

