<?php

use App\Http\Controllers\Frontend\PageController;
use Illuminate\Support\Facades\Route;

Route::get('/welcome', fn () => view('welcome'));

/*
|--------------------------------------------------------------------------
| Public dynamic pages (Terms, Privacy, etc.)
|--------------------------------------------------------------------------
*/
Route::get('/page/{slug}', [PageController::class, 'show'])->name('page.show');
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
