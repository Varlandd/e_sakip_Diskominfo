<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PohonKinerjaController;
use App\Http\Controllers\Api\RenstraController;

Route::get('/test', function () {
    return response()->json([
        'success' => true,
        'message' => 'API E-SAKIP berhasil!'
    ]);
});

Route::post(
    '/pohon-kinerja/upload',
    [PohonKinerjaController::class, 'upload']
);

Route::get(
    '/pohon-kinerja/tree',
    [PohonKinerjaController::class, 'getTree']
);

Route::get(
    '/pohon-kinerja/years',
    [PohonKinerjaController::class, 'getYears']
);

Route::get(
    '/pohon-kinerja/check-year/{tahun}',
    [PohonKinerjaController::class, 'checkYearExists']
);

Route::get(
    '/pohon-kinerja/archived',
    [PohonKinerjaController::class, 'getArchived']
);

Route::post(
    '/pohon-kinerja/archive/{id}',
    [PohonKinerjaController::class, 'archive']
);

Route::post(
    '/pohon-kinerja/restore/{id}',
    [PohonKinerjaController::class, 'restore']
);

Route::post(
    '/pohon-kinerja/duplicate',
    [PohonKinerjaController::class, 'duplicate']
);
Route::post(
    '/pohon-kinerja/node',
    [PohonKinerjaController::class, 'createNode']
);

Route::put(
    '/pohon-kinerja/node/{level}/{id}',
    [PohonKinerjaController::class, 'updateNode']
);

Route::get('/renstra', [RenstraController::class, 'index']);
Route::match(['put', 'patch'], '/renstra/nodes/{level}/{id}', [RenstraController::class, 'updateNode']);
Route::delete('/renstra/nodes/{level}/{id}', [RenstraController::class, 'deleteNode']);
Route::delete('/pohon-kinerja/node/{level}/{id}', [PohonKinerjaController::class, 'deleteNode']);
