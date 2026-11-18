<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private const ENVELOPE = ['success', 'message', 'errors', 'data'];

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Budi',
            'email' => 'Budi@Example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([...self::ENVELOPE, 'data' => ['user' => ['id', 'name', 'email', 'role'], 'token', 'token_type']])
            ->assertJsonPath('success', true)
            ->assertJsonPath('errors', null)
            ->assertJsonPath('data.user.email', 'budi@example.com')
            ->assertJsonPath('data.user.role', 'user')
            ->assertJsonMissingPath('data.user.password');
    }

    public function test_register_validation_errors_use_envelope(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/auth/register', ['email' => 'taken@example.com', 'password' => 'short'])
            ->assertUnprocessable()
            ->assertJsonStructure(self::ENVELOPE)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonPath('data', [])
            ->assertJsonValidationErrors(['name', 'email', 'password'], 'errors');
    }

    public function test_user_can_login_and_fetch_session(): void
    {
        $user = User::factory()->create(['email' => 'budi@example.com', 'password' => 'password123']);

        $token = $this->postJson('/api/auth/login', ['email' => 'budi@example.com', 'password' => 'password123'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json('data.token');

        $this->withToken($token)->getJson('/api/auth/session')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $user->id);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create(['email' => 'budi@example.com', 'password' => 'password123']);

        $this->postJson('/api/auth/login', ['email' => 'budi@example.com', 'password' => 'wrong-password'])
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors', null);
    }

    public function test_login_fails_for_user_without_password(): void
    {
        User::factory()->create(['email' => 'budi@example.com', 'password' => null]);

        $this->postJson('/api/auth/login', ['email' => 'budi@example.com', 'password' => 'anything'])
            ->assertUnauthorized();
    }

    public function test_session_requires_token_even_without_accept_header(): void
    {
        $this->get('/api/auth/session')
            ->assertUnauthorized()
            ->assertExactJson(['success' => false, 'message' => 'Unauthenticated.', 'errors' => null, 'data' => []]);
    }

    public function test_logout_revokes_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_unknown_api_route_uses_envelope(): void
    {
        $this->get('/api/does-not-exist')
            ->assertNotFound()
            ->assertJsonStructure(self::ENVELOPE)
            ->assertJsonPath('success', false);
    }
}
