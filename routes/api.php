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

Route::put(
    '/pohon-kinerja/node/{level}/{id}',
    [PohonKinerjaController::class, 'updateNode']
);