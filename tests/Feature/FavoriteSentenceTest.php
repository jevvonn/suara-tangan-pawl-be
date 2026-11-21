<?php

namespace Tests\Feature;

use App\Models\FavoriteSentence;
use App\Models\Letter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FavoriteSentenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_save_and_list_favorites_with_letters(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        Letter::create(['symbol' => 'H', 'image_url' => 'letters/h.webp']);
        Letter::create(['symbol' => 'A', 'image_url' => 'letters/a.webp']);
        Letter::create(['symbol' => 'Z', 'image_url' => 'letters/z.webp']);

        $this->postJson('/api/favorite-sentences', ['sentence' => '  halo   apa  '])
            ->assertCreated()
            ->assertJsonPath('data.sentence', 'halo apa')
            ->assertJsonCount(2, 'data.letters');

        $this->travel(1)->minutes();
        $this->postJson('/api/favorite-sentences', ['sentence' => 'Zaza'])->assertCreated();

        $response = $this->getJson('/api/favorite-sentences')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['id', 'sentence', 'letters' => [['id', 'symbol', 'image_url']], 'created_at', 'updated_at']]]);

        $this->assertSame(['Zaza', 'halo apa'], array_column($response->json('data'), 'sentence'));
        $this->assertSame(['A', 'Z'], collect($response->json('data.0.letters'))->pluck('symbol')->sort()->values()->all());
        $this->assertSame(['A', 'H'], collect($response->json('data.1.letters'))->pluck('symbol')->sort()->values()->all());
    }

    public function test_saving_the_same_sentence_again_returns_the_existing_one(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $id = $this->postJson('/api/favorite-sentences', ['sentence' => 'Halo'])->assertCreated()->json('data.id');

        $this->postJson('/api/favorite-sentences', ['sentence' => 'HALO'])
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('message', 'Sentence is already in favorites.');

        $this->assertSame(1, FavoriteSentence::count());
    }

    public function test_sentence_is_validated(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/favorite-sentences', ['sentence' => '   '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sentence'], 'errors');

        $this->postJson('/api/favorite-sentences', ['sentence' => str_repeat('a', 256)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sentence'], 'errors');
    }

    public function test_user_only_sees_and_deletes_own_favorites(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $theirs = FavoriteSentence::create(['user_id' => $other->id, 'sentence' => 'punya orang lain']);
        $mine = FavoriteSentence::create(['user_id' => $owner->id, 'sentence' => 'punyaku']);

        Sanctum::actingAs($owner);

        $this->getJson('/api/favorite-sentences')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id);

        $this->deleteJson("/api/favorite-sentences/{$theirs->id}")
            ->assertNotFound()
            ->assertJsonPath('message', 'Data not found.');
        $this->assertModelExists($theirs);

        $this->deleteJson("/api/favorite-sentences/{$mine->id}")
            ->assertOk()
            ->assertJsonPath('success', true);
        $this->assertModelMissing($mine);
    }

    public function test_admins_can_use_favorites_too(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/favorite-sentences', ['sentence' => 'halo'])->assertCreated();
    }

    public function test_guests_cannot_use_favorites(): void
    {
        $this->getJson('/api/favorite-sentences')->assertUnauthorized();
        $this->postJson('/api/favorite-sentences', ['sentence' => 'halo'])->assertUnauthorized();
        $this->deleteJson('/api/favorite-sentences/1')->assertUnauthorized();
    }
}
