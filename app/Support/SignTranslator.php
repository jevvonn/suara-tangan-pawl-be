<?php

namespace App\Support;

use App\Models\Letter;
use Illuminate\Database\Eloquent\Collection;

/**
 * Finds the hand-sign letters a sentence needs.
 *
 * The frontend builds the cards; this only decides which symbols to look up:
 * every non-space character (uppercased, as symbols are stored), plus whole
 * numbers of 2-3 digits so a symbol like "10" can be shown as one sign.
 */
class SignTranslator
{
    /** Same limit as the `letters.symbol` column. */
    public const MAX_SYMBOL_LENGTH = 3;

    /**
     * @return list<string> Unique symbols to look up, e.g. "Halo 10!" -> ["H","A","L","O","1","0","!","10"].
     */
    public static function symbolsIn(string $sentence): array
    {
        $upper = mb_strtoupper($sentence);

        $characters = preg_split('//u', (string) preg_replace('/\s+/u', '', $upper), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        preg_match_all('/\d+/', $upper, $matches);
        $numbers = array_filter(
            $matches[0],
            fn (string $number) => strlen($number) > 1 && strlen($number) <= self::MAX_SYMBOL_LENGTH,
        );

        return array_values(array_unique([...$characters, ...$numbers]));
    }

    /**
     * Letters for the given symbols, in one `WHERE symbol IN (...)` query.
     *
     * @param  list<string>  $symbols
     * @return Collection<int, Letter>
     */
    public static function lettersFor(array $symbols): Collection
    {
        if ($symbols === []) {
            return new Collection;
        }

        return Letter::query()->whereIn('symbol', $symbols)->get();
    }
}
