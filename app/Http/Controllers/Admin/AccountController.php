<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\PasskeyDeviceName;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Laravel\Passkeys\Actions\DeletePasskey;
use Laravel\Passkeys\Passkey;

/**
 * 「帳號與安全」(JYU-566): the admin's own account — for now the passkeys used to
 * sign in. Adding and deleting a passkey sit behind a fresh identity check
 * (see routes/web.php); the passkey ceremonies themselves are laravel/passkeys'.
 */
class AccountController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();

        return view('admin.account.show', [
            'user' => $user,
            'passkeys' => $user->passkeys()->oldest()->get(),
            'defaultPasskeyName' => PasskeyDeviceName::fromUserAgent($request->userAgent()),
        ]);
    }

    public function update(Request $request, Passkey $passkey): RedirectResponse
    {
        $this->ensureOwned($request, $passkey);

        $data = $request->validate(['name' => ['required', 'string', 'max:60']], [], ['name' => '名稱']);
        $passkey->update(['name' => $data['name']]);

        return redirect()->route('admin.account.show')->with('success', "已改名為「{$passkey->name}」");
    }

    public function destroy(Request $request, Passkey $passkey, DeletePasskey $delete): RedirectResponse
    {
        $this->ensureOwned($request, $passkey);

        $delete($request->user(), $passkey);

        return redirect()->route('admin.account.show')->with('success', "已刪除 passkey「{$passkey->name}」，之後不能再用它登入");
    }

    private function ensureOwned(Request $request, Passkey $passkey): void
    {
        abort_unless((string) $passkey->user_id === (string) $request->user()->getKey(), 404);
    }
}
