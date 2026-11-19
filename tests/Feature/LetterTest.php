<?php

namespace Tests\Feature;

use App\Models\Letter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LetterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        return $admin;
    }

    private function createLetter(string $symbol): Letter
    {
        $path = UploadedFile::fake()->image("{$symbol}.png")->store('letters', 'public');

        return Letter::create(['symbol' => $symbol, 'image_url' => $path]);
    }

    public function test_anyone_can_list_letters_sorted_with_full_image_urls(): void
    {
        foreach (['?', '10', 'B', '2', 'A'] as $symbol) {
            $this->createLetter($symbol);
        }

        $response = $this->getJson('/api/letters')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(5, 'data');

        $this->assertSame(['A', 'B', '2', '10', '?'], array_column($response->json('data'), 'symbol'));
        $this->assertSame(['letter', 'letter', 'number', 'number', 'symbol'], array_column($response->json('data'), 'category'));
        $this->assertStringStartsWith('/storage/letters/', parse_url($response->json('data.0.image_url'), PHP_URL_PATH));
    }

    public function test_anyone_can_show_a_letter(): void
    {
        $letter = $this->createLetter('A');

        $this->getJson("/api/letters/{$letter->id}")
            ->assertOk()
            ->assertJsonPath('data.symbol', 'A')
            ->assertJsonPath('data.image_url', Storage::disk('public')->url($letter->image_url));
    }

    public function test_show_missing_letter_returns_404_envelope(): void
    {
        $this->getJson('/api/letters/999')
            ->assertNotFound()
            ->assertExactJson(['success' => false, 'message' => 'Data not found.', 'errors' => null, 'data' => []]);
    }

    public function test_admin_can_create_letter(): void
    {
        $admin = $this->actingAsAdmin();

        $response = $this->post('/api/letters', [
            'symbol' => ' a ',
            'image' => UploadedFile::fake()->image('a.png'),
        ])->assertCreated()
            ->assertJsonPath('data.symbol', 'A')
            ->assertJsonPath('data.category', 'letter')
            ->assertJsonPath('data.created_by', $admin->id);

        $letter = Letter::firstOrFail();
        $this->assertStringStartsWith('letters/', $letter->image_url); // DB keeps the path, not the URL
        Storage::disk('public')->assertExists($letter->image_url);
        $this->assertSame(Storage::disk('public')->url($letter->image_url), $response->json('data.image_url'));
    }

    public function test_create_validates_symbol_and_image(): void
    {
        $this->actingAsAdmin();
        $this->createLetter('A');

        $this->post('/api/letters', ['symbol' => 'a'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['symbol', 'image'], 'errors');

        $this->post('/api/letters', [
            'symbol' => 'ABCD',
            'image' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['symbol', 'image'], 'errors');
    }

    public function test_admin_can_update_symbol_only_and_keep_image(): void
    {
        $this->actingAsAdmin();
        $letter = $this->createLetter('A');
        $oldPath = $letter->image_url;

        // Multipart forms use POST + _method=PUT
        $this->post("/api/letters/{$letter->id}", ['_method' => 'PUT', 'symbol' => 'b'])
            ->assertOk()
            ->assertJsonPath('data.symbol', 'B');

        $this->assertSame($oldPath, $letter->fresh()->image_url);
        Storage::disk('public')->assertExists($oldPath);
    }

    public function test_admin_can_replace_image_and_old_file_is_deleted(): void
    {
        $this->actingAsAdmin();
        $letter = $this->createLetter('A');
        $oldPath = $letter->image_url;

        $this->post("/api/letters/{$letter->id}", [
            '_method' => 'PUT',
            'symbol' => 'A', // same symbol must not fail the unique check
            'image' => UploadedFile::fake()->image('new.png'),
        ])->assertOk();

        $newPath = $letter->fresh()->image_url;
        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);
    }

    public function test_update_rejects_symbol_taken_by_another_letter(): void
    {
        $this->actingAsAdmin();
        $this->createLetter('A');
        $letter = $this->createLetter('B');

        $this->post("/api/letters/{$letter->id}", ['_method' => 'PUT', 'symbol' => 'a'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['symbol'], 'errors');
    }

    public function test_admin_can_delete_letter_and_its_image(): void
    {
        $this->actingAsAdmin();
        $letter = $this->createLetter('A');

        $this->deleteJson("/api/letters/{$letter->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertModelMissing($letter);
        Storage::disk('public')->assertMissing($letter->image_url);
    }

    public function test_guests_and_non_admins_cannot_modify_letters(): void
    {
        $letter = $this->createLetter('A');

        $this->postJson('/api/letters', ['symbol' => 'B'])->assertUnauthorized();
        $this->deleteJson("/api/letters/{$letter->id}")->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/letters', ['symbol' => 'B'])
            ->assertForbidden()
            ->assertJsonPath('success', false);
        $this->deleteJson("/api/letters/{$letter->id}")->assertForbidden();
        $this->assertModelExists($letter);
    }
}
