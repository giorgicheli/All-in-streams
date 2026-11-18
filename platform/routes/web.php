<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\AgencyPortalController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\ProviderDeliveryController;
use App\Http\Controllers\WebsiteController;
use App\Http\Controllers\PartnerEnquiryController;
use App\Http\Controllers\AccountVerificationController;
use App\Http\Middleware\EnsurePartnerVerified;
use App\Http\Controllers\AccountSettingsController;
use App\Http\Controllers\CreatorProofVideoController;
use App\Http\Controllers\UserFileController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Middleware\SensitiveAuthResponse;
use App\Http\Middleware\EnsureRole;
use App\Livewire\BriefWizard;
use App\Livewire\CreatorApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', [WebsiteController::class, 'home'])->name('home');
Route::get('/white', [WebsiteController::class, 'white'])->name('home.white');
Route::get('/contact', [ContactController::class, 'create'])->name('contact.create');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:contact-message')->name('contact.store');
Route::get('/talk-to-us', [PartnerEnquiryController::class, 'create'])->middleware('guest')->name('enquiries.create');
Route::post('/talk-to-us', [PartnerEnquiryController::class, 'store'])->middleware(['guest', 'throttle:10,1'])->name('enquiries.store');
Route::get('/privacy-policy', [WebsiteController::class, 'privacy'])->name('privacy');
Route::get('/terms-and-conditions', [WebsiteController::class, 'terms'])->name('terms');
Route::get('/admin/website-preview/{page}', [WebsiteController::class, 'preview'])
    ->whereIn('page', ['home', 'settings', 'privacy', 'terms'])->middleware('auth')->name('website.preview');
Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth.login')->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::view('/register', 'auth.register')->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
});
Route::middleware([SensitiveAuthResponse::class, 'guest'])->group(function () {
    Route::get('/forgot-password', [PasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:password-email-ip')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:password-update-ip')->name('password.update');
});
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/account', fn (Request $request, AuthController $auth) => redirect($auth->home($request->user())))->name('account');
    Route::get('/account/pending', [AccountVerificationController::class, 'pending'])->name('account.pending');
    Route::get('/account/settings', [AccountSettingsController::class, 'edit'])->name('account.settings');
    Route::post('/account/settings', [AccountSettingsController::class, 'update'])->name('account.settings.update');
    Route::get('/admin/creators/{creator}/verification-video', [CreatorProofVideoController::class, 'show'])->withTrashed()->name('admin.creator-video');
    Route::post('/admin/user-files/upload', [UserFileController::class, 'store'])->name('admin.user-files.store');
    Route::get('/admin/user-files/{userFile}/download', [UserFileController::class, 'download'])->name('admin.user-files.download');
    Route::delete('/admin/user-files/{userFile}', [UserFileController::class, 'destroy'])->name('admin.user-files.destroy');
    Route::get('/documents', [PortalController::class, 'documents'])->middleware(EnsurePartnerVerified::class)->name('documents');
    Route::get('/documents/{document}/download', [PortalController::class, 'download'])->middleware(EnsurePartnerVerified::class)->name('documents.download');
    Route::get('/briefs/{brief}/attachment', [PortalController::class, 'briefAttachment'])->middleware(EnsurePartnerVerified::class)->name('briefs.attachment');
    Route::get('/provider/deliveries', [ProviderDeliveryController::class, 'index'])->middleware(EnsurePartnerVerified::class)->name('provider.deliveries');
    Route::post('/provider/invitations/{invitation}/deliveries', [ProviderDeliveryController::class, 'store'])->middleware(EnsurePartnerVerified::class)->name('provider.deliveries.store');
    Route::get('/provider/payments', [ProviderDeliveryController::class, 'payments'])->middleware(EnsurePartnerVerified::class)->name('provider.payments');
    Route::get('/provider/deliveries/{delivery}/download', [ProviderDeliveryController::class, 'download'])->middleware(EnsurePartnerVerified::class)->name('provider.deliveries.download');
    Route::middleware([EnsureRole::class.':business', EnsurePartnerVerified::class])->group(function () {
        Route::get('/dashboard', [PortalController::class, 'dashboard'])->name('dashboard');
        Route::get('/briefs/create', BriefWizard::class)->name('briefs.create');
        Route::get('/briefs/{brief}/edit', BriefWizard::class)->name('briefs.edit');
        Route::get('/campaigns', [PortalController::class, 'campaigns'])->name('campaigns');
        Route::get('/campaigns/{brief}', [PortalController::class, 'campaign'])->name('campaigns.show');
        Route::get('/proposals', [PortalController::class, 'proposals'])->name('proposals');
        Route::post('/proposals/{invitation}/request', [PortalController::class, 'requestCreator'])->name('proposals.request');
    });
    Route::middleware([EnsureRole::class.':creator', EnsurePartnerVerified::class])->group(function () {
        Route::get('/creator/opportunities', [PortalController::class, 'opportunities'])->name('creator.opportunities');
        Route::get('/creator/apply', CreatorApplication::class)->name('creator.apply');
        Route::post('/creator/invitations/{invitation}/respond', [PortalController::class, 'respond'])->name('creator.respond');
    });
    Route::prefix('agency')->name('agency.')->middleware([EnsureRole::class.':agency', EnsurePartnerVerified::class])->group(function () {
        Route::redirect('/', '/agency/dashboard');
        Route::get('/dashboard', [AgencyPortalController::class, 'dashboard'])->name('dashboard');
        Route::get('/briefs/{brief}/edit', BriefWizard::class)->name('briefs.edit');
        Route::get('/profile', [AgencyPortalController::class, 'profile'])->name('profile');
        Route::post('/profile', [AgencyPortalController::class, 'saveProfile'])->name('profile.save');
        Route::get('/roster', [AgencyPortalController::class, 'roster'])->name('roster');
        Route::post('/roster', [AgencyPortalController::class, 'storeTalent'])->name('talent.store');
        Route::patch('/roster/{talent}', [AgencyPortalController::class, 'updateTalent'])->name('talent.update');
        Route::delete('/roster/{talent}', [AgencyPortalController::class, 'deleteTalent'])->name('talent.destroy');
        Route::get('/opportunities', [AgencyPortalController::class, 'opportunities'])->name('opportunities');
        Route::post('/invitations/{invitation}/respond', [PortalController::class, 'respond'])->name('respond');
        Route::get('/briefs/create', BriefWizard::class)->name('briefs.create');
        Route::get('/campaigns', [PortalController::class, 'campaigns'])->name('campaigns');
        Route::get('/campaigns/{brief}', [PortalController::class, 'campaign'])->name('campaigns.show');
        Route::get('/proposals', [PortalController::class, 'proposals'])->name('proposals');
        Route::post('/proposals/{invitation}/request', [PortalController::class, 'requestCreator'])->name('proposals.request');
    });
});
