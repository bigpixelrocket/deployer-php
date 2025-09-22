<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Console\Server;

use Bigpixelrocket\DeployerPHP\Items\ServerItem;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'server:list', description: 'List all configured servers')]
class ServerListCommand extends Command
{
    public function __construct(
        private readonly ServerItem $servers,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $servers = $this->servers->list();

            if (empty($servers)) {
                $io->info('No servers configured.');
                return Command::SUCCESS;
            }

            $io->section('Configured Servers');

            // Prepare table data
            $tableData = [];
            foreach ($servers as $name => $server) {
                if (!is_array($server)) {
                    continue;
                }

                $host = $server['host'] ?? 'N/A';
                $port = $server['port'] ?? 'N/A';
                $user = $server['user'] ?? 'N/A';
                $key = !empty($server['key']) ? 'Yes' : 'No';

                $tableData[] = [$name, $host, $port, $user, $key];
            }

            $io->table(
                ['Name', 'Host', 'Port', 'User', 'Private Key'],
                $tableData
            );

            $io->info(sprintf('Total servers: %d', count($tableData)));

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}
