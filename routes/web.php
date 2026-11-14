<?php

use App\Http\Controllers\Frontend\PageController;
use App\Http\Controllers\Frontend\PublicDocumentController;
use Illuminate\Support\Facades\Route;

Route::get('/welcome', fn () => view('welcome'));

/*
|--------------------------------------------------------------------------
| Public dynamic pages (Terms, Privacy, etc.)
|--------------------------------------------------------------------------
*/
Route::get('/page/{slug}', [PageController::class, 'show'])->name('page.show');

// A privacy request's data export, by the signed link e-mailed to the person who asked (Doc 15 Phase G1).
Route::get('/privacy-exports/{id}', [\App\Http\Controllers\PrivacyExportController::class, 'download'])->whereNumber('id')->middleware(['signed', 'throttle:public-documents'])->name('privacy-exports.download');

/*
|--------------------------------------------------------------------------
| Secure document links (Doc 06 section 9): a customer opens a sales
| document, and downloads its PDF, without signing in
|--------------------------------------------------------------------------
*/
Route::middleware('throttle:public-documents')->prefix('d')->group(function () {
    Route::get('/{token}', [PublicDocumentController::class, 'show'])->where('token', '[A-Za-z0-9]{48}')->name('documents.public');
    Route::get('/{token}/pdf', [PublicDocumentController::class, 'pdf'])->where('token', '[A-Za-z0-9]{48}')->name('documents.public.pdf');
});
Route::get('/', function () {
    if (auth()->check() && auth()->user()->isAdmin()) {
        return redirect()->route('admin.overview');
    }

    return redirect()->route('login');
})->name('home');

/*
|--------------------------------------------------------------------------
| Social Login Demo (development only)
|--------------------------------------------------------------------------
*/
Route::get('/social-login-demo', fn () => view('social-login-demo'))
    ->name('social-login-demo');

Route::get('/auth/linkedin/callback', fn () => view('social-login-callback'))
    ->name('social-login-demo.linkedin.callback');

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
