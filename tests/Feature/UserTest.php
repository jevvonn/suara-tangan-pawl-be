<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\FavoriteSentence;
use App\Models\Letter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_admin_can_list_users_newest_first(): void
    {
        $this->actingAsAdmin();
        $older = User::factory()->create(['created_at' => now()->subDay()]);
        $newer = User::factory()->create(['created_at' => now()->addMinute()]);

        $response = $this->getJson('/api/users')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data' => [['id', 'name', 'email', 'role', 'email_verified_at', 'created_at', 'updated_at']]]);

        $ids = array_column($response->json('data'), 'id');
        $this->assertSame($newer->id, $ids[0]);
        $this->assertSame($older->id, $ids[2]);
        $this->assertArrayNotHasKey('password', $response->json('data.0'));
    }

    public function test_admin_can_show_a_user(): void
    {
        $this->actingAsAdmin();
        $user = User::factory()->create();

        $this->getJson("/api/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.role', 'user');

        $this->getJson('/api/users/999')->assertNotFound()->assertJsonPath('message', 'Data not found.');
    }

    public function test_admin_can_change_only_the_role(): void
    {
        $this->actingAsAdmin();
        $user = User::factory()->create(['name' => 'Budi', 'email' => 'budi@example.com']);

        $this->putJson("/api/users/{$user->id}", [
            'role' => 'admin',
            'name' => 'Hacked',
            'email' => 'hacked@example.com',
        ])->assertOk()
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.name', 'Budi');

        $user->refresh();
        $this->assertSame(UserRole::Admin, $user->role);
        $this->assertSame('budi@example.com', $user->email);
    }

    public function test_role_must_be_valid(): void
    {
        $this->actingAsAdmin();
        $user = User::factory()->create();

        $this->putJson("/api/users/{$user->id}", ['role' => 'superadmin'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role'], 'errors');

        $this->putJson("/api/users/{$user->id}", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role'], 'errors');
    }

    public function test_admin_cannot_change_own_role(): void
    {
        $admin = $this->actingAsAdmin();

        $this->putJson("/api/users/{$admin->id}", ['role' => 'user'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.role.0', 'You cannot change your own role.');

        $this->assertSame(UserRole::Admin, $admin->fresh()->role);
    }

    public function test_admin_can_delete_user_with_related_data(): void
    {
        $this->actingAsAdmin();
        $user = User::factory()->create();
        $user->createToken('api');
        $letter = Letter::create(['symbol' => 'A', 'image_url' => 'letters/a.png', 'created_by' => $user->id]);
        $favorite = FavoriteSentence::create(['user_id' => $user->id, 'sentence' => 'halo']);

        $this->deleteJson("/api/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertModelMissing($user);
        $this->assertModelMissing($favorite);
        $this->assertNull($letter->fresh()->created_by);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $admin = $this->actingAsAdmin();

        $this->deleteJson("/api/users/{$admin->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'You cannot delete your own account.');

        $this->assertModelExists($admin);
    }

    public function test_users_cannot_be_created_through_users_endpoint(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/users', ['name' => 'X', 'email' => 'x@example.com', 'role' => 'user'])
            ->assertMethodNotAllowed()
            ->assertJsonPath('success', false);
    }

    public function test_guests_and_non_admins_are_blocked(): void
    {
        $user = User::factory()->create();

        $this->getJson('/api/users')->assertUnauthorized();

        Sanctum::actingAs($user);

        $this->getJson('/api/users')->assertForbidden();
        $this->putJson("/api/users/{$user->id}", ['role' => 'admin'])->assertForbidden();
        $this->deleteJson("/api/users/{$user->id}")->assertForbidden();
        $this->assertSame(UserRole::User, $user->fresh()->role);
    }
}
