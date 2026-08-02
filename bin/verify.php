<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$commands = [
    ['PHP verification', escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tests/run.php')],
    ['Template catalogue verification', escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tests/template_catalogue_run.php')],
    ['Assurance workflow verification', escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tests/assurance_workflows_run.php')],
];

if (commandExists('npm')) {
    $commands[] = ['TypeScript verification', 'npm test'];
}

foreach ($commands as [$label, $command]) {
    echo "\n== {$label} ==\n";
    passthru('cd ' . escapeshellarg($root) . ' && ' . $command, $status);
    if ($status !== 0) {
        fwrite(STDERR, "{$label} failed with status {$status}.\n");
        exit($status);
    }
}

echo "\nVerification complete.\n";

function commandExists(string $command): bool
{
    exec('command -v ' . escapeshellarg($command) . ' 2>/dev/null', $output, $status);
    return $status === 0 && $output !== [];
}
