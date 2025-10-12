<?php

namespace App\Utils;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class ColorUtils
{
    private const REMOTE_ENDPOINT = 'https://raw.githubusercontent.com/ghosh/uiGradients/master/gradients.json';

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
        $gradients = self::fetchGradients();

        if (empty($gradients)) {
            $gradients = self::FALLBACK_GRADIENTS;
        }

        return Arr::random($gradients);
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
