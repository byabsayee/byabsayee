<?php
namespace App\Services\Integration\Mappers;

/** Tiny public shim so services can read the book timezone without extending BaseMapper. */
final class BaseMapperTz
{
    public static function tz(array $conn): string { return BaseMapper::tz($conn); }
}
