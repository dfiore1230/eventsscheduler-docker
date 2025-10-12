<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/sign_up', function () {
        try {
            if (function_exists('view') && view()->exists('auth.register')) {
                return view('auth.register');
            }
        } catch (\Throwable $e) {
            Log::warning('Sign up override failed rendering auth.register view; falling back to controller.', [
                'exception' => $e,
            ]);
        }

        // Fallback: call the app's controller if the view isn't present
        if (class_exists(\App\Http\Controllers\Auth\RegisteredUserController::class)) {
            try {
                return app()->call([
                    app(\App\Http\Controllers\Auth\RegisteredUserController::class),
                    'create',
                ]);
            } catch (\Throwable $e) {
                Log::error('Sign up override failed calling RegisteredUserController::create.', [
                    'exception' => $e,
                ]);
                throw $e;
            }
        }

        abort(404);
    })->name('sign_up');
});

