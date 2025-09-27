<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Contracts;

use Bigpixelrocket\DeployerPHP\Container;
use Bigpixelrocket\DeployerPHP\Services\EnvService;
use Bigpixelrocket\DeployerPHP\Services\InventoryService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class BaseCommand extends Command
{
    protected SymfonyStyle $io;

    public function __construct(
        protected readonly Container $container,
        protected readonly EnvService $env,
        protected readonly InventoryService $inventory,
    ) {
        parent::__construct();
    }

    /**
     * The main execution method in Symfony commands.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $envStatus = $this->env->getEnvFileStatus();
        $inventoryStatus = $this->inventory->getInventoryFileStatus();

        $this->hr();
        $this->writeln([
            '',
            ' <fg=cyan>Environment:</> ',
            ' <fg=gray>'.$envStatus.'</>',
            '',
            ' <fg=cyan>Inventory:</> ',
            ' <fg=gray>'.$inventoryStatus.'</>',
            '',
        ]);

        return Command::SUCCESS;
    }

    //
    // Output helpers
    // -------------------------------------------------------------------------------

    /**
     * Write-out multiple lines.
     *
     * @param array<int, string> $lines
     */
    private function writeln(array $lines): void
    {
        foreach ($lines as $line) {
            $this->io->writeln(' ' . $line);
        }
    }

    /**
     * Write-out a separator line.
     */
    private function hr(): void
    {
        $this->writeln(['<fg=cyan>╭───────</><fg=blue>─────────</><fg=bright-blue>─────────</><fg=magenta>─────────</><fg=gray>────────</>']);
    }
}
