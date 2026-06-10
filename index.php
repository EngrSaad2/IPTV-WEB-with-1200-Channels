<?php

/**
 * Laravel - A PHP Framework For Web Artisans
 *
 * @package  Laravel
 * @author   Taylor Otwell <taylor@laravel.com>
 */

if ($_SERVER['REQUEST_URI'] === '/LiveTVweb') {
    header('Location: /LiveTVweb/', true, 301);
    exit;
}

require_once __DIR__.'/public/index.php';
