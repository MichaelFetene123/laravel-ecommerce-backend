<?php

use App\Models\Address;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

test('unauthenticated users cannot access addresses endpoint', function () {
    getJson('/api/addresses')->assertUnauthorized();
    postJson('/api/addresses', [])->assertUnauthorized();
});

test('authenticated user can list their own addresses', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $address1 = Address::factory()->create([
        'user_id' => $user1->id,
        'full_name' => 'John Doe',
        'city' => 'Addis Ababa',
    ]);

    $address2 = Address::factory()->create([
        'user_id' => $user1->id,
        'full_name' => 'Jane Doe',
        'city' => 'Hawassa',
    ]);

    // Address for another user
    Address::factory()->create([
        'user_id' => $user2->id,
        'full_name' => 'Other User',
        'city' => 'Dire Dawa',
    ]);

    Sanctum::actingAs($user1);

    $response = getJson('/api/addresses');

    $response->assertOk()
        ->assertJsonCount(2)
        ->assertJsonFragment(['full_name' => 'John Doe', 'city' => 'Addis Ababa'])
        ->assertJsonFragment(['full_name' => 'Jane Doe', 'city' => 'Hawassa'])
        ->assertJsonMissing(['full_name' => 'Other User']);
});

test('authenticated user can store a new address', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $payload = [
        'full_name' => 'Abebe Bikila',
        'line1' => 'Bole Subcity, Woreda 03',
        'line2' => 'House No. 123',
        'city' => 'Addis Ababa',
        'region' => 'Addis Ababa',
        'country' => 'Ethiopia',
        'postal_code' => '1000',
        'phone' => '+251911223344',
    ];

    $response = postJson('/api/addresses', $payload);

    $response->assertCreated()
        ->assertJsonFragment([
            'user_id' => $user->id,
            'full_name' => 'Abebe Bikila',
            'line1' => 'Bole Subcity, Woreda 03',
            'city' => 'Addis Ababa',
            'country' => 'Ethiopia',
        ]);

    expect(Address::where('user_id', $user->id)->count())->toBe(1);
});

test('storing an address validates required fields', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = postJson('/api/addresses', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['full_name', 'line1', 'city', 'country']);
});
