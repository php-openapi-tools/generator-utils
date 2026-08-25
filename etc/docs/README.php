<?php

declare(strict_types=1);

use DTL\Docbot\Article\Article;
use DTL\Docbot\Extension\Core\Block\SectionBlock;
use OpenAPITools\Generator\Utils\Example\ExampleRepresentation;
use OpenAPITools\Generator\Utils\Example\ExampleSnippets;
use OpenAPITools\Utils\Namespace_;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$namespace  = new Namespace_('ApiClients\Client\Example', 'ApiClients\Tests\Client\Example');
$namespaced = ExampleRepresentation::create($namespace);

/** @var list<SectionBlock> $builderSections */
$builderSections = [];

foreach (ExampleSnippets::fromRepresentation($namespaced) as $snippet) {
    $builderSections[] = new SectionBlock($snippet->title, [
        $snippet->description,
        <<<PHP
        ```php
        {$snippet->call}
        ```
        PHP,
        '**Rendered:**',
        <<<PHP
        ```php
        {$snippet->rendered}
        ```
        PHP,
    ]);
}

return Article::create('../../README', 'generator-utils', [
    <<<'TEXT'
    Shared [nikic/php-parser](https://github.com/nikic/PHP-Parser) utilities for [OpenAPI Tools](https://github.com/php-openapi-tools) code generators: AST builders, type resolution, and file pretty-printing helpers consumed by packages such as [`generator-schema`](https://github.com/php-openapi-tools/generator-schema) and [`generator-hydrator`](https://github.com/php-openapi-tools/generator-hydrator).

    ![Continuous Integration](https://github.com/php-openapi-tools/generator-utils/workflows/Continuous%20Integration/badge.svg)
    [![Latest Stable Version](https://poser.pugx.org/openapi-tools/generator-utils/v/stable.png)](https://packagist.org/packages/openapi-tools/generator-utils)
    [![Total Downloads](https://poser.pugx.org/openapi-tools/generator-utils/downloads.png)](https://packagist.org/packages/openapi-tools/generator-utils/stats)
    [![License](https://poser.pugx.org/openapi-tools/generator-utils/license.png)](https://packagist.org/packages/openapi-tools/generator-utils)
    TEXT,
    new SectionBlock('Requirements', [
        <<<'TEXT'
        - PHP `^8.4`
        TEXT,
    ]),
    new SectionBlock('Installation', [
        <<<'TEXT'
        ```
        composer require openapi-tools/generator-utils
        ```
        TEXT,
    ]),
    new SectionBlock('Where it fits', [
        <<<'TEXT'
        Generator packages assemble PHP source with PhpParser nodes instead of string templates. This library provides the shared building blocks they all rely on: expression and statement factories, match-arm helpers, namespace file wrapping, OpenAPI type resolution, and final pretty-printing.

        ```mermaid
        flowchart LR
          spec[OpenAPI spec] --> rep[Representation]
          rep --> type[Type resolvers]
          rep --> builders[AST builders]
          builders --> nodes[PhpParser nodes]
          nodes --> ns[NamespaceBuilder]
          ns --> print[FileStringyfier]
          print --> php[Generated PHP]
        ```
        TEXT,
    ]),
    new SectionBlock('Components', [
        <<<'TEXT'
        | Class | Purpose |
        | --- | --- |
        | `FileStringyfier` | Pretty-print AST `File` contents with `strict_types` |
        | `Builder\DocBlockBuilder` | Format PHPDoc blocks from line arrays |
        | `Builder\ExpressionBuilder` | Common AST expressions (`var`, `thisMethod`, `identical`, `andAll`, …) |
        | `Builder\StatementBuilder` | Common AST statements (`assign`, `yieldMapOverIterable`, …) |
        | `Builder\MatchBuilder` | Build `match` expressions on variables or class name strings |
        | `Builder\HydrationBuilder` | Generate `$hydrator->hydrate($class, $data)`-style calls |
        | `Builder\NamespaceBuilder` | Wrap class nodes in namespace `File` objects |
        | `Type\UnionTypeUtils` | Resolve OpenAPI union types to PHP type strings |
        | `Type\PropertyTypeResolver` | Resolve schema property types and docblock lines |
        TEXT,
    ]),
    new SectionBlock('Examples', [
        'Each builder renders AST nodes or PHPDoc strings that generators embed in emitted PHP:',
        ...$builderSections,
    ]),
    new SectionBlock('Related packages', [
        <<<'TEXT'
        | Package | Relationship |
        | --- | --- |
        | [`representation`](https://github.com/php-openapi-tools/representation) | Input model for type resolution |
        | [`gatherer`](https://github.com/php-openapi-tools/gatherer) | Builds the representation from OpenAPI |
        | [`utils`](https://github.com/php-openapi-tools/utils) | `File`, `ClassString`, and namespace helpers |
        | [`generator-schema`](https://github.com/php-openapi-tools/generator-schema) | Primary consumer — schema/contract/error generation |
        | [`generator-hydrator`](https://github.com/php-openapi-tools/generator-hydrator) | Hydrator generator built on these utilities |
        | [`generator`](https://github.com/php-openapi-tools/generator) | CLI and run loop that orchestrates all generators |
        TEXT,
    ]),
    new SectionBlock('Contributing', ['Please see [CONTRIBUTING](CONTRIBUTING.md) for details.']),
    new SectionBlock('License', [
        <<<'TEXT'
        The MIT License (MIT)

        Copyright (c) 2026 Cees-Jan Kiewiet

        Permission is hereby granted, free of charge, to any person obtaining a copy
        of this software and associated documentation files (the "Software"), to deal
        in the Software without restriction, including without limitation the rights
        to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
        copies of the Software, and to permit persons to whom the Software is
        furnished to do so, subject to the following conditions:

        The above copyright notice and this permission notice shall be included in all
        copies or substantial portions of the Software.

        THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
        IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
        FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
        AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
        LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
        OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
        SOFTWARE.
        TEXT,
    ]),
]);
