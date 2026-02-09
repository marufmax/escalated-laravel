<?php

namespace Escalated\Laravel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class InstallCommand extends Command
{
    protected $signature = 'escalated:install
        {--force : Overwrite existing files}
        {--config : Only publish configuration}
        {--migrations : Only publish migrations}';

    protected $description = 'Install the Escalated support ticket system';

    public function handle(): int
    {
        $this->info('Installing Escalated...');
        $this->newLine();

        $force = $this->option('force');
        $onlyConfig = $this->option('config');
        $onlyMigrations = $this->option('migrations');
        $publishAll = ! $onlyConfig && ! $onlyMigrations;

        if ($publishAll || $onlyConfig) {
            $this->publishConfig($force);
        }

        if ($publishAll || $onlyMigrations) {
            $this->publishMigrations($force);
        }

        if ($publishAll) {
            $this->publishEmailViews($force);
            $this->installNpmPackage();
        }

        $this->newLine();
        $this->outputSetupInstructions();

        return self::SUCCESS;
    }

    protected function publishConfig(bool $force): void
    {
        $this->components->task('Publishing configuration', function () use ($force) {
            $this->callSilently('vendor:publish', [
                '--tag' => 'escalated-config',
                '--force' => $force,
            ]);
        });
    }

    protected function publishMigrations(bool $force): void
    {
        $this->components->task('Publishing migrations', function () use ($force) {
            $this->callSilently('vendor:publish', [
                '--tag' => 'escalated-migrations',
                '--force' => $force,
            ]);
        });
    }

    protected function publishEmailViews(bool $force): void
    {
        $this->components->task('Publishing email views', function () use ($force) {
            $this->callSilently('vendor:publish', [
                '--tag' => 'escalated-views',
                '--force' => $force,
            ]);
        });
    }

    protected function installNpmPackage(): void
    {
        $this->components->task('Installing npm package', function () {
            $result = Process::run('npm install @escalated-dev/escalated');

            if (! $result->successful()) {
                $this->components->warn('Could not install npm package automatically. Run manually:');
                $this->line('  npm install @escalated-dev/escalated');

                return false;
            }
        });
    }

    protected function outputSetupInstructions(): void
    {
        $this->components->info('Escalated installed successfully!');
        $this->newLine();

        $this->line('  Next steps:');
        $this->newLine();
        $this->line('  1. Implement the Ticketable interface on your User model:');
        $this->newLine();
        $this->line('     use Escalated\Laravel\Contracts\HasTickets;');
        $this->line('     use Escalated\Laravel\Contracts\Ticketable;');
        $this->newLine();
        $this->line('     class User extends Authenticatable implements Ticketable');
        $this->line('     {');
        $this->line('         use HasTickets;');
        $this->line('     }');
        $this->newLine();
        $this->line('  2. Define authorization gates in your AuthServiceProvider:');
        $this->newLine();
        $this->line('     Gate::define(\'escalated-admin\', fn ($user) => $user->is_admin);');
        $this->line('     Gate::define(\'escalated-agent\', fn ($user) => $user->is_agent);');
        $this->newLine();
        $this->line('  3. Run migrations:');
        $this->newLine();
        $this->line('     php artisan migrate');
        $this->newLine();
        $this->line('  4. Add Escalated pages to your Tailwind content config:');
        $this->newLine();
        $this->line('     // tailwind.config.js');
        $this->line('     content: [');
        $this->line('         // ...existing paths,');
        $this->line('         \'./node_modules/@escalated-dev/escalated/src/**/*.vue\',');
        $this->line('     ]');
        $this->newLine();
        $this->line('  5. Add the Inertia page resolver and plugin in your app.ts:');
        $this->newLine();
        $this->line('     import { EscalatedPlugin } from \'@escalated-dev/escalated\';');
        $this->newLine();
        $this->line('     // In createInertiaApp resolve:');
        $this->line('     const escalatedPages = import.meta.glob(');
        $this->line('         \'../../node_modules/@escalated-dev/escalated/src/pages/**/*.vue\'');
        $this->line('     );');
        $this->line('     resolve: (name) => {');
        $this->line('         if (name.startsWith(\'Escalated/\')) {');
        $this->line('             const path = name.replace(\'Escalated/\', \'\');');
        $this->line('             return resolvePageComponent(');
        $this->line('                 `../../node_modules/@escalated-dev/escalated/src/pages/${path}.vue`,');
        $this->line('                 escalatedPages');
        $this->line('             );');
        $this->line('         }');
        $this->line('         // ...existing resolver');
        $this->line('     }');
        $this->newLine();
        $this->line('     // In setup:');
        $this->line('     app.use(EscalatedPlugin, { layout: YourAppLayout })');
        $this->newLine();
        $this->line('  6. Visit /support to see the customer portal');
        $this->newLine();
    }
}
