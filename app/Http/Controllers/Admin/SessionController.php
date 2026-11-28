<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    /**
     * The session's current CSRF token (JYU-565). When the session has expired
     * while an editor is open, a valid 「記住我」cookie has already logged the
     * admin back in by the time this runs; without one the auth middleware
     * answers 401 and the editor shows its logged-out state.
     */
    public function csrfToken(Request $request): JsonResponse
    {
        return response()
            ->json(['token' => $request->session()->token()])
            ->header('Cache-Control', 'no-store, private');
    }
}
