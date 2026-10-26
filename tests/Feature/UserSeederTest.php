<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\OtherUserSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_admins_are_assigned_to_office_one_on_initial_and_repeat_runs(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(UserSeeder::class);

        $this->assertDatabaseCount('users', 6);
        $this->assertSame(0, User::query()->where('office_id', '!=', 1)->orWhereNull('office_id')->count());
        $this->assertSame(0, User::query()->whereNull('position')->count());

        User::query()->where('email', 'fassadicon@dswd.gov.ph')->update(['office_id' => null]);
        $this->seed(UserSeeder::class);

        $this->assertDatabaseCount('users', 6);
        $this->assertSame(0, User::query()->where('office_id', '!=', 1)->orWhereNull('office_id')->count());
    }

    public function test_seeded_reviewers_have_positions(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(OtherUserSeeder::class);

        $reviewers = User::query()->role('reviewer')->get();
        $this->assertCount(2, $reviewers);
        $this->assertTrue($reviewers->every(fn (User $user): bool => filled($user->position)));
    }
}
