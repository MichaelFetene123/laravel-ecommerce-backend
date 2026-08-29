<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withHeaders;
use function Pest\Laravel\withToken;

uses(RefreshDatabase::class);

test('mobile login returns a sanctum bearer token when device_name is provided', function () {
    $user = User::factory()->create([
        'email' => 'flutter.dev@example.com',
        'password' => Hash::make('password123'),
    ]);

    $response = postJson('/api/login', [
        'email' => 'flutter.dev@example.com',
        'password' => 'password123',
        'device_name' => 'Flutter_Pixel_7',
    ]);

    $response->assertSuccessful()
        ->assertJsonStructure([
            'user' => ['id', 'name', 'email'],
            'token',
            'token_type',
        ])
        ->assertJson([
            'token_type' => 'Bearer',
            'user' => [
                'email' => 'flutter.dev@example.com',
            ],
        ]);

    $token = $response->json('token');
    expect($token)->not->toBeEmpty();
    expect($user->tokens()->count())->toBe(1);

    // Verify token can access protected /api/me
    $meResponse = withToken($token)
        ->getJson('/api/me');

    $meResponse->assertSuccessful()
        ->assertJson([
            'id' => $user->id,
            'email' => 'flutter.dev@example.com',
        ]);
});

test('mobile user can logout and revoke their personal access token', function () {
    $user = User::factory()->create([
        'email' => 'mobile.user@example.com',
        'password' => Hash::make('secret123'),
    ]);

    $loginResponse = postJson('/api/login', [
        'email' => 'mobile.user@example.com',
        'password' => 'secret123',
        'device_name' => 'iPhone_15_Pro',
    ]);

    $token = $loginResponse->json('token');
    expect($user->tokens()->count())->toBe(1);

    // Logout with the token
    $logoutResponse = withToken($token)
        ->postJson('/api/logout');

    $logoutResponse->assertSuccessful()
        ->assertJson(['message' => 'Logged out successfully.']);

    expect($user->tokens()->count())->toBe(0);

    // Reset cached guards so Sanctum re-evaluates database token
    app('auth')->forgetGuards();

    // Attempting to access protected route with revoked token should fail
    withToken($token)
        ->getJson('/api/me')
        ->assertUnauthorized();
});

test('web login creates a stateful session when device_name is omitted', function () {
    /** @var User $user */
    $user = User::factory()->create([
        'email' => 'nextjs.user@example.com',
        'password' => Hash::make('webpassword'),
    ]);

    $response = withHeaders(['referer' => 'http://localhost:3000'])
        ->postJson('/api/login', [
            'email' => 'nextjs.user@example.com',
            'password' => 'webpassword',
        ]);

    $response->assertSuccessful()
        ->assertJson([
            'message' => 'Authenticated successfully.',
            'user' => [
                'email' => 'nextjs.user@example.com',
            ],
        ])
        ->assertJsonMissing(['token']);

    assertAuthenticatedAs($user, 'web');

    // Access /api/me via authenticated session
    withHeaders(['referer' => 'http://localhost:3000'])
        ->getJson('/api/me')
        ->assertSuccessful()
        ->assertJson([
            'id' => $user->id,
            'email' => 'nextjs.user@example.com',
        ]);
});

test('web user can logout and destroy session', function () {
    /** @var User $user */
    $user = User::factory()->create([
        'email' => 'spa.user@example.com',
        'password' => Hash::make('password123'),
    ]);

    actingAs($user, 'web');

    $response = withHeaders(['referer' => 'http://localhost:3000'])
        ->postJson('/api/logout');

    $response->assertSuccessful()
        ->assertJson(['message' => 'Logged out successfully.']);

    assertGuest('web');
});

test('login fails with invalid credentials', function () {
    User::factory()->create([
        'email' => 'valid.user@example.com',
        'password' => Hash::make('correct_password'),
    ]);

    // Wrong password
    postJson('/api/login', [
        'email' => 'valid.user@example.com',
        'password' => 'wrong_password',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    // Non-existent email
    postJson('/api/login', [
        'email' => 'nonexistent@example.com',
        'password' => 'some_password',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('unauthenticated request to protected route is rejected', function () {
    getJson('/api/me')->assertUnauthorized();
    getJson('/api/user')->assertUnauthorized();
    postJson('/api/logout')->assertUnauthorized();
});
