<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\RegisterResponse;

class RegistrationResponse implements RegisterResponse
{
    public function toResponse(mixed $request): \Symfony\Component\HttpFoundation\Response
    {
        return $request->wantsJson()
            ? response()->json(['redirect' => route('registration.pending')], 201)
            : redirect()->route('registration.pending');
    }
}
