<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\KanbanCardController;
use App\Http\Controllers\KanbanCommentController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\MinistryMemberController;
use App\Http\Controllers\MinistryController;
use App\Http\Controllers\LiveStreamSettingController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SuperadminRosterController;
use App\Http\Controllers\SuperadminScheduleController;
use App\Http\Controllers\InspirationContentController;
use App\Http\Controllers\InspirationMediaController;
use App\Http\Controllers\PublicEventController;
use App\Http\Controllers\PublicMinistryDirectoryController;
use App\Http\Controllers\EventRegistrationController;
use App\Http\Controllers\BootstrapController;
use App\Http\Controllers\CommerceMarketplaceController;
use App\Http\Controllers\PlatformOrganisationController;
use App\Http\Controllers\TenantAdminController;
use App\Http\Controllers\MediaAssetController;
use App\Http\Controllers\ApiBibleController;
use App\Http\Controllers\FinanceSlipController;

Route::prefix('api')->group(function () {
    Route::get('bootstrap', [BootstrapController::class, 'show']);
    Route::middleware('auth')->patch('preferences', [BootstrapController::class, 'preferences']);
    Route::middleware('auth')->get('media', [MediaAssetController::class, 'index']);
    Route::middleware('auth')->post('media', [MediaAssetController::class, 'store']);

    Route::middleware(['auth', 'organisation.admin'])->prefix('admin')->group(function () {
        Route::get('settings', [TenantAdminController::class, 'settings']);
        Route::patch('settings', [TenantAdminController::class, 'updateSettings']);
        Route::patch('modules', [TenantAdminController::class, 'updateModules']);
        Route::get('ministries', [TenantAdminController::class, 'ministries']);
        Route::post('ministries', [TenantAdminController::class, 'storeMinistry']);
        Route::patch('ministries/{ministry:slug}', [TenantAdminController::class, 'updateMinistry']);
        Route::get('users', [TenantAdminController::class, 'users']);
        Route::post('users', [TenantAdminController::class, 'storeUser']);
        Route::post('users/{membership}/invite', [TenantAdminController::class, 'invite'])->middleware('throttle:10,1');
        Route::patch('users/{membership}', [TenantAdminController::class, 'updateUser']);
        Route::delete('users/{membership}', [TenantAdminController::class, 'destroyUser']);
        Route::get('audits', [TenantAdminController::class, 'audits']);
        Route::get('media', [MediaAssetController::class, 'index']);
        Route::post('media', [MediaAssetController::class, 'store']);
        Route::delete('media/{mediaAsset}', [MediaAssetController::class, 'destroy']);
    });

    Route::middleware(['auth', 'module:finance'])->prefix('finance')->group(function () {
        Route::post('slips', [FinanceSlipController::class, 'store']);

        Route::middleware('finance.access')->group(function () {
            Route::get('config', [FinanceSlipController::class, 'config']);
            Route::get('slips', [FinanceSlipController::class, 'index']);
            Route::patch('slips/{financeSlip}', [FinanceSlipController::class, 'update']);
            Route::delete('slips/{financeSlip}', [FinanceSlipController::class, 'destroy']);
        });
    });

    Route::middleware('auth')->prefix('platform')->group(function () {
        Route::get('organisations', [PlatformOrganisationController::class, 'index']);
        Route::post('organisations', [PlatformOrganisationController::class, 'store']);
        Route::patch('organisations/{organisation}', [PlatformOrganisationController::class, 'update']);
        Route::delete('organisations/{organisation}', [PlatformOrganisationController::class, 'destroy']);
        Route::post('organisations/{organisation}/domains', [PlatformOrganisationController::class, 'domain']);
        Route::post('organisations/{organisation}/domains/{domain}/verify', [PlatformOrganisationController::class, 'verifyDomain']);
        Route::post('organisations/{organisation}/impersonate/{user}', [PlatformOrganisationController::class, 'impersonate']);
        Route::post('organisations/{organisation}/users', [PlatformOrganisationController::class, 'storeUser']);
        Route::post('organisations/{organisation}/users/{membership}/invite', [PlatformOrganisationController::class, 'invite'])->middleware('throttle:10,1');
        Route::patch('organisations/{organisation}/users/{membership}', [PlatformOrganisationController::class, 'updateUser']);
        Route::delete('organisations/{organisation}/users/{membership}', [PlatformOrganisationController::class, 'destroyUser']);
        Route::post('impersonation/stop', [PlatformOrganisationController::class, 'stopImpersonating']);
        Route::get('audits', [PlatformOrganisationController::class, 'audits']);
    });

    Route::get('ministries', [MinistryController::class, 'tenantIndex'])->middleware('module:public_ministries');
    Route::get('ministries/{ministry:slug}', [MinistryController::class, 'tenantShow'])->middleware('module:public_ministries');
    Route::get('public/ministry-directory', [PublicMinistryDirectoryController::class, 'index']);
    Route::get('commerce/marketplace', [CommerceMarketplaceController::class, 'index'])->middleware('module:commerce');
    Route::prefix('bible')->group(function () {
        Route::get('bibles', [ApiBibleController::class, 'bibles'])->middleware('throttle:60,1');
        Route::get('bibles/{bible}/books', [ApiBibleController::class, 'books'])->middleware('throttle:60,1');
        Route::get('bibles/{bible}/books/{book}/chapters', [ApiBibleController::class, 'chapters'])->middleware('throttle:60,1');
        Route::get('bibles/{bible}/chapters/{chapter}', [ApiBibleController::class, 'chapter'])->middleware('throttle:120,1');
        Route::get('bibles/{bible}/passage', [ApiBibleController::class, 'passage'])->middleware('throttle:120,1');
    });
});

Route::get('/media/{mediaAsset}', [MediaAssetController::class, 'show'])->name('media.assets.show');

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login.post');
    Route::post('forgot-password', [\App\Http\Controllers\PasswordController::class, 'forgot'])->middleware('throttle:5,1');
    Route::post('reset-password', [\App\Http\Controllers\PasswordController::class, 'reset'])->middleware('throttle:10,1');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('me', [AuthController::class, 'me']);
});

Route::prefix('brackenfell')->group(function () {
    Route::get('ministries', [MinistryController::class, 'index'])->defaults('entity', 'brackenfell');
    Route::get('ministries/{ministry:slug}', [MinistryController::class, 'show'])
        ->defaults('entity', 'brackenfell');

    Route::middleware('auth')->group(function () {
        Route::get('stream-settings', [LiveStreamSettingController::class, 'show'])->middleware('module:livestreaming');
        Route::patch('stream-settings', [LiveStreamSettingController::class, 'update'])->middleware('module:livestreaming');

        Route::get('roster', [SuperadminRosterController::class, 'index']);
        Route::get('schedules', [SuperadminScheduleController::class, 'index']);

        Route::get('ministries/{ministry:slug}/members', [MinistryMemberController::class, 'index'])
            ->middleware('ministry.role:admin');
        Route::get('ministries/{ministry:slug}/members/available', [MinistryMemberController::class, 'available'])
            ->middleware('ministry.role:admin');
        Route::get('ministries/{ministry:slug}/members/export', [MinistryMemberController::class, 'export'])
            ->middleware('ministry.role:admin');
        Route::post('ministries/{ministry:slug}/members/import', [MinistryMemberController::class, 'import'])
            ->middleware('ministry.role:admin');
        Route::post('ministries/{ministry:slug}/members', [MinistryMemberController::class, 'store'])
            ->middleware('ministry.role:admin');
        Route::post('ministries/{ministry:slug}/members/register', [MinistryMemberController::class, 'register'])
            ->middleware('ministry.role:admin');
        Route::patch('ministries/{ministry:slug}/members/{user}', [MinistryMemberController::class, 'update'])
            ->middleware('ministry.role:admin');
        Route::delete('ministries/{ministry:slug}/members/{user}', [MinistryMemberController::class, 'destroy'])
            ->middleware('ministry.role:admin');

        Route::get('ministries/{ministry:slug}/schedules', [ScheduleController::class, 'index'])
            ->middleware('ministry.role:user');
        Route::get('ministries/{ministry:slug}/schedules/audits', [ScheduleController::class, 'audits'])
            ->middleware('ministry.role:leader');
        Route::post('ministries/{ministry:slug}/schedules', [ScheduleController::class, 'store'])
            ->middleware('ministry.role:member');
        Route::patch('ministries/{ministry:slug}/schedules/{schedule}', [ScheduleController::class, 'update'])
            ->middleware('ministry.role:member');
        Route::delete('ministries/{ministry:slug}/schedules/{schedule}', [ScheduleController::class, 'destroy'])
            ->middleware('ministry.role:member');

        Route::get('ministries/{ministry:slug}/kanban/cards', [KanbanCardController::class, 'index'])
            ->middleware('ministry.role:user');
        Route::post('ministries/{ministry:slug}/kanban/cards', [KanbanCardController::class, 'store'])
            ->middleware('ministry.role:admin');
        Route::patch('ministries/{ministry:slug}/kanban/cards/{card}', [KanbanCardController::class, 'update'])
            ->middleware('ministry.role:admin');
        Route::post('ministries/{ministry:slug}/kanban/cards/{card}/comments', [KanbanCommentController::class, 'store'])
            ->middleware('ministry.role:user');

        Route::get('ministries/{ministry:slug}/announcements', [AnnouncementController::class, 'index'])
            ->middleware('ministry.role:user');
        Route::post('ministries/{ministry:slug}/announcements', [AnnouncementController::class, 'store'])
            ->middleware('ministry.role:leader');
        Route::patch('ministries/{ministry:slug}/announcements/{announcement}', [AnnouncementController::class, 'update'])
            ->middleware('ministry.role:leader');
        Route::delete('ministries/{ministry:slug}/announcements/{announcement}', [AnnouncementController::class, 'destroy'])
            ->middleware('ministry.role:leader');
    });
});

Route::get('/live-settings', [LiveStreamSettingController::class, 'publicShow'])->middleware('module:livestreaming');
Route::view('/login', 'welcome')->name('login');
Route::get('/reset-password/{token}', fn () => response()->view('welcome')->header('Referrer-Policy', 'no-referrer')->header('Cache-Control', 'no-store'))->name('password.reset');
Route::view('/shop', 'welcome')->name('voca.shop');
Route::redirect('/commerce', '/commerce/voca/shop/search')->name('voca.commerce');
Route::get('/events', [PublicEventController::class, 'index'])->middleware('module:events');
Route::post('/events/register', [EventRegistrationController::class, 'store'])->middleware(['module:events', 'throttle:10,1']);
Route::get('/events/tickets/{token}', [EventRegistrationController::class, 'ticket'])->middleware(['module:events', 'throttle:30,1']);
Route::middleware(['auth', 'module:events'])->prefix('event-portal')->group(function () {
    Route::get('registrations', [EventRegistrationController::class, 'index']);
    Route::post('check-in', [EventRegistrationController::class, 'checkIn'])->middleware('throttle:120,1');
});

Route::prefix('inspiration')->middleware('module:inspiration')->group(function () {
    Route::get('content', [InspirationContentController::class, 'publicIndex']);
    Route::get('content/{content:slug}', [InspirationContentController::class, 'show']);
    Route::get('media/{media}', [InspirationMediaController::class, 'show'])->name('inspiration.media');

    Route::middleware('auth')->group(function () {
        Route::get('member/content', [InspirationContentController::class, 'memberIndex']);
        Route::get('admin/content', [InspirationContentController::class, 'adminIndex']);
        Route::post('admin/content', [InspirationContentController::class, 'store']);
        Route::post('admin/content/{content}', [InspirationContentController::class, 'update']);
        Route::post('admin/content/{content}/duplicate', [InspirationContentController::class, 'duplicate']);
        Route::post('admin/content/{content}/{action}', [InspirationContentController::class, 'transition'])
            ->whereIn('action', ['publish', 'unpublish', 'archive']);
        Route::delete('admin/content/{content}', [InspirationContentController::class, 'destroy']);
    });
});

Route::view('/', 'welcome');
Route::view('/{any}', 'welcome')->where('any', '.*');
