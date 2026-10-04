<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$manifestPath = $root . '/files.sha256.json';
$excludedDirectories = ['.git', 'node_modules', 'vendor', 'dist', 'coverage', '__pycache__'];
$files = [];

$directory = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
$filter = new RecursiveCallbackFilterIterator($directory, static function (SplFileInfo $current) use ($excludedDirectories, $manifestPath): bool {
    if ($current->isDir()) {
        return !in_array($current->getFilename(), $excludedDirectories, true);
    }
    return $current->getRealPath() !== realpath($manifestPath);
});

foreach (new RecursiveIteratorIterator($filter) as $file) {
    if (!$file->isFile()) {
        continue;
    }
    $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root) + 1));
    $hash = hash_file('sha256', $file->getPathname());
    if ($hash === false) {
        fwrite(STDERR, "Unable to hash {$relative}.\n");
        exit(2);
    }
    $files[$relative] = $hash;
}

ksort($files, SORT_STRING);
$contents = json_encode($files, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
if (($argv[1] ?? null) === '--write') {
    file_put_contents($manifestPath, $contents);
    fwrite(STDOUT, 'Wrote ' . count($files) . " file hashes to files.sha256.json.\n");
    exit(0);
}

$existing = is_file($manifestPath) ? file_get_contents($manifestPath) : false;
if ($existing === false || !hash_equals($contents, $existing)) {
    fwrite(STDERR, "SHA-256 manifest is stale. Run php bin/manifest.php --write and commit the result.\n");
    exit(1);
}

fwrite(STDOUT, 'Verified ' . count($files) . " file hashes.\n");
