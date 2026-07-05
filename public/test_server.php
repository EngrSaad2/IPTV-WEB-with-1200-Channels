<?php

$token = "eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiJkZGI5ZTg4MGIwOGE5YzJjZDczZjg2OTQ2NDNkYmYxNyIsIm5iZiI6MTc4MDgzMzg4Mi40MjU5OTk5LCJzdWIiOiI2YTI1NWU1YWM4MmFkYWVkZDYxZTVjN2EiLCJzY29wZXMiOlsiYXBpX3JlYWQiXSwidmVyc2lvbiI6MX0.fmB7AdXKUzs3n37Q7oU7arLaqX3TSfnkS1cfU_2SrPY";
$url = "https://api.themoviedb.org/3/movie/popular?language=en-US&page=1";

echo "<h1>Testing TMDB connection from production server</h1>";

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
        echo "<ul>";
        foreach (array_slice($data['results'], 0, 5) as $movie) {
            echo "<li>" . htmlspecialchars($movie['title']) . " (Votes: " . $movie['vote_count'] . ")</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color: red;'>Invalid response format: " . htmlspecialchars(substr($response, 0, 500)) . "</p>";
    }
}
