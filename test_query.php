<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$transactions = App\Models\Transaction::with(['machine'])
    ->selectRaw('DATE(created_at) as date, machine_id, COUNT(*) as total_transactions, SUM(amount) as total_amount')
    ->where('status', 'COMPLETED')
    ->whereRaw('LOWER(payment_type) = ?', ['qris'])
    ->groupBy('date', 'machine_id')
    ->orderBy('date', 'ASC')
    ->get();

foreach($transactions as $t) {
    echo $t->date . ' | ' . ($t->machine->name ?? 'Unknown') . ' | ' . $t->total_transactions . ' | ' . $t->total_amount . "\n";
}
