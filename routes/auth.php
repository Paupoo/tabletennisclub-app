<?php

declare(strict_types=1);

use App\Http\Controllers\ClubAdmin\Users\Auth\AuthenticatedSessionController;
use App\Http\Controllers\ClubAdmin\Users\Auth\ConfirmablePasswordController;
use App\Http\Controllers\ClubAdmin\Users\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\ClubAdmin\Users\Auth\EmailVerificationPromptController;
use App\Http\Controllers\ClubAdmin\Users\Auth\NewPasswordController;
use App\Http\Controllers\ClubAdmin\Users\Auth\PasswordController;
use App\Http\Controllers\ClubAdmin\Users\Auth\PasswordResetLinkController;
use App\Http\Controllers\ClubAdmin\Users\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    // The broker already limits one address to one mail a minute, which does
    // nothing against a caller walking a list of addresses: the reply differs
    // for a known and an unknown one, and every hit mails a real member.
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
