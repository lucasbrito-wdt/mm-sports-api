<?php

namespace App\Domains\Auth\Services;

use App\Domains\ACL\Enums\RoleEnum;
use App\Domains\Auth\Models\User;
use App\Domains\Auth\Requests\ForgotPasswordRequest;
use App\Domains\Auth\Requests\LoginRequest;
use App\Domains\Auth\Requests\RegisterRequest;
use App\Domains\Auth\Requests\ResetPasswordRequest;
use App\Domains\Shared\Services\BaseService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Str;
use Tymon\JWTAuth\Facades\JWTAuth;

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
            'phone' => $request->phone,
            'cpf' => $request->input('cpf'),
            'rg' => $request->input('rg'),
            'gender' => $request->input('gender'),
            'birthdate' => $request->input('birthdate'),
            'favorite_team' => $request->input('favorite_team'),
            'password' => Hash::make($request->password),
            'terms' => $request->terms,
        ]);

        $user->assignRole(RoleEnum::User->value);

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
        /** @var User|null $user */
        $user = auth('api')->user();

        return response()->json($user);
    }

    /**
     * Update the authenticated user's profile.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateProfile(array $data): JsonResponse
    {
        /** @var User $user */
        $user = auth('api')->user();

        $emailChanged = isset($data['email']) && $data['email'] !== $user->email;

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        // TODO: when `email` changes, dispatch a re-verification flow
        // (reset email_verified_at and resend VerifyEmail notification).
        // Out of scope for this endpoint until product decision.
        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        return response()->json($user->refresh());
    }

    /**
     * Refresh a token.
     *
     * @return JsonResponse
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
            Log::info('Logout realizado com token expirado/inválido: '.$e->getMessage());
        }
    }

    /**
     * Get the token array structure.
     *
     * @param  string  $token
     * @return JsonResponse
     */
    protected function respondWithToken($token)
    {
        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $this->getTTLInSeconds(),
        ];
    }

    protected function validateCredentials(LoginRequest $request)
    {
        $token = Auth::attempt($request->only('email', 'password'));

        if (! $token) {
            throw ValidationException::withMessages([
                'email' => ['As credenciais fornecidas estão incorretas.'],
            ]);
        }

        if (! Auth::user()->active) {
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
        } catch (\Exception) {
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
