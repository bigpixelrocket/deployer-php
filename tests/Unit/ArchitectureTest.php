<?php

declare(strict_types=1);

use Bigpixelrocket\DeployerPHP\Contracts\BaseCommand;

//
// Architecture tests
// -------------------------------------------------------------------------------

arch('commands extend BaseCommand', function () {
    expect('Bigpixelrocket\\DeployerPHP\\Console\\')
        ->classes()
        ->toHaveSuffix('Command')
        ->toExtend(BaseCommand::class);
});

arch('base command contract', function () {
    expect(BaseCommand::class)
        ->toBeAbstract()
        ->toExtend(\Symfony\Component\Console\Command\Command::class)
        ->toHaveConstructor();
});

arch('commands expose Symfony metadata', function () {
    expect('Bigpixelrocket\\DeployerPHP\\Console\\')
        ->classes()
        ->toHaveAttribute(\Symfony\Component\Console\Attribute\AsCommand::class);
});
