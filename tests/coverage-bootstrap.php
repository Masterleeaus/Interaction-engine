<?php

declare(strict_types=1);

if (!function_exists('xdebug_start_code_coverage')) {
    fwrite(STDERR, "Xdebug coverage is unavailable; install Xdebug or rerun without the coverage bootstrap.\n");
    return;
}

$projectRoot = realpath(dirname(__DIR__));
xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);
register_shutdown_function(static function () use ($projectRoot): void {
    $coverage = xdebug_get_code_coverage();
    $roots = [
        $projectRoot . '/src/',
        $projectRoot . '/packages/policy-engine/src/',
    ];
    $files = [];
    $executable = 0;
    $covered = 0;
    foreach ($coverage as $path => $lines) {
        $realPath = realpath($path);
        $belongsToSource = false;
        if ($realPath !== false) {
            foreach ($roots as $root) {
                if (str_starts_with($realPath, $root)) {
                    $belongsToSource = true;
                    break;
                }
            }
        }
        if (!$belongsToSource) {
            continue;
        }
        $lineCount = 0;
        $hitCount = 0;
        foreach ($lines as $hits) {
            if (!is_int($hits) || $hits < 0) {
                continue;
            }
            $lineCount++;
            if ($hits > 0) {
                $hitCount++;
            }
        }
        if ($lineCount === 0) {
            continue;
        }
        $relative = ltrim(substr($realPath, strlen($projectRoot)), '/');
        $files[$relative] = ['executable_lines' => $lineCount, 'covered_lines' => $hitCount];
        $executable += $lineCount;
        $covered += $hitCount;
    }

    $report = [
        'date_utc' => gmdate(DATE_ATOM),
        'php_version' => PHP_VERSION,
        'commit' => trim((string) shell_exec('git rev-parse HEAD 2>/dev/null')) ?: 'unavailable',
        'scope' => 'Executable lines in project PHP source files loaded by tests/run.php.',
        'covered_lines' => $covered,
        'executable_lines' => $executable,
        'line_coverage_percent' => $executable === 0 ? 0 : round(100 * $covered / $executable, 2),
        'files' => $files,
    ];
    $destination = getenv('PHP_COVERAGE_OUTPUT') ?: ($projectRoot . '/coverage/php.json');
    if (!is_dir(dirname($destination))) {
        mkdir(dirname($destination), 0775, true);
    }
    file_put_contents($destination, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
    fwrite(STDOUT, sprintf("PHP source line coverage: %d/%d (%.2f%%) across %d loaded files\n", $covered, $executable, $report['line_coverage_percent'], count($files)));
});
