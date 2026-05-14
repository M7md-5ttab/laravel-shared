<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Support;

final class PathHelper
{
    public static function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }

    public static function normalize(string $path): string
    {
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $segments = [];

        foreach (explode(DIRECTORY_SEPARATOR, $normalized) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        $prefix = '';

        if (preg_match('/^[A-Za-z]:/', $normalized) === 1) {
            $prefix = substr($normalized, 0, 2) . DIRECTORY_SEPARATOR;
        } elseif (str_starts_with($normalized, DIRECTORY_SEPARATOR)) {
            $prefix = DIRECTORY_SEPARATOR;
        }

        return $prefix . implode(DIRECTORY_SEPARATOR, $segments);
    }

    public static function join(string ...$segments): string
    {
        return self::normalize(implode(DIRECTORY_SEPARATOR, $segments));
    }

    public static function relativePath(string $from, string $to): string
    {
        $from = self::normalize($from);
        $to = self::normalize($to);

        $fromParts = array_values(array_filter(explode(DIRECTORY_SEPARATOR, trim($from, DIRECTORY_SEPARATOR)), 'strlen'));
        $toParts = array_values(array_filter(explode(DIRECTORY_SEPARATOR, trim($to, DIRECTORY_SEPARATOR)), 'strlen'));

        if (preg_match('/^[A-Za-z]:/', $from) === 1 && preg_match('/^[A-Za-z]:/', $to) === 1) {
            if (strtolower(substr($from, 0, 2)) !== strtolower(substr($to, 0, 2))) {
                return $to;
            }
        }

        while ($fromParts !== [] && $toParts !== [] && strtolower($fromParts[0]) === strtolower($toParts[0])) {
            array_shift($fromParts);
            array_shift($toParts);
        }

        $relative = array_merge(array_fill(0, count($fromParts), '..'), $toParts);

        return $relative === [] ? '.' : implode('/', $relative);
    }
}
