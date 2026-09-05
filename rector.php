<?php

use Rector\CodeQuality\Rector\Stmt\DeclareStrictTypesRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/bootstrap',
        __DIR__.'/config',
        __DIR__.'/database',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ])
    ->withPreparedSets(deadCode: true, codeQuality: true, earlyReturn: true)
    ->withPhpSets()
    ->withSkip([DeclareStrictTypesRector::class]);
