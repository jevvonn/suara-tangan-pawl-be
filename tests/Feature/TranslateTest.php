<?php

namespace Tests\Feature;

use App\Models\Letter;
use App\Support\SignTranslator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TranslateTest extends TestCase
{
    use RefreshDatabase;

    private function letters(string ...$symbols): void
    {
        foreach ($symbols as $symbol) {
            Letter::create(['symbol' => $symbol, 'image_url' => 'letters/'.md5($symbol).'.webp']);
        }
    }

    public function test_symbols_in_a_sentence(): void
    {
        $this->assertSame(
            ['H', 'A', 'L', 'O', '1', '0', '!', '10'],
            SignTranslator::symbolsIn("Halo\t 10!"),
        );
        // Long numbers are signed digit by digit only.
        $this->assertSame(['2', '0', '6'], SignTranslator::symbolsIn('2026'));
        $this->assertSame([], SignTranslator::symbolsIn('   '));
    }

    public function test_returns_only_letters_found_for_the_sentence(): void
    {
        $this->letters('H', 'A', 'L', 'O', 'B', '10', '?');

        $response = $this->getJson('/api/letters/translate?sentence='.urlencode('halo, 10 apa?'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => [['id', 'symbol', 'category', 'image_url']]]);

        $symbols = collect($response->json('data'))->pluck('symbol')->sort()->values()->all();
        // "B" isn't in the sentence; "," "P" "1" "0" aren't in the database.
        $this->assertSame(['10', '?', 'A', 'H', 'L', 'O'], $symbols);
    }

    public function test_no_matches_returns_empty_array(): void
    {
        $this->getJson('/api/letters/translate?sentence=xyz')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_sentence_is_required_and_limited(): void
    {
        $this->getJson('/api/letters/translate')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sentence'], 'errors');

        $this->getJson('/api/letters/translate?sentence='.str_repeat('a', 256))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sentence'], 'errors');
    }

    public function test_translate_route_does_not_clash_with_show(): void
    {
        $this->letters('A');

        $this->getJson('/api/letters/'.Letter::first()->id)->assertOk()->assertJsonPath('data.symbol', 'A');
    }
}
