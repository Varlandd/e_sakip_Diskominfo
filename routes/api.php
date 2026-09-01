<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PohonKinerjaController;

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
Route::delete('/pohon-kinerja/node/{level}/{id}', [PohonKinerjaController::class, 'deleteNode']);
