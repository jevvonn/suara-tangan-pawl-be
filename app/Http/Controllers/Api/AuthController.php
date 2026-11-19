<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    /**
     * POST /api/auth/register
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->safe()->only(['name', 'email', 'password']));

        return ApiResponse::success(
            $this->tokenPayload($user->refresh()),
            'Registration successful.',
            Response::HTTP_CREATED,
        );
    }

    /**
     * POST /api/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (!$user || !$user->password || !Hash::check($request->validated('password'), $user->password)) {
            return ApiResponse::error(
                'The provided credentials are incorrect.',
                null,
                Response::HTTP_UNAUTHORIZED,
            );
        }

        return ApiResponse::success(
            $this->tokenPayload($user),
            'Login successful.',
        );
    }

    /**
     * GET /api/auth/session - the currently authenticated user.
     */
    public function session(Request $request): JsonResponse
    {
        return ApiResponse::success(
            ['user' => $request->user()],
            'Session retrieved successfully.',
        );
    }

    /**
     * POST /api/auth/logout - revokes the token used for this request.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(null, 'Logout successful.');
    }

    /**
     * @return array{user: User, token: string, token_type: string}
     */
    private function tokenPayload(User $user): array
    {
        return [
            'user' => $user,
            'token' => $user->createToken('api')->plainTextToken,
            'token_type' => 'Bearer',
        ];
    }
}
