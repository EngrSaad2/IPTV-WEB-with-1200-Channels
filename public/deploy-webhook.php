<?php
// Set execution limit and disable memory limit
set_time_limit(600);
ini_set('memory_limit', '512M');
ignore_user_abort(true);

// Load env helper
function get_env_var($key, $default = null) {
    static $env = null;
    if ($env === null) {
        $env = [];
        $envPath = __DIR__ . '/../.env';
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || strpos($line, '#') === 0) continue;
                $parts = explode('=', $line, 2);
                if (count($parts) === 2) {
                    $envName = trim($parts[0]);
                    $envVal = trim($parts[1]);
                    if (preg_match('/^"(.*)"$/', $envVal, $matches)) {
                        $envVal = $matches[1];
                    } elseif (preg_match("/^'(.*)'$/", $envVal, $matches)) {
                        $envVal = $matches[1];
                    }
                    $env[$envName] = $envVal;
                }
            }
        }
    }
    return isset($env[$key]) ? $env[$key] : $default;
}

// Log function
function log_msg($msg) {
    $logDir = __DIR__ . '/../storage/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    file_put_contents($logDir . '/deploy.log', '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
}

// Authentication
$secret = get_env_var('GITHUB_WEBHOOK_SECRET');
if (empty($secret)) {
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'GITHUB_WEBHOOK_SECRET is not set in .env']);
    log_msg('Error: GITHUB_WEBHOOK_SECRET not configured.');
    exit;
}

$headers = getallheaders();
$signature = isset($headers['X-Hub-Signature-256']) ? $headers['X-Hub-Signature-256'] : '';

if (empty($signature)) {
    header('HTTP/1.1 401 Unauthorized');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Signature header missing']);
    log_msg('Error: X-Hub-Signature-256 header missing.');
    exit;
}

$payload = file_get_contents('php://input');
$expected_signature = 'sha256=' . hash_hmac('sha256', $payload, $secret);

if (!hash_equals($expected_signature, $signature)) {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid signature']);
    log_msg('Error: Webhook signature verification failed.');
    exit;
}

// Proceed with deployment
log_msg('Webhook signature verified. Starting deployment...');

$rootDir = realpath(__DIR__ . '/..');

// Helper to execute commands and log output
function execute($cmd, $dir) {
    log_msg("Executing: $cmd");
    $descriptorspec = [
        0 => ["pipe", "r"],
        1 => ["pipe", "w"],
        2 => ["pipe", "w"]
    ];
    
    // Set environment PATH to make sure common command paths are included
    $env = $_ENV;
    if (!isset($env['PATH'])) {
        $env['PATH'] = '/usr/local/bin:/usr/bin:/bin:/usr/local/sbin:/usr/sbin:/sbin';
    } else {
        $env['PATH'] .= ':/usr/local/bin:/usr/bin:/bin:/usr/local/sbin:/usr/sbin:/sbin';
    }
    
    $process = proc_open($cmd, $descriptorspec, $pipes, $dir, $env);
    $stdout = '';
    $stderr = '';
    
    if (is_resource($process)) {
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        
        log_msg("Exit Code: $exitCode");
        if (!empty($stdout)) log_msg("STDOUT: " . trim($stdout));
        if (!empty($stderr)) log_msg("STDERR: " . trim($stderr));
        
        return [
            'command' => $cmd,
            'exit_code' => $exitCode,
            'stdout' => $stdout,
            'stderr' => $stderr
        ];
    }
    
    log_msg("Failed to run: $cmd");
    return [
        'command' => $cmd,
        'exit_code' => -1,
        'stdout' => '',
        'stderr' => 'Failed to execute command'
    ];
}

$results = [];

// Clean up environment variables that might interfere with composer
putenv('COMPOSER_PROCESS_TIMEOUT=0');
putenv('COMPOSER_NO_INTERACTION=1');
putenv('COMPOSER_NO_AUDIT=1');

// Run sequence
$commands = [
    'git fetch origin',
    'git reset --hard origin/main',
    'composer install --no-dev --optimize-autoloader --no-interaction',
    'php artisan migrate --force',
    'php artisan db:seed --force',
    'php artisan optimize:clear',
    'php artisan optimize'
];

foreach ($commands as $cmd) {
    $res = execute($cmd, $rootDir);
    $results[] = $res;
    if ($res['exit_code'] !== 0) {
        log_msg("Deployment failed at command: $cmd");
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'failed',
            'failed_command' => $cmd,
            'results' => $results
        ], JSON_PRETTY_PRINT);
        exit;
    }
}

log_msg('Deployment finished successfully.');
header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'results' => $results
], JSON_PRETTY_PRINT);
