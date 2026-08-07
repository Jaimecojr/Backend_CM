<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

Route::post('/login', function (Request $request) {
    $request->validate([
        'user' => 'required',
        'password' => 'required',
    ]);

    if (!Auth::attempt($request->only('user', 'password'))) {
        return response()->json(['message' => 'Credenciales inválidas'], 422);
    }

    $request->session()->regenerate();

    // "auth_hint" NO es la fuente de verdad de autenticación (eso lo sigue
    // validando /user vía auth:sanctum) — solo le permite al middleware de
    // Next.js (proxy.ts) redirigir al login en el edge sin round-trip al
    // backend cuando claramente no hay sesión. A diferencia de XSRF-TOKEN,
    // esta cookie solo existe si hubo un login exitoso.
    return response()->json(['message' => 'Autenticado'])->cookie(
        'auth_hint',
        '1',
        config('session.lifetime'),
        config('session.path'),
        config('session.domain'),
        config('session.secure'),
        false, // httpOnly=false: no guarda nada sensible, solo es una bandera de presencia
        false,
        config('session.same_site')
    );
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return response()->json(['message' => 'Logout OK'])->withCookie(
        Cookie::forget('auth_hint', config('session.path'), config('session.domain'))
    );
});
