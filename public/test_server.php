<?php

// Include Composer autoloader
require __DIR__ . '/../vendor/autoload.php';

// Include Laravel bootstrap to get application instance
$app = require __DIR__ . '/../bootstrap/app.php';

// Boot the application
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "<h1>Testing Laravel config values on production server</h1>";

$configToken = config('services.tmdb.read_token');
$configKey = config('services.tmdb.api_key');

echo "<p>Config Read Token Length: " . strlen($configToken) . "</p>";
echo "<p>Config API Key: <strong>$configKey</strong></p>";

$token = $configToken ?: "eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiJkZGI5ZTg4MGIwOGE5YzJjZDczZjg2OTQ2NDNkYmYxNyIsIm5iZiI6MTc4MDgzMzg4Mi40MjU5OTk5LCJzdWIiOiI2YTI1NWU1YWM4MmFkYWVkZDYxZTVjN2EiLCJzY29wZXMiOlsiYXBpX3JlYWQiXSwidmVyc2lvbiI6MX0.fmB7AdXKUzs3n37Q7oU7arLaqX3TSfnkS1cfU_2SrPY";
$url = "https://api.themoviedb.org/3/movie/popular?language=en-US&page=1";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . $token,
    "Accept: application/json"
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

echo "<p>HTTP Status Code: <strong>$httpCode</strong></p>";

if ($response === false) {
    echo "<p style='color: red;'>CURL Error: $err</p>";
} else {
    echo "<p>Response length: " . strlen($response) . " bytes</p>";
    $data = json_decode($response, true);
    if (isset($data['results'])) {
        echo "<p>Successfully fetched " . count($data['results']) . " movies.</p>";
    } else {
        echo "<p style='color: red;'>Invalid response format: " . htmlspecialchars(substr($response, 0, 500)) . "</p>";
    }
}
