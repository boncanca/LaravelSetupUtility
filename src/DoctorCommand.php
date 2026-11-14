<?php

declare(strict_types=1);

namespace LaravelSetup;

use JsonException;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class DoctorCommand extends Command
{
    public function __construct()
    {
        parent::__construct('doctor');
    }

    protected function configure(): void
    {
        $this->setDescription('Report runtime requirements and project facts without running target code.')
            ->addArgument('path', InputArgument::REQUIRED, 'Laravel project directory');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $report = (new Doctor())->inspect($input->getArgument('path'));
            $output->writeln(json_encode($report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), OutputInterface::OUTPUT_RAW);

            return $report['requirements_satisfied'] ? Command::SUCCESS : Command::FAILURE;
        } catch (RuntimeException | JsonException $exception) {
            $errors = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            $errors->writeln('Doctor failed: '.$exception->getMessage(), OutputInterface::OUTPUT_RAW);

            return Command::FAILURE;
        }
    }
}
