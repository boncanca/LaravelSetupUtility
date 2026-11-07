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
                $file = $input->getArgument('plan-file');
                if (!is_file($file)) {
                    throw new RuntimeException('Plan file is missing or is not a file.');
                }
                $contents = @file_get_contents($file, false, null, 0, 1048577);
                if ($contents === false || strlen($contents) > 1048576) {
                    throw new RuntimeException('Plan file is unreadable or exceeds 1 MiB.');
                }
                $saved = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
                if (!is_array($saved) || !is_array($saved['recipes'] ?? null) || !is_string($saved['catalog_channel'] ?? null)) {
                    throw new RuntimeException('Plan format is unsupported.');
                }
                $current = $planner->create($input->getArgument('path'), $saved['recipes'], $saved['catalog_channel']);
                if ($saved !== $current) {
                    throw new RuntimeException('Plan does not match current inputs, runtime or catalog. Replan before proceeding.');
                }
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
