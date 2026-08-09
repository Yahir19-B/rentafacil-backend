<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\User;
use Illuminate\Auth\Events\Verified;

Route::get('/email/verify/{id}/{hash}', function (
    Request $request,
    string $id,
    string $hash
) {
    $frontendUrl = rtrim(
        env('FRONTEND_URL', 'http://localhost:8100'),
        '/'
    );

    $user = User::find($id);

    if (! $user) {
        return redirect(
            $frontendUrl . '/login?verification=user-not-found'
        );
    }

    if (! hash_equals(
        sha1($user->getEmailForVerification()),
        $hash
    )) {
        return redirect(
            $frontendUrl . '/login?verification=invalid'
        );
    }

    if (! $user->hasVerifiedEmail()) {
        $user->markEmailAsVerified();

        event(new Verified($user));
    }

    return redirect(
        $frontendUrl . '/login?verification=success'
    );
})
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');