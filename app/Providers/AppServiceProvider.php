<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        // Global rate limiter (100 requests per minute)
        RateLimiter::for('global', function (Request $request) {
            return Limit::perMinute(100)->by($request->ip())->response(function (Request $request, array $headers) {
                $ip = $request->ip();
                
                // Track strikes
                $strikesKey = "firewall_strikes_" . $ip;
                $strikes = Cache::get($strikesKey, 0) + 1;
                Cache::put($strikesKey, $strikes, 3600);

                Log::warning("IP {$ip} exceeded global rate limit.", [
                    'ip' => $ip,
                    'url' => $request->fullUrl(),
                    'strikes' => $strikes
                ]);

                if ($strikes >= 5) {
                    Cache::put("firewall_blocked_" . $ip, true, 3600);
                    Log::warning("IP {$ip} blacklisted by Firewall Shield (Global rate limit abuse).");
                }

                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json([
                        'error' => 'Too Many Requests',
                        'message' => 'Rate limit exceeded. Please try again later.'
                    ], 429, $headers);
                }

                return response()->view('errors.429', [
                    'retryAfter' => $headers['Retry-After'] ?? 60
                ], 429, $headers);
            });
        });

        // API rate limiter (60 requests per minute)
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip())->response(function (Request $request, array $headers) {
                $ip = $request->ip();
                
                // Track strikes
                $strikesKey = "firewall_strikes_" . $ip;
                $strikes = Cache::get($strikesKey, 0) + 1;
                Cache::put($strikesKey, $strikes, 3600);

                Log::warning("IP {$ip} exceeded API rate limit.", [
                    'ip' => $ip,
                    'url' => $request->fullUrl(),
                    'strikes' => $strikes
                ]);

                if ($strikes >= 5) {
                    Cache::put("firewall_blocked_" . $ip, true, 3600);
                    Log::warning("IP {$ip} blacklisted by Firewall Shield (API rate limit abuse).");
                }

                return response()->json([
                    'error' => 'Too Many Requests',
                    'message' => 'API rate limit exceeded. Please try again later.'
                ], 429, $headers);
            });
        });
    }
}
