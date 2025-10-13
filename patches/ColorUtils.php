<?php

namespace App\Utils;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class ColorUtils
{
    private const REMOTE_ENDPOINT = 'https://raw.githubusercontent.com/ghosh/uiGradients/master/gradients.json';
    private const LOCAL_GRADIENT_FILE = 'storage/gradients.json';

    private const FALLBACK_GRADIENTS = [
        ['#FF5F6D', '#FFC371'],
        ['#00C6FF', '#0072FF'],
        ['#7F00FF', '#E100FF'],
        ['#FC5C7D', '#6A82FB'],
        ['#11998E', '#38EF7D'],
        ['#F7971E', '#FFD200'],
        ['#3A1C71', '#D76D77', '#FFAF7B'],
        ['#6441A5', '#2A0845'],
        ['#CB356B', '#BD3F32'],
        ['#12C2E9', '#C471ED', '#F64F59'],
    ];

    /**
     * Retrieve a random gradient definition.
     *
     * @return array<int, string>
     */
    public static function randomGradient(): array
    {
        $gradients = self::loadLocalGradients();

        if (empty($gradients)) {
            $gradients = self::fetchGradients();
        }

        if (empty($gradients)) {
            $gradients = self::FALLBACK_GRADIENTS;
        }

        return Arr::random($gradients);
    }

    /**
     * Build a CSS-ready linear gradient definition from a random palette.
     */
    public static function randomBackgroundImage(): string
    {
        $colors = self::randomGradient();

        if (empty($colors)) {
            return '';
        }

        $stops = implode(', ', $colors);

        return sprintf('linear-gradient(135deg, %s)', $stops);
    }

    /**
     * Attempt to read gradients from the local storage directory.
     *
     * @return array<int, array<int, string>>
     */
    private static function loadLocalGradients(): array
    {
        $path = base_path(self::LOCAL_GRADIENT_FILE);

        if (!is_file($path) || !is_readable($path)) {
            return [];
        }

        $contents = @file_get_contents($path);

        if ($contents === false || $contents === '') {
            return [];
        }

        $decoded = json_decode($contents);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }

        $entries = [];

        if ($decoded instanceof \Traversable) {
            $decoded = iterator_to_array($decoded);
        }

        if ($decoded instanceof \stdClass) {
            $decoded = get_object_vars($decoded);
        }

        if (!is_array($decoded)) {
            return [];
        }

        foreach ($decoded as $entry) {
            $colors = self::extractColors($entry);

            if (count($colors) >= 2) {
                $entries[] = $colors;
            }
        }

        return $entries;
    }

    /**
     * Attempt to download gradients from the upstream source.
     *
     * @return array<int, array<int, string>>
     */
    private static function fetchGradients(): array
    {
        try {
            $response = Http::timeout(5)
                ->acceptJson()
                ->get(self::REMOTE_ENDPOINT);

            if ($response->failed()) {
                return [];
            }

            $payload = $response->json();
        } catch (\Throwable $e) {
            return [];
        }

        if (!is_array($payload)) {
            return [];
        }

        $gradients = [];

        foreach ($payload as $entry) {
            $colors = self::extractColors($entry);

            if (count($colors) >= 2) {
                $gradients[] = $colors;
            }
        }

        return $gradients;
    }

    /**
     * Normalize gradient color definitions regardless of structure.
     *
     * @param mixed $entry
     * @return array<int, string>
     */
    private static function extractColors($entry): array
    {
        if ($entry instanceof \JsonSerializable) {
            $entry = $entry->jsonSerialize();
        }

        if ($entry instanceof \stdClass) {
            $entry = get_object_vars($entry);
        }

        if (!is_array($entry)) {
            return [];
        }

        if (isset($entry['colors']) && is_array($entry['colors'])) {
            return self::sanitizeColors($entry['colors']);
        }

        $pairKeys = [
            ['from', 'to'],
            ['start', 'end'],
            ['left', 'right'],
        ];

        foreach ($pairKeys as $pair) {
            [$firstKey, $secondKey] = $pair;

            if (isset($entry[$firstKey], $entry[$secondKey])) {
                return self::sanitizeColors([$entry[$firstKey], $entry[$secondKey]]);
            }
        }

        if (array_is_list($entry)) {
            return self::sanitizeColors($entry);
        }

        $possible = [];

        foreach ($entry as $value) {
            if (is_array($value)) {
                $possible = $value;
                break;
            }
        }

        return self::sanitizeColors($possible);
    }

    /**
     * Filter a list of colors down to valid strings.
     *
     * @param array<int, mixed> $colors
     * @return array<int, string>
     */
    private static function sanitizeColors(array $colors): array
    {
        $sanitized = [];

        foreach ($colors as $color) {
            if (!is_string($color)) {
                continue;
            }

            $trimmed = trim($color);

            if ($trimmed === '') {
                continue;
            }

            $sanitized[] = $trimmed;
        }

        return array_values($sanitized);
    }
}
