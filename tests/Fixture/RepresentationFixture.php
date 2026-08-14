<?php

declare(strict_types=1);

namespace OpenAPITools\Tests\Generator\Utils\Fixture;

use cebe\openapi\spec\Schema;
use OpenAPITools\Representation\ExampleData;
use OpenAPITools\Representation\Namespaced;
use OpenAPITools\Utils\ClassString;
use OpenAPITools\Utils\Namespace_;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Name;

final class RepresentationFixture
{
    public static function namespace(): Namespace_
    {
        return new Namespace_('Vendor\\Package', 'Vendor\\Package\\Tests');
    }

    public static function schema(string $relative = 'Schema\\Example'): Namespaced\Schema
    {
        $namespace = self::namespace();

        return new Namespaced\Schema(
            ClassString::factory($namespace, $relative),
            [],
            ClassString::factory($namespace, 'Schema\\Error\\Example'),
            ClassString::factory($namespace, 'Schema\\ErrorAlias\\Example'),
            'title',
            'description',
            [],
            [],
            new Schema([]),
            false,
            [],
            [],
        );
    }

    /** @param string|Namespaced\Schema|Namespaced\Property\Type|array<Namespaced\Property\Type> $payload */
    public static function type(
        string $type,
        string|Namespaced\Schema|Namespaced\Property\Type|array $payload,
    ): Namespaced\Property\Type {
        return new Namespaced\Property\Type(
            $type,
            null,
            null,
            $payload,
            false,
        );
    }

    public static function property(
        string $name,
        Namespaced\Property\Type $type,
        bool $nullable = false,
    ): Namespaced\Property {
        return new Namespaced\Property(
            $name,
            $name,
            'description',
            new ExampleData(
                null,
                new ConstFetch(new Name('NULL')),
            ),
            $type,
            $nullable,
            [],
        );
    }
}
