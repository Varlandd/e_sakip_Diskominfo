<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PohonKinerjaController;
use App\Http\Controllers\Api\RenstraController;
use App\Http\Controllers\Api\CapaianController;

Route::get('/test', function () {
    return response()->json([
        'success' => true,
        'message' => 'API E-SAKIP berhasil!'
    ]);
});

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Capaian Kinerja
    Route::get('/capaian', [CapaianController::class, 'index']);
    Route::post('/capaian', [CapaianController::class, 'store']);
    Route::get('/capaian/summary', [CapaianController::class, 'summary']);
});

/*
|--------------------------------------------------------------------------
| Pohon Kinerja
| - Read: Dilihat & diekspor oleh semua role
| - Action (Upload, Node, Arsip, Restore, Duplikasi): Hanya Admin
|--------------------------------------------------------------------------
*/
Route::get('/pohon-kinerja/tree', [PohonKinerjaController::class, 'getTree']);
Route::get('/pohon-kinerja/summary', [PohonKinerjaController::class, 'summary']);
Route::get('/pohon-kinerja/years', [PohonKinerjaController::class, 'getYears']);
Route::get('/pohon-kinerja/check-year/{tahun}', [PohonKinerjaController::class, 'checkYearExists']);
Route::get('/pohon-kinerja/archived', [PohonKinerjaController::class, 'getArchived']);
Route::get('/pohon-kinerja/recent-activity', [PohonKinerjaController::class, 'recentActivity']);

// Aksi Pohon Kinerja (Khusus Admin)
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::post('/pohon-kinerja/upload', [PohonKinerjaController::class, 'upload']);
    Route::post('/pohon-kinerja/archive/{id}', [PohonKinerjaController::class, 'archive']);
    Route::post('/pohon-kinerja/restore/{id}', [PohonKinerjaController::class, 'restore']);
    Route::post('/pohon-kinerja/duplicate', [PohonKinerjaController::class, 'duplicate']);
    Route::post('/pohon-kinerja/node', [PohonKinerjaController::class, 'createNode']);
    Route::put('/pohon-kinerja/node/{level}/{id}', [PohonKinerjaController::class, 'updateNode']);
    Route::delete('/pohon-kinerja/node/{level}/{id}', [PohonKinerjaController::class, 'deleteNode']);
});

/*
|--------------------------------------------------------------------------
| Renstra
|--------------------------------------------------------------------------
*/
Route::get('/renstra', [RenstraController::class, 'index']);
Route::match(['put', 'patch'], '/renstra/nodes/{level}/{id}', [RenstraController::class, 'updateNode']);
Route::delete('/renstra/nodes/{level}/{id}', [RenstraController::class, 'deleteNode']);
