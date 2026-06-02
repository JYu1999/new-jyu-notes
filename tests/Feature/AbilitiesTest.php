<?php

namespace Tests\Feature;

use App\Support\Abilities;
use Tests\TestCase;

class AbilitiesTest extends TestCase
{
    public function test_all_flattens_matrix_to_resource_action_strings(): void
    {
        $all = Abilities::all();

        $this->assertContains('posts:read', $all);
        $this->assertContains('posts:publish', $all);
        $this->assertContains('media:create', $all);
        // media has no update; categories/tags have no publish
        $this->assertNotContains('media:update', $all);
        $this->assertNotContains('tags:publish', $all);
        $this->assertNotContains('categories:publish', $all);

        $this->assertContains('todos:read', $all);
        $this->assertContains('todos:delete', $all);
        $this->assertNotContains('todos:publish', $all);

        // 5 + 5 + 4 + 4 + 3 + 4 = 25 abilities
        $this->assertCount(25, $all);
    }

    public function test_is_valid_checks_membership(): void
    {
        $this->assertTrue(Abilities::isValid('posts:create'));
        $this->assertFalse(Abilities::isValid('media:update'));
        $this->assertFalse(Abilities::isValid('nonsense:foo'));
    }
}
