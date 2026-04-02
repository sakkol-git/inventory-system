<?php

use App\Modules\Inventory\Controllers\AchievementController;
use App\Modules\Inventory\Controllers\BorrowRecordController;
use App\Modules\Inventory\Controllers\ChemicalBatchController;
use App\Modules\Inventory\Controllers\ChemicalController;
use App\Modules\Inventory\Controllers\ChemicalUsageLogController;
use App\Modules\Inventory\Controllers\DashboardController;
use App\Modules\Inventory\Controllers\EquipmentController;
use App\Modules\Inventory\Controllers\MaintenanceRecordController;
use App\Modules\Inventory\Controllers\PlantSampleController;
use App\Modules\Inventory\Controllers\PlantSpeciesController;
use App\Modules\Inventory\Controllers\PlantStockController;
use App\Modules\Inventory\Controllers\PlantVarietyController;
use App\Modules\Inventory\Controllers\ProfileController;
use App\Modules\Inventory\Controllers\ReportController;
use App\Modules\Inventory\Controllers\TransactionController;
use App\Modules\Inventory\Controllers\UserDocumentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function () {

    // // ─── Dashboard ───────────────────────────────────────────────────────────
    // Route::get('dashboard', DashboardController::class)->name('dashboard');

    // // ─── Profile Management ──────────────────────────────────────────────────
    // Route::prefix('profile')->name('profile.')->group(function () {
    //     Route::get('/', [ProfileController::class, 'show'])->name('show');
    //     Route::put('/', [ProfileController::class, 'update'])->name('update');
    //     Route::get('contributions', [ProfileController::class, 'contributions'])->name('contributions');
    //     Route::get('achievements', [ProfileController::class, 'achievements'])->name('achievements');
    //     Route::get('activity', [ProfileController::class, 'activity'])->name('activity');
    // });

    // ─── Plant Module ────────────────────────────────────────────────────────
    Route::apiResource('plant-species', PlantSpeciesController::class)
        ->parameters(['plant-species' => 'plantSpecies']);

    Route::apiResource('plant-varieties', PlantVarietyController::class)
        ->parameters(['plant-varieties' => 'plantVariety']);

    Route::apiResource('plant-samples', PlantSampleController::class)
        ->parameters(['plant-samples' => 'plantSample']);

    Route::apiResource('plant-stocks', PlantStockController::class)
        ->parameters(['plant-stocks' => 'plantStock']);

//     // ─── Chemical Module ─────────────────────────────────────────────────────
    Route::apiResource('chemicals', ChemicalController::class);
//     Route::apiResource('chemical-batches', ChemicalBatchController::class);
//     Route::apiResource('chemical-usage-logs', ChemicalUsageLogController::class)
//         ->only(['index', 'store', 'show'])
//         ->parameters(['chemical-usage-logs' => 'chemicalUsageLog']);

//     // ─── Equipment Module ────────────────────────────────────────────────────
    Route::apiResource('equipment', EquipmentController::class);
//     Route::apiResource('maintenance-records', MaintenanceRecordController::class);

//     // ─── Borrow Module ───────────────────────────────────────────────────────
//     Route::get('borrow-records/overdue', [BorrowRecordController::class, 'overdue'])
//         ->name('borrow-records.overdue');
//     Route::get('borrow-records/pending', [BorrowRecordController::class, 'pending'])
//         ->name('borrow-records.pending');
//     Route::post('borrow-records/{borrowRecord}/return', [BorrowRecordController::class, 'returnItem'])
//         ->name('borrow-records.return');
//     Route::post('borrow-records/{borrowRecord}/approve', [BorrowRecordController::class, 'approve'])
//         ->name('borrow-records.approve');
//     Route::post('borrow-records/{borrowRecord}/reject', [BorrowRecordController::class, 'reject'])
//         ->name('borrow-records.reject');

//     Route::apiResource('borrow-records', BorrowRecordController::class)
//         ->only(['index', 'store', 'show']);

//     // ─── Operations Module ───────────────────────────────────────────────────
    Route::apiResource('transactions', TransactionController::class)
        ->only(['index', 'show']);

//     // ─── Achievements ────────────────────────────────────────────────────────
    Route::apiResource('achievements', AchievementController::class);
    Route::post('achievements/{achievement}/assign/{user}', [AchievementController::class, 'assign'])
        ->name('achievements.assign');
    Route::delete('achievements/{achievement}/revoke/{user}', [AchievementController::class, 'revoke'])
        ->name('achievements.revoke');

//     // ─── User Documents ──────────────────────────────────────────────────────
//     Route::apiResource('user-documents', UserDocumentController::class)
//         ->only(['index', 'store', 'show', 'destroy'])
//         ->parameters(['user-documents' => 'userDocument']);
//     Route::get('user-documents/{userDocument}/download', [UserDocumentController::class, 'download'])
//         ->name('user-documents.download');

//     // ─── Reports & Export ────────────────────────────────────────────────────
//     Route::prefix('reports')->name('reports.')->group(function () {
//         Route::get('inventory', [ReportController::class, 'inventory'])->name('inventory');
//         Route::get('chemical-usage', [ReportController::class, 'chemicalUsage'])->name('chemical-usage');
//         Route::get('expired-items', [ReportController::class, 'expiredItems'])->name('expired-items');
//         Route::get('borrowed-items', [ReportController::class, 'borrowedItems'])->name('borrowed-items');
//         Route::get('user-activity', [ReportController::class, 'userActivity'])->name('user-activity');
//         Route::get('{type}/export', [ReportController::class, 'export'])->name('export');
//     });
});
