<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BoutiqueController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CommandeController;
use App\Http\Controllers\CommissionController;
use App\Http\Controllers\CommuneController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AuthController::class, 'showLoginForm'])->name('login');
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login.form');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');

Route::middleware('commercial')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profil', [ProfileController::class, 'show'])->name('profil.show');
    Route::put('/profil', [ProfileController::class, 'update'])->name('profil.update');
    Route::get('/profil/avatar', [AuthController::class, 'avatar'])->name('profil.avatar');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
    Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');
    Route::put('/clients/{client}', [ClientController::class, 'update'])->name('clients.update');
    Route::delete('/clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');

    Route::get('/commandes', [CommandeController::class, 'index'])->name('commandes.index');
    Route::post('/commandes', [CommandeController::class, 'store'])->name('commandes.store');
    Route::put('/commandes/{commande}', [CommandeController::class, 'update'])->name('commandes.update');
    Route::patch('/commandes/{commande}/livreur', [CommandeController::class, 'assignLivreur'])->name('commandes.assign-livreur');
    Route::delete('/commandes/{commande}', [CommandeController::class, 'destroy'])->name('commandes.destroy');

    Route::get('/commissions', [CommissionController::class, 'index'])->name('commissions.index');

    Route::get('/boutiques', [BoutiqueController::class, 'index'])->name('boutiques.index');
    Route::post('/boutiques', [BoutiqueController::class, 'store'])->name('boutiques.store');
    Route::get('/boutiques/{boutique}/logo', [BoutiqueController::class, 'logo'])->name('boutiques.logo');
    Route::get('/boutiques/{boutique}', [BoutiqueController::class, 'show'])->name('boutiques.show');
    Route::put('/boutiques/{boutique}', [BoutiqueController::class, 'update'])->name('boutiques.update');
    Route::delete('/boutiques/{boutique}', [BoutiqueController::class, 'destroy'])->name('boutiques.destroy');

    Route::get('/communes', [CommuneController::class, 'index'])->name('communes.index');
    Route::post('/communes', [CommuneController::class, 'store'])->name('communes.store');
    Route::put('/communes/{commune}', [CommuneController::class, 'update'])->name('communes.update');
    Route::delete('/communes/{commune}', [CommuneController::class, 'destroy'])->name('communes.destroy');
});
