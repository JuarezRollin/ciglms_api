<?php

namespace Tests\Feature\Models;

use App\Models\Relationship;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivityLoggingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_model_updates_log_only_changed_fillable_attributes(): void
    {
        $relationship = Relationship::query()->create(['name' => 'Sibling']);

        $relationship->update(['name' => 'Parent']);

        $activity = Activity::query()->latest('id')->firstOrFail();

        $this->assertSame('updated', $activity->description);
        $this->assertSame(Relationship::class, $activity->subject_type);
        $this->assertSame(['name' => 'Parent'], $activity->attribute_changes->get('attributes'));
        $this->assertSame(['name' => 'Sibling'], $activity->attribute_changes->get('old'));
    }

    public function test_user_activity_never_logs_credentials(): void
    {
        User::factory()->create([
            'name' => 'Audit User',
            'email' => 'audit@example.test',
            'password' => 'secret-password',
        ]);

        $attributes = Activity::query()->latest('id')->firstOrFail()->attribute_changes->get('attributes');

        $this->assertSame('Audit User', $attributes['name']);
        $this->assertSame('audit@example.test', $attributes['email']);
        $this->assertArrayNotHasKey('password', $attributes);
        $this->assertArrayNotHasKey('remember_token', $attributes);
    }
}
