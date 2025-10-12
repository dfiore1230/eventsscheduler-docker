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

$pattern = '/(json_decode\(((?>[^()]+|(?R))*)\))\s*(\?->|->)\s*name/';
$updated = false;

$code = preg_replace_callback($pattern, function (array $matches) use (&$updated) {
    $updated = true;
    $call = trim($matches[1]);

    return '$this->resolveLocalizedName(' . $call . ')';
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
    private function resolveLocalizedName($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            } else {
                $value = trim($value);

                return $value;
            }
        }

        if ($value instanceof \JsonSerializable) {
            $value = $value->jsonSerialize();
        }

        if ($value instanceof \Traversable) {
            $value = iterator_to_array($value);
        }

        if ($value instanceof \stdClass) {
            $value = get_object_vars($value);
        }

        if (is_array($value)) {
            $firstNonNull = null;

            if (isset($value['name']) && is_string($value['name'])) {
                $candidate = trim($value['name']);

                if ($candidate !== '') {
                    return $candidate;
                }

                $firstNonNull = $candidate;
            }

            foreach ($value as $candidate) {
                $resolved = $this->resolveLocalizedName($candidate);

                if ($resolved === null) {
                    continue;
                }

                if ($resolved !== '') {
                    return $resolved;
                }

                if ($firstNonNull === null) {
                    $firstNonNull = $resolved;
                }
            }

            return $firstNonNull;
        }

        if (is_object($value) && isset($value->name) && is_string($value->name)) {
            return trim($value->name);
        }

        if (is_string($value)) {
            return trim($value);
        }

        return null;
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
