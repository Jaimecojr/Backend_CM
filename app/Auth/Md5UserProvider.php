<?php

declare(strict_types=1);

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

class Md5UserProvider extends EloquentUserProvider
{
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        $stored = (string) $user->getAuthPassword();

        // New passwords use bcrypt (starts with $2y$); passwords from the legacy system are MD5
        if (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$')) {
            return $this->hasher->check($credentials['password'], $stored);
        }

        // Constant-time comparison. On success the session guard rehashes the password with bcrypt
        // (`hashing.rehash_on_login`), so each legacy user leaves MD5 on their first login.
        return hash_equals($stored, md5($credentials['password']));
    }
}
