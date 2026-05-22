<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

class Md5UserProvider extends EloquentUserProvider
{
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        $stored = $user->getAuthPassword();

        // Contraseñas nuevas usan bcrypt (empieza con $2y$); las del sistema anterior son MD5
        if (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$')) {
            return $this->hasher->check($credentials['password'], $stored);
        }

        return md5($credentials['password']) === $stored;
    }
}
