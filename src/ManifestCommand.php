<?php

declare(strict_types=1);

namespace LaravelSetup;

use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ManifestCommand extends Command
{
    public function __construct(private readonly bool $recover = false)
    {
        parent::__construct($recover ? 'recover-resolution' : 'apply-resolution');
    }

    protected function configure(): void
    {
        $this->setDescription($this->recover ? 'Restore original manifests if utility-owned changes have not been edited.' : 'Apply only approved Composer manifest/lock files; no dependencies are installed.')
            ->addArgument('path', InputArgument::REQUIRED, 'Laravel project directory')
            ->addArgument('bundle', InputArgument::REQUIRED, 'External private resolution bundle');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $transaction = new ManifestTransaction();
            $result = $this->recover
                ? $transaction->recover($input->getArgument('path'), $input->getArgument('bundle'))
                : $transaction->apply($input->getArgument('path'), $input->getArgument('bundle'));
            $output->writeln(json_encode($result, JSON_THROW_ON_ERROR), OutputInterface::OUTPUT_RAW);

            return Command::SUCCESS;
        } catch (RuntimeException $error) {
            $errors = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            $errors->writeln('Manifest transaction failed: '.$error->getMessage(), OutputInterface::OUTPUT_RAW);

            return Command::FAILURE;
        }
    }
}
