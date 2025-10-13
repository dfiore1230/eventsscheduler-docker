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
$recursiveCallPattern = '\(((?>[^()]+|(?R))*)\)';
$fallbackCallPattern = '\((?:[^()]|\([^()]*\))*\)';

/**
 * Apply a pattern that may fail to compile on older PCRE builds, with a
 * graceful fallback that uses a less capable (but broadly compatible)
 * alternative. Warnings from compilation failures are trapped to avoid
 * polluting the build log while still surfacing fatal errors when both
 * patterns are unusable.
 */
function applyPattern(
    string $pattern,
    string $fallbackPattern,
    callable $callback,
    string $code
): string {
    $original = $code;
    $hadError = false;

    $handler = static function (int $severity, string $message) use (&$hadError): bool {
        if ($severity === E_WARNING && strpos($message, 'preg_') !== false) {
            $hadError = true;
            return true;
        }

        return false;
    };

    set_error_handler($handler, E_WARNING);
    $result = preg_replace_callback($pattern, $callback, $code);
    restore_error_handler();

    if ($hadError || $result === null) {
        $hadError = false;
        $code = $original;

        set_error_handler($handler, E_WARNING);
        $result = preg_replace_callback($fallbackPattern, $callback, $code);
        restore_error_handler();

        if ($hadError || $result === null) {
            fwrite(STDERR, "Warning: unable to rewrite RoleController.php with pattern {$pattern}. Skipping.\n");
            return $original;
        }
    }

    return $result;
}

/**
 * Execute preg_match_all with a recursion-aware fallback.
 */
function matchAll(
    string $pattern,
    string $fallbackPattern,
    string $code,
    int $flags,
    array &$matches
): bool {
    $hadError = false;

    $handler = static function (int $severity, string $message) use (&$hadError): bool {
        if ($severity === E_WARNING && strpos($message, 'preg_') !== false) {
            $hadError = true;
            return true;
        }

        return false;
    };

    set_error_handler($handler, E_WARNING);
    $count = preg_match_all($pattern, $code, $matches, $flags);
    restore_error_handler();

    if ($hadError || $count === false) {
        $hadError = false;

        set_error_handler($handler, E_WARNING);
        $count = preg_match_all($fallbackPattern, $code, $matches, $flags);
        restore_error_handler();

        if ($hadError || $count === false) {
            fwrite(STDERR, "Warning: unable to scan RoleController.php with pattern {$pattern}. Skipping.\n");
            $matches = [];
            return false;
        }
    }

    return $count > 0;
}

$pattern = implode('', [
    '/(?<expression>',
    $jsonDecodePattern,
    $recursiveCallPattern,
    $chainPattern,
    ')\s*(\?->|->)\s*name/i',
]);

$patternFallback = implode('', [
    '/(?<expression>',
    $jsonDecodePattern,
    $fallbackCallPattern,
    $chainPattern,
    ')\s*(\?->|->)\s*name/i',
]);

$optionalPattern = implode('', [
    '/optional\s*\(\s*(?<expression>',
    $jsonDecodePattern,
    $recursiveCallPattern,
    $chainPattern,
    ')\s*\)\s*(\?->|->)\s*name/i',
]);

$optionalFallback = implode('', [
    '/optional\s*\(\s*(?<expression>',
    $jsonDecodePattern,
    $fallbackCallPattern,
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

$code = applyPattern($pattern, $patternFallback, function (array $matches) use (&$updated) {
    $updated = true;
    $call = trim($matches['expression'] ?? $matches[1] ?? '');

    return '$this->resolveLocalizedName(' . $call . ')';
}, $code);

$code = applyPattern($optionalPattern, $optionalFallback, function (array $matches) use (&$updated) {
    $updated = true;
    $call = trim($matches['expression'] ?? $matches[1] ?? '');

    return '$this->resolveLocalizedName(' . $call . ')';
}, $code);
// applyPattern already handles pattern compilation failures, so $code will
// always be a string at this point.

$assignmentPattern = '/(?<target>\$[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*(?:\s*(?:->\s*[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*|\[[^\]]+\]))*)\s*=\s*' . $jsonDecodePattern . $recursiveCallPattern . '\s*;/i';
$assignmentFallback = '/(?<target>\$[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*(?:\s*(?:->\s*[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*|\[[^\]]+\]))*)\s*=\s*' . $jsonDecodePattern . $fallbackCallPattern . '\s*;/i';

if (matchAll($assignmentPattern, $assignmentFallback, $code, PREG_SET_ORDER, $assignmentMatches)) {
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
