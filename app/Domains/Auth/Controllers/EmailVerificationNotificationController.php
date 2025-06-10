<?php

namespace App\Domains\Auth\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     */
    public function store(Request $request)
    {
        // Verifica se o usuário ainda não está verificado
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'E-mail já verificado'], 400);
        }

        // Envia o e-mail de verificação novamente
        $request->user()->sendEmailVerificationNotification();

        return response()->json(['message' => 'E-mail de verificação reenviado']);
    }
}
