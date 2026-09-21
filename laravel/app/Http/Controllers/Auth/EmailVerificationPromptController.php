<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationPromptController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->is_admin) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->role === 'vendor' || $user->shop()->exists()) {
            return redirect()->route('vendor.dashboard');
        }

        if (in_array($user->role, ['logistique', 'logistics'], true)) {
            return redirect()->route('logistics.dashboard');
        }

        return redirect()->route('client.dashboard');
    }
}
