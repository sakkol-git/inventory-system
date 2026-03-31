<?php

use App\Modules\Core\Controllers\ActivityLogController;
use App\Modules\Core\Controllers\AuthController;
use App\Modules\Core\Controllers\NotificationController;
use App\Modules\Core\Controllers\PermissionController;
use App\Modules\Core\Controllers\RoleController;
use App\Modules\Core\Controllers\SearchController;
use App\Modules\Core\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// ─── Authentication Routes (public) ─────────────────────────────────────────
Route::prefix('auth')->group( function (){
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:api')->group(function (){
        Route::get('profile', [AuthController::class, 'profile']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
    });
});

// // ─── All protected routes require authentication ─────────────────────────────
Route::middleware('auth:api')->group(function () {

//     // ─── Global Search (UX-04) ───────────────────────────────────────────────
//     Route::get('search', SearchController::class)->name('search');

//     // ─── Notifications (UX-05) ───────────────────────────────────────────────
//     Route::prefix('notifications')->name('notifications.')->group(function () {
//         Route::get('/', [NotificationController::class, 'index'])->name('index');
//         Route::get('unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
//         Route::post('{id}/read', [NotificationController::class, 'markAsRead'])->name('read');
//         Route::post('read-all', [NotificationController::class, 'markAllAsRead'])->name('read-all');
//         Route::delete('{id}', [NotificationController::class, 'destroy'])->name('destroy');
//     });

//     // ─── Activity Logs (MF-01) ───────────────────────────────────────────────
//     Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
//     Route::get('activity-logs/{id}', [ActivityLogController::class, 'show'])->name('activity-logs.show');

//     // ─── User Management (admin only) ────────────────────────────────────────
      Route::apiResource('users', UserController::class)->middleware( 'admin');

//     // ─── Role & Permission Management (admin only) ───────────────────────────
      Route::middleware('admin')->group(function () {
        Route::apiResource('roles', RoleController::class)->except(['create', 'edit']);

//         Route::get('roles/{id}/permissions', [RoleController::class, 'permissions']);
//         Route::post('roles/{id}/permissions', [RoleController::class, 'assignPermission']);
//         Route::delete('roles/{id}/permissions/{permission}', [RoleController::class, 'revokePermission']);

//         Route::get('roles/{id}/users', [RoleController::class, 'users']);
//         Route::post('roles/{id}/users', [RoleController::class, 'assignToUser']);
//         Route::delete('roles/{id}/users/{userId}', [RoleController::class, 'revokeFromUser']);

//         Route::apiResource('permissions', PermissionController::class)->except(['create', 'edit']);
    });
});
