<?php
$basePath = $argv[1] ?? null;
if ($basePath === null) {
    fwrite(STDERR, "Usage: php force_https_patch.php <path>\n");
    exit(1);
}
$file = rtrim($basePath, "\/") . '/app/Providers/AppServiceProvider.php';
if (!file_exists($file)) {
    exit(0);
}
$contents = file_get_contents($file);
if ($contents === false) {
    fwrite(STDERR, "Unable to read {$file}\n");
    exit(1);
}
$replacement = "if (env('FORCE_HTTPS', false)) { URL::forceScheme('https'); }";
$count = 0;
if (str_contains($contents, "\\URL::forceScheme('https');")) {
    $contents = str_replace("\\URL::forceScheme('https');", $replacement, $contents, $count);
} elseif (str_contains($contents, "URL::forceScheme('https');")) {
    $contents = str_replace("URL::forceScheme('https');", $replacement, $contents, $count);
}
if ($count > 0) {
    file_put_contents($file, $contents);
}
