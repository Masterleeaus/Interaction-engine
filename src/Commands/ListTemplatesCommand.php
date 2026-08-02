<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Commands;

use Illuminate\Console\Command;
use TitanZero\Interaction\Registry\CapabilityRegistry;
use TitanZero\Interaction\Template\TemplateCompatibilityChecker;
use TitanZero\Interaction\Template\TemplateDefinition;
use TitanZero\Interaction\Template\TemplateRegistry;

final class ListTemplatesCommand extends Command
{
    protected $signature = 'interaction:templates {--status= : Filter by draft, ready or deprecated} {--json : Emit machine-readable JSON}';
    protected $description = 'List Interaction Engine templates and WorkCore compatibility';

    public function handle(
        TemplateRegistry $templates,
        TemplateCompatibilityChecker $compatibility,
        CapabilityRegistry $capabilities,
    ): int {
        $status = (string) ($this->option('status') ?? '');
        $items = array_values(array_filter(
            $templates->all(),
            static fn(TemplateDefinition $template): bool => $status === '' || $template->status === $status,
        ));

        $rows = array_map(function (TemplateDefinition $template) use ($compatibility, $capabilities): array {
            $available = array_values(array_filter(
                $template->capabilities,
                static fn(string $capability): bool => $capabilities->has($capability),
            ));
            $report = $compatibility->check($template, $available);
            return [
                'id' => $template->id,
                'version' => $template->version,
                'status' => $template->status,
                'category' => $template->category,
                'entry_wizard' => $template->entryWizard,
                'compatible' => $report->compatible,
                'missing_capabilities' => $report->missingCapabilities,
            ];
        }, $items);

        if ($this->option('json')) {
            $this->line((string) json_encode(['templates' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Version', 'Status', 'Category', 'Entry wizard', 'Host compatible'],
            array_map(static fn(array $row): array => [
                $row['id'],
                $row['version'],
                $row['status'],
                $row['category'],
                $row['entry_wizard'],
                $row['compatible'] ? 'yes' : 'no',
            ], $rows),
        );

        return self::SUCCESS;
    }
}
