<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "First Machine (Old Logic): " . App\Models\Machine::first()->id . "\n";
echo "First Machine (New Logic): " . App\Models\Machine::orderBy('name')->first()->id . "\n";
