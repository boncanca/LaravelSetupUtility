<?php

declare(strict_types=1);

namespace LaravelSetup;

use JsonException;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class PlanningCommand extends Command
{
    public function __construct(private readonly bool $check = false)
    {
        parent::__construct($check ? 'check-plan' : 'plan');
    }

    protected function configure(): void
    {
        $this->setDescription($this->check ? 'Check a canonical plan against current inputs and catalog.' : 'Print a read-only engineering package proposal with unresolved dependencies.')
            ->addArgument('path', InputArgument::REQUIRED, 'Laravel project directory');
        if ($this->check) {
            $this->addArgument('plan-file', InputArgument::REQUIRED, 'Previously saved canonical plan JSON');
        } else {
            $this->addOption('recipe', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Supported recipe: pint or phpstan')
                ->addOption('channel', null, InputOption::VALUE_REQUIRED, 'Catalog channel', 'stable');
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $planner = new Planner();
            if ($this->check) {
                $saved = Planner::readPlan($input->getArgument('plan-file'));
                $planner->validate($input->getArgument('path'), $saved);
                $result = ['valid' => true];
            } else {
                $result = $planner->create($input->getArgument('path'), $input->getOption('recipe'), $input->getOption('channel'));
            }
            $output->writeln(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), OutputInterface::OUTPUT_RAW);

            return Command::SUCCESS;
        } catch (JsonException) {
            $message = 'Plan JSON is invalid.';
        } catch (RuntimeException $exception) {
            $message = $exception->getMessage();
        }
        $errors = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $errors->writeln('Planning failed: '.$message, OutputInterface::OUTPUT_RAW);

        return Command::FAILURE;
    }
}
