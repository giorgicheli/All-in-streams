<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\WebsiteController;
use App\Http\Middleware\EnsureRole;
use App\Livewire\BriefWizard;
use App\Livewire\CreatorApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', [WebsiteController::class, 'home'])->name('home');
Route::get('/admin/website-preview/{page}', [WebsiteController::class, 'preview'])
    ->whereIn('page', ['home', 'settings'])->middleware('auth')->name('website.preview');
Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth.login')->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::view('/register', 'auth.register')->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
});
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/account', fn (Request $request, AuthController $auth) => redirect($auth->home($request->user())))->name('account');
    Route::get('/documents', [PortalController::class, 'documents'])->name('documents');
    Route::get('/documents/{document}/download', [PortalController::class, 'download'])->name('documents.download');
    Route::middleware(EnsureRole::class.':business')->group(function () {
        Route::get('/dashboard', [PortalController::class, 'dashboard'])->name('dashboard');
        Route::get('/briefs/create', BriefWizard::class)->name('briefs.create');
        Route::get('/campaigns', [PortalController::class, 'campaigns'])->name('campaigns');
        Route::get('/campaigns/{brief}', [PortalController::class, 'campaign'])->name('campaigns.show');
        Route::get('/proposals', [PortalController::class, 'proposals'])->name('proposals');
        Route::post('/proposals/{invitation}/request', [PortalController::class, 'requestCreator'])->name('proposals.request');
    });
    Route::middleware(EnsureRole::class.':creator')->group(function () {
        Route::get('/creator/opportunities', [PortalController::class, 'opportunities'])->name('creator.opportunities');
        Route::get('/creator/apply', CreatorApplication::class)->name('creator.apply');
        Route::post('/creator/invitations/{invitation}/respond', [PortalController::class, 'respond'])->name('creator.respond');
    });
});
