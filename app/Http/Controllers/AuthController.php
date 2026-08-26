<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'user'     => 'required',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('user', 'password'))) {
            return response()->json(['message' => 'Credenciales inválidas'], 422);
        }

        $request->session()->regenerate();

        // "auth_hint" is NOT the source of truth for authentication (that's still
        // validated by /user via auth:sanctum) — it only lets the Next.js
        // middleware (proxy.ts) redirect to login at the edge without a round-trip
        // to the backend when there's clearly no session. Unlike XSRF-TOKEN,
        // this cookie only exists if there was a successful login.
        return response()->json(['message' => 'Autenticado'])->cookie(
            'auth_hint',
            '1',
            config('session.lifetime'),
            config('session.path'),
            config('session.domain'),
            config('session.secure'),
            false, // httpOnly=false: doesn't store anything sensitive, it's just a presence flag
            false,
            config('session.same_site')
        );
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logout OK'])->withCookie(
            Cookie::forget('auth_hint', config('session.path'), config('session.domain'))
        );
    }
}
