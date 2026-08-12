<?php

use App\Http\Controllers\LocaleController;
use App\Http\Controllers\WebsiteController;
use Illuminate\Support\Facades\Route;

// Canonical English public routes
Route::get('/', [WebsiteController::class, 'home'])->name('home');
Route::get('/map', [WebsiteController::class, 'map'])->name('map');
Route::get('/about', [WebsiteController::class, 'about'])->name('about');
Route::get('/submit', [WebsiteController::class, 'submit'])->name('submit');
Route::get('/terms', [WebsiteController::class, 'terms'])->name('terms');
// Dutch URL aliases — permanent redirect to English equivalents
Route::permanentRedirect('/kaart', '/map');
Route::permanentRedirect('/over', '/about');
Route::permanentRedirect('/indienen', '/submit');
Route::permanentRedirect('/algemene-voorwaarden', '/terms');

// Locale switcher
Route::post('/locale/{locale}', LocaleController::class)
    ->name('locale.switch')
    ->where('locale', 'nl|en');
