<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\LookupController;
use App\Http\Controllers\SystemStatusController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:3,1');

    Route::middleware('auth:api')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::post('change-password', [AuthController::class, 'changePassword']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('heartbeat', [AuthController::class, 'heartbeat']);
    });
});

Route::middleware('auth:api')->group(function (): void {
    Route::get('categories', [LookupController::class, 'categories']);
    Route::get('tags', [LookupController::class, 'tags']);

    Route::apiResource('tickets', TicketController::class)->except('destroy');

    Route::post('tickets/{ticket}/attachments', [AttachmentController::class, 'store']);
    Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download']);

    Route::middleware('role:admin')->group(function (): void {
        Route::get('status', SystemStatusController::class);
        Route::post('status/clear-cache', [SystemStatusController::class, 'clearCache']);

        Route::delete('tickets/{ticket}', [TicketController::class, 'destroy']);

        Route::post('users/suspend-all', [UserController::class, 'suspendAll']);
        Route::post('users/logout-all', [UserController::class, 'logoutAll']);
        Route::patch('users/{user}/approve', [UserController::class, 'approve']);
        Route::post('users/{user}/force-logout', [UserController::class, 'forceLogout']);
        Route::post('users/{user}/suspend', [UserController::class, 'suspend']);
        Route::apiResource('users', UserController::class)->except(['create', 'edit']);
    });
});
