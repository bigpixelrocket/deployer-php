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

    protected bool $isQuiet = false;

    public function __construct(
        protected readonly Container $container,
        protected readonly EnvService $env,
        protected readonly InventoryService $inventory,
    ) {
        parent::__construct();
    }

    /**
     * Initialize IO early so subclasses can use $this->io in initialize()/interact().
     */
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->io = new SymfonyStyle($input, $output);
        $this->isQuiet = $output->isQuiet();

        $envStatus = $this->env->getEnvFileStatus();
        $inventoryStatus = $this->inventory->getInventoryFileStatus();

        $this->hr();
        $this->writeln([
            ' <fg=cyan>Environment:</> ',
            ' <fg=gray>'.$envStatus.'</>',
            '',
            ' <fg=cyan>Inventory:</> ',
            ' <fg=gray>'.$inventoryStatus.'</>',
            '',
        ]);
    }


    /**
     * The main execution method in Symfony commands.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
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
    protected function writeln(string|array $lines): void
    {
        if ($this->isQuiet) {
            return;
        }

        $writeLines = is_array($lines) ? $lines : [$lines];
        foreach ($writeLines as $line) {
            $this->io->writeln(' ' . $line);
        }
    }

    /**
     * Write-out styled text lines.
     *
     * @param array<int, string> $lines
     */
    protected function text(string|array $lines): void
    {
        if ($this->isQuiet) {
            return;
        }

        $writeLines = is_array($lines) ? $lines : [$lines];
        foreach ($writeLines as $line) {
            $this->io->text(' ' . $line);
        }
    }

    /**
     * Write-out a separator line.
     */
    protected function hr(): void
    {
        if ($this->isQuiet) {
            return;
        }

        $this->writeln([
            '<fg=cyan>╭───────</><fg=blue>─────────</><fg=bright-blue>─────────</><fg=magenta>─────────</><fg=gray>────────</>',
            '',
        ]);
    }
}
