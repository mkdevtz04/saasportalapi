<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminLoginController;
use App\Http\Controllers\Admin\AdminTenantController;
use App\Http\Controllers\Admin\AdminWithdrawalController;
use App\Http\Controllers\AgentPosController;
use App\Http\Controllers\DashboardAgentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardPackageController;
use App\Http\Controllers\DashboardRouterController;
use App\Http\Controllers\DashboardVoucherController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\TenantLoginController;
use App\Http\Controllers\TenantRegistrationController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureOwner;
use Illuminate\Support\Facades\Route;

// ── Main domain: trinetpay.online ─────────────────────────────────────────────

Route::get('/', function () {
    return view('welcome');
});

// Auth
// Named 'login' so Laravel's auth middleware redirects here automatically
Route::get('/login',  [TenantLoginController::class, 'show'])->name('login');
Route::post('/login', [TenantLoginController::class, 'store'])->name('tenant.login.store');
Route::post('/logout',[TenantLoginController::class, 'destroy'])->name('tenant.logout');

// Registration
Route::get('/register',  [TenantRegistrationController::class, 'show'])->name('register');
Route::post('/register', [TenantRegistrationController::class, 'store'])->name('register.store');

// ── Onboarding wizard ─────────────────────────────────────────────────────────
Route::middleware('auth:tenant')->prefix('onboarding')->name('onboarding.')->group(function () {
    Route::get('/router',       [OnboardingController::class, 'router'])->name('router');
    Route::post('/router',      [OnboardingController::class, 'storeRouter'])->name('router.store');
    Route::post('/test-router', [OnboardingController::class, 'testRouter'])->name('test-router');

    Route::get('/packages',     [OnboardingController::class, 'packages'])->name('packages');
    Route::post('/packages',    [OnboardingController::class, 'storePackages'])->name('packages.store');

    Route::get('/payment',      [OnboardingController::class, 'payment'])->name('payment');
    Route::post('/payment',     [OnboardingController::class, 'storePayment'])->name('payment.store');
});

// ── ISP Dashboard (owner only) ────────────────────────────────────────────────
Route::middleware(['auth:tenant', EnsureOwner::class])->prefix('dashboard')->name('dashboard.')->group(function () {

    // Home / Overview
    Route::get('/', [DashboardController::class, 'index'])->name('home');

    // Transactions (read-only)
    Route::get('/transactions', [DashboardController::class, 'transactions'])->name('transactions');

    // Router fleet
    Route::get('/routers',                  [DashboardRouterController::class, 'index'])->name('routers.index');
    Route::get('/routers/create',           [DashboardRouterController::class, 'create'])->name('routers.create');
    Route::post('/routers',                 [DashboardRouterController::class, 'store'])->name('routers.store');
    Route::post('/routers/test',            [DashboardRouterController::class, 'testConnection'])->name('routers.test');
    Route::get('/routers/{router}/edit',    [DashboardRouterController::class, 'edit'])->name('routers.edit');
    Route::put('/routers/{router}',         [DashboardRouterController::class, 'update'])->name('routers.update');
    Route::delete('/routers/{router}',      [DashboardRouterController::class, 'destroy'])->name('routers.destroy');

    // Packages
    Route::get('/packages',                 [DashboardPackageController::class, 'index'])->name('packages.index');
    Route::get('/packages/create',          [DashboardPackageController::class, 'create'])->name('packages.create');
    Route::post('/packages',                [DashboardPackageController::class, 'store'])->name('packages.store');
    Route::get('/packages/{package}/edit',  [DashboardPackageController::class, 'edit'])->name('packages.edit');
    Route::put('/packages/{package}',       [DashboardPackageController::class, 'update'])->name('packages.update');
    Route::delete('/packages/{package}',    [DashboardPackageController::class, 'destroy'])->name('packages.destroy');
    Route::post('/packages/{package}/toggle', [DashboardPackageController::class, 'toggle'])->name('packages.toggle');

    // Settings
    Route::get('/settings',  [DashboardController::class, 'settings'])->name('settings');
    Route::post('/settings', [DashboardController::class, 'updateSettings'])->name('settings.update');

    // Wallet & withdrawals
    Route::get('/wallet',          [DashboardController::class, 'wallet'])->name('wallet');
    Route::post('/wallet/withdraw',[DashboardController::class, 'requestWithdrawal'])->name('wallet.withdraw');

    // Vouchers
    Route::get('/vouchers',                       [DashboardVoucherController::class, 'index'])->name('vouchers.index');
    Route::get('/vouchers/generate',              [DashboardVoucherController::class, 'generate'])->name('vouchers.generate');
    Route::post('/vouchers/generate',             [DashboardVoucherController::class, 'store'])->name('vouchers.store');
    Route::get('/vouchers/{batchRef}/print',      [DashboardVoucherController::class, 'print'])->name('vouchers.print');
    Route::delete('/vouchers/{batchRef}',         [DashboardVoucherController::class, 'destroyBatch'])->name('vouchers.destroy');

    // Agents
    Route::get('/agents',                         [DashboardAgentController::class, 'index'])->name('agents.index');
    Route::get('/agents/create',                  [DashboardAgentController::class, 'create'])->name('agents.create');
    Route::post('/agents',                        [DashboardAgentController::class, 'store'])->name('agents.store');
    Route::post('/agents/{agent}/topup',          [DashboardAgentController::class, 'topup'])->name('agents.topup');
    Route::delete('/agents/{agent}',              [DashboardAgentController::class, 'destroy'])->name('agents.destroy');
});

// ── Agent POS (any authenticated tenant user) ─────────────────────────────────
Route::middleware('auth:tenant')->prefix('pos')->name('pos.')->group(function () {
    Route::get('/',     [AgentPosController::class, 'index'])->name('index');
    Route::post('/sell',[AgentPosController::class, 'sell'])->name('sell');
});

// ── Super-admin panel ─────────────────────────────────────────────────────────
Route::get('/admin/login',  [AdminLoginController::class, 'show'])->name('admin.login');
Route::post('/admin/login', [AdminLoginController::class, 'store'])->name('admin.login.store');
Route::post('/admin/logout',[AdminLoginController::class, 'destroy'])->name('admin.logout');

Route::middleware(EnsureAdmin::class)->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    // ISP management
    Route::get('/tenants',                          [AdminTenantController::class, 'index'])->name('tenants.index');
    Route::get('/tenants/{tenant}',                 [AdminTenantController::class, 'show'])->name('tenants.show');
    Route::post('/tenants/{tenant}/suspend',        [AdminTenantController::class, 'suspend'])->name('tenants.suspend');
    Route::post('/tenants/{tenant}/activate',       [AdminTenantController::class, 'activate'])->name('tenants.activate');
    Route::post('/tenants/{tenant}/set-fee',        [AdminTenantController::class, 'setFee'])->name('tenants.set-fee');

    // Withdrawal requests
    Route::get('/withdrawals',                      [AdminWithdrawalController::class, 'index'])->name('withdrawals.index');
    Route::post('/withdrawals/{withdrawal}/approve',[AdminWithdrawalController::class, 'approve'])->name('withdrawals.approve');
    Route::post('/withdrawals/{withdrawal}/paid',   [AdminWithdrawalController::class, 'markPaid'])->name('withdrawals.paid');
    Route::post('/withdrawals/{withdrawal}/reject', [AdminWithdrawalController::class, 'reject'])->name('withdrawals.reject');
});

// ── Captive portal (tenant subdomains: {slug}.trinetpay.online) ───────────────
Route::get('/portal', [PaymentController::class, 'index'])->name('portal');
