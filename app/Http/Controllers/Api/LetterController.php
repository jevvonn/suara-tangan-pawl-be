<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Letter\LetterRequest;
use App\Http\Resources\LetterResource;
use App\Http\Responses\ApiResponse;
use App\Models\Letter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LetterController extends Controller
{
    /** Folder on the public disk (storage/app/public/letters). */
    private const IMAGE_DIR = 'letters';

    /**
     * GET /api/letters - letters first, then numbers, then symbols ("2" before "10").
     */
    public function index(): JsonResponse
    {
        $letters = Letter::query()
            ->orderBy('category')
            ->orderByRaw('LENGTH(symbol)')
            ->orderBy('symbol')
            ->get();

        return ApiResponse::success(
            LetterResource::collection($letters),
            'Letters retrieved successfully.',
        );
    }

    /**
     * GET /api/letters/{letter}
     */
    public function show(Letter $letter): JsonResponse
    {
        return ApiResponse::success(new LetterResource($letter), 'Letter retrieved successfully.');
    }

    /**
     * POST /api/letters (multipart: symbol, image)
     */
    public function store(LetterRequest $request): JsonResponse
    {
        $path = $request->file('image')->store(self::IMAGE_DIR, 'public');

        try {
            $letter = Letter::create([
                'symbol' => $request->validated('symbol'),
                'image_url' => $path,
                'created_by' => $request->user()->id,
            ]);
        } catch (Throwable $e) {
            Storage::disk('public')->delete($path); // don't leave an orphaned file behind
            throw $e;
        }

        return ApiResponse::success(
            new LetterResource($letter->refresh()), // refresh to load the generated `category`
            'Letter created successfully.',
            Response::HTTP_CREATED,
        );
    }

    /**
     * PUT /api/letters/{letter} (multipart: symbol, image?)
     * Multipart forms must be sent as POST with `_method=PUT`, because PHP doesn't parse multipart PUT bodies.
     */
    public function update(LetterRequest $request, Letter $letter): JsonResponse
    {
        $oldPath = null;
        $newPath = $request->hasFile('image')
            ? $request->file('image')->store(self::IMAGE_DIR, 'public')
            : null;

        $letter->symbol = $request->validated('symbol');

        if ($newPath) {
            $oldPath = $letter->image_url;
            $letter->image_url = $newPath;
        }

        try {
            $letter->save();
        } catch (Throwable $e) {
            if ($newPath) {
                Storage::disk('public')->delete($newPath);
            }
            throw $e;
        }

        // Only remove the old image once the new one is safely saved.
        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return ApiResponse::success(new LetterResource($letter->refresh()), 'Letter updated successfully.');
    }

    /**
     * DELETE /api/letters/{letter}
     */
    public function destroy(Letter $letter): JsonResponse
    {
        $path = $letter->image_url;

        $letter->delete();
        Storage::disk('public')->delete($path);

        return ApiResponse::success(null, 'Letter deleted successfully.');
    }
}
