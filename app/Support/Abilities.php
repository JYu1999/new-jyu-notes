<?php

namespace App\Support;

class Abilities
{
    /** The resource => [actions] matrix. */
    public static function matrix(): array
    {
        return config('abilities');
    }

    /** Flattened list of valid "resource:action" ability strings. */
    public static function all(): array
    {
        $out = [];
        foreach (self::matrix() as $resource => $actions) {
            foreach ($actions as $action) {
                $out[] = "{$resource}:{$action}";
            }
        }

        return $out;
    }

    public static function isValid(string $ability): bool
    {
        return in_array($ability, self::all(), true);
    }
}
