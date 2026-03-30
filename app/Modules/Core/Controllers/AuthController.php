<?php

declare(strict_types=1);

namespace App\Modules\Core\Controllers;

use App\Modules\Core\Enums\UserRole;
use App\Modules\Core\Http\Controllers\Controller;
use App\Modules\Core\Requests\Auth\LoginRequest;
use App\Modules\Core\Requests\Auth\RegisterRequest;
use App\Modules\Core\Resources\UserResource;
use App\Modules\Core\Models\User;
use Illuminate\Http\JsonResponse;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    /** Returns the JWT-typed guard, satisfying static analysis. */
    private function jwt(): JWTGuard
    {
        /** @var JWTGuard */
        return auth('api');
    }

    /**
     * Register a new user (always as student).
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            ...$request->validated(),
            'role' => UserRole::STUDENT->value,
        ]);

        // Assign Spatie student role so permissions are active.
        // Create the role if it does not exist yet to avoid RoleDoesNotExist errors.
        $spatieRole = Role::firstOrCreate([
            'name' => UserRole::STUDENT->value,
            'guard_name' => 'api',
        ]);

        $user->assignRole($spatieRole);

        $token = $this->jwt()->login($user);

        return $this->respondWithToken($token, $user)->setStatusCode(201);
    }

    /**
     * Login user and return JWT token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        if (! $token = $this->jwt()->attempt($credentials)) {
            return response()->json(['message' => 'Invalid email or password.'], 401);
        }

        /** @var User $user */
        $user = $this->jwt()->user();

        return $this->respondWithToken($token, $user);
    }

    /**
     * Get the authenticated user's profile with their Spatie permissions.
     *
     * Returns a flat structure (no `data` wrapper) so the frontend
     * AuthContext can read id, role, permissions directly.
     */
    public function profile(): JsonResponse
    {
        /** @var User $user */
        $user = $this->jwt()->user();
        $user->loadMissing('permissions');

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role?->value,
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
        ]);
    }

    /**
     * Logout user (invalidate token + clear cookie).
     */
    public function logout(): JsonResponse
    {
        $this->jwt()->logout();

        $cookie = cookie()->forget(config('jwt.cookie_key_name', 'token'));

        return response()->json(['message' => 'Successfully logged out'])->withCookie($cookie);
    }

    /**
     * Refresh JWT token.
     */
    public function refresh(): JsonResponse
    {
        /** @var User $user */
        $user = $this->jwt()->user();

        return $this->respondWithToken($this->jwt()->refresh(), $user);
    }

    /**
     * Build token response structure.
     *
     * The JWT is attached as an httpOnly cookie so the frontend never
     * touches the raw token.  The json body still includes it for
     * Postman / mobile clients that cannot use cookies.
     */
    protected function respondWithToken(string $token, User $user): JsonResponse
    {
        $ttlSeconds = $this->jwt()->factory()->getTTL() * 60;

        $cookie = cookie(
            name: config('jwt.cookie_key_name', 'token'),
            value: $token,
            minutes: (int) ($ttlSeconds / 60),
            path: '/',
            secure: app()->environment('production'),
            httpOnly: true,
            sameSite: 'Lax',
        );

        return response()->json([
            'user' => new UserResource($user),
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $ttlSeconds,
        ])->withCookie($cookie);
    }
}
