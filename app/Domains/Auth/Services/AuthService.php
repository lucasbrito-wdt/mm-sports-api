<?php

namespace App\Domains\Auth\Services;

use Str;
use App\Domains\Auth\Models\User;
use Illuminate\Http\JsonResponse;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;
use App\Domains\Auth\Requests\LoginRequest;
use App\Domains\Shared\Services\BaseService;
use App\Domains\Auth\Requests\RegisterRequest;
use Illuminate\Validation\ValidationException;
use App\Domains\Auth\Requests\ResetPasswordRequest;
use App\Domains\Auth\Requests\ForgotPasswordRequest;

class AuthService extends BaseService
{
    private string $token;

    public function __construct(private User $user) {}

    /**
     * @throws \Exception
     */
    public function login(LoginRequest $request)
    {
        $this->token = $this->validateCredentials($request);
        $this->user = Auth::user();

        $ACL = collect($this->user->permissions())
            ->reduce(function ($ACL, $permission) {
                [$subject, $action] = explode(' ', $permission['slug']);
                $ACL['permissions'][] = [
                    'subject' => $subject,
                    'action' => $action,
                ];
                $ACL['subjects'][] = $action;

                return $ACL;
            }, []);

        return response()->json([
            'user' => $this->user,
            'authorization' => [
                'token' => $this->token,
                'type' => 'Bearer',
                'expires_in' => $this->getTTLInSeconds(),
                'subjects' => $ACL['subjects'],
                'permissions' => $ACL['permissions'],
            ],
        ]);
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->user->create([
            'name' => $request->name,
            'email' => $request->email,
            'celular' => $request->celular,
            'password' => Hash::make($request->password),
            'termos' => $request->termos,
        ]);

        if (! empty($request->roles)) {
            $user->assignRole($request->roles);
        }

        $this->user->sendEmailVerificationNotification();

        $loggedIn = $this->login(new LoginRequest([
            'email' => $request->email,
            'password' => $request->password,
        ]));

        return response()->json($loggedIn->getData());
    }

    public function forgotPassword(ForgotPasswordRequest $request): array
    {
        $message = [];
        $status = Password::sendResetLink(
            $request->only('email'),
        );

        if ($status == Password::RESET_THROTTLED) {
            $message['message'] = 'Tentativa de reinicialização acelerada.';
            $message['status'] = false;
        }
        if ($status == Password::INVALID_USER) {
            $message['message'] = 'Usuário não existe';
            $message['status'] = false;
        }

        return $message;
    }

    public function resetPassword(ResetPasswordRequest $request): array
    {
        $message = [];
        Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $user->save();

                $status = event(new PasswordReset($user));

                if ($status == 'passwords.reset') {
                    $message['message'] = 'Senha alterada com sucesso!';
                    $message['status'] = true;
                } else {
                    $message['message'] = 'Ocorreu um error ao alterar a senha!';
                    $message['status'] = false;
                }
            },
        );

        return $message;
    }

    public function profile(): JsonResponse
    {
        return response()->json(auth()->user());
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken(Auth::refresh());
    }


    public function logout()
    {
        try {
            Auth::logout();
        } catch (\Exception $e) {
            // Se o token já expirou ou é inválido, ainda consideramos logout bem-sucedido
            Log::info('Logout realizado com token expirado/inválido: ' . $e->getMessage());
        }
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $this->getTTLInSeconds()
        ];
    }

    protected function validateCredentials(LoginRequest $request)
    {
        $token = Auth::attempt($request->only('email', 'password'));

        if (!$token) {
            throw ValidationException::withMessages([
                'email' => ['As credenciais fornecidas estão incorretas.'],
            ]);
        }

        if (!Auth::user()->ativo) {
            throw ValidationException::withMessages([
                'email' => ['O seu usuário não está ativo. Por favor, entre em contato com o suporte para solicitar a ativação da sua conta.'],
            ]);
        }

        return $token;
    }

    /**
     * Get TTL in seconds with fallback
     */
    private function getTTLInSeconds(): int
    {
        try {
            return JWTAuth::factory()->getTTL() * 60;
        } catch (\Exception $e) {
            // Fallback para config direto
            return config('jwt.ttl', 60) * 60;
        }
    }

    protected function deletePreviousAccessTokensOnLogin(User $credentials): void
    {
        if (config('cdf.delete_previous_access_tokens_on_login', false)) {
            $credentials->tokens()->delete();
        }
    }
}
