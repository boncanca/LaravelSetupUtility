<?php

declare(strict_types=1);

namespace LaravelSetup;

use JsonException;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class InspectCommand extends Command
{
    public function __construct()
    {
        parent::__construct('inspect');
    }

    protected function configure(): void
    {
        $this->setDescription('Read declared PHP and Laravel constraints without executing application code.')
            ->addArgument('path', InputArgument::REQUIRED, 'Laravel project directory');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $snapshot = ProjectSnapshot::read($input->getArgument('path'));
            $output->writeln(json_encode([
                'root' => $snapshot['root'],
                'php_constraint' => $snapshot['php_constraint'],
                'laravel_constraint' => $snapshot['laravel_constraint'],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), OutputInterface::OUTPUT_RAW);

            return Command::SUCCESS;
        } catch (JsonException) {
            $error = 'Composer manifest is not valid JSON.';
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        }

        $errors = $output instanceof \Symfony\Component\Console\Output\ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $errors->writeln('Inspection failed: '.$error, OutputInterface::OUTPUT_RAW);

        return Command::FAILURE;
    }
}
