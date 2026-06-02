<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApiToken\StoreRequest;
use App\Services\ApiTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ApiTokenController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.tokens.index', [
            'tokens' => $request->user()->tokens()->latest()->get(),
        ]);
    }

    public function store(StoreRequest $request, ApiTokenService $service): RedirectResponse
    {
        $data = $request->validated();

        $expiresAt = match ($data['expires_in']) {
            '1h' => now()->addHour(),
            '8h' => now()->addHours(8),
            '24h' => now()->addDay(),
            '7d' => now()->addWeek(),
            'custom' => Carbon::parse($data['expires_at']),
        };

        $new = $service->create($request->user(), $data['name'], $data['abilities'], $expiresAt);

        return redirect()
            ->route('admin.tokens.index')
            ->with('newToken', $new->plainTextToken)
            ->with('newTokenName', $data['name']);
    }

    public function destroy(Request $request, int $id, ApiTokenService $service): RedirectResponse
    {
        $service->revoke($request->user(), $id);

        return redirect()->route('admin.tokens.index')->with('success', 'Token 已撤銷');
    }
}
