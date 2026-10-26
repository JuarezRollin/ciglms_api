<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountInitialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_accounts_generate_or_accept_initials_and_expose_them_in_the_list(): void
    {
        $this->actingAs(User::factory()->create(), 'api');
        $payload = [
            'name' => 'Frans Audrey Sadicon',
            'position' => 'Administrative Assistant II',
            'email' => 'frans@example.test',
            'password' => 'Password#123',
            'password_confirmation' => 'Password#123',
        ];

        $this->postJson('/api/v1/accounts', $payload)
            ->assertCreated()
            ->assertJsonPath('data.initials', 'FAS')
            ->assertJsonPath('data.position', 'Administrative Assistant II');
        $this->postJson('/api/v1/accounts', [
            ...$payload,
            'name' => 'Another Creator',
            'email' => 'another@example.test',
            'initials' => 'acx',
        ])->assertCreated()->assertJsonPath('data.initials', 'ACX');
        $this->getJson('/api/v1/accounts?search=Frans')
            ->assertOk()
            ->assertJsonPath('data.0.initials', 'FAS');
        $this->getJson('/api/v1/accounts?search=ACX')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Another Creator');
        $account = User::query()->where('email', 'frans@example.test')->firstOrFail();
        $password = $account->password;
        $this->putJson('/api/v1/accounts/'.$account->id, [
            'name' => 'Frans Audrey Sadicon',
            'email' => 'frans@example.test',
            'initials' => 'fasx',
            'position' => 'Administrative Officer I',
        ])->assertOk()->assertJsonPath('data.initials', 'FASX')->assertJsonPath('data.position', 'Administrative Officer I');
        $this->assertSame($password, $account->fresh()->password);
        $this->postJson('/api/v1/accounts', [
            ...$payload,
            'email' => 'invalid@example.test',
            'initials' => 'F A S',
        ])->assertUnprocessable()->assertJsonValidationErrors('initials');

        $this->assertDatabaseHas('users', ['email' => 'another@example.test', 'initials' => 'ACX']);
    }
}
