<?php

namespace Tests\Feature;

use App\Models\EducationNews;
use App\Models\Letter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_gets_counts_and_recent_items(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        User::factory()->count(2)->create();

        foreach (['A', 'B', 'C', '7', '10', '?'] as $i => $symbol) {
            Letter::create(['symbol' => $symbol, 'image_url' => "letters/{$i}.png", 'created_at' => now()->addMinutes($i)]);
        }
        foreach (range(1, 4) as $i) {
            EducationNews::create([
                'title' => "Berita {$i}",
                'content' => 'Isi.',
                'thumbnail_url' => $i % 2 ? "education-news/{$i}.png" : null,
                'created_at' => now()->addMinutes($i),
            ]);
        }

        $response = $this->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.letters', ['total' => 6, 'letters' => 3, 'numbers' => 2, 'symbols' => 1])
            ->assertJsonPath('data.users', ['total' => 3, 'admins' => 1, 'users' => 2])
            ->assertJsonPath('data.education_news', ['total' => 4, 'with_thumbnail' => 2])
            ->assertJsonCount(4, 'data.recent_letters')
            ->assertJsonCount(3, 'data.recent_education_news');

        // Newest first, in the same shape as the letters / education news endpoints.
        $this->assertSame(['?', '10', '7', 'C'], array_column($response->json('data.recent_letters'), 'symbol'));
        $this->assertSame(['Berita 4', 'Berita 3', 'Berita 2'], array_column($response->json('data.recent_education_news'), 'title'));
        $this->assertStringContainsString('/storage/letters/', $response->json('data.recent_letters.0.image_url'));
        $this->assertNull($response->json('data.recent_education_news.0.thumbnail_url'));
    }

    public function test_empty_database_returns_zeroes(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data.letters.total', 0)
            ->assertJsonPath('data.education_news', ['total' => 0, 'with_thumbnail' => 0])
            ->assertJsonPath('data.recent_letters', [])
            ->assertJsonPath('data.recent_education_news', []);
    }

    public function test_only_admins_can_see_stats(): void
    {
        $this->getJson('/api/dashboard/stats')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/dashboard/stats')->assertForbidden();
    }
}
