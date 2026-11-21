<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\UpdateUserRoleRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin user management. Users are created only through POST /api/auth/register.
 */
class UserController extends Controller
{
    /**
     * GET /api/users - newest first.
     */
    public function index(): JsonResponse
    {
        $users = User::query()->latest()->latest('id')->get();

        return ApiResponse::success(UserResource::collection($users), 'Users retrieved successfully.');
    }

    /**
     * GET /api/users/{user}
     */
    public function show(User $user): JsonResponse
    {
        return ApiResponse::success(new UserResource($user), 'User retrieved successfully.');
    }

    /**
     * PUT/PATCH /api/users/{user} - body: { "role": "admin" | "user" }
     */
    public function update(UpdateUserRoleRequest $request, User $user): JsonResponse
    {
        // `role` is not mass assignable on purpose, so it is set explicitly here.
        $user->role = UserRole::from($request->validated('role'));
        $user->save();

        return ApiResponse::success(new UserResource($user), 'User role updated successfully.');
    }

    /**
     * DELETE /api/users/{user}
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->is($request->user())) {
            return ApiResponse::error('You cannot delete your own account.', null, Response::HTTP_FORBIDDEN);
        }

        DB::transaction(function () use ($user) {
            // Tokens use a polymorphic relation with no foreign key, so they don't cascade.
            $user->tokens()->delete();
            // letters/education_news.created_by -> NULL and favorite_sentences are deleted by their foreign keys.
            $user->delete();
        });

        return ApiResponse::success(null, 'User deleted successfully.');
    }
}
