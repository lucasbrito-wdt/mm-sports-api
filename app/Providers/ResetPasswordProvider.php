<?php

namespace App\Providers;

use Domains\Auth\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class ResetPasswordProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        ResetPassword::createUrlUsing(function (User $user, string $token) {
            return env('CDF_FRONT_END_URL')."/auth/redefinir-senha?email={$user->email}&token=$token";
        });
    }
}
