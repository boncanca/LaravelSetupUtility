<?php

declare(strict_types=1);

namespace LaravelSetup;

use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class NewCommand extends Command
{
    public function __construct()
    {
        parent::__construct('new');
    }

    protected function configure(): void
    {
        $this->setDescription('Preview new raw Laravel creation; --apply installs upstream code and verifies it in staging.')
            ->addArgument('path', InputArgument::REQUIRED, 'New application destination inside an existing directory')
            ->addOption('engineering', null, InputOption::VALUE_REQUIRED, 'none or strict (retains Essentials/Pest/Pint/Larastan/Rector/constitution/CI)', 'none')
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Install dependencies, execute upstream boot/tests, then publish; no migrations');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $service = new NewProject(phpCommand: [PHP_BINARY, '-c', php_ini_loaded_file() ?: '']);
            $result = $input->getOption('apply') ? $service->create($input->getArgument('path'), $input->getOption('engineering')) : $service->plan($input->getArgument('path'), $input->getOption('engineering'));
            $output->writeln(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), OutputInterface::OUTPUT_RAW);

            return Command::SUCCESS;
        } catch (RuntimeException $exception) {
            $errors = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            $errors->writeln('New project failed: '.$exception->getMessage(), OutputInterface::OUTPUT_RAW);

            return Command::FAILURE;
        }
    }
}
