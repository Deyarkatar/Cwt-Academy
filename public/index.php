<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

// Determine the application base directory relative to this script.
$baseDir = dirname(__DIR__);

// Autoload the Composer dependencies.
require_once $baseDir.'/vendor/autoload.php';

// Bootstrap the Laravel application.
$app = require_once $baseDir.'/bootstrap/app.php';

// Create the HTTP kernel and handle the incoming request.
$kernel = $app->make(Kernel::class);

$request = Request::capture();

$response = $kernel->handle($request);

$response->send();

$kernel->terminate($request, $response);
