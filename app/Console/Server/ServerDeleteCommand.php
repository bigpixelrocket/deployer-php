<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Console\Server;

use Bigpixelrocket\DeployerPHP\Items\ServerItem;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'server:delete', description: 'Delete a server entry from inventory')]
class ServerDeleteCommand extends Command
{
    public function __construct(
        private readonly ServerItem $servers,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Server name to delete');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $name */
        $name = $input->getArgument('name');

        try {
            // Confirm deletion
            if (!$io->confirm(sprintf("Are you sure you want to delete server '%s'?", $name))) {
                $io->info('Deletion cancelled.');
                return Command::SUCCESS;
            }

            $this->servers->delete($name);

            $io->success(sprintf("Server '%s' deleted from .deployer/inventory.yml", $name));

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}
