<?php

declare(strict_types=1);

namespace LaravelSetup;

use Symfony\Component\Console\Application as ConsoleApplication;

final class Application extends ConsoleApplication
{
    public function __construct()
    {
        parent::__construct('Laravel Setup Utility', '0.1.0-dev');
        $this->add(new InspectCommand());
        $this->add(new PlanningCommand());
        $this->add(new PlanningCommand(check: true));
        $this->add(new ResolveCommand());
        $this->add(new ManifestCommand());
        $this->add(new ManifestCommand(recover: true));
    }
}
