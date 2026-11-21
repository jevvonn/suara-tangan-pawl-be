<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EducationNews\EducationNewsRequest;
use App\Http\Resources\EducationNewsResource;
use App\Http\Responses\ApiResponse;
use App\Models\EducationNews;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EducationNewsController extends Controller
{
    /** Folder on the public disk (storage/app/public/education-news). */
    private const IMAGE_DIR = 'education-news';

    /**
     * GET /api/education-news - newest first.
     */
    public function index(): JsonResponse
    {
        $news = EducationNews::query()->latest()->latest('id')->get();

        return ApiResponse::success(
            EducationNewsResource::collection($news),
            'Education news retrieved successfully.',
        );
    }

    /**
     * GET /api/education-news/{news}
     */
    public function show(EducationNews $news): JsonResponse
    {
        return ApiResponse::success(new EducationNewsResource($news), 'Education news retrieved successfully.');
    }

    /**
     * POST /api/education-news (multipart: title, content, thumbnail?)
     */
    public function store(EducationNewsRequest $request): JsonResponse
    {
        $path = $request->hasFile('thumbnail')
            ? $request->file('thumbnail')->store(self::IMAGE_DIR, 'public')
            : null;

        try {
            $news = EducationNews::create([
                'title' => $request->validated('title'),
                'content' => $request->validated('content'),
                'thumbnail_url' => $path,
                'created_by' => $request->user()->id,
            ]);
        } catch (Throwable $e) {
            if ($path) {
                Storage::disk('public')->delete($path); // don't leave an orphaned file behind
            }
            throw $e;
        }

        return ApiResponse::success(
            new EducationNewsResource($news->refresh()),
            'Education news created successfully.',
            Response::HTTP_CREATED,
        );
    }

    /**
     * PUT /api/education-news/{news} (multipart: title, content, thumbnail?, remove_thumbnail?)
     * Multipart forms must be sent as POST with `_method=PUT`, because PHP doesn't parse multipart PUT bodies.
     */
    public function update(EducationNewsRequest $request, EducationNews $news): JsonResponse
    {
        $oldPath = $news->thumbnail_url;
        $newPath = $request->hasFile('thumbnail')
            ? $request->file('thumbnail')->store(self::IMAGE_DIR, 'public')
            : null;

        $news->title = $request->validated('title');
        $news->content = $request->validated('content');

        if ($newPath) {
            $news->thumbnail_url = $newPath;
        } elseif ($request->boolean('remove_thumbnail')) {
            $news->thumbnail_url = null;
        }

        try {
            $news->save();
        } catch (Throwable $e) {
            if ($newPath) {
                Storage::disk('public')->delete($newPath);
            }
            throw $e;
        }

        // Only remove the old file once the change is safely saved.
        if ($oldPath && $oldPath !== $news->thumbnail_url) {
            Storage::disk('public')->delete($oldPath);
        }

        return ApiResponse::success(new EducationNewsResource($news->refresh()), 'Education news updated successfully.');
    }

    /**
     * DELETE /api/education-news/{news}
     */
    public function destroy(EducationNews $news): JsonResponse
    {
        $path = $news->thumbnail_url;

        $news->delete();

        if ($path) {
            Storage::disk('public')->delete($path);
        }

        return ApiResponse::success(null, 'Education news deleted successfully.');
    }
}
