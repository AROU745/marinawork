<?php

use App\Http\Controllers\AdminDeliveryController;
use App\Http\Controllers\AdminSalesController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UserDeskController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/', function () {
    if (! Auth::check()) {
        return redirect()->route('login');
    }

    return Auth::user()->isAdmin()
        ? redirect()->route('tickets.index')
        : redirect()->route('user.desk');
})->name('home');

Route::middleware('auth')->group(function () {
    // Page user : vente tickets + clôture journalière
    Route::get('/user', [UserDeskController::class, 'desk'])->name('user.desk');
    Route::get('/user/tache-journaliere', [UserDeskController::class, 'dailyTask'])->name('user.daily');
    Route::post('/user/ajouter-ticket', [UserDeskController::class, 'addTicket'])->name('user.ticket.add');
    Route::post('/user/fin-de-journee', [UserDeskController::class, 'closeDay'])->name('user.day.close');
    Route::get('/user/journee/{date}', [UserDeskController::class, 'dayReport'])->name('user.day');
    Route::post('/user/clients', [UserDeskController::class, 'storeCustomer'])->name('user.customers.store');
    Route::delete('/user/clients/{customer}', [UserDeskController::class, 'destroyCustomer'])->name('user.customers.destroy');
    Route::post('/user/livraisons', [UserDeskController::class, 'storeDelivery'])->name('user.deliveries.store');
    Route::post('/user/livraisons/{delivery}/livree', [UserDeskController::class, 'markDeliveryDone'])->name('user.deliveries.done');
    Route::delete('/user/livraisons/{delivery}', [UserDeskController::class, 'destroyDelivery'])->name('user.deliveries.destroy');

    // Admin : gestion complète des PDF / lots + ventes
    Route::middleware('admin')->group(function () {
        Route::get('/admin', [TicketController::class, 'index'])->name('tickets.index');
        Route::get('/admin/ventes', [AdminSalesController::class, 'index'])->name('admin.sales');
        Route::get('/admin/livraisons', [AdminDeliveryController::class, 'index'])->name('admin.deliveries');
        Route::post('/upload', [TicketController::class, 'upload'])->name('tickets.upload');
        Route::get('/lots/{batch}', [TicketController::class, 'show'])->name('tickets.show');
        Route::get('/lots/{batch}/visuel', [TicketController::class, 'visual'])->name('tickets.visual');
        Route::get('/lots/{batch}/pdf', [TicketController::class, 'pdfFile'])->name('tickets.pdf');
        Route::post('/lots/{batch}/visuel', [TicketController::class, 'storeVisual'])->name('tickets.visual.store');
        Route::post('/lots/{batch}/nouveau', [TicketController::class, 'next'])->name('tickets.next');
        Route::delete('/lots/{batch}', [TicketController::class, 'destroy'])->name('tickets.destroy');
    });
});
