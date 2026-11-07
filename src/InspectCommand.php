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
            $root = realpath($input->getArgument('path'));
            if ($root === false || !is_dir($root)) {
                throw new RuntimeException('Project directory does not exist.');
            }
            $manifest = realpath($root.'/composer.json');
            if ($manifest === false || !is_file($manifest)) {
                throw new RuntimeException('Project composer.json is missing.');
            }
            if (!str_starts_with($manifest, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
                throw new RuntimeException('Composer manifest resolves outside the project.');
            }
            if (!is_readable($manifest)) {
                throw new RuntimeException('Composer manifest is unreadable.');
            }
            $contents = @file_get_contents($manifest, false, null, 0, 1048577);
            if ($contents === false) {
                throw new RuntimeException('Composer manifest could not be read.');
            }
            if (strlen($contents) > 1048576) {
                throw new RuntimeException('Composer manifest exceeds 1 MiB.');
            }
            $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
            $requirements = is_array($data) ? ($data['require'] ?? null) : null;
            $php = is_array($requirements) ? ($requirements['php'] ?? null) : null;
            $laravel = is_array($requirements) ? ($requirements['laravel/framework'] ?? null) : null;
            if (!is_string($php) || trim($php) === '' || !is_string($laravel) || trim($laravel) === '') {
                throw new RuntimeException('Composer manifest must declare PHP and laravel/framework string constraints in require.');
            }
            $output->writeln(json_encode([
                'root' => $root,
                'php_constraint' => $php,
                'laravel_constraint' => $laravel,
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
