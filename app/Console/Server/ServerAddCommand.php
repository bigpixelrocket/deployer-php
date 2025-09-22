<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Console\Server;

use Bigpixelrocket\DeployerPHP\Items\ServerItem;
use Bigpixelrocket\DeployerPHP\Services\SSHService;
use Bigpixelrocket\DeployerPHP\DTOs\ServerDTO;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'server:add', description: 'Add a server entry and verify SSH connectivity')]
class ServerAddCommand extends Command
{
    public function __construct(
        private readonly SSHService $sshService,
        private readonly ServerItem $servers,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Server name (unique identifier)')
            ->addArgument('host', InputArgument::REQUIRED, 'Server host (IP or FQDN)')
            ->addOption('port', null, InputOption::VALUE_REQUIRED, 'SSH port', '22')
            ->addOption('user', null, InputOption::VALUE_REQUIRED, 'SSH username', 'root')
            ->addOption('key', null, InputOption::VALUE_OPTIONAL, 'Path to SSH private key (default: ~/.ssh/id_rsa)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $name */
        $name = $input->getArgument('name');

        /** @var string $host */
        $host = $input->getArgument('host');

        /** @var string|int $portOption */
        $portOption = $input->getOption('port');
        $port = (int) $portOption;

        /** @var string $user */
        $user = $input->getOption('user');

        /** @var string|null $keyPath */
        $keyPath = $input->getOption('key');
        $keyPath = $keyPath !== null && $keyPath !== '' ? $keyPath : null;

        try {
            $io->section('Verifying SSH connectivity');
            $io->writeln(sprintf('<info>Connecting to</info> %s@%s:%d', $user, $host, $port));

            $this->sshService->assertCanConnect($host, $port, $user, $keyPath);

            $io->success('SSH connectivity verified.');

            $dto = new ServerDTO(host: $host, port: $port, user: $user, key: $keyPath);
            $this->servers->create($name, $dto);

            $io->success(sprintf("Server '%s' saved to .deployer/inventory.yml", $name));

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}
