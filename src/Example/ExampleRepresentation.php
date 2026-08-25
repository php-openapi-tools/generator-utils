<?php

declare(strict_types=1);

namespace OpenAPITools\Generator\Utils\Example;

use cebe\openapi\spec\Schema as OpenApiSchema;
use OpenAPITools\Representation\ExampleData;
use OpenAPITools\Representation\Namespaced;
use OpenAPITools\Representation\Namespaced\Property\Type;
use OpenAPITools\Utils\ClassString;
use OpenAPITools\Utils\Namespace_;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Name;

/** Documentation example that maps schema metadata to a namespaced representation. */
final class ExampleRepresentation
{
    public static function create(Namespace_ $namespace): Namespaced\Representation
    {
        $basicSchema = ClassString::factory($namespace, 'Schema\\Basic');
        $contract    = new Namespaced\Contract(
            ClassString::factory($namespace, 'Contract\\Basic'),
            [
                self::property(
                    'id',
                    new Type('string', 'uuid', null, 'string', false),
                ),
                self::property(
                    'name',
                    new Type('string', null, null, 'string', false),
                ),
            ],
        );

        return new Namespaced\Representation(
            new Namespaced\Client('http://api.example.com/v1/', []),
            [],
            [
                new Namespaced\Schema(
                    $basicSchema,
                    [$contract],
                    ClassString::factory($namespace, 'Error\\Basic'),
                    ClassString::factory($namespace, 'Error\\Basic'),
                    '',
                    '',
                    [
                        'id' => '4ccda740-74c3-4cfa-8571-ebf83c8f300a',
                        'name' => 'generated',
                    ],
                    $contract->properties,
                    new OpenApiSchema([
                        'type' => 'object',
                        'required' => ['id', 'name'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'],
                            'name' => ['type' => 'string'],
                        ],
                    ]),
                    false,
                    [],
                    [],
                ),
            ],
        );
    }

    private static function property(string $name, Type $type): Namespaced\Property
    {
        return new Namespaced\Property(
            $name,
            $name,
            '',
            new ExampleData(null, new ConstFetch(new Name('NULL'))),
            $type,
            false,
            [],
        );
    }
}
