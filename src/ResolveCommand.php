<?php

declare(strict_types=1);

namespace LaravelSetup;

use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ResolveCommand extends Command
{
    public function __construct()
    {
        parent::__construct('resolve-plan');
    }

    protected function configure(): void
    {
        $this->setDescription('Resolve public Composer dependencies in disposable staging without applying changes.')
            ->addArgument('path', InputArgument::REQUIRED, 'Laravel project directory')
            ->addArgument('plan-file', InputArgument::REQUIRED, 'Saved canonical plan JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $result = (new Resolver())->resolve($input->getArgument('path'), Planner::readPlan($input->getArgument('plan-file')));
            $output->writeln(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), OutputInterface::OUTPUT_RAW);

            return Command::SUCCESS;
        } catch (RuntimeException | \JsonException $exception) {
            $errors = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            $errors->writeln('Resolution failed: '.$exception->getMessage(), OutputInterface::OUTPUT_RAW);

            return Command::FAILURE;
        }
    }
}
