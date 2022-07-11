<?php

use App\Http\Controllers\Api\AccountsListingsController;
use App\Http\Controllers\Api\DashboardLatestAccountsController;
use App\Http\Controllers\Api\DashboardServersController;
use App\Http\Controllers\Api\DashboardStatsController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RefreshServerController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ServersController;
use App\Http\Controllers\ServersTokenController;
use App\Http\Controllers\UserChangePasswordController;
use App\Http\Controllers\UserController;
use App\Http\Livewire\Account\Listings as AccountListings;
use App\Http\Livewire\Server\Create as ServerCreate;
use App\Http\Livewire\Server\Details as ServerDetails;
use App\Http\Livewire\Server\Listings as ServerListings;
use App\Http\Livewire\User\Create as UserCreate;
use App\Http\Livewire\User\Listings as UserListings;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

// Authentication Routes...
Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('login', [LoginController::class, 'login']);
Route::post('logout', [LoginController::class, 'logout'])->name('logout');

// Password Reset Routes...
Route::get('password/reset', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
Route::post('password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');
Route::get('password/reset/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
Route::post('password/reset', [ResetPasswordController::class, 'reset'])->name('password.update');

// Dashboard Routes...
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

// Account Routes...
Route::prefix('accounts')->middleware('auth')->group(function () {
    Route::get('/', AccountListings::class)->name('accounts.index');
});

// Server Routes...
Route::prefix('servers')->middleware('auth')->group(function () {
    Route::get('/', ServerListings::class)->name('servers.index');
    Route::get('/create', ServerCreate::class)->name('servers.create');
    Route::get('/{server}', ServerDetails::class)->name('servers.show');

    Route::put('/{server}/token', [ServersTokenController::class, 'update'])->name('servers.token');
    Route::delete('/{server}/token', [ServersTokenController::class, 'destroy'])->name('servers.token-destroy');

    Route::get('/{server}/refresh', [RefreshServerController::class, 'update'])->name('servers.refresh');
});

// User Routes...
Route::prefix('users')->middleware('auth')->group(function () {
    Route::get('/', UserListings::class)->name('users.index');
    Route::get('/create', UserCreate::class)->name('users.create');
    Route::put('/{user}', [UserController::class, 'update'])->name('users.update');
    Route::get('/{user}/edit', [UserController::class, 'edit'])->name('users.edit');

    Route::put('/{user}/change-password', [UserChangePasswordController::class, 'update'])->name('users.change-password');
});

// Search Routes...
Route::middleware('auth')->group(function () {
    Route::get('/search', [SearchController::class, 'index'])->name('search');
});

// API Routes...
Route::prefix('api')->middleware('auth')->group(function () {
    Route::get('/accounts', [AccountsListingsController::class, 'index'])->name('accounts.listing');
    Route::get('/accounts/{server}', [AccountsListingsController::class, 'index'])->name('accounts.server-listing');
    Route::get('/dashboard/stats', [DashboardStatsController::class, 'index'])->name('dashboard.stats');
    Route::get('/dashboard/servers', [DashboardServersController::class, 'index'])->name('dashboard.servers');
    Route::get('/dashboard/latest-accounts', [DashboardLatestAccountsController::class, 'index'])->name('dashboard.latest-accounts');
});
