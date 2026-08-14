<?php

declare(strict_types=1);

namespace OpenAPITools\Generator\Utils\Builder;

use function implode;
use function str_replace;

use const PHP_EOL;

/** @api */
final class DocBlockBuilder
{
    /** @param list<string> $lines */
    public static function fromLines(array $lines): string
    {
        return '/**' . PHP_EOL . ' * ' . implode(PHP_EOL . ' * ', $lines) . PHP_EOL . ' */';
    }

    /** @param list<string> $lines */
    public static function fromDocLines(array $lines): string
    {
        return self::fromLines(str_replace(['/**', '*/'], '', $lines));
    }
}
