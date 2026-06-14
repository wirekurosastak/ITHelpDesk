<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\LookupController;
use App\Http\Controllers\SystemStatusController;
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);

    Route::middleware('auth:api')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::middleware('auth:api')->group(function (): void {
    Route::get('categories', [LookupController::class, 'categories']);
    Route::get('tags', [LookupController::class, 'tags']);
    Route::get('status', SystemStatusController::class);

    Route::apiResource('tickets', TicketController::class)->except('destroy');
    Route::delete('tickets/{ticket}', [TicketController::class, 'destroy'])->middleware('role:admin');

    Route::post('tickets/{ticket}/attachments', [AttachmentController::class, 'store']);
    Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download']);
});
