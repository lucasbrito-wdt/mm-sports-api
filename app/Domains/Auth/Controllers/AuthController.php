<?php

namespace App\Domains\Auth\Controllers;

use App\Domains\Auth\Requests\ForgotPasswordRequest;
use App\Domains\Auth\Requests\ResetPasswordRequest;
use App\Domains\Auth\Requests\LoginRequest;
use App\Domains\Auth\Requests\RegisterRequest;
use App\Domains\Auth\Services\AuthService;
use App\Domains\Shared\Controller\BaseController;

class AuthController extends BaseController
{
    public function __construct(private readonly AuthService $authService)
    {
        parent::__construct();

        $this->setService($this->authService);
    }

    /**
     * Get the authenticated User.
     *
     * @param LoginRequest $request
     */
    public function login(LoginRequest $request)
    {
        return $this->authService->login($request);
    }


    /**
     * Create the authenticated User.
     *
     * @param RegisterRequest $request
     */
    public function register(RegisterRequest $request)
    {
        return $this->authService->register($request);
    }

    public function forgotPassword(ForgotPasswordRequest $payload)
    {
        return $this->authService->forgotPassword($payload);
    }

    public function resetPassword(ResetPasswordRequest $payload)
    {
        return $this->authService->resetPassword($payload);
    }

    /**
     * Get the authenticated User.
     *
     */
    public function profile()
    {
        return $this->authService->profile();
    }

    /**
     * Log the user out (Invalidate the token).
     */
    public function logout()
    {
        return $this->authService->logout();
    }
}
