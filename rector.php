<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/bin',
        __DIR__.'/tests',
    ])
    ->withCache(sys_get_temp_dir() . '/rector')
    ->withPhpSets();
