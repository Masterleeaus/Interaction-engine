<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$trackedOutput = shell_exec('git -C ' . escapeshellarg($root) . ' ls-files -z');
if (!is_string($trackedOutput)) {
    fwrite(STDERR, "Unable to enumerate tracked files.\n");
    exit(2);
}

$trackedFiles = array_values(array_filter(explode("\0", $trackedOutput), static fn(string $path): bool => $path !== ''));
$rules = [
    'private-key' => '/-----BEGIN [^-]+ PRIVATE KEY-----/',
    'known-token' => '/(?<![A-Za-z0-9])(?:sk|pk|rk)-[A-Za-z0-9]{16,}(?![A-Za-z0-9])/',
    'github-token' => '/(?<![A-Za-z0-9])(?:ghp|github_pat)_[A-Za-z0-9_]{20,}(?![A-Za-z0-9])/',
    'aws-access-key' => '/\bAKIA[0-9A-Z]{16}\b/',
    'credential-assignment' => "/\b(?:api[_-]?key|secret|password|token)\b\s*[:=]\s*[\"']?([A-Za-z0-9_\/.=+\-]{12,})/i",
];
$allowlistedFixtures = [
    'scripts/authority-policy-eval.php' => 'interaction-policy-eval-secret-2026',
];

$findings = [];
$allowlistedMatches = 0;
$textFileCount = 0;

foreach ($trackedFiles as $relativePath) {
    $absolutePath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (!is_file($absolutePath)) {
        continue;
    }

    $contents = file_get_contents($absolutePath);
    if (!is_string($contents) || str_contains($contents, "\0")) {
        continue;
    }
    $textFileCount++;

    foreach ($rules as $rule => $pattern) {
        $matches = [];
        if (preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            continue;
        }

        foreach ($matches[0] as $match) {
            $value = (string) $match[0];
            $offset = (int) $match[1];
            $line = 1 + substr_count(substr($contents, 0, $offset), "\n");
            $prefix = substr($contents, 0, $offset + strlen($value));
            $lines = explode("\n", $prefix);
            $lineText = (string) ($lines[array_key_last($lines)] ?? '');
            $isAllowlistedFixture = $rule === 'credential-assignment'
                && isset($allowlistedFixtures[$relativePath])
                && str_contains($lineText, $allowlistedFixtures[$relativePath]);

            if ($isAllowlistedFixture) {
                $allowlistedMatches++;
                continue;
            }

            $findings[$relativePath . ':' . $line . ':' . $rule] = true;
        }
    }
}

if ($findings !== []) {
    ksort($findings);
    fwrite(STDERR, "Secret-like values detected (values redacted):\n");
    foreach (array_keys($findings) as $finding) {
        fwrite(STDERR, " - {$finding}\n");
    }
    exit(1);
}

echo "No secret-like values found in {$textFileCount} tracked text files; allowlisted deterministic fixture matches: {$allowlistedMatches}.\n";
