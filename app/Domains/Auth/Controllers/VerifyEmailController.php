<?php

namespace App\Domains\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Auth\Models\User;
use Illuminate\Auth\Events\Verified;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(string $email)
    {
        $user = User::where('email', $email)->first();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'E-mail já verificado'], 400);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json(['message' => 'E-mail verificado com sucesso']);
    }
}
