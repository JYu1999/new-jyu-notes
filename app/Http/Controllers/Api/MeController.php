<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        return response()->json([
            'user' => $request->user()->only(['id', 'name', 'email']),
            'abilities' => $token->abilities ?? [],
            'expires_at' => $token->expires_at,
        ]);
    }
}
