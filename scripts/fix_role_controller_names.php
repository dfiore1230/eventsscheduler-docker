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

$chainPattern = '(?:(?:\s*(?:\?->|->)\s*[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)|\s*\[[^\]]+\])*';
$jsonDecodePattern = '(?:\\)?json_decode';
$pattern = implode('', [
    '/(?<expression>',
    $jsonDecodePattern,
    '\(((?>[^()]+|(?R))*)\)',
    $chainPattern,
    ')\s*(\?->|->)\s*name/i',
]);

$optionalPattern = implode('', [
    '/optional\s*\(\s*(?<expression>',
    $jsonDecodePattern,
    '\(((?>[^()]+|(?R))*)\)',
    $chainPattern,
    ')\s*\)\s*(\?->|->)\s*name/i',
]);
$updated = false;

/**
 * Convert an assignment target expression into a regex fragment that tolerates whitespace.
 */
function buildExpressionPattern(string $expression): string
{
    $pattern = preg_quote($expression, '/');

    // Allow arbitrary whitespace where the original expression used spaces.
    $pattern = preg_replace('/\\\s+/', '\\s*', $pattern);

    $replacements = [
        '\\-\\>' => '\\s*->\\s*',
        '\\[' => '\\s*\\[\\s*',
        '\\]' => '\\s*\\]\\s*',
    ];

    return str_replace(array_keys($replacements), array_values($replacements), $pattern);
}

$code = preg_replace_callback($pattern, function (array $matches) use (&$updated) {
    $updated = true;
    $call = trim($matches['expression'] ?? $matches[1] ?? '');

    return '$this->resolveLocalizedName(' . $call . ')';
}, $code);

$code = preg_replace_callback($optionalPattern, function (array $matches) use (&$updated) {
    $updated = true;
    $call = trim($matches['expression'] ?? $matches[1] ?? '');

    return '$this->resolveLocalizedName(' . $call . ')';
}, $code);

if ($code === null) {
    fwrite(STDERR, "Failed to process RoleController.php\n");
    exit(1);
}

$assignmentPattern = '/(?<target>\$[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*(?:\s*(?:->\s*[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*|\[[^\]]+\]))*)\s*=\s*' . $jsonDecodePattern . '\(((?>[^()]+|(?R))*)\)\s*;/i';

if (preg_match_all($assignmentPattern, $code, $assignmentMatches, PREG_SET_ORDER)) {
    $expressions = [];

    foreach ($assignmentMatches as $match) {
        $expression = trim($match['target'] ?? $match[1] ?? '');

        if ($expression !== '') {
            $expressions[$expression] = true;
        }
    }

    foreach (array_keys($expressions) as $expression) {
        $expressionPattern = buildExpressionPattern($expression);
        $variablePattern = '/(' . $expressionPattern . ')\s*(\?->|->)\s*name/i';
        $optionalVariablePattern = '/optional\s*\(\s*(' . $expressionPattern . ')\s*\)\s*(\?->|->)\s*name/i';

        $replacements = [];

        if (preg_match_all($variablePattern, $code, $propertyMatches, PREG_OFFSET_CAPTURE)) {
            foreach ($propertyMatches[0] as $index => $match) {
                [$text, $offset] = $match;
                $before = substr($code, 0, $offset);

                $skip = false;

                foreach (['isset', 'empty', 'property_exists'] as $fn) {
                    if (preg_match('/' . $fn . '\s*\($/i', $before)) {
                        $skip = true;
                        break;
                    }
                }

                if ($skip) {
                    continue;
                }

                $expressionText = $propertyMatches[1][$index][0] ?? '';
                $replacement = '$this->resolveLocalizedName(' . trim($expressionText) . ')';

                $replacements[$offset] = [$offset, strlen($text), $replacement];
            }
        }

        if (preg_match_all($optionalVariablePattern, $code, $optionalMatches, PREG_OFFSET_CAPTURE)) {
            foreach ($optionalMatches[0] as $index => $match) {
                [$text, $offset] = $match;
                $inner = $optionalMatches[1][$index][0] ?? '';
                $replacement = '$this->resolveLocalizedName(' . trim($inner) . ')';

                $replacements[$offset] = [$offset, strlen($text), $replacement];
            }
        }

        if (!$replacements) {
            continue;
        }

        $updated = true;

        krsort($replacements);

        foreach ($replacements as [$offset, $length, $replacement]) {
            $code = substr_replace($code, $replacement, $offset, $length);
        }
    }
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
