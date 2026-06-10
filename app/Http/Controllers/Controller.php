<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

abstract class Controller
{
    protected function getIpResolveMode(): string
    {
        // On local environment (XAMPP), default to IPv4 as IPv6 is typically not configured.
        // On production environment (Hostinger), force IPv6 to bypass their outgoing connection block.
        if (app()->environment('local') || env('APP_ENV') === 'local' || (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] === 'localhost')) {
            return 'default';
        }
        return 'v6';
    }

    protected function adaptiveGet(string $url, array $params = [], array $headers = []): \Illuminate\Http\Client\Response
    {
        $resolveMode = $this->getIpResolveMode();
        
        $options = [];
        if ($resolveMode === 'v6') {
            $options['force_ip_resolve'] = 'v6';
        }
        
        try {
            $response = Http::withHeaders($headers)
                ->withOptions($options)
                ->timeout(10)
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
                    ->timeout(10)
                    ->get($url, $params);
            } catch (\Exception $ex) {
                // Ignore and fallthrough
            }
        }

        return Http::withHeaders($headers)
            ->timeout(10)
            ->get($url, $params);
    }
}

