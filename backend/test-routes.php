<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';

$routes = $app['router']->getRoutes();
$found = false;

foreach ($routes as $route) {
    if (strpos($route->uri(), 'api/customers') !== false) {
        echo $route->methods()[0] . ' ' . $route->uri() . PHP_EOL;
        $found = true;
    }
}

if (!$found) echo "❌ No API customers routes found\n";
