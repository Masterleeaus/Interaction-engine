<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Commands;

use Illuminate\Console\Command;

final class InstallCommand extends Command
{
    protected $signature = 'interaction:install {--force : Replace published configuration and definitions} {--no-migrate : Skip database migrations}';
    protected $description = 'Publish, seed, migrate and verify the Titan Zero Interaction Engine';

    public function handle(): int
    {
        $publishOptions = [
            '--provider' => 'TitanZero\\Interaction\\Providers\\InteractionServiceProvider',
            '--tag' => 'interaction-config',
        ];
        if ($this->option('force')) {
            $publishOptions['--force'] = true;
        }
        $this->call('vendor:publish', $publishOptions);
        $this->call('interaction:seed', $this->option('force') ? ['--force' => true] : []);

        if (!$this->option('no-migrate')) {
            $this->call('migrate', ['--force' => true]);
        }

        $status = $this->call('interaction:health');
        if ($status !== self::SUCCESS) {
            $this->error('Installation completed, but health checks failed. Review the output above.');
            return self::FAILURE;
        }
        $this->info('Titan Zero Interaction Engine installed successfully.');
        return self::SUCCESS;
    }
}
