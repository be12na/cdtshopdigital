<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// --- cPanel Configuration ---
// Change 'cepatshop_app' to the name of the folder where you placed
// your Laravel files (everything except the 'public' folder)
$appPath = __DIR__.'/../cepatshop_app';
// ----------------------------

if (file_exists($maintenance = $appPath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $appPath.'/vendor/autoload.php';

$app = require_once $appPath.'/bootstrap/app.php';

// Tell Laravel that the public path is the current directory (public_html)
$app->bind('path.public', function() {
    return __DIR__;
});

$kernel = $app->make(Kernel::class);

$response = $kernel->handle(
    $request = Request::capture()
)->send();

$kernel->terminate($request, $response);
