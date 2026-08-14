<?php

declare(strict_types=1);

namespace OpenAPITools\Generator\Utils\Type;

use OpenAPITools\Representation;
use RuntimeException;

use function array_unique;
use function array_values;
use function count;
use function explode;
use function implode;
use function is_array;
use function is_string;

/** @api */
final class PropertyTypeResolver
{
    public static function resolve(Representation\Namespaced\Property $property, DocBlockTag $docBlockTag = DocBlockTag::Property): ResolvedPropertyType
    {
        $types        = [];
        $docBlockLine = null;

        if ($property->type->type === 'union' && is_array($property->type->payload)) {
            $types[] = UnionTypeUtils::buildUnionType($property->type);
        }

        if ($property->type->type === 'array' && ! is_string($property->type->payload)) {
            $arrayDocBlockLine = self::resolveArrayDocBlockLine($property);
            if ($arrayDocBlockLine !== null) {
                $docBlockLine = match ($docBlockTag) {
                    DocBlockTag::Property => '@property ' . $arrayDocBlockLine . ' $' . $property->name,
                    DocBlockTag::Param => '@param ' . $arrayDocBlockLine . ' $' . $property->name,
                };
            }

            $types[] = 'array';
        } elseif ($property->type->payload instanceof Representation\Namespaced\Schema) {
            $types[] = $property->type->payload->className->fullyQualified->source;
        } elseif (is_string($property->type->payload)) {
            $types[] = $property->type->payload;
        }

        /** @var list<string> $types */
        $types = array_values(array_unique($types));

        $nullablePrefix = '';
        if ($property->nullable) {
            $nullablePrefix = count($types) > 1 || count(explode('|', implode('|', $types))) > 1 ? 'null|' : '?';
        }

        if ($docBlockLine === null && count($types) > 0) {
            $docBlockLine = match ($docBlockTag) {
                DocBlockTag::Property => '@property ' . $nullablePrefix . implode('|', $types) . ' $' . $property->name,
                DocBlockTag::Param => null,
            };
        }

        if ($docBlockLine === null && count($types) === 0 && $docBlockTag === DocBlockTag::Property) {
            $docBlockLine = '@property $' . $property->name;
        }

        return new ResolvedPropertyType($types, $nullablePrefix, $docBlockLine ?? '');
    }

    private static function resolveArrayDocBlockLine(Representation\Namespaced\Property $property): string|null
    {
        if ($property->type->type !== 'array' || is_string($property->type->payload)) {
            return null;
        }

        if ($property->type->payload instanceof Representation\Namespaced\Property\Type) {
            if ($property->type->payload->payload instanceof Representation\Namespaced\Property\Type) {
                return null;
            }

            $iterableTypeNode     = $property->type->payload;
            $compiledIterableType = null;

            if ($iterableTypeNode->payload instanceof Representation\Namespaced\Schema) {
                $compiledIterableType = $iterableTypeNode->payload->className->fullyQualified->source;
            }

            $remainingIterableType = $compiledIterableType === null ? $iterableTypeNode : null;

            if ($remainingIterableType instanceof Representation\Namespaced\Property\Type && (($remainingIterableType->payload instanceof Representation\Namespaced\Property\Type && $remainingIterableType->payload->type === 'union') || is_array($remainingIterableType->payload))) {
                $compiledIterableType  = UnionTypeUtils::buildUnionType($remainingIterableType);
                $remainingIterableType = null;
            }

            if ($remainingIterableType instanceof Representation\Namespaced\Property\Type) {
                $payload = $remainingIterableType->payload;
                if (is_string($payload)) {
                    $compiledIterableType = $payload;
                }
            }

            if (! is_string($compiledIterableType)) {
                throw new RuntimeException('At this point $compiledIterableType should be a string');
            }

            return ($property->nullable ? '?' : '') . 'array<' . $compiledIterableType . '>';
        }

        if (! is_array($property->type->payload)) {
            return null;
        }

        $schemaClasses = [];
        foreach ($property->type->payload as $payloadType) {
            $schemaClasses = [...$schemaClasses, ...UnionTypeUtils::getUnionTypeSchemas($payloadType)];
        }

        if (count($schemaClasses) === 0) {
            return null;
        }

        return ($property->nullable ? '?' : '') . 'array<' . implode('|', array_unique([
            ...(static function (Representation\Namespaced\Schema ...$schemas): iterable {
                foreach ($schemas as $schema) {
                    yield $schema->className->fullyQualified->source;
                }
            })(...$schemaClasses),
        ])) . '>';
    }
}
