<?php

declare(strict_types=1);

use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;
use WyriHaximus\TestUtilities\RectorConfig;

return RectorConfig::configure(dirname(__DIR__, 2))
    ->withSkip([
        StringClassNameToClassConstantRector::class => [
            __DIR__ . '/../../src/Hydrator.php',
        ],
    ]);
