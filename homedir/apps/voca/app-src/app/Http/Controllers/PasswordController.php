<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordController extends Controller
{
    public function forgot(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $email = strtolower(trim($data['email']));
        $user = User::where('email', $email)->first();
        if ($user?->canAccessOrganisation($request->attributes->get('organisation')?->id)) {
            Password::sendResetLink(['email' => $email]);
        }

        return response()->json(['message' => 'If an active account matches that email, a password link has been sent.']);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'], 'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->numbers()],
        ]);
        $data['email'] = strtolower(trim($data['email']));
        $user = User::where('email', $data['email'])->first();
        if (! $user?->canAccessOrganisation($request->attributes->get('organisation')?->id)) {
            return response()->json(['message' => 'This password link is invalid or expired.'], 422);
        }
        $status = Password::reset($data, function ($user, $password) {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }
            event(new PasswordReset($user));
        });
        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['message' => 'This password link is invalid or expired.'], 422);
        }
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Password saved. Please sign in with your new password.']);
    }
}
