<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FirewallShield
{
    /**
     * Sensitive file patterns that indicate malicious scanning.
     */
    private array $maliciousPaths = [
        '/\.env/i',
        '/\.git/i',
        '/wp-admin/i',
        '/wp-login/i',
        '/xmlrpc\.php/i',
        '/composer\.json/i',
        '/composer\.lock/i',
        '/package\.json/i',
        '/package-lock\.json/i',
        '/phpinfo/i',
        '/\.config/i',
        '/database\.sqlite/i',
        '/web_migration_blueprint\.md/i',
        '/deploy-webhook\.php/i', // Block webhook url scanning
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();

        // 1. Check if IP is blacklisted in cache
        if (Cache::has("firewall_blocked_" . $ip)) {
            Log::info("Rejected request from blocked IP: {$ip} to " . $request->fullUrl());

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'Forbidden',
                    'message' => 'Your IP address has been temporarily blacklisted due to security policy violations.'
                ], 403);
            }

            return response()->view('errors.403', [
                'reason' => 'Your IP address has been blocked due to multiple security violations.'
            ], 403);
        }

        // 2. Scan request path for malicious scanning behaviors
        $path = $request->path();
        foreach ($this->maliciousPaths as $pattern) {
            if (preg_match($pattern, $path)) {
                // Add 3 strikes to the firewall for this IP
                $strikesKey = "firewall_strikes_" . $ip;
                $strikes = Cache::get($strikesKey, 0) + 3;
                Cache::put($strikesKey, $strikes, 3600); // 1 hour TTL

                Log::warning("Firewall Shield: Sensitive path probe blocked", [
                    'ip' => $ip,
                    'path' => $path,
                    'strikes' => $strikes
                ]);

                if ($strikes >= 5) {
                    Cache::put("firewall_blocked_" . $ip, true, 3600); // 1 hour block
                    Log::warning("IP {$ip} blacklisted by Firewall Shield (Path scan threshold reached).");
                }

                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json([
                        'error' => 'Forbidden',
                        'message' => 'Access denied.'
                    ], 403);
                }

                return response()->view('errors.403', [
                    'reason' => 'Access to system resource files is restricted.'
                ], 403);
            }
        }

        return $next($request);
    }
}
