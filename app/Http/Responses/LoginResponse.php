<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements \Laravel\Fortify\Contracts\TwoFactorLoginResponse, LoginResponseContract
{
    public function toResponse(mixed $request): \Symfony\Component\HttpFoundation\Response
    {
        if (! $request->user()->is_active) {
            $request->session()->forget('url.intended');

            return $request->wantsJson()
                ? response()->json(['redirect' => route('registration.pending')])
                : redirect()->route('registration.pending');
        }

        return $request->wantsJson() ? response()->json(['two_factor' => false]) : redirect()->intended(route('dashboard'));
    }
}
