<?php

declare(strict_types=1);

namespace IndexNowKit\Testing\Conformance;

/**
 * Array helpers of the conformance harnesses. {@see merge()} is the one every adapter's test `Fixtures` used to carry
 * by itself: overrides on top of the test options of a package (`Fixtures::merge(self::options(), ['dry_run' => true])`),
 * where a nested option block is merged key by key and a list — `engines`, `hosts` entries, `production_environments` —
 * replaces the base value as a whole, the way an application's configuration file overrides a default one.
 */
final class Arrays
{
    /**
     * Overrides on top of a base array: nested associative arrays merge recursively, a list, an empty array and a
     * scalar (null included) replace the base value; keys only in the base stay.
     *
     * @param array<string, mixed> $base
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    public static function merge(array $base, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            $current = $base[$key] ?? null;
            if (\is_array($value) && $value !== [] && !array_is_list($value) && \is_array($current)) {
                /** @var array<string, mixed> $current */
                /** @var array<string, mixed> $value */
                $base[$key] = self::merge($current, $value);
                continue;
            }
            $base[$key] = $value;
        }

        return $base;
    }
}
