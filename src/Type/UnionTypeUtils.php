<?php

declare(strict_types=1);

namespace OpenAPITools\Generator\Utils\Type;

use OpenAPITools\Representation;

use function array_filter;
use function array_unique;
use function array_values;
use function explode;
use function gettype;
use function implode;
use function is_array;
use function is_string;
use function trim;

/** @api */
final class UnionTypeUtils
{
    public static function buildUnionType(Representation\Namespaced\Property\Type $type): string
    {
        $typeList = [];
        if (is_array($type->payload)) {
            foreach ($type->payload as $typeInUnion) {
                $typeList[] = match (gettype($typeInUnion->payload)) {
                    'string' => $typeInUnion->payload,
                    'array' => 'array',
                    'object' => match ($typeInUnion->payload::class) {
                        Representation\Namespaced\Schema::class => $typeInUnion->payload->className->fullyQualified->source,
                        Representation\Namespaced\Property\Type::class => self::buildUnionType($typeInUnion->payload),
                    },
                };
            }
        } else {
            $typeList[] = $type->payload;
        }

        return implode(
            '|',
            self::uniqueTypeParts(
                array_values(
                    array_filter(
                        array_filter(
                            $typeList,
                            is_string(...),
                        ),
                        static fn (string $item): bool => trim($item) !== '',
                    ),
                ),
            ),
        );
    }

    /**
     * @param list<string> $typeList
     *
     * @return list<string>
     */
    private static function uniqueTypeParts(array $typeList): array
    {
        $typeParts = [];

        foreach ($typeList as $type) {
            foreach (explode('|', $type) as $typePart) {
                $typePart = trim($typePart);
                if ($typePart === '') {
                    continue;
                }

                $typeParts[] = $typePart;
            }
        }

        return array_values(array_unique($typeParts));
    }

    /** @return iterable<Representation\Namespaced\Schema> */
    public static function getUnionTypeSchemas(Representation\Namespaced\Property\Type $type): iterable
    {
        if (! is_array($type->payload)) {
            return;
        }

        foreach ($type->payload as $typeInUnion) {
            if ($typeInUnion->payload instanceof Representation\Namespaced\Schema) {
                yield $typeInUnion->payload;
            }

            if (! ($typeInUnion->payload instanceof Representation\Namespaced\Property\Type)) {
                continue;
            }

            yield from self::getUnionTypeSchemas($typeInUnion->payload);
        }
    }
}
