<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\TokenRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    // Precomputed bcrypt hash at the production default cost (12); never generated per request.
    private const DUMMY_PASSWORD_HASH = '$2y$12$PPa.M16l2us7WxSpXRLQsuuFjoMb2agxMyc6Pg1VU5HEDXCW.Pvfm';

    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $user = User::create($request->safe()->only(['name', 'email', 'password']));
        } catch (UniqueConstraintViolationException $exception) {
            // Preserve the validation contract for concurrent registrations.
            throw ValidationException::withMessages(['email' => ['The email has already been taken.']]);
        }

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function login(LoginRequest $request): UserResource
    {
        if (! Auth::guard('web')->attempt($request->safe()->only(['email', 'password']))) {
            throw new AuthenticationException('Invalid credentials.');
        }

        $request->session()->regenerate();

        return new UserResource(Auth::guard('web')->user());
    }

    public function logout(Request $request): JsonResponse
    {
        abort_unless(Auth::guard('web')->check(), 401);
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['data' => ['message' => 'Logged out.']]);
    }

    public function token(TokenRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        $validPassword = Hash::check($request->validated('password'), $user?->password ?? self::DUMMY_PASSWORD_HASH);

        if (! $user || ! $validPassword) {
            throw new AuthenticationException('Invalid credentials.');
        }

        $token = $user->createToken($request->validated('device_name'), ['*'], now()->addMinutes(config('sanctum.mobile_token_expiration')));

        return response()->json(['data' => [
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at->toISOString(),
            'user' => (new UserResource($user))->resolve($request),
        ]], 201)->header('Cache-Control', 'no-store');
    }

    public function revoke(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        abort_unless($token instanceof PersonalAccessToken, 403);
        $token->delete();

        return response()->json(['data' => ['message' => 'Current token revoked.']]);
    }
}
