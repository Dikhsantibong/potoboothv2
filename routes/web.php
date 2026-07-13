<?php

use App\Http\Controllers\DownloadController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\MachineController;
use App\Http\Controllers\PaperSizeController;
use App\Http\Controllers\Settings\PaymentGatewayController;
use App\Http\Controllers\StickerController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\VoucherController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // Routes available to both admin and mitra
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('/transactions/export', [TransactionController::class, 'export'])->name('transactions.export');
    Route::resource('transactions', TransactionController::class)->only(['index', 'show']);
    Route::get('gallery', [GalleryController::class, 'index'])->name('gallery.index');
    Route::post('machines/current', [\App\Http\Controllers\CurrentMachineController::class, 'store'])->name('machines.current');

    // Admin-only routes
    Route::middleware('admin')->group(function () {
        // Users management
        Route::resource('users', \App\Http\Controllers\UserController::class);
        
        Route::resource('machines', MachineController::class);
        Route::post('machines/{machine}/reset-paper', [MachineController::class, 'resetPaper'])->name('machines.reset-paper');
        Route::resource('paper-sizes', PaperSizeController::class);
        Route::resource('stickers', StickerController::class);
        Route::resource('templates', TemplateController::class);
        Route::patch('templates/{template}/toggle', [TemplateController::class, 'toggle'])->name('templates.toggle');
        Route::resource('transactions', TransactionController::class)->except(['index', 'show']);
        Route::resource('vouchers', VoucherController::class);
        Route::delete('gallery/{finalImage}/media', [GalleryController::class, 'destroyMedia'])->name('gallery.media.destroy');

        // Settings
        Route::get('settings/payment-gateway', [PaymentGatewayController::class, 'edit'])->name('settings.payment-gateway.edit');
        Route::put('settings/payment-gateway', [PaymentGatewayController::class, 'update'])->name('settings.payment-gateway.update');
    });
});

require __DIR__ . '/settings.php';

Route::get('/downloads/{token}', [DownloadController::class, 'show']);
