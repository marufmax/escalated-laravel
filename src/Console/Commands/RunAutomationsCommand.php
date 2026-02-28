<?php

namespace Escalated\Laravel\Console\Commands;

use Escalated\Laravel\Services\AutomationRunner;
use Illuminate\Console\Command;

class RunAutomationsCommand extends Command
{
    protected $signature = 'escalated:run-automations';

    protected $description = 'Run time-based automations against open tickets';

    public function handle(AutomationRunner $runner): int
    {
        $affected = $runner->run();

        $this->info("Automation run complete: {$affected} tickets affected.");

        return self::SUCCESS;
    }
}
