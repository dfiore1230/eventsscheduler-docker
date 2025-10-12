<?php
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

$root = $argv[1] ?? getcwd();
$path = rtrim($root, DIRECTORY_SEPARATOR) . '/app/Http/Controllers/RoleController.php';

if (!is_file($path)) {
    exit(0);
}

$code = file_get_contents($path);

if ($code === false) {
    fwrite(STDERR, "Failed to read RoleController.php\n");
    exit(1);
}

$pattern = '/json_decode\(([^)]+)\)->name/';
$updated = false;

$code = preg_replace_callback($pattern, function (array $matches) use (&$updated) {
    $updated = true;
    $argument = trim($matches[1]);

    return '$this->resolveLocalizedName(' . $argument . ')';
}, $code);

if ($code === null) {
    fwrite(STDERR, "Failed to process RoleController.php\n");
    exit(1);
}

if (!$updated) {
    exit(0);
}

if (strpos($code, 'function resolveLocalizedName(') === false) {
    $method = <<<'METHOD'

    /**
     * Safely extract a translated "name" value from JSON or nested structures.
     */
    private function resolveLocalizedName($value): string
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            }
        }

        if ($value instanceof \JsonSerializable) {
            $value = $value->jsonSerialize();
        }

        if ($value instanceof \stdClass) {
            $value = get_object_vars($value);
        }

        if (is_array($value)) {
            if (isset($value['name']) && is_string($value['name'])) {
                return trim($value['name']);
            }

            foreach ($value as $candidate) {
                if (is_string($candidate) && trim($candidate) !== '') {
                    return trim($candidate);
                }

                if (is_array($candidate)) {
                    foreach ($candidate as $nested) {
                        if (is_string($nested) && trim($nested) !== '') {
                            return trim($nested);
                        }
                    }
                }

                if (is_object($candidate) && isset($candidate->name) && is_string($candidate->name)) {
                    return trim($candidate->name);
                }
            }
        }

        if (is_object($value) && isset($value->name) && is_string($value->name)) {
            return trim($value->name);
        }

        return is_string($value) ? trim($value) : '';
    }
METHOD;

    $pos = strrpos($code, '}');

    if ($pos === false) {
        fwrite(STDERR, "Could not locate class closing brace in RoleController.php\n");
        exit(1);
    }

    $code = substr($code, 0, $pos) . $method . "\n}";
}

if (file_put_contents($path, $code) === false) {
    fwrite(STDERR, "Failed to write RoleController.php\n");
    exit(1);
}
