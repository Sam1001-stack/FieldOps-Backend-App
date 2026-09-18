<?php

/**
 * FieldOps REST API v1.
 *
 * Public: login, customer register, CMS pages.
 * Authenticated (Sanctum + org): jobs, Plantafel, CRM, field capture, billing, platform CMS.
 *
 * Route keys bind on public UUID except {page} (content slug).
 */

use App\Http\Api\V1\AuthController;
use App\Http\Api\V1\BillingController;
use App\Http\Api\V1\ContentController;
use App\Http\Api\V1\CustomerController;
use App\Http\Api\V1\DispatchController;
use App\Http\Api\V1\FieldController;
use App\Http\Api\V1\HealthController;
use App\Http\Api\V1\InvitationController;
use App\Http\Api\V1\InvoiceController;
use App\Http\Api\V1\JobController;
use App\Http\Api\V1\MemberController;
use App\Http\Api\V1\NotificationController;
use App\Http\Api\V1\OrganizationController;
use App\Http\Api\V1\PlatformController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('health', HealthController::class);

    Route::middleware('throttle:auth')->group(function (): void {
        Route::post('auth/login', [AuthController::class, 'login']);
        Route::post('auth/register', [AuthController::class, 'register']);
        Route::post('auth/super-admin', [AuthController::class, 'registerSuperAdmin']);
        Route::post('invitations/accept', [InvitationController::class, 'accept']);
    });

    Route::get('content', [ContentController::class, 'index']);
    Route::get('content/{page}', [ContentController::class, 'show']);

    Route::middleware(['auth:sanctum', 'org'])->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);

        Route::get('jobs', [JobController::class, 'index']);
        Route::post('jobs', [JobController::class, 'store'])->middleware('plan:jobs');
        Route::get('jobs/{job}', [JobController::class, 'show']);
        Route::put('jobs/{job}', [JobController::class, 'update']);
        Route::delete('jobs/{job}', [JobController::class, 'destroy']);
        Route::post('jobs/{job}/transition', [JobController::class, 'transition']);

        Route::get('dispatch/board', [DispatchController::class, 'board']);
        Route::post('jobs/{job}/assign', [DispatchController::class, 'assign']);

        Route::get('customers', [CustomerController::class, 'index']);
        Route::post('customers', [CustomerController::class, 'store']);
        Route::put('customers/{customer}', [CustomerController::class, 'update']);
        Route::post('customers/{customer}/verify', [CustomerController::class, 'verify']);
        Route::delete('customers/{customer}', [CustomerController::class, 'destroy']);

        Route::get('notifications', [NotificationController::class, 'index']);
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead']);

        Route::get('invoices', [InvoiceController::class, 'index']);
        Route::get('invoices/export', [InvoiceController::class, 'exportCsv']);
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show']);
        Route::post('jobs/{job}/invoice', [InvoiceController::class, 'fromJob']);
        Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send']);
        Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy']);

        Route::get('catalog', [FieldController::class, 'catalog']);
        Route::post('jobs/{job}/materials', [FieldController::class, 'addMaterial']);
        Route::post('jobs/{job}/photos', [FieldController::class, 'photo']);
        Route::post('jobs/{job}/signature', [FieldController::class, 'sign']);
        Route::post('jobs/{job}/time/start', [FieldController::class, 'startTime']);
        Route::post('jobs/{job}/time/stop', [FieldController::class, 'stopTime']);

        Route::get('organization/settings', [OrganizationController::class, 'settings']);
        Route::put('organization/settings', [OrganizationController::class, 'updateSettings']);
        Route::get('billing', [BillingController::class, 'show']);
        Route::post('billing/checkout', [BillingController::class, 'checkout']);
        Route::post('billing/sync', [BillingController::class, 'sync']);
        Route::post('invitations', [InvitationController::class, 'store'])->middleware('plan:users');

        Route::get('organization/members', [MemberController::class, 'index']);
        Route::get('organization/members/logs', [MemberController::class, 'logs']);
        Route::post('organization/members', [MemberController::class, 'store']);
        Route::put('organization/members/{member}', [MemberController::class, 'update']);
        Route::delete('organization/members/{member}', [MemberController::class, 'destroy']);
        Route::post('organization/members/{member}/approve', [MemberController::class, 'approve']);

        Route::get('platform/overview', [PlatformController::class, 'overview']);
        Route::post('platform/switch-organization', [PlatformController::class, 'switchOrganization']);
        Route::get('platform/organizations', [PlatformController::class, 'organizations']);
        Route::post('platform/organizations/{organization}/suspend', [PlatformController::class, 'suspend']);
        Route::post('platform/impersonate', [PlatformController::class, 'impersonate']);
        Route::get('platform/health', [PlatformController::class, 'health']);
        Route::get('platform/content', [ContentController::class, 'adminIndex']);
        Route::put('platform/content/{page}', [ContentController::class, 'upsert']);
    });
});
