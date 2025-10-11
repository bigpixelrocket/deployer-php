<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Contracts;

use Bigpixelrocket\DeployerPHP\Container;
use Bigpixelrocket\DeployerPHP\Repositories\ServerRepository;
use Bigpixelrocket\DeployerPHP\Repositories\SiteRepository;
use Bigpixelrocket\DeployerPHP\Services\EnvService;
use Bigpixelrocket\DeployerPHP\Services\InventoryService;
use Bigpixelrocket\DeployerPHP\Services\ProcessService;
use Bigpixelrocket\DeployerPHP\Services\PrompterService;
use Bigpixelrocket\DeployerPHP\Services\SSHService;
use Bigpixelrocket\DeployerPHP\Traits\ConsoleInputTrait;
use Bigpixelrocket\DeployerPHP\Traits\ConsoleOutputTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Base command with shared functionality for all commands.
 *
 * Uses ConsoleInputTrait for input gathering and ConsoleOutputTrait
 * for formatted output. All console commands should extend this class.
 */
abstract class BaseCommand extends Command
{
    use ConsoleInputTrait;
    use ConsoleOutputTrait;

    protected InputInterface $input;
    protected OutputInterface $output;
    protected SymfonyStyle $io;

    /**
     * Create a new BaseCommand with the application's services and repositories.
     *
     * The constructor accepts and stores dependencies (environment and inventory services,
     * process and prompting helpers, server/site repositories, SSH service, and the DI container)
     * used by this command and its subclasses.
     */
    public function __construct(
        // Framework
        protected readonly Container $container,

        // Base services
        protected readonly EnvService $env,
        protected readonly InventoryService $inventory,
        protected readonly ProcessService $proc,
        protected readonly PrompterService $prompter,

        // Servers & sites
        protected readonly ServerRepository $servers,
        protected readonly SiteRepository $sites,
        protected readonly SSHService $ssh,
    ) {
        parent::__construct();
    }

    //
    // Configuration
    // -------------------------------------------------------------------------------

    /**
     * Add custom env and inventory options.
     */
    protected function configure(): void
    {
        parent::configure();

        $this->addOption(
            'env',
            null,
            InputOption::VALUE_OPTIONAL,
            'Custom path to .env file (defaults to .env in the current working directory)'
        );

        $this->addOption(
            'inventory',
            null,
            InputOption::VALUE_OPTIONAL,
            'Custom path to inventory.yml file (defaults to inventory.yml in the current working directory)'
        );
    }

    /**
     * Prepare console IO and initialize environment, inventory, and repositories.
     *
     * Sets the command's input/output properties, creates a SymfonyStyle IO helper,
     * applies any custom paths provided via the `--env` and `--inventory` options,
     * loads the corresponding files, and populates the servers and sites repositories
     * from the loaded inventory.
     *
     * @param InputInterface  $input  The current console input.
     * @param OutputInterface $output The current console output.
     */
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);

        $this->input = $input;
        $this->output = $output;
        $this->io = new SymfonyStyle($input, $output);

        //
        // Initialize env service

        /** @var ?string $customEnvPath */
        $customEnvPath = $input->getOption('env');
        $this->env->setCustomPath($customEnvPath);
        $this->env->loadEnvFile();

        //
        // Initialize inventory service

        /** @var ?string $customInventoryPath */
        $customInventoryPath = $input->getOption('inventory');
        $this->inventory->setCustomPath($customInventoryPath);
        $this->inventory->loadInventoryFile();

        //
        // Initialize repositories

        $this->servers->loadInventory($this->inventory);
        $this->sites->loadInventory($this->inventory);
    }

    //
    // Execution
    // -------------------------------------------------------------------------------

    /**
     * Common execution logic.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        //
        // Display env and inventory statuses

        $envStatus = $this->env->getEnvFileStatus();
        $color = str_starts_with($envStatus, 'No .env') ? 'yellow' : 'gray';
        $this->writeln([
            ' <fg=cyan>Environment:</> ',
            " <fg={$color}>{$envStatus}</>",
            '',
        ]);

        $inventoryStatus = $this->inventory->getInventoryFileStatus();
        $this->writeln([
            ' <fg=cyan>Inventory:</> ',
            ' <fg=gray>'.$inventoryStatus.'</>',
            '',
        ]);

        return Command::SUCCESS;
    }
}