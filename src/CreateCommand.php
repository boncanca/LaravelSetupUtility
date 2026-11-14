<?php

declare(strict_types=1);

namespace LaravelSetup;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class CreateCommand extends Command implements SignalableCommandInterface
{
    private ?Creator $creator = null;

    private bool $cancelled = false;

    public function __construct()
    {
        parent::__construct('create');
    }

    protected function configure(): void
    {
        $this->setDescription('Download and verify an official Laravel 12 app, including upstream Composer dev dependencies, into a new path.')
            ->addArgument('path', InputArgument::REQUIRED, 'Absent destination under an existing private writable parent');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            if (! extension_loaded('pcntl')) {
                throw new \RuntimeException('The pcntl extension is required for cancellation cleanup.');
            }
            $composer = is_executable('/usr/local/bin/composer') ? '/usr/local/bin/composer' : '/usr/bin/composer';
            $this->creator = new Creator([$composer]);
            if ($this->cancelled) {
                $this->creator->cancel();
            }
            $result = $this->creator->create($input->getArgument('path'));
            $output->writeln(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), OutputInterface::OUTPUT_RAW);

            return Command::SUCCESS;
        } catch (\RuntimeException|\JsonException $exception) {
            $errors = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            $errors->writeln('Creation failed: '.$exception->getMessage(), OutputInterface::OUTPUT_RAW);

            return Command::FAILURE;
        }
    }

    /** @return list<int> */
    public function getSubscribedSignals(): array
    {
        return extension_loaded('pcntl') ? [SIGINT, SIGTERM] : [];
    }

    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        $this->cancelled = true;
        $this->creator?->cancel();

        return false;
    }
}
