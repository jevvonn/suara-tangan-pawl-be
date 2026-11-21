<?php

namespace Tests\Feature;

use App\Models\EducationNews;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EducationNewsTest extends TestCase
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

    private function createNews(array $attributes = [], bool $withThumbnail = false): EducationNews
    {
        $path = $withThumbnail
            ? UploadedFile::fake()->image('thumb.png', 640, 360)->store('education-news', 'public')
            : null;

        return EducationNews::create([
            'title' => 'Belajar BISINDO',
            'content' => 'Isi berita.',
            'thumbnail_url' => $path,
            ...$attributes,
        ]);
    }

    public function test_anyone_can_list_news_newest_first(): void
    {
        $older = $this->createNews(['title' => 'Lama', 'created_at' => now()->subDay()]);
        $newer = $this->createNews(['title' => 'Baru'], withThumbnail: true);

        $response = $this->getJson('/api/education-news')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['id', 'title', 'content', 'thumbnail_url', 'created_by', 'created_at', 'updated_at']]]);

        $this->assertSame([$newer->id, $older->id], array_column($response->json('data'), 'id'));
        $this->assertSame(Storage::disk('public')->url($newer->thumbnail_url), $response->json('data.0.thumbnail_url'));
        $this->assertNull($response->json('data.1.thumbnail_url'));
    }

    public function test_anyone_can_show_news(): void
    {
        $news = $this->createNews();

        $this->getJson("/api/education-news/{$news->id}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Belajar BISINDO');

        $this->getJson('/api/education-news/999')
            ->assertNotFound()
            ->assertJsonPath('message', 'Data not found.');
    }

    public function test_admin_can_create_news_with_thumbnail(): void
    {
        $admin = $this->actingAsAdmin();

        $response = $this->post('/api/education-news', [
            'title' => '  Kelas BISINDO Gratis  ',
            'content' => 'Daftar sekarang.',
            'thumbnail' => UploadedFile::fake()->image('thumb.jpg'),
        ])->assertCreated()
            ->assertJsonPath('data.title', 'Kelas BISINDO Gratis')
            ->assertJsonPath('data.created_by', $admin->id);

        $news = EducationNews::firstOrFail();
        $this->assertStringStartsWith('education-news/', $news->thumbnail_url); // DB keeps the path
        Storage::disk('public')->assertExists($news->thumbnail_url);
        $this->assertSame(Storage::disk('public')->url($news->thumbnail_url), $response->json('data.thumbnail_url'));
    }

    public function test_admin_can_create_news_without_thumbnail(): void
    {
        $this->actingAsAdmin();

        $this->post('/api/education-news', ['title' => 'Tanpa gambar', 'content' => 'Isi.'])
            ->assertCreated()
            ->assertJsonPath('data.thumbnail_url', null);
    }

    public function test_create_validates_input(): void
    {
        $this->actingAsAdmin();

        $this->post('/api/education-news', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'content'], 'errors');

        $this->post('/api/education-news', [
            'title' => str_repeat('a', 151),
            'content' => 'Isi.',
            'thumbnail' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'thumbnail'], 'errors');
    }

    public function test_update_keeps_thumbnail_when_none_sent(): void
    {
        $this->actingAsAdmin();
        $news = $this->createNews(withThumbnail: true);
        $path = $news->thumbnail_url;

        // Multipart forms use POST + _method=PUT
        $this->post("/api/education-news/{$news->id}", ['_method' => 'PUT', 'title' => 'Judul baru', 'content' => 'Isi baru.'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Judul baru')
            ->assertJsonPath('data.content', 'Isi baru.');

        $this->assertSame($path, $news->fresh()->thumbnail_url);
        Storage::disk('public')->assertExists($path);
    }

    public function test_update_replaces_thumbnail_and_deletes_old_file(): void
    {
        $this->actingAsAdmin();
        $news = $this->createNews(withThumbnail: true);
        $oldPath = $news->thumbnail_url;

        $this->post("/api/education-news/{$news->id}", [
            '_method' => 'PUT',
            'title' => 'Judul',
            'content' => 'Isi.',
            'thumbnail' => UploadedFile::fake()->image('new.webp'),
        ])->assertOk();

        $newPath = $news->fresh()->thumbnail_url;
        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);
    }

    public function test_update_can_remove_thumbnail(): void
    {
        $this->actingAsAdmin();
        $news = $this->createNews(withThumbnail: true);
        $oldPath = $news->thumbnail_url;

        $this->post("/api/education-news/{$news->id}", [
            '_method' => 'PUT',
            'title' => 'Judul',
            'content' => 'Isi.',
            'remove_thumbnail' => '1',
        ])->assertOk()
            ->assertJsonPath('data.thumbnail_url', null);

        $this->assertNull($news->fresh()->thumbnail_url);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_update_rejects_thumbnail_and_remove_together(): void
    {
        $this->actingAsAdmin();
        $news = $this->createNews();

        $this->post("/api/education-news/{$news->id}", [
            '_method' => 'PUT',
            'title' => 'Judul',
            'content' => 'Isi.',
            'thumbnail' => UploadedFile::fake()->image('new.png'),
            'remove_thumbnail' => '1',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['remove_thumbnail'], 'errors');
    }

    public function test_admin_can_delete_news_and_its_thumbnail(): void
    {
        $this->actingAsAdmin();
        $news = $this->createNews(withThumbnail: true);

        $this->deleteJson("/api/education-news/{$news->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertModelMissing($news);
        Storage::disk('public')->assertMissing($news->thumbnail_url);
    }

    public function test_guests_and_non_admins_cannot_modify_news(): void
    {
        $news = $this->createNews();
        $payload = ['title' => 'X', 'content' => 'Y'];

        $this->postJson('/api/education-news', $payload)->assertUnauthorized();
        $this->putJson("/api/education-news/{$news->id}", $payload)->assertUnauthorized();
        $this->deleteJson("/api/education-news/{$news->id}")->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/education-news', $payload)->assertForbidden();
        $this->putJson("/api/education-news/{$news->id}", $payload)->assertForbidden();
        $this->deleteJson("/api/education-news/{$news->id}")->assertForbidden();
        $this->assertModelExists($news);
    }
}
