<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SqlInjectionProtection
{
    /**
     * Regex patterns matching common SQL injection signatures.
     */
    private array $patterns = [
        '/union\s+(all\s+)?select/i',
        '/select\s+.*\s+from/i',
        '/insert\s+into/i',
        '/update\s+.*\s+set/i',
        '/delete\s+from/i',
        '/drop\s+table/i',
        '/truncate\s+table/i',
        '/alter\s+table/i',
        '/information_schema/i',
        '/or\s+[\'"]?\d+[\'"]?\s*=\s*[\'"]?\d+[\'"]?/i',
        '/and\s+[\'"]?\d+[\'"]?\s*=\s*[\'"]?\d+[\'"]?/i',
        '/exec\s*\(|execute\s*\(/i',
        '/db_name\s*\(|user_name\s*\(/i',
        '/sysdatabases/i',
        '/sysobjects/i',
        '/sleep\(\s*\d+\s*\)/i',
        '/benchmark\(\s*\d+\s*,\s*.*\s*\)/i',
        '/[\'"]\s*(or|and)\s*[\'"]?\d+[\'"]?\s*=\s*[\'"]?\d+/i',
        '/xmlparse\s*\(|xmlserialize\s*\(/i',
        '/extractvalue\s*\(|updatexml\s*\(/i',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get all request inputs (query params, post parameters, files, etc)
        $inputs = $request->all();

        foreach ($inputs as $key => $value) {
            if ($this->detectSqlInjection($value)) {
                $ip = $request->ip();
                
                // Add 3 strikes to the firewall for this IP
                $strikesKey = "firewall_strikes_" . $ip;
                $strikes = Cache::get($strikesKey, 0) + 3;
                Cache::put($strikesKey, $strikes, 3600); // 1 hour TTL

                Log::warning("Potential SQL Injection blocked", [
                    'ip' => $ip,
                    'url' => $request->fullUrl(),
                    'key' => $key,
                    'value' => is_string($value) ? substr($value, 0, 100) : gettype($value),
                    'strikes' => $strikes
                ]);

                // Check if client should be blocked immediately
                if ($strikes >= 5) {
                    Cache::put("firewall_blocked_" . $ip, true, 3600);
                    Log::warning("IP {$ip} blacklisted by Firewall Shield (SQL Injection threshold reached).");
                }

                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json([
                        'error' => 'Security policy violation.',
                        'message' => 'Potential malicious input detected.'
                    ], 400);
                }

                // Redirect to the 403 Forbidden page
                return response()->view('errors.403', [
                    'reason' => 'Security policy violation: Potential malicious query content.'
                ], 403);
            }
        }

        return $next($request);
    }

    /**
     * Recursively scan input values for SQL injection signatures.
     */
    private function detectSqlInjection(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if ($this->detectSqlInjection($item)) {
                    return true;
                }
            }
            return false;
        }

        if (!is_string($value)) {
            return false;
        }

        foreach ($this->patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }
}
