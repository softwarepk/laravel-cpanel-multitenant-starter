<?php

use App\Http\Controllers\InstallationController;
use Illuminate\Support\Facades\Route;

Route::get('/install', [InstallationController::class, 'create'])->name('install.create');
Route::post('/install', [InstallationController::class, 'store'])->name('install.store');
