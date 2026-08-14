<?php

declare(strict_types=1);

namespace OpenAPITools\Generator\Utils\Type;

use function implode;

/** @api */
final readonly class ResolvedPropertyType
{
    /** @param list<string> $typeHints */
    public function __construct(
        public array $typeHints,
        public string $nullablePrefix,
        public string $docBlockLine,
    ) {
    }

    public function typeHint(): string
    {
        if ($this->typeHints === []) {
            return '';
        }

        return $this->nullablePrefix . implode('|', $this->typeHints);
    }
}
