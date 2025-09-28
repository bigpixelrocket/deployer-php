<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Console;

use Bigpixelrocket\DeployerPHP\Contracts\BaseCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'hello', description: 'Display a friendly hello message')]
class HelloCommand extends BaseCommand
{
    /**
     * The main execution method in Symfony commands.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $user = $this->env->get(['USER', 'USERNAME'], false) ?? 'there';

        $this->io->success('Hello ' . $user . '!');

        return Command::SUCCESS;
    }
}
