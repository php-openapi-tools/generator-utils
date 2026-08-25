<?php

declare(strict_types=1);

namespace OpenAPITools\Generator\Utils\Example;

/** @api */
final readonly class ExampleSnippet
{
    public function __construct(
        public string $title,
        public string $description,
        public string $call,
        public string $rendered,
    ) {
    }
}
