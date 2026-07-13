<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$admin = App\Models\User::where('role', 'admin')->first();
$request = Illuminate\Http\Request::create('/dashboard', 'GET');
auth()->login($admin);
$request->setUserResolver(function () use ($admin) {
    return $admin;
});

$controller = new App\Http\Controllers\DashboardController();
$response = $controller->__invoke($request);
echo "Dashboard API call succeeded!\n";
