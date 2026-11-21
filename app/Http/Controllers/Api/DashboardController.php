<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\EducationNewsResource;
use App\Http\Resources\LetterResource;
use App\Http\Responses\ApiResponse;
use App\Models\EducationNews;
use App\Models\Letter;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    private const RECENT_LETTERS = 4;

    private const RECENT_EDUCATION_NEWS = 3;

    /**
     * GET /api/dashboard/stats - counts and recent items for the admin dashboard.
     */
    public function stats(): JsonResponse
    {
        // toBase() skips Eloquent casts, so the grouped keys stay plain strings.
        $lettersByCategory = Letter::query()->toBase()
            ->selectRaw('category, COUNT(*) AS total')
            ->groupBy('category')
            ->pluck('total', 'category');

        $usersByRole = User::query()->toBase()
            ->selectRaw('role, COUNT(*) AS total')
            ->groupBy('role')
            ->pluck('total', 'role');

        $newsTotal = EducationNews::count();

        return ApiResponse::success([
            'letters' => [
                'total' => (int) $lettersByCategory->sum(),
                'letters' => (int) ($lettersByCategory['letter'] ?? 0),
                'numbers' => (int) ($lettersByCategory['number'] ?? 0),
                'symbols' => (int) ($lettersByCategory['symbol'] ?? 0),
            ],
            'users' => [
                'total' => (int) $usersByRole->sum(),
                'admins' => (int) ($usersByRole[UserRole::Admin->value] ?? 0),
                'users' => (int) ($usersByRole[UserRole::User->value] ?? 0),
            ],
            'education_news' => [
                'total' => $newsTotal,
                'with_thumbnail' => EducationNews::whereNotNull('thumbnail_url')->count(),
            ],
            'recent_letters' => LetterResource::collection(
                Letter::query()->latest()->latest('id')->limit(self::RECENT_LETTERS)->get()
            )->resolve(),
            'recent_education_news' => EducationNewsResource::collection(
                EducationNews::query()->latest()->latest('id')->limit(self::RECENT_EDUCATION_NEWS)->get()
            )->resolve(),
        ], 'Dashboard stats retrieved successfully.');
    }
}
