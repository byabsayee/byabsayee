<?php
namespace App\Services\Integration;

/** Loop prevention: while an inbound event is being applied nothing may be queued back toward its origin. */
final class Applying
{
    private static int $depth = 0;

    /** Runs $fn with outbound emission suppressed. */
    public static function run(callable $fn)
    {
        self::$depth++;
        try { return $fn(); } finally { self::$depth--; }
    }

    public static function active(): bool { return self::$depth > 0; }
}
