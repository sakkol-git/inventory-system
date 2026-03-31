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
   // Custom Static JWT Guard for Authentication
   protected function jwt(): JWTGuard
   {
         return auth('api');
   }

   // Customized response with user data and token and cookies
    protected function respondWithToken(string $token, User $user): JsonResponse
    {
        $ttlSeconds = $this->jwt()->factory()->getTTL() * 60;

        // Set HttpOnly cookie with token
        $cookie = cookie(
            name: config('jwt.cookie_key_name', 'token'),
            value: $token,
            minutes: (int) ($ttlSeconds / 60),
            path: '/',
            secure: app()->environment('production'),
            httpOnly: true,
            sameSite: 'lax'
        );

        return response()->json([
            'user' => new UserResource($user),
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $ttlSeconds,
        ])->withCookie($cookie);
    }

   // Register a new User and assign default role
   public function register(RegisterRequest $request): JsonResponse
   {
        $user = User::create([
            ...$request->validated(),
            'role' => UserRole::STUDENT->value, // Default role
        ]);

        // Assign default role using Spatie's Permission package
        $spatieRole = Role::firstOrCreate([
            'name' => UserRole::STUDENT->value,
            'guard_name' => 'api',]);
        $user->assignRole($spatieRole);
        $token = $this->jwt()->login($user);

        return $this->respondWithToken($token, $user)->setStatusCode(201);
   }


    // Login user and return token
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        // check if user exists and credentials are valid
        if(!$token = $this->jwt()->attempt($credentials))
            {
                return response()->json(['error' => 'Invalid credentials'], 401);
            }

        $user = $this->jwt()->user();

        return $this->respondWithToken($token, $user);

    }


    // Get authenticated user profile
    public function profile(): JsonResponse
    {
        $user = $this->jwt()->user();

        return response()->json(['data' => new UserResource($user)]);
    }

    // Logout user and invalidate token
    public function logout(): JsonResponse
    {
        $this->jwt()->logout();

        // Clear the token cookie
        $cookie = cookie()->forget(config('jwt.cookie_key_name', 'token'));

        return response()->json(['message' => 'Successfully logged out'])->withCookie($cookie);
    }


    // Refresh token
    public function refresh(): JsonResponse
    {
        $user = $this->jwt()->user();

        return $this->respondWithToken($this->jwt()->refresh(), $user);
    }
}





