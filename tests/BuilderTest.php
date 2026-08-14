<?php

declare(strict_types=1);

namespace OpenAPITools\Tests\Generator\Utils;

use OpenAPITools\Generator\Utils\Builder\DocBlockBuilder;
use OpenAPITools\Generator\Utils\Builder\ExpressionBuilder;
use OpenAPITools\Generator\Utils\Builder\HydrationBuilder;
use OpenAPITools\Generator\Utils\Builder\MatchBuilder;
use OpenAPITools\Generator\Utils\Builder\NamespaceBuilder;
use OpenAPITools\Generator\Utils\Builder\StatementBuilder;
use OpenAPITools\Utils\ClassString;
use OpenAPITools\Utils\File;
use OpenAPITools\Utils\Namespace_;
use PhpParser\BuilderFactory;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PhpParser\PrettyPrinter\Standard;
use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\TestUtilities\TestCase;

final class BuilderTest extends TestCase
{
    private Standard $printer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->printer = new Standard();
    }

    #[Test]
    public function docBlockBuilderFormatsLines(): void
    {
        self::assertSame(
            "/**\n * @property string \$name\n * @property int \$count\n */",
            DocBlockBuilder::fromLines(['@property string $name', '@property int $count']),
        );
    }

    #[Test]
    public function docBlockBuilderStripsExistingDocBlockMarkers(): void
    {
        self::assertStringContainsString(
            '@property string $name',
            DocBlockBuilder::fromDocLines(['/** @property string $name */']),
        );
    }

    #[Test]
    public function namespaceBuilderCreatesClassFile(): void
    {
        $builderFactory = new BuilderFactory();
        $classString    = ClassString::factory(new Namespace_('Vendor\\Package', 'Vendor\\Package\\Tests'), 'Internal\\Example');
        $classNode      = $builderFactory->class($classString->className)->makeFinal()->getNode();
        $builder        = new NamespaceBuilder($builderFactory);
        $file           = $builder->classFile(
            'src',
            $classString,
            $classNode,
            File::DO_NOT_LOAD_ON_WRITE,
        );
        $loadedFile     = $builder->classFile(
            'src',
            $classString,
            $classNode,
            File::DO_LOAD_ON_WRITE,
        );

        self::assertSame('src', $file->pathPrefix);
        self::assertSame('Internal\\Example', $file->fqcn);
        self::assertFalse($file->loadOnWrite);
        self::assertTrue($loadedFile->loadOnWrite);
        self::assertInstanceOf(Node::class, $file->contents);
        self::assertStringContainsString(
            'namespace Vendor\\Package\\Internal;',
            $this->printer->prettyPrint([$file->contents]),
        );
        self::assertStringContainsString('final class Example', $this->printer->prettyPrint([$file->contents]));
        self::assertSame(
            $this->printer->prettyPrint([$file->contents]),
            $this->printer->prettyPrint([$builder->namespaceNode($classString->namespace->source, $classNode)]),
        );
    }

    #[Test]
    public function expressionBuilderCreatesLazyInit(): void
    {
        $if = ExpressionBuilder::lazyInitIfNotInstance('mapper', '\\Vendor\\Mapper');

        self::assertStringContainsString(
            '$this->mapper = new \\Vendor\\Mapper',
            $this->printer->prettyPrint([$if]),
        );
    }

    #[Test]
    public function expressionBuilderCreatesAccessors(): void
    {
        self::assertSame('$this->mapper', $this->printer->prettyPrintExpr(ExpressionBuilder::thisProperty('mapper')));
        self::assertSame('$this->run($input)', $this->printer->prettyPrintExpr(ExpressionBuilder::thisMethod('run', ['input'])));
        self::assertSame('\\Vendor\\Foo::class', $this->printer->prettyPrintExpr(ExpressionBuilder::classConstant('\\Vendor\\Foo')));
        self::assertSame('$data[\'id\']', $this->printer->prettyPrintExpr(ExpressionBuilder::arrayFetch('data', 'id')));
        self::assertSame(
            '$data[$index]',
            $this->printer->prettyPrintExpr(ExpressionBuilder::arrayFetchKey('data', ExpressionBuilder::var('index'))),
        );
        self::assertSame(
            '$data[$index]',
            $this->printer->prettyPrintExpr(ExpressionBuilder::arrayFetchKey(ExpressionBuilder::var('data'), ExpressionBuilder::var('index'))),
        );
        self::assertSame(
            '$receiver->run($input)',
            $this->printer->prettyPrintExpr(ExpressionBuilder::methodCall(ExpressionBuilder::var('receiver'), 'run', ['input'])),
        );
        self::assertSame(
            '$className::class',
            $this->printer->prettyPrintExpr(ExpressionBuilder::objectClassConstant('className')),
        );
    }

    #[Test]
    public function expressionBuilderCreatesLiteralsAndNullSafeTernary(): void
    {
        self::assertSame('null', $this->printer->prettyPrintExpr(ExpressionBuilder::null()));
        self::assertSame('true', $this->printer->prettyPrintExpr(ExpressionBuilder::true()));
        self::assertSame('false', $this->printer->prettyPrintExpr(ExpressionBuilder::false()));
        self::assertSame('42', $this->printer->prettyPrintExpr(ExpressionBuilder::literalInt(42)));
        self::assertSame(
            '$value === null ? null : $this->value',
            $this->printer->prettyPrintExpr(ExpressionBuilder::nullSafe(
                ExpressionBuilder::var('value'),
                ExpressionBuilder::thisProperty('value'),
            )),
        );
    }

    #[Test]
    public function expressionBuilderCreatesCallsAndThrows(): void
    {
        self::assertSame(
            'strlen($value)',
            $this->printer->prettyPrintExpr(ExpressionBuilder::funcCall('strlen', ['value'])),
        );
        self::assertSame(
            '$callback($value)',
            $this->printer->prettyPrintExpr(ExpressionBuilder::invoke(ExpressionBuilder::var('callback'), ['value'])),
        );
        self::assertSame(
            'new \\Vendor\\Foo($value)',
            $this->printer->prettyPrintExpr(ExpressionBuilder::newInstance('\\Vendor\\Foo', ['value'])),
        );
        self::assertSame(
            'throw new \\RuntimeException(\'missing\');',
            $this->printer->prettyPrint([ExpressionBuilder::throwRuntimeException('missing')]),
        );
        self::assertSame(
            'throw new \\InvalidArgumentException($message);',
            $this->printer->prettyPrint([ExpressionBuilder::throwNew('\\InvalidArgumentException', ['message'])]),
        );
        self::assertSame(
            'return $this->mapper;',
            $this->printer->prettyPrint([ExpressionBuilder::returnProperty('mapper')]),
        );
    }

    #[Test]
    public function expressionBuilderCreatesCatchAndLazyInitWithArguments(): void
    {
        self::assertSame(
            'catch (\\Throwable $throwable) {
    $error = $throwable;
}',
            $this->printer->prettyPrint([ExpressionBuilder::catchThrowable('throwable', 'error')]),
        );
        self::assertStringContainsString(
            'new \\Vendor\\Mapper($config)',
            $this->printer->prettyPrint([ExpressionBuilder::lazyInitIfNotInstance('mapper', '\\Vendor\\Mapper', ExpressionBuilder::args(['config']))]),
        );
        $args = ExpressionBuilder::args([new Arg(ExpressionBuilder::var('value')), 'other']);

        self::assertCount(2, $args);
        self::assertInstanceOf(Expr\Variable::class, $args[0]->value);
        self::assertSame('value', $args[0]->value->name);
        self::assertInstanceOf(Expr\Variable::class, $args[1]->value);
        self::assertSame('other', $args[1]->value->name);
    }

    #[Test]
    public function expressionBuilderCombinesConditions(): void
    {
        $left  = ExpressionBuilder::literalString('a');
        $right = ExpressionBuilder::literalString('b');

        self::assertSame(
            '\'a\' === \'a\' && \'b\' === \'b\'',
            $this->printer->prettyPrintExpr(ExpressionBuilder::andAll([
                ExpressionBuilder::identical($left, $left),
                ExpressionBuilder::identical($right, $right),
            ])),
        );
        self::assertSame(
            '\'a\' === \'a\' || \'b\' === \'b\'',
            $this->printer->prettyPrintExpr(ExpressionBuilder::orAll([
                ExpressionBuilder::identical($left, $left),
                ExpressionBuilder::identical($right, $right),
            ])),
        );
    }

    #[Test]
    public function statementBuilderCreatesAssignAndYieldForeach(): void
    {
        self::assertSame(
            '$value = \'test\';',
            $this->printer->prettyPrint([StatementBuilder::assign('value', ExpressionBuilder::literalString('test'))]),
        );
        self::assertSame(
            '$this->mapper = $mapper;',
            $this->printer->prettyPrint([StatementBuilder::assign(ExpressionBuilder::thisProperty('mapper'), ExpressionBuilder::var('mapper'))]),
        );
        self::assertStringContainsString(
            'foreach ($items as $index => $item) {',
            $this->printer->prettyPrint([StatementBuilder::yieldMapOverIterable('items', 'item', 'index', 'map', ['item'])]),
        );
        self::assertStringContainsString(
            'yield $index => $this->map($item);',
            $this->printer->prettyPrint([StatementBuilder::yieldMapOverIterable('items', 'item', 'index', 'map', ['item'])]),
        );
        self::assertSame(
            'try {
    return $value;
} catch (\\Throwable) {
}',
            $this->printer->prettyPrint([StatementBuilder::tryReturnIgnoringThrowable(new Node\Stmt\Return_(ExpressionBuilder::var('value')))]),
        );
        self::assertSame(
            'throw $error;',
            $this->printer->prettyPrint([StatementBuilder::throwVariable('error')]),
        );
        self::assertSame(
            'return $receiver->run($input);',
            $this->printer->prettyPrint([StatementBuilder::returnMethodCall('receiver', 'run', ['input'])]),
        );
    }

    #[Test]
    public function matchBuilderCreatesDefaultArm(): void
    {
        $match = MatchBuilder::onVariable('className', [
            [
                'conditions' => [new Scalar\String_('\\Vendor\\Foo')],
                'body' => new Expr\Variable('foo'),
            ],
        ], new Expr\Throw_(ExpressionBuilder::newRuntimeException('missing')));

        self::assertCount(2, $match->arms);
        self::assertNull($match->arms[1]->conds);
        self::assertStringContainsString(
            'default => throw new \\RuntimeException(\'missing\')',
            $this->printer->prettyPrintExpr($match),
        );
    }

    #[Test]
    public function matchBuilderCreatesStringClassNameArms(): void
    {
        $match = MatchBuilder::onClassNameStrings('className', [
            [
                'classNames' => ['\\Vendor\\Foo', '\\Vendor\\Bar'],
                'body' => ExpressionBuilder::thisMethod('handle'),
            ],
        ]);

        self::assertCount(1, $match->arms);
        self::assertNotNull($match->arms[0]->conds);
        self::assertCount(2, $match->arms[0]->conds);
        self::assertStringContainsString(
            '\'\\Vendor\\Foo\', \'\\Vendor\\Bar\' => $this->handle()',
            $this->printer->prettyPrintExpr($match),
        );

        $matchWithDefault = MatchBuilder::onClassNameStrings(
            'className',
            [
                [
                    'classNames' => ['\\Vendor\\Foo'],
                    'body' => new Expr\Variable('foo'),
                ],
            ],
            new Expr\Throw_(ExpressionBuilder::newRuntimeException('missing')),
        );

        self::assertCount(2, $matchWithDefault->arms);
        self::assertNull($matchWithDefault->arms[1]->conds);
    }

    #[Test]
    public function hydrationBuilderCreatesHydrateCall(): void
    {
        self::assertSame(
            '$hydrator->hydrate(\\Vendor\\Foo::class, $data)',
            $this->printer->prettyPrintExpr(HydrationBuilder::hydrateCall(
                '\\Vendor\\Foo',
                ExpressionBuilder::var('data'),
                ExpressionBuilder::var('hydrator'),
            )),
        );
        self::assertSame(
            '$hydrator->fromArray(\\Vendor\\Foo::class, $data)',
            $this->printer->prettyPrintExpr(HydrationBuilder::hydrateCall(
                '\\Vendor\\Foo',
                ExpressionBuilder::var('data'),
                ExpressionBuilder::var('hydrator'),
                'fromArray',
            )),
        );
    }
}
