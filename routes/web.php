<?php

use App\Http\Controllers\Admin\AdminAuditController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminImpersonationController;
use App\Http\Controllers\Admin\AdminLoginController;
use App\Http\Controllers\Admin\AdminReconciliationController;
use App\Http\Controllers\Admin\AdminTenantController;
use App\Http\Controllers\Admin\AdminWithdrawalController;
use App\Http\Controllers\AgentPosController;
use App\Http\Controllers\DashboardAgentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardPackageController;
use App\Http\Controllers\DashboardReportController;
use App\Http\Controllers\DashboardRouterController;
use App\Http\Controllers\DashboardSessionController;
use App\Http\Controllers\DashboardVoucherController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RouterProvisionController;
use App\Http\Controllers\TenantLoginController;
use App\Http\Controllers\TenantRegistrationController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureOwner;
use Illuminate\Support\Facades\Route;

// ── Main domain (APP_URL) ─────────────────────────────────────────────

Route::get('/', function () {
    return view('welcome');
});

// ── 1-Command MikroTik Router Auto-Provisioning Public Endpoints ───────────────
Route::middleware('throttle:provision')->group(function () {
    Route::get('/provision/{token}',            [RouterProvisionController::class, 'downloadScript'])->name('router.provision.script');
    Route::get('/provision/{token}/login.html', [RouterProvisionController::class, 'loginPage'])->name('router.provision.login');
    Route::get('/provision/{token}/complete',   [RouterProvisionController::class, 'completeProvision'])->name('router.provision.complete');
});
Route::get('/provision/{token}/status',   [RouterProvisionController::class, 'checkStatus'])->name('router.provision.status');

// Uptime monitors call this. Details are only shown with the X-Health-Token header.
Route::get('/health', HealthController::class)->name('health');

// Auth
// Named 'login' so Laravel's auth middleware redirects here automatically
Route::get('/login',  [TenantLoginController::class, 'show'])->name('login');
Route::post('/login', [TenantLoginController::class, 'store'])->name('tenant.login.store');
Route::post('/logout',[TenantLoginController::class, 'destroy'])->name('tenant.logout');

// Registration
Route::get('/register',  [TenantRegistrationController::class, 'show'])->name('register');
Route::post('/register', [TenantRegistrationController::class, 'store'])->middleware('throttle:register')->name('register.store');

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
    Route::post('/routers/{router}/command', [DashboardRouterController::class, 'command'])->name('routers.command');
    Route::post('/routers/{router}/switch',  [DashboardRouterController::class, 'switchToRadius'])->name('routers.switch');
    Route::post('/routers/{router}/rotate',  [DashboardRouterController::class, 'rotateSecrets'])->name('routers.rotate');

    // Packages
    Route::get('/packages',                 [DashboardPackageController::class, 'index'])->name('packages.index');
    Route::get('/packages/create',          [DashboardPackageController::class, 'create'])->name('packages.create');
    Route::post('/packages',                [DashboardPackageController::class, 'store'])->name('packages.store');
    Route::get('/packages/{package}/edit',  [DashboardPackageController::class, 'edit'])->name('packages.edit');
    Route::put('/packages/{package}',       [DashboardPackageController::class, 'update'])->name('packages.update');
    Route::delete('/packages/{package}',    [DashboardPackageController::class, 'destroy'])->name('packages.destroy');
    Route::post('/packages/{package}/toggle', [DashboardPackageController::class, 'toggle'])->name('packages.toggle');

    // Live sessions and usage
    Route::get('/sessions',              [DashboardSessionController::class, 'index'])->name('sessions');
    Route::post('/sessions/disconnect',  [DashboardSessionController::class, 'disconnect'])->name('sessions.disconnect');

    // Sales reports
    Route::get('/reports',        [DashboardReportController::class, 'index'])->name('reports');
    Route::get('/reports/export', [DashboardReportController::class, 'export'])->name('reports.export');

    // Settings
    Route::get('/settings',  [DashboardController::class, 'settings'])->name('settings');
    Route::post('/settings', [DashboardController::class, 'updateSettings'])->middleware('no.impersonation')->name('settings.update');

    // Wallet & withdrawals
    Route::get('/wallet',          [DashboardController::class, 'wallet'])->name('wallet');
    Route::post('/wallet/withdraw',[DashboardController::class, 'requestWithdrawal'])->middleware('no.impersonation')->name('wallet.withdraw');

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
    Route::post('/agents/{agent}/topup',          [DashboardAgentController::class, 'topup'])->middleware('no.impersonation')->name('agents.topup');
    Route::delete('/agents/{agent}',              [DashboardAgentController::class, 'destroy'])->name('agents.destroy');
});

// Ends a platform-support session. Available to whoever is being impersonated.
Route::post('/impersonation/stop', [AdminImpersonationController::class, 'stop'])
    ->middleware('auth:tenant')->name('impersonation.stop');

// ── Your own account (owners and agents alike) ────────────────────────────────
// Outside the dashboard group on purpose: agents work in the POS and still need their own
// name, sign-in email and password. Support may look but never change credentials.
Route::middleware('auth:tenant')->prefix('profile')->name('profile.')->group(function () {
    Route::get('/', [ProfileController::class, 'edit'])->name('edit');

    Route::middleware(['no.impersonation', 'throttle:profile'])->group(function () {
        Route::put('/',          [ProfileController::class, 'update'])->name('update');
        Route::put('/password',  [ProfileController::class, 'updatePassword'])->name('password');
    });
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

    // Money checks, audit trail and support access
    Route::get('/reconciliation',        [AdminReconciliationController::class, 'index'])->name('reconciliation');
    Route::get('/reconciliation/export', [AdminReconciliationController::class, 'export'])->name('reconciliation.export');
    Route::get('/audit',                 [AdminAuditController::class, 'index'])->name('audit');
    Route::post('/tenants/{tenant}/impersonate', [AdminImpersonationController::class, 'start'])->name('tenants.impersonate');

    // Withdrawal requests
    Route::get('/withdrawals',                      [AdminWithdrawalController::class, 'index'])->name('withdrawals.index');
    Route::post('/withdrawals/{withdrawal}/approve',[AdminWithdrawalController::class, 'approve'])->name('withdrawals.approve');
    Route::post('/withdrawals/{withdrawal}/paid',   [AdminWithdrawalController::class, 'markPaid'])->name('withdrawals.paid');
    Route::post('/withdrawals/{withdrawal}/reject', [AdminWithdrawalController::class, 'reject'])->name('withdrawals.reject');
});

// ── Captive portal ────────────────────────────────────────────────────────────
// Every ISP has their own address, /portal/{their key}, which is what their routers link to.
// Plain /portal still works for routers that identify themselves with ?nas=, and for the
// older {key}.<your domain> subdomains.
Route::middleware('portal.locale')->group(function () {
    Route::get('/portal',              [PaymentController::class, 'index'])->name('portal');
    Route::get('/portal/{portal_key}', [PaymentController::class, 'index'])
        ->where('portal_key', '[A-Za-z0-9-]{1,63}')
        ->name('portal.tenant');
});
