<?php

declare(strict_types=1);

namespace OpenAPITools\Generator\Utils\Builder;

use OpenAPITools\Utils\ClassString;
use OpenAPITools\Utils\File;
use PhpParser\BuilderFactory;
use PhpParser\Node;

/** @api */
final readonly class NamespaceBuilder
{
    public function __construct(
        private BuilderFactory $builderFactory,
    ) {
    }

    public function namespaceNode(string $namespace, Node ...$statements): Node\Stmt\Namespace_
    {
        $namespaceBuilder = $this->builderFactory->namespace($namespace);

        foreach ($statements as $statement) {
            $namespaceBuilder->addStmt($statement);
        }

        return $namespaceBuilder->getNode();
    }

    public function classFile(string $pathPrefix, ClassString $className, Node $classNode, bool $loadOnWrite = File::DO_LOAD_ON_WRITE): File
    {
        return new File(
            $pathPrefix,
            $className->relative,
            $this->namespaceNode($className->namespace->source, $classNode),
            $loadOnWrite,
        );
    }
}
