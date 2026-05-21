<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home', ['title' => 'Ting Hao | Baking Ingredient Supplier']);
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::view('/dashboard', 'dashboard', ['title' => 'Ting Hao | Dashboard'])->name('dashboard');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});
