<?php

declare(strict_types=1);

$base = $argv[1] ?? getcwd();
$base = rtrim($base, "/");

$webPath = $base . '/routes/web.php';
if (is_file($webPath)) {
    $code = file_get_contents($webPath);
    $useStatement = "use App\\Http\\Controllers\\EmailTemplateController;";

    if (strpos($code, $useStatement) === false) {
        if (preg_match_all('/^use\s+[^;]+;/m', $code, $matches, PREG_OFFSET_CAPTURE)) {
            $last = end($matches[0]);
            if ($last) {
                $insertPos = $last[1] + strlen($last[0]);
                $code = substr_replace($code, "\n" . $useStatement, $insertPos, 0);
            }
        } else {
            $phpPos = strpos($code, '<?php');
            if ($phpPos !== false) {
                $insertPos = $phpPos + strlen('<?php');
                $code = substr_replace($code, "\n\n" . $useStatement, $insertPos, 0);
            } else {
                $code = "<?php\n\n" . $useStatement . "\n" . ltrim($code);
            }
        }
    }

    if (strpos($code, 'EmailTemplateController::class') === false) {
        $snippetLines = [
            "",
            "Route::middleware(['auth'])->prefix('settings')->name('settings.')->group(function () {",
            "    Route::get('email-templates', [EmailTemplateController::class, 'index'])->name('email-templates.index');",
            "    Route::get('email-templates/{template}/edit', [EmailTemplateController::class, 'edit'])->name('email-templates.edit');",
            "    Route::put('email-templates/{template}', [EmailTemplateController::class, 'update'])->name('email-templates.update');",
            "});",
        ];

        $code = rtrim($code) . "\n" . implode("\n", $snippetLines) . "\n";
    }

    file_put_contents($webPath, $code);
}

$settingsPaths = [];

$settingsDir = $base . '/resources/views/settings';
if (is_dir($settingsDir)) {
    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($settingsDir, \RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }

        if (!str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        $settingsPaths[] = $file->getPathname();
    }
}

$rootSettingsView = $base . '/resources/views/settings.blade.php';
if (is_file($rootSettingsView)) {
    $settingsPaths[] = $rootSettingsView;
}

$settingsPaths = array_values(array_unique($settingsPaths));

$noteBlock = <<<'NOTE'
    <div class="mt-8 bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-md settings-mail-warning">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l6.451 11.48C18.944 15.943 18.094 17 16.823 17H3.177c-1.27 0-2.121-1.057-1.371-2.421l6.451-11.48zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-.25-5.75a.75.75 0 00-1.5 0v3.5a.75.75 0 001.5 0v-3.5z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3 text-sm text-yellow-800 space-y-2">
                <p>{{ __('If you use Gmail for outbound mail you must create an app password in your Google Account and paste it here instead of your normal login password.') }}</p>
                <p>{{ __('Google blocks standard passwords for SMTP connections, so EventSchedule will only connect successfully with an app-specific password.') }}</p>
                @if (Route::has('settings.email-templates.index'))
                    <p>
                        <a href="{{ route('settings.email-templates.index') }}" class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded-md text-yellow-900 bg-yellow-100 hover:bg-yellow-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500">
                            {{ __('Manage email templates') }}
                        </a>
                    </p>
                @endif
            </div>
        </div>
    </div>
NOTE;

$removalPatterns = [
    '/<[^>]*id="build-info"[^>]*>.*?<\\/[^>]+>\s*/is',
    '/<section[^>]*build[^>]*>.*?<\\/section>\s*/is',
    '/<div[^>]*>\s*<h[1-6][^>]*>[^<]*Build[^<]*<\\/h[1-6]>.*?<\\/div>\s*/is',
];

foreach ($settingsPaths as $settingsPath) {
    if (!is_file($settingsPath)) {
        continue;
    }

    $contents = file_get_contents($settingsPath);

    foreach ($removalPatterns as $pattern) {
        $contents = preg_replace($pattern, "\n", $contents, 1, $count);
        if ($count) {
            break;
        }
    }

    if (strpos($contents, 'settings-mail-warning') === false) {
        $insertion = "\n" . $noteBlock . "\n";
        $pos = strripos($contents, '</x-app-layout>');
        if ($pos === false) {
            $pos = strripos($contents, '@endsection');
        }

        if ($pos === false) {
            continue;
        }

        $contents = substr_replace($contents, $insertion, $pos, 0);
    }

    file_put_contents($settingsPath, $contents);
}
