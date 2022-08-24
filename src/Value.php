<?php
/**
 * This file is part of phpCacheAdmin.
 *
 * Copyright (c) Róbert Kelčák (https://kelcak.com/)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace RobiNN\Pca;

use JsonException;

class Value {
    /**
     * Decode and format key value.
     *
     * @param string $value
     *
     * @return array<int, mixed>
     */
    public static function format(string $value): array {
        $encode_fn = null;
        $is_formatted = false;

        if (!self::isJson($value)) {
            $decoded = self::decoded($value, $encode_fn);

            if ($decoded !== null) {
                $value = $decoded;
            }

            $formatted = self::formatted($value, $is_formatted);

            if ($formatted !== null) {
                $value = $formatted;
            }
        }

        // Always pretty print the JSON because some formatter may return the value as JSON
        $value = self::prettyPrintJson($value);

        return [$value, $encode_fn, $is_formatted];
    }

    /**
     * Decoded value.
     *
     * @param string  $value
     * @param ?string $encode_fn
     *
     * @return ?string
     */
    private static function decoded(string $value, ?string &$encode_fn): ?string {
        foreach (Config::get('encoding') as $name => $decoder) {
            if (is_callable($decoder['view']) && $decoder['view']($value) !== null) {
                $encode_fn = (string) $name;

                return $decoder['view']($value);
            }
        }

        return null;
    }

    /**
     * Formatted value.
     *
     * @param string $value
     * @param bool   $is_formatted
     *
     * @return ?string
     */
    private static function formatted(string $value, bool &$is_formatted): ?string {
        foreach (Config::get('formatters') as $formatter) {
            if (is_callable($formatter) && $formatter($value) !== null) {
                $is_formatted = true;

                return $formatter($value);
            }
        }

        return null;
    }

    /**
     * Format JSON.
     *
     * @param string $value
     *
     * @return string
     */
    private static function prettyPrintJson(string $value): string {
        try {
            $json_array = json_decode($value, false, 512, JSON_THROW_ON_ERROR);

            if (!is_numeric($value) && $json_array !== null) {
                $value = json_encode($json_array, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);

                return '<pre>'.htmlspecialchars($value).'</pre>';
            }
        } catch (JsonException $e) {
            return htmlspecialchars($value);
        }

        return htmlspecialchars($value);
    }

    /**
     * Check if string is valid JSON.
     *
     * @param string $value
     *
     * @return bool
     */
    private static function isJson(string $value): bool {
        if (!is_string($value)) {
            return false;
        }

        try {
            json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return false;
        }

        return true;
    }

    /**
     * Encode value.
     *
     * @param string $value
     * @param string $encoder
     *
     * @return string
     */
    public static function encode(string $value, string $encoder): string {
        if ($encoder === 'none') {
            return $value;
        }

        $encoder = Config::get('encoding')[$encoder];

        if (is_callable($encoder['save']) && $encoder['save']($value) !== null) {
            return $encoder['save']($value);
        }

        return $value;
    }

    /**
     * Decode value.
     *
     * @param string $value
     * @param string $decoder
     *
     * @return string
     */
    public static function decode(string $value, string $decoder): string {
        if ($decoder === 'none') {
            return $value;
        }

        $decoder = Config::get('encoding')[$decoder];

        if (is_callable($decoder['view']) && $decoder['view']($value) !== null) {
            $value = $decoder['view']($value);
        }

        return $value;
    }
}
