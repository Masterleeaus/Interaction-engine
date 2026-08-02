<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class SeedCommand extends Command
{
    protected $signature = 'interaction:seed {--force : Replace existing definitions}';
    protected $description = 'Install the bundled interaction and universal wizard definitions';

    public function handle(): int
    {
        $copied = 0;
        $copied += $this->copyDefinitions(
            dirname(__DIR__, 2) . '/interactions',
            (string) config('interaction.definitions_path', base_path('interactions')),
        );
        $copied += $this->copyDefinitions(
            dirname(__DIR__, 2) . '/wizards',
            (string) config('interaction.wizard.definitions_path', base_path('wizards')),
        );
        $copied += $this->copyDefinitions(
            dirname(__DIR__, 2) . '/templates',
            (string) config('interaction.template.definitions_path', base_path('templates')),
        );
        $this->info("Installed {$copied} definition files.");
        return self::SUCCESS;
    }

    private function copyDefinitions(string $source, string $destination): int
    {
        File::ensureDirectoryExists($destination);
        $copied = 0;
        foreach (File::files($source) as $file) {
            if (!in_array(strtolower($file->getExtension()), ['json', 'yaml', 'yml'], true)) {
                continue;
            }
            $target = rtrim($destination, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file->getFilename();
            if (File::exists($target) && !$this->option('force')) {
                $this->line("Skipped existing {$target}");
                continue;
            }
            File::copy($file->getPathname(), $target);
            $copied++;
        }
        return $copied;
    }
}
