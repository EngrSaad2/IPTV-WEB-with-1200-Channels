<?php
// Set execution limit and disable memory limit
set_time_limit(600);
ini_set('memory_limit', '512M');
ignore_user_abort(true);

// Log function
function log_msg($msg) {
    $logDir = __DIR__ . '/../storage/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    file_put_contents($logDir . '/deploy.log', '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
}

// Authentication
$secret = 'SaadDeploy2026@TV';

$headers = getallheaders();
$signature = $headers['X-Hub-Signature-256'] ?? '';
$payload = file_get_contents('php://input');

$expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);

if (!hash_equals($expected, $signature)) {
    http_response_code(403);
    log_msg('Error: Invalid signature');
    header('Content-Type: application/json');
    exit(json_encode(['error' => 'Invalid signature']));
}

log_msg('Webhook signature verified. Starting deployment...');

$rootDir = '/home/u263680919/domains/triangletech.com.bd/public_html/tv.triangletech.com.bd';

// Execute deployment command block
$cmd = 'cd ' . $rootDir . ' && ' .
       'git fetch origin && ' .
       'git reset --hard origin/main && ' .
       'composer install --no-dev --optimize-autoloader && ' .
       'php artisan migrate --force && ' .
       'php artisan optimize:clear && ' .
       'php artisan optimize 2>&1';

log_msg("Executing command sequence: $cmd");

$descriptorspec = [
    0 => ["pipe", "r"],
    1 => ["pipe", "w"],
    2 => ["pipe", "w"]
];

$env = $_ENV;
if (!isset($env['PATH'])) {
    $env['PATH'] = '/usr/local/bin:/usr/bin:/bin:/usr/local/sbin:/usr/sbin:/sbin';
} else {
    $env['PATH'] .= ':/usr/local/bin:/usr/bin:/bin:/usr/local/sbin:/usr/sbin:/sbin';
}

$env['HOME'] = '/home/u263680919';
$env['COMPOSER_HOME'] = '/home/u263680919/.composer';
$env['COMPOSER_PROCESS_TIMEOUT'] = '0';
$env['COMPOSER_NO_INTERACTION'] = '1';
$env['COMPOSER_NO_AUDIT'] = '1';

putenv('COMPOSER_PROCESS_TIMEOUT=0');
putenv('COMPOSER_NO_INTERACTION=1');
putenv('COMPOSER_NO_AUDIT=1');

$process = proc_open($cmd, $descriptorspec, $pipes, $rootDir, $env);
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
    if (!empty($stdout)) log_msg("Output:\n" . trim($stdout));
    if (!empty($stderr)) log_msg("Errors:\n" . trim($stderr));
    
    if ($exitCode === 0) {
        log_msg('Deployment finished successfully.');
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success']);
    } else {
        log_msg("Deployment failed with exit code $exitCode");
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'failed',
            'exit_code' => $exitCode,
            'output' => $stdout,
            'error' => $stderr
        ]);
    }
} else {
    log_msg("Failed to run execution process");
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'failed',
        'error' => 'Failed to execute command'
    ]);
}
