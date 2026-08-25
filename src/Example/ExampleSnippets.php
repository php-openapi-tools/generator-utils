<?php

declare(strict_types=1);

namespace OpenAPITools\Generator\Utils\Example;

use OpenAPITools\Generator\Utils\Builder\DocBlockBuilder;
use OpenAPITools\Generator\Utils\Builder\ExpressionBuilder;
use OpenAPITools\Generator\Utils\Builder\HydrationBuilder;
use OpenAPITools\Generator\Utils\Builder\MatchBuilder;
use OpenAPITools\Generator\Utils\Builder\NamespaceBuilder;
use OpenAPITools\Generator\Utils\Builder\StatementBuilder;
use OpenAPITools\Generator\Utils\FileStringyfier;
use OpenAPITools\Generator\Utils\Type\DocBlockTag;
use OpenAPITools\Generator\Utils\Type\PropertyTypeResolver;
use OpenAPITools\Representation\Namespaced;
use OpenAPITools\Utils\ClassString;
use PhpParser\BuilderFactory;
use PhpParser\Node\Expr;
use PhpParser\PrettyPrinter\Standard;
use RuntimeException;

use function array_key_exists;
use function array_map;
use function array_values;
use function implode;
use function str_replace;

/** Documentation examples that render generator-utils builder output. */
final class ExampleSnippets
{
    /** @return list<ExampleSnippet> */
    public static function fromRepresentation(Namespaced\Representation $representation): array
    {
        $basicSchema = self::basicSchema($representation);
        $printer     = new Standard();
        $snippets    = [];

        $propertyLines = [];
        foreach ($basicSchema->properties as $property) {
            $resolved = PropertyTypeResolver::resolve($property, DocBlockTag::Property);
            if ($resolved->docBlockLine === '' || array_key_exists($property->name, $propertyLines)) {
                continue;
            }

            $propertyLines[$property->name] = $resolved->docBlockLine;
        }

        $snippets[] = new ExampleSnippet(
            'PropertyTypeResolver',
            'Resolving OpenAPI property types from the `basic` schema yields these PHPDoc lines:',
            <<<'PHP'
            use OpenAPITools\Generator\Utils\Type\DocBlockTag;
            use OpenAPITools\Generator\Utils\Type\PropertyTypeResolver;

            $resolved = PropertyTypeResolver::resolve($property, DocBlockTag::Property);
            $resolved->docBlockLine;
            PHP,
            implode("\n", array_values($propertyLines)),
        );

        $docBlockLineEntries = implode(",\n    ", array_map(
            static fn (string $line): string => "'" . str_replace("'", "\\'", $line) . "'",
            array_values($propertyLines),
        ));
        $snippets[]          = new ExampleSnippet(
            'DocBlockBuilder',
            '`DocBlockBuilder::fromLines()` turns those lines into a PHPDoc block:',
            <<<PHP
            use OpenAPITools\Generator\Utils\Builder\DocBlockBuilder;

            DocBlockBuilder::fromLines([
                {$docBlockLineEntries},
            ]);
            PHP,
            DocBlockBuilder::fromLines(array_values($propertyLines)),
        );

        $className = $basicSchema->className->fullyQualified->source;
        $match     = MatchBuilder::onClassNameStrings('className', [
            [
                'classNames' => [$className],
                'body'       => HydrationBuilder::hydrateCall(
                    $className,
                    ExpressionBuilder::var('data'),
                    ExpressionBuilder::var('hydrator'),
                ),
            ],
        ], new Expr\Throw_(ExpressionBuilder::newRuntimeException('Unknown schema')));

        $snippets[] = new ExampleSnippet(
            'MatchBuilder and HydrationBuilder',
            'Routing hydration by class name produces a `match` expression with a hydrate call:',
            <<<PHP
            use OpenAPITools\Generator\Utils\Builder\ExpressionBuilder;
            use OpenAPITools\Generator\Utils\Builder\HydrationBuilder;
            use OpenAPITools\Generator\Utils\Builder\MatchBuilder;
            use PhpParser\Node\Expr;

            MatchBuilder::onClassNameStrings('className', [
                [
                    'classNames' => ['{$className}'],
                    'body' => HydrationBuilder::hydrateCall(
                        '{$className}',
                        ExpressionBuilder::var('data'),
                        ExpressionBuilder::var('hydrator'),
                    ),
                ],
            ], new Expr\Throw_(ExpressionBuilder::newRuntimeException('Unknown schema')));
            PHP,
            'return ' . $printer->prettyPrintExpr($match) . ';',
        );

        $snippets[] = new ExampleSnippet(
            'ExpressionBuilder',
            'Lazy property initialization as used by hydrator facades:',
            <<<'PHP'
            use OpenAPITools\Generator\Utils\Builder\ExpressionBuilder;

            ExpressionBuilder::lazyInitIfNotInstance(
                'operationRoot',
                '\ApiClients\Client\Example\Internal\Hydrator\Operation\Root',
            );
            ExpressionBuilder::returnProperty('operationRoot');
            PHP,
            $printer->prettyPrint([
                ExpressionBuilder::lazyInitIfNotInstance(
                    'operationRoot',
                    '\\ApiClients\\Client\\Example\\Internal\\Hydrator\\Operation\\Root',
                ),
                ExpressionBuilder::returnProperty('operationRoot'),
            ]),
        );

        $snippets[] = new ExampleSnippet(
            'StatementBuilder',
            'Mapping over iterables for batch hydrate/serialize helpers:',
            <<<'PHP'
            use OpenAPITools\Generator\Utils\Builder\StatementBuilder;

            StatementBuilder::yieldMapOverIterable(
                'payloads',
                'payload',
                'index',
                'hydrateObject',
                ['className', 'payload'],
            );
            PHP,
            $printer->prettyPrint([
                StatementBuilder::yieldMapOverIterable('payloads', 'payload', 'index', 'hydrateObject', ['className', 'payload']),
            ]),
        );

        $builderFactory   = new BuilderFactory();
        $namespaceBuilder = new NamespaceBuilder($builderFactory);
        $classString      = ClassString::factory($basicSchema->className->baseNamespace, 'Internal\\Example');
        $classNode        = $builderFactory->class($classString->className)->makeFinal()->getNode();
        $file             = $namespaceBuilder->classFile('src', $classString, $classNode);

        $snippets[] = new ExampleSnippet(
            'NamespaceBuilder and FileStringyfier',
            'Wrapping a class node in a namespace `File` and pretty-printing with `strict_types`:',
            <<<'PHP'
            use OpenAPITools\Generator\Utils\Builder\NamespaceBuilder;
            use OpenAPITools\Generator\Utils\FileStringyfier;
            use OpenAPITools\Utils\ClassString;
            use PhpParser\BuilderFactory;
            use PhpParser\PrettyPrinter\Standard;

            $builderFactory = new BuilderFactory();
            $namespaceBuilder = new NamespaceBuilder($builderFactory);
            $classString = ClassString::factory($namespace, 'Internal\Example');
            $classNode = $builderFactory->class($classString->className)->makeFinal()->getNode();
            $file = $namespaceBuilder->classFile('src', $classString, $classNode);

            new FileStringyfier(new Standard())->toString($file);
            PHP,
            new FileStringyfier($printer)->toString($file),
        );

        return $snippets;
    }

    private static function basicSchema(Namespaced\Representation $representation): Namespaced\Schema
    {
        foreach ($representation->schemas as $schema) {
            if ($schema->className->className === 'Basic') {
                return $schema;
            }
        }

        throw new RuntimeException('OpenAPI document has no basic schema.');
    }
}
