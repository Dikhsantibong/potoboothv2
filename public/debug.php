<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::where('name', 'Mitra REA')->first();
Auth::login($user);

// Simulate the query in TransactionController@index
$query = App\Models\Transaction::with(['machine', 'template', 'finalImage'])->latest();
if (auth()->check() && auth()->user()->isMitra()) {
    $machineIds = auth()->user()->machines()->pluck('id');
    $query->whereIn('machine_id', $machineIds);
}

echo "SQL: " . $query->toSql() . "\n";
echo "Bindings: " . json_encode($query->getBindings()) . "\n";
echo "Count: " . $query->count() . "\n";
