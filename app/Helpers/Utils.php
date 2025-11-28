<?php
if (!function_exists('searchInListOfDicts')) {
    function searchInListOfDicts(array $listOfDicts, string $searchStr): array
    {
        $results = [];

        foreach ($listOfDicts as $dictionary) {
            foreach ($dictionary as $key => $value) {
                $keyStr = is_array($key) ? json_encode($key) : (string)$key;
                $valueStr = is_array($value) ? json_encode($value) : (string)$value;

                if (strpos($keyStr, $searchStr) !== false || strpos($valueStr, $searchStr) !== false) {
                    $results[] = ['key' => $key, 'value' => $value, 'dict' => $dictionary];
                }
            }
        }

        return $results;
    }
}

if (!function_exists('parse_device_attributes')) {
    function parse_device_attributes(mixed $attributes): array
    {
        if (is_string($attributes)) {
            $decoded = json_decode($attributes, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException(
                    'Invalid JSON in device attributes: ' . json_last_error_msg()
                );
            }

            return $decoded;
        }

        return $attributes ?? [];
    }
}

