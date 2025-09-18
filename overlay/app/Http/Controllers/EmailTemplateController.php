<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EmailTemplateController extends Controller
{
    /**
     * Get the base path for Blade templates.
     */
    protected function viewsBasePath(): string
    {
        return resource_path('views');
    }

    /**
     * Directories that may contain email templates.
     *
     * @return array<int, string>
     */
    protected function templateDirectories(): array
    {
        $base = $this->viewsBasePath();

        $candidates = [
            $base . DIRECTORY_SEPARATOR . 'emails',
            $base . DIRECTORY_SEPARATOR . 'mail',
            $base . DIRECTORY_SEPARATOR . 'email',
            $base . DIRECTORY_SEPARATOR . 'mailers',
        ];

        return array_values(array_filter($candidates, static fn (string $path) => is_dir($path)));
    }

    /**
     * List all discoverable template files.
     *
     * @return array<int, array<string, string>>
     */
    protected function discoverTemplates(): array
    {
        $templates = [];
        $base = realpath($this->viewsBasePath());

        if ($base === false) {
            return $templates;
        }

        foreach ($this->templateDirectories() as $directory) {
            foreach (File::allFiles($directory) as $file) {
                $filename = $file->getFilename();

                if (!str_ends_with($filename, '.blade.php')) {
                    continue;
                }

                $relative = Str::after($file->getPathname(), $base . DIRECTORY_SEPARATOR);
                $templates[] = [
                    'key' => $this->encodeKey($relative),
                    'relative' => str_replace(DIRECTORY_SEPARATOR, '/', $relative),
                    'label' => Str::headline(Str::before($filename, '.blade.php')),
                ];
            }
        }

        usort($templates, static fn ($a, $b) => strcmp($a['label'], $b['label']));

        return $templates;
    }

    /**
     * Encode a relative template path into a URL-safe key.
     */
    protected function encodeKey(string $relativePath): string
    {
        $relativePath = str_replace('\\', '/', $relativePath);
        $encoded = base64_encode($relativePath);
        return rtrim(strtr($encoded, '+/', '-_'), '=');
    }

    /**
     * Decode a key back into a relative path.
     */
    protected function decodeKey(string $key): ?string
    {
        $key = str_replace(['-', '_'], ['+', '/'], $key);
        $padding = strlen($key) % 4;
        if ($padding) {
            $key .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($key, true);

        if ($decoded === false) {
            return null;
        }

        $decoded = str_replace('\\', '/', $decoded);

        if (str_contains($decoded, '..')) {
            return null;
        }

        return $decoded;
    }

    /**
     * Resolve a template key into its absolute path and relative label.
     *
     * @return array{relative: string, path: string}
     */
    protected function resolveTemplate(string $key): array
    {
        $relative = $this->decodeKey($key);
        if ($relative === null) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $base = realpath($this->viewsBasePath());
        if ($base === false) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $absolute = realpath($base . DIRECTORY_SEPARATOR . $relative);
        if ($absolute === false || !is_file($absolute)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        if (!str_starts_with($absolute, $base)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return [
            'relative' => str_replace(DIRECTORY_SEPARATOR, '/', $relative),
            'path' => $absolute,
        ];
    }

    public function index(Request $request)
    {
        $this->authorizeSettings($request);

        return view('settings.email-templates.index', [
            'templates' => $this->discoverTemplates(),
        ]);
    }

    public function edit(Request $request, string $template)
    {
        $this->authorizeSettings($request);

        $resolved = $this->resolveTemplate($template);

        return view('settings.email-templates.edit', [
            'templateKey' => $template,
            'templateRelativePath' => $resolved['relative'],
            'contents' => File::get($resolved['path']),
        ]);
    }

    public function update(Request $request, string $template)
    {
        $this->authorizeSettings($request);

        $resolved = $this->resolveTemplate($template);

        $data = $request->validate([
            'contents' => ['required', 'string'],
        ]);

        File::put($resolved['path'], $data['contents']);

        return redirect()
            ->route('settings.email-templates.edit', ['template' => $template])
            ->with('status', 'Email template updated successfully.');
    }

    protected function authorizeSettings(Request $request): void
    {
        $user = $request->user();

        if ($user === null) {
            abort(Response::HTTP_FORBIDDEN);
        }

        if (method_exists($user, 'can') && $user->can('manage-settings')) {
            return;
        }

        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return;
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
            return;
        }

        abort(Response::HTTP_FORBIDDEN);
    }
}
