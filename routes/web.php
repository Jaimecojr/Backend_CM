<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

Route::post('/login', function (Request $request) {
    $request->validate([
        'user' => 'required',
        'password' => 'required',
    ]);

    if (!Auth::attempt($request->only('user', 'password'))) {
        return response()->json(['message' => 'Credenciales inválidas'], 422);
    }

    $request->session()->regenerate();
    return response()->json(['message' => 'Autenticado']);
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return response()->json(['message' => 'Logout OK']);
});
