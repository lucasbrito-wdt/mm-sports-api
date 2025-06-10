<?php

namespace App\Domains\Auth\Services;

use App\Domains\Auth\Requests\ForgotPasswordRequest;
use App\Domains\Auth\Requests\ResetPasswordRequest;
use App\Domains\Auth\Models\User;
use App\Domains\Auth\Requests\LoginRequest;
use App\Domains\Auth\Requests\RegisterRequest;
use App\Domains\Shared\Services\BaseService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Str;

class AuthService extends BaseService
{
    public function __construct(private User $user) {}

    /**
     * @throws \Exception
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $this->user = $this->validateCredentials($request);

        $this->deletePreviousAccessTokensOnLogin($this->user);

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

        $authorization = $this->respondWithToken($this->user, 'cdf-api-token', [
            'role' => $this->user->role['slug'],
            'permissions' => $ACL['permissions'] ?? [],
            'subjects' => array_unique($ACL['subjects'], SORT_REGULAR) ?? [],
        ]);

        auth()->setUser($this->user);

        return response()->json([
            'user' => $this->user,
            'authorization' => $authorization,
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

    public function logout(): JsonResponse
    {
        auth()->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Desconectado com sucesso.',
        ]);
    }

    /**
     * Get the token array structure.
     */
    protected function respondWithToken(User $user, $name, array $abilities = ['*']): array
    {
        $authorization = $user->createToken($name, $abilities);

        return [
            'token' => $authorization->plainTextToken,
            'abilities' => $authorization->accessToken->abilities,
        ];
    }

    protected function validateCredentials(LoginRequest $request): User
    {
        $credentials = $this->user->where('email', $request->email)->first();

        if (! $credentials || ! Hash::check($request->password, $credentials->password)) {
            throw ValidationException::withMessages([
                'email' => ['As credenciais fornecidas estão incorretas.'],
            ]);
        }

        return $credentials;
    }

    protected function deletePreviousAccessTokensOnLogin(User $credentials): void
    {
        if (config('cdf.delete_previous_access_tokens_on_login', false)) {
            $credentials->tokens()->delete();
        }
    }
}
