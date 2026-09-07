<?php

use App\Http\Controllers\InstallationController;
use Illuminate\Support\Facades\Route;

Route::get('/install', [InstallationController::class, 'create'])->name('install.create');
Route::post('/install/cpanel-check', [InstallationController::class, 'checkCpanel'])->name('install.cpanel-check');
Route::post('/install', [InstallationController::class, 'store'])->name('install.store');
