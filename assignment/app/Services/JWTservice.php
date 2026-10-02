<?php

namespace App\Services;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Log;

class JwtService
{
    private string $secret;
    private int $expiry;
    private string $algo = 'HS256';

    public function __construct()
    {
        $this->secret = config('jwt.secret');
        $this->expiry = (int) config('jwt.expiry', 3600);
    }

    public function generateToken(User $user): string
    {
        $now = time();

        $payload = [
            'iss'   => config('app.url'),
            'iat'   => $now,
            'exp'   => $now + $this->expiry,
            'sub'   => $user->id,
            'role'  => $user->role,
            'email' => $user->email,
        ];

        return JWT::encode($payload, $this->secret, $this->algo);
    }

    public function decodeToken(string $token): ?object
    {
        try {
            return JWT::decode($token, new Key($this->secret, $this->algo));
        } catch (\Throwable $e) {
            Log::warning('JWT decode failed: ' . $e->getMessage());
            return null;
        }
    }

    public function getUserFromToken(string $token): ?User
    {
        $payload = $this->decodeToken($token);

        if (!$payload || !isset($payload->sub)) {
            return null;
        }

        return User::find($payload->sub);
    }
}