<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BoutiqueController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CommandeController;
use App\Http\Controllers\CommissionController;
use App\Http\Controllers\CommuneController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Manager;
use App\Http\Controllers\ManagerDashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AuthController::class, 'showLoginForm'])->name('login');
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login.form');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');

Route::middleware('crm.role:commercial,manager')->group(function () {
    Route::get('/profil', [ProfileController::class, 'show'])->name('profil.show');
    Route::put('/profil', [ProfileController::class, 'update'])->name('profil.update');
    Route::get('/profil/avatar', [AuthController::class, 'avatar'])->name('profil.avatar');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

Route::middleware('crm.role:manager')->prefix('manager')->name('manager.')->group(function () {
    Route::get('/dashboard', [ManagerDashboardController::class, 'index'])->name('dashboard');

    Route::get('/livreurs', [Manager\LivreurController::class, 'index'])->name('livreurs.index');
    Route::post('/livreurs', [Manager\LivreurController::class, 'store'])->name('livreurs.store');
    Route::get('/livreurs/{livreur}', [Manager\LivreurController::class, 'show'])->name('livreurs.show');
    Route::get('/livreurs/{livreur}/photo', [Manager\LivreurController::class, 'photo'])->name('livreurs.photo');
    Route::post('/livreurs/{livreur}/documents', [Manager\LivreurController::class, 'storeDocument'])->name('livreurs.documents.store');
    Route::get('/livreurs/{livreur}/documents/{document}', [Manager\LivreurController::class, 'document'])->name('livreurs.documents.show');
    Route::delete('/livreurs/{livreur}/documents/{document}', [Manager\LivreurController::class, 'destroyDocument'])->name('livreurs.documents.destroy');
    Route::patch('/livreurs/{livreur}/kyc', [Manager\LivreurController::class, 'decisionKyc'])->name('livreurs.kyc');
    Route::put('/livreurs/{livreur}', [Manager\LivreurController::class, 'update'])->name('livreurs.update');
    Route::delete('/livreurs/{livreur}', [Manager\LivreurController::class, 'destroy'])->name('livreurs.destroy');
    Route::patch('/livreurs/{livreur}/statut', [Manager\LivreurController::class, 'toggleStatut'])->name('livreurs.toggle-statut');

    Route::get('/motos', [Manager\MotoController::class, 'index'])->name('motos.index');
    Route::post('/motos', [Manager\MotoController::class, 'store'])->name('motos.store');
    Route::put('/motos/{moto}', [Manager\MotoController::class, 'update'])->name('motos.update');
    Route::delete('/motos/{moto}', [Manager\MotoController::class, 'destroy'])->name('motos.destroy');

    Route::get('/contrats', [Manager\ContratController::class, 'index'])->name('contrats.index');
    Route::post('/contrats', [Manager\ContratController::class, 'store'])->name('contrats.store');
    Route::get('/contrats/{contrat}', [Manager\ContratController::class, 'show'])->name('contrats.show');
    Route::patch('/contrats/{contrat}/resilier', [Manager\ContratController::class, 'resilier'])->name('contrats.resilier');

    Route::get('/paiements', [Manager\PaiementController::class, 'index'])->name('paiements.index');
    Route::post('/paiements', [Manager\PaiementController::class, 'store'])->name('paiements.store');
    Route::delete('/paiements/{paiement}', [Manager\PaiementController::class, 'destroy'])->name('paiements.destroy');
});

Route::middleware('crm.role:commercial')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

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
