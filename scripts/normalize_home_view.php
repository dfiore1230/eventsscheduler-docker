<?php
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

$root = $argv[1] ?? getcwd();
$path = rtrim($root, DIRECTORY_SEPARATOR) . '/resources/views/home.blade.php';

if (!is_file($path)) {
    exit(0);
}

$contents = file_get_contents($path);

if ($contents === false) {
    fwrite(STDERR, "Failed to read home.blade.php\n");
    exit(1);
}

$marker = 'HOME_VIEW_VARIABLE_GUARD';

if (strpos($contents, $marker) !== false) {
    exit(0);
}

$normalizer = <<<'BLADE'
{{-- HOME_VIEW_VARIABLE_GUARD --}}
@php
    $schedules = $schedules ?? [];
    if (!$schedules instanceof \Illuminate\Support\Collection) {
        $schedules = collect($schedules);
    }

    $venues = $venues ?? [];
    if (!$venues instanceof \Illuminate\Support\Collection) {
        $venues = collect($venues);
    }

    $curators = $curators ?? [];
    if (!$curators instanceof \Illuminate\Support\Collection) {
        $curators = collect($curators);
    }
@endphp

BLADE;

$normalizer = str_replace("\n", PHP_EOL, $normalizer);

if (preg_match('/^\s*@extends[^\n]*\n/i', $contents, $match)) {
    $insertPos = strlen($match[0]);
    $contents = substr($contents, 0, $insertPos) . PHP_EOL . $normalizer . substr($contents, $insertPos);
} else {
    $contents = $normalizer . $contents;
}

if (file_put_contents($path, $contents) === false) {
    fwrite(STDERR, "Failed to write home.blade.php\n");
    exit(1);
}
