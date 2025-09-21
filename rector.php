<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withCache('/tmp/rector')
    ->withPhpSets()
    ->withSkip([
        __DIR__.'/tests/CICanary.php',
    ]);
