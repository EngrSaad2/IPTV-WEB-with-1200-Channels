<?php

// Include Composer autoloader
require __DIR__ . '/../vendor/autoload.php';

// Include Laravel bootstrap to get application instance
$app = require __DIR__ . '/../bootstrap/app.php';

// Boot the application
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "<h1>Clearing Laravel Cache</h1>";

try {
    Illuminate\Support\Facades\Cache::flush();
    echo "<p style='color: green;'>Successfully flushed all cache stores!</p>";
} catch (\Exception $e) {
    echo "<p style='color: red;'>Error flushing cache: " . $e->getMessage() . "</p>";
}
