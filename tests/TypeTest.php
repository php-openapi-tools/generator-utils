<?php

declare(strict_types=1);

namespace OpenAPITools\Tests\Generator\Utils;

use OpenAPITools\Generator\Utils\Type\DocBlockTag;
use OpenAPITools\Generator\Utils\Type\PropertyTypeResolver;
use OpenAPITools\Generator\Utils\Type\UnionTypeUtils;
use OpenAPITools\Representation\Namespaced\Property\Type;
use OpenAPITools\Tests\Generator\Utils\Fixture\RepresentationFixture;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use WyriHaximus\TestUtilities\TestCase;

final class TypeTest extends TestCase
{
    #[Test]
    public function buildUnionTypeFromScalarPayload(): void
    {
        self::assertSame(
            'string',
            UnionTypeUtils::buildUnionType(RepresentationFixture::type('string', 'string')),
        );
    }

    #[Test]
    public function buildUnionTypeFromUnionMembers(): void
    {
        $schema = RepresentationFixture::schema('Schema\\User');

        self::assertSame(
            'string|int|array|\\Vendor\\Package\\Schema\\User',
            UnionTypeUtils::buildUnionType(RepresentationFixture::type('union', [
                RepresentationFixture::type('string', 'string'),
                RepresentationFixture::type('string', 'int'),
                RepresentationFixture::type('array', []),
                RepresentationFixture::type('object', $schema),
                RepresentationFixture::type('union', [
                    RepresentationFixture::type('string', 'bool'),
                ]),
                RepresentationFixture::type('string', '  '),
                RepresentationFixture::type('string', 'string|int'),
                RepresentationFixture::type('string', '|'),
            ])),
        );
    }

    #[Test]
    public function buildUnionTypeIgnoresEmptyUnionParts(): void
    {
        self::assertSame(
            'string',
            UnionTypeUtils::buildUnionType(RepresentationFixture::type('union', [
                RepresentationFixture::type('string', 'string|| |'),
            ])),
        );
    }

    #[Test]
    public function getUnionTypeSchemasFromNestedUnion(): void
    {
        $schemaA = RepresentationFixture::schema('Schema\\Alpha');
        $schemaB = RepresentationFixture::schema('Schema\\Beta');
        $type    = RepresentationFixture::type('union', [
            RepresentationFixture::type('object', $schemaA),
            RepresentationFixture::type('wrapper', RepresentationFixture::type('union', [
                RepresentationFixture::type('object', $schemaB),
            ])),
            RepresentationFixture::type('string', 'string'),
        ]);

        self::assertSame(
            [
                $schemaA,
                $schemaB,
            ],
            [...UnionTypeUtils::getUnionTypeSchemas($type)],
        );
    }

    #[Test]
    public function getUnionTypeSchemasReturnsNothingForScalarPayload(): void
    {
        self::assertSame([], [...UnionTypeUtils::getUnionTypeSchemas(RepresentationFixture::type('string', 'string'))]);
    }

    #[Test]
    public function resolvePropertyDocBlockForUnionType(): void
    {
        $resolved = PropertyTypeResolver::resolve(
            RepresentationFixture::property(
                'value',
                RepresentationFixture::type('union', [
                    RepresentationFixture::type('string', 'string'),
                    RepresentationFixture::type('string', 'int'),
                ]),
                true,
            ),
        );

        self::assertSame(['string|int'], $resolved->typeHints);
        self::assertSame('null|', $resolved->nullablePrefix);
        self::assertSame('@property null|string|int $value', $resolved->docBlockLine);
        self::assertSame('null|string|int', $resolved->typeHint());
    }

    #[Test]
    public function resolvePropertyDocBlockForSchemaAndScalarTypes(): void
    {
        $schema = RepresentationFixture::schema('Schema\\Item');

        $resolved = PropertyTypeResolver::resolve(
            RepresentationFixture::property(
                'item',
                RepresentationFixture::type('object', $schema),
                true,
            ),
        );

        self::assertSame(['\\Vendor\\Package\\Schema\\Item'], $resolved->typeHints);
        self::assertSame('?', $resolved->nullablePrefix);
        self::assertSame('@property ?\\Vendor\\Package\\Schema\\Item $item', $resolved->docBlockLine);
        self::assertSame('?\\Vendor\\Package\\Schema\\Item', $resolved->typeHint());
    }

    #[Test]
    public function resolvePropertyDocBlockForStringTypeWithoutTypesUsesBarePropertyTag(): void
    {
        $property = RepresentationFixture::property(
            'unknown',
            new Type('unknown', null, null, [], false),
        );

        $resolved = PropertyTypeResolver::resolve($property);

        self::assertSame([], $resolved->typeHints);
        self::assertSame('', $resolved->nullablePrefix);
        self::assertSame('@property $unknown', $resolved->docBlockLine);
        self::assertSame('', $resolved->typeHint());
    }

    #[Test]
    public function resolveParamDocBlockForArrayOfSchemaUnions(): void
    {
        $schemaA = RepresentationFixture::schema('Schema\\Alpha');
        $schemaB = RepresentationFixture::schema('Schema\\Beta');

        $resolved = PropertyTypeResolver::resolve(
            RepresentationFixture::property(
                'items',
                RepresentationFixture::type('array', [
                    RepresentationFixture::type('union', [
                        RepresentationFixture::type('object', $schemaA),
                        RepresentationFixture::type('object', $schemaB),
                    ]),
                ]),
                true,
            ),
            DocBlockTag::Param,
        );

        self::assertSame(['array'], $resolved->typeHints);
        self::assertSame('?', $resolved->nullablePrefix);
        self::assertSame(
            '@param ?array<\\Vendor\\Package\\Schema\\Alpha|\\Vendor\\Package\\Schema\\Beta> $items',
            $resolved->docBlockLine,
        );
        self::assertSame('?array', $resolved->typeHint());
    }

    #[Test]
    public function resolvePropertyDocBlockForArrayWithSchemaPayload(): void
    {
        $resolved = PropertyTypeResolver::resolve(
            RepresentationFixture::property(
                'items',
                RepresentationFixture::type('array', RepresentationFixture::schema('Schema\\Item')),
            ),
        );

        self::assertSame('@property array $items', $resolved->docBlockLine);
    }

    #[Test]
    public function resolvePropertyDocBlockForArrayOfSchemaClass(): void
    {
        $schema = RepresentationFixture::schema('Schema\\Item');

        $resolved = PropertyTypeResolver::resolve(
            RepresentationFixture::property(
                'items',
                RepresentationFixture::type('array', RepresentationFixture::type('object', $schema)),
            ),
        );

        self::assertSame(
            '@property array<\\Vendor\\Package\\Schema\\Item> $items',
            $resolved->docBlockLine,
        );
    }

    #[Test]
    public function resolvePropertyDocBlockForArrayOfUnionType(): void
    {
        $resolved = PropertyTypeResolver::resolve(
            RepresentationFixture::property(
                'items',
                RepresentationFixture::type('array', RepresentationFixture::type('union', [
                    RepresentationFixture::type('string', 'string'),
                    RepresentationFixture::type('string', 'int'),
                ])),
            ),
        );

        self::assertSame(
            '@property array<string|int> $items',
            $resolved->docBlockLine,
        );
    }

    #[Test]
    public function resolvePropertyDocBlockForArrayOfScalarClass(): void
    {
        $resolved = PropertyTypeResolver::resolve(
            RepresentationFixture::property(
                'tags',
                RepresentationFixture::type('array', RepresentationFixture::type('string', 'string')),
            ),
        );

        self::assertSame(
            '@property array<string> $tags',
            $resolved->docBlockLine,
        );
    }

    #[Test]
    public function resolvePropertyDocBlockSkipsNestedIterableType(): void
    {
        $resolved = PropertyTypeResolver::resolve(
            RepresentationFixture::property(
                'items',
                RepresentationFixture::type('array', RepresentationFixture::type('array', RepresentationFixture::type('string', 'string'))),
            ),
        );

        self::assertSame('@property array $items', $resolved->docBlockLine);
    }

    #[Test]
    public function resolvePropertyDocBlockSkipsArrayPayloadWithoutSchemas(): void
    {
        $resolved = PropertyTypeResolver::resolve(
            RepresentationFixture::property(
                'items',
                RepresentationFixture::type('array', [
                    RepresentationFixture::type('string', 'string'),
                ]),
            ),
        );

        self::assertSame('@property array $items', $resolved->docBlockLine);
    }

    #[Test]
    public function resolveParamDocBlockDoesNotGeneratePropertyLineWhenOnlyTypesExist(): void
    {
        $resolved = PropertyTypeResolver::resolve(
            RepresentationFixture::property(
                'value',
                RepresentationFixture::type('string', 'string'),
            ),
            DocBlockTag::Param,
        );

        self::assertSame(['string'], $resolved->typeHints);
        self::assertSame('', $resolved->docBlockLine);
        self::assertSame('string', $resolved->typeHint());
    }

    #[Test]
    public function resolveArrayDocBlockLineReturnsNullForStringArrayPayload(): void
    {
        $method = new ReflectionMethod(PropertyTypeResolver::class, 'resolveArrayDocBlockLine');

        self::assertNull($method->invoke(null, RepresentationFixture::property(
            'items',
            RepresentationFixture::type('array', 'string'),
        )));
    }

    #[Test]
    public function resolveArrayDocBlockLineReturnsNullForNonArrayPayload(): void
    {
        $method = new ReflectionMethod(PropertyTypeResolver::class, 'resolveArrayDocBlockLine');

        self::assertNull($method->invoke(null, RepresentationFixture::property(
            'items',
            RepresentationFixture::type('array', RepresentationFixture::schema('Schema\\Item')),
        )));
    }
}
