<?php

use App\Http\Controllers\Api\DeployController;
use App\Http\Controllers\Api\ExperienceController;
use Illuminate\Support\Facades\Route;

Route::get('/experiences', [ExperienceController::class, 'index'])->name('api.experiences');

Route::post('/deploy/migrate', [DeployController::class, 'migrate'])->name('api.deploy.migrate');
