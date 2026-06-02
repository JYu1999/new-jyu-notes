<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PruneExpiredTokensTest extends TestCase
{
    use RefreshDatabase;

    public function test_prune_command_is_scheduled(): void
    {
        $events = app(Schedule::class)->events();
        $found = collect($events)->contains(
            fn ($e) => str_contains($e->command ?? '', 'sanctum:prune-expired')
        );

        $this->assertTrue($found, 'sanctum:prune-expired should be scheduled');
    }

    public function test_prune_deletes_long_expired_tokens(): void
    {
        $user = User::create([
            'name' => 'Admin', 'email' => 'a@b.c',
            'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN,
        ]);
        $new = $user->createToken('t', ['posts:read'], now()->subDays(10));
        // Make it clearly older than the prune window.
        DB::table('personal_access_tokens')->where('id', $new->accessToken->id)
            ->update(['expires_at' => now()->subDays(10)]);

        $this->artisan('sanctum:prune-expired --hours=24')->assertExitCode(0);

        $this->assertCount(0, $user->fresh()->tokens);
    }
}
