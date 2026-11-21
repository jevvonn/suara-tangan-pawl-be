<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FavoriteSentence\StoreFavoriteSentenceRequest;
use App\Http\Resources\FavoriteSentenceResource;
use App\Http\Responses\ApiResponse;
use App\Models\FavoriteSentence;
use App\Support\SignTranslator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The logged-in user's own favorite sentences (any role).
 */
class FavoriteSentenceController extends Controller
{
    /**
     * GET /api/favorite-sentences - newest first, each with its letters.
     */
    public function index(Request $request): JsonResponse
    {
        $favorites = $request->user()->favoriteSentences()->latest()->latest('id')->get();

        return ApiResponse::success(
            FavoriteSentenceResource::collection($this->attachLetters($favorites)),
            'Favorite sentences retrieved successfully.',
        );
    }

    /**
     * POST /api/favorite-sentences - body: { "sentence": "Halo apa kabar" }
     *
     * Saving a sentence that is already a favorite (ignoring letter case) returns the existing one.
     */
    public function store(StoreFavoriteSentenceRequest $request): JsonResponse
    {
        $user = $request->user();
        $sentence = $request->validated('sentence');

        $existing = $this->findBySentence($request, $sentence);

        if (! $existing) {
            try {
                $favorite = $user->favoriteSentences()->create(['sentence' => $sentence]);
            } catch (UniqueConstraintViolationException) {
                // Saved by a parallel request in the meantime.
                $existing = $this->findBySentence($request, $sentence);
            }
        }

        if ($existing) {
            return ApiResponse::success(
                new FavoriteSentenceResource($this->attachLetters($existing)),
                'Sentence is already in favorites.',
            );
        }

        return ApiResponse::success(
            new FavoriteSentenceResource($this->attachLetters($favorite)),
            'Sentence added to favorites.',
            Response::HTTP_CREATED,
        );
    }

    /**
     * DELETE /api/favorite-sentences/{favorite} - only your own (someone else's is a 404).
     */
    public function destroy(Request $request, string $favorite): JsonResponse
    {
        $request->user()->favoriteSentences()->findOrFail($favorite)->delete();

        return ApiResponse::success(null, 'Sentence removed from favorites.');
    }

    private function findBySentence(Request $request, string $sentence): ?FavoriteSentence
    {
        return $request->user()->favoriteSentences()
            ->whereRaw('LOWER(sentence) = ?', [mb_strtolower($sentence)])
            ->first();
    }

    /**
     * Loads the letters for one or many favorites with a single `IN` query.
     *
     * @template T of FavoriteSentence|Collection<int, FavoriteSentence>
     *
     * @param  T  $favorites
     * @return T
     */
    private function attachLetters(FavoriteSentence|Collection $favorites): FavoriteSentence|Collection
    {
        $items = $favorites instanceof FavoriteSentence ? new Collection([$favorites]) : $favorites;

        $symbols = $items->mapWithKeys(fn (FavoriteSentence $favorite) => [
            $favorite->id => SignTranslator::symbolsIn($favorite->sentence),
        ]);

        // toBase(): Eloquent's only() filters by primary key, we need the symbol keys.
        $letters = SignTranslator::lettersFor($symbols->flatten()->unique()->values()->all())
            ->keyBy('symbol')
            ->toBase();

        foreach ($items as $favorite) {
            $favorite->setRelation('letters', new Collection($letters->only($symbols[$favorite->id])->values()->all()));
        }

        return $favorites;
    }
}
