<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Console\Server;

use Bigpixelrocket\DeployerPHP\Contracts\BaseCommand;
use Bigpixelrocket\DeployerPHP\DTOs\ServerDTO;
use Bigpixelrocket\DeployerPHP\Traits\ServerHelpersTrait;
use Bigpixelrocket\DeployerPHP\Traits\ServerValidationTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Add and register a new server to the inventory.
 *
 * Prompts for server details and verifies SSH connectivity before saving.
 */
#[AsCommand(name: 'server:add', description: 'Add a new server to the inventory')]
class ServerAddCommand extends BaseCommand
{
    use ServerHelpersTrait;
    use ServerValidationTrait;

    //
    // Configuration
    // -------------------------------------------------------------------------------

    protected function configure(): void
    {
        parent::configure();

        $this
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Server name')
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'Host/IP address')
            ->addOption('port', null, InputOption::VALUE_REQUIRED, 'SSH port (default: 22)')
            ->addOption('username', null, InputOption::VALUE_REQUIRED, 'SSH username (default: root)')
            ->addOption('private-key-path', null, InputOption::VALUE_REQUIRED, 'SSH private key path')
            ->addOption('skip', null, InputOption::VALUE_NONE, 'Skip SSH connection check')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Skip confirmation prompt');
    }

    //
    // Execution
    // -------------------------------------------------------------------------------

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);

        $this->io->hr();

        $this->io->h1('Add New Server');

        //
        // Gather server details

        /** @var string|null $name */
        $name = $this->io->getValidatedOptionOrPrompt(
            'name',
            fn ($validate) => $this->io->promptText(
                label: 'Server name:',
                placeholder: 'web1',
                required: true,
                validate: $validate
            ),
            fn ($value) => $this->validateNameInput($value)
        );

        if ($name === null) {
            return Command::FAILURE;
        }

        /** @var string|null $host */
        $host = $this->io->getValidatedOptionOrPrompt(
            'host',
            fn ($validate) => $this->io->promptText(
                label: 'Host/IP address:',
                placeholder: '192.168.1.100',
                required: true,
                validate: $validate
            ),
            fn ($value) => $this->validateHostInput($value)
        );

        if ($host === null) {
            return Command::FAILURE;
        }

        /** @var string|null $portString */
        $portString = $this->io->getValidatedOptionOrPrompt(
            'port',
            fn ($validate) => $this->io->promptText(
                label: 'SSH port:',
                default: '22',
                required: true,
                validate: $validate
            ),
            fn ($value) => $this->validatePortInput($value)
        );

        if ($portString === null) {
            return Command::FAILURE;
        }

        $port = (int) $portString;

        /** @var string $username */
        $username = $this->io->getOptionOrPrompt(
            'username',
            fn (): string => $this->io->promptText(
                label: 'SSH username:',
                default: 'root',
                required: true
            )
        );

        /** @var string $privateKeyPathRaw */
        $privateKeyPathRaw = $this->io->getOptionOrPrompt(
            'private-key-path',
            fn (): string => $this->io->promptText(
                label: 'SSH private key path (leave empty for default ~/.ssh/id_ed25519 or ~/.ssh/id_rsa):',
                default: '',
                required: false
            )
        );

        /** @var ?string $privateKeyPath */
        $privateKeyPath = $privateKeyPathRaw !== '' ? $privateKeyPathRaw : null;

        //
        // Create DTO and display server info

        $server = new ServerDTO(
            name: $name,
            host: $host,
            port: $port,
            username: $username,
            privateKeyPath: $privateKeyPath
        );

        $this->io->hr();

        $this->displayServerDeets($server);

        //
        // Verify connectivity

        /** @var bool $skipCheck */
        $skipCheck = $this->io->getOptionOrPrompt(
            'skip',
            fn (): bool => !$this->io->promptConfirm(
                label: 'Test SSH connection before saving?',
                default: true
            )
        );

        if ($skipCheck) {
            $this->io->warning('Skipping SSH connection check');
            $this->io->writeln('');
        } else {
            if (!$this->testConnection($server)) {
                return Command::FAILURE;
            }
        }

        //
        // Confirm creation

        /** @var bool $confirmed */
        $confirmed = $this->io->getOptionOrPrompt(
            'yes',
            fn (): bool => $this->io->promptConfirm(
                label: 'Save this server to inventory?',
                default: true
            )
        );

        if (!$confirmed) {
            $this->io->warning('Cancelled adding server');
            $this->io->writeln('');

            return Command::SUCCESS;
        }

        //
        // Save to repository

        try {
            $this->servers->create($server);
        } catch (\RuntimeException $e) {
            $this->io->error('Failed to add server: ' . $e->getMessage());

            return Command::FAILURE;
        }

        $this->io->success('Server added successfully');
        $this->io->writeln('');

        //
        // Show command hint

        $this->io->showCommandHint('server:add', [
            'name' => $name,
            'host' => $host,
            'port' => $port,
            'username' => $username,
            'private-key-path' => $privateKeyPath,
            'skip' => $skipCheck,
            'yes' => $confirmed,
        ]);

        return Command::SUCCESS;
    }

    //
    // Private Helpers
    // -------------------------------------------------------------------------------

    /**
     * Test SSH connection to server with detailed output.
     */
    private function testConnection(ServerDTO $server): bool
    {
        try {
            $this->io->promptSpin(
                callback: fn () => $this->ssh->assertCanConnect(
                    $server->host,
                    $server->port,
                    $server->username,
                    $server->privateKeyPath
                ),
                message: 'Connecting to server...'
            );

            $this->io->success('SSH connection successful');

            return true;
        } catch (\RuntimeException $e) {
            $this->io->error($e->getMessage());

            $this->io->writeln([
                '',
                '  <fg=yellow>Common issues:</>',
                '',
                '  <fg=gray>• Check that the server is accessible from your network</>',
                '  <fg=gray>• Verify SSH is running on the server (port '.$server->port.')</>',
                '  <fg=gray>• Ensure your SSH key has correct permissions (chmod 600)</>',
                '  <fg=gray>• Confirm username "'.$server->username.'" exists on the server</>',
                '',
                '  <fg=gray>Tip: Use</> <fg=cyan>--skip</> <fg=gray>to add server without testing connection.</>',
                '',
            ]);

            return false;
        }
    }
}
