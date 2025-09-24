<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Console;

use Bigpixelrocket\DeployerPHP\Services\EnvService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'hello', description: 'Display a friendly hello message')]
class HelloCommand extends Command
{
    private SymfonyStyle $io;

    public function __construct(
        private readonly EnvService $envService,
    ) {
        parent::__construct();
    }

    /**
     * The main execution method in Symfony commands.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $user = $this->envService->get(['USER', 'USERNAME'], false) ?? 'there';
        $this->io->text("Hello {$user}!");

        return Command::SUCCESS;
    }
}
