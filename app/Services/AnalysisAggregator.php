<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class AnalysisAggregator
{
    /**
     * @return array<string, mixed>
     */
    public function aggregate(array $chunkResults): array
    {
        $result = $this->defaultResult();

        foreach ($chunkResults as $chunk) {
            if (!is_array($chunk)) {
                continue;
            }

            $chunk = $this->normalizeChunk($chunk);

            $result['has_get_reservations_endpoint'] = $result['has_get_reservations_endpoint']
                || (bool) $chunk['has_get_reservations_endpoint'];
            $result['supports_webhooks'] = $result['supports_webhooks']
                || (bool) $chunk['supports_webhooks'];
            $result['credentials_available'] = $result['credentials_available']
                || (bool) $chunk['credentials_available'];
            $result['credential_types'] = $this->mergeStrings(
                $result['credential_types'],
                $chunk['credential_types']
            );
            if ($result['pms_name'] === '' && $chunk['pms_name'] !== '') {
                $result['pms_name'] = $chunk['pms_name'];
            }
            $result['get_reservations_endpoints'] = $this->mergeStrings(
                $result['get_reservations_endpoints'],
                $chunk['get_reservations_endpoints']
            );
            $result['get_reservations_endpoint_names'] = $this->mergeStrings(
                $result['get_reservations_endpoint_names'],
                $chunk['get_reservations_endpoint_names']
            );
            $result['get_reservations_available_filters'] = $this->mergeStrings(
                $result['get_reservations_available_filters'],
                $chunk['get_reservations_available_filters']
            );

            foreach ($result['fields'] as $key => $field) {
                $incoming = $chunk['fields'][$key] ?? $field;
                $result['fields'][$key]['available'] = $field['available'] || $incoming['available'];
                $result['fields'][$key]['confidence'] = max($field['confidence'], $incoming['confidence']);
                $result['fields'][$key]['source_fields'] = $this->mergeStrings(
                    $field['source_fields'],
                    $incoming['source_fields']
                );
            }

            foreach ($result['get_reservations_filters'] as $key => $filter) {
                $incoming = $chunk['get_reservations_filters'][$key] ?? $filter;
                $result['get_reservations_filters'][$key]['available'] = $filter['available'] || $incoming['available'];
                $result['get_reservations_filters'][$key]['confidence'] = max(
                    $filter['confidence'],
                    $incoming['confidence']
                );
                $result['get_reservations_filters'][$key]['source_fields'] = $this->mergeStrings(
                    $filter['source_fields'],
                    $incoming['source_fields']
                );
            }

            $result['reservation_statuses'] = $this->mergeStrings(
                $result['reservation_statuses'],
                array_map(
                    fn ($status) => Str::lower(trim((string) $status)),
                    $chunk['reservation_statuses']
                )
            );

            $result['notes'] = $this->mergeStrings($result['notes'], $chunk['notes']);
        }

        $result['reservation_statuses'] = array_values(array_filter($result['reservation_statuses']));
        $result['notes'] = array_values(array_filter($result['notes']));

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function normalizeChunk(array $chunk): array
    {
        $defaults = $this->defaultResult();

        $chunk['has_get_reservations_endpoint'] = (bool) Arr::get(
            $chunk,
            'has_get_reservations_endpoint',
            false
        );
        $chunk['get_reservations_endpoints'] = $this->normalizeStrings(
            Arr::get($chunk, 'get_reservations_endpoints', [])
        );
        $chunk['pms_name'] = trim((string) Arr::get($chunk, 'pms_name', ''));
        $chunk['get_reservations_endpoint_names'] = $this->normalizeStrings(
            Arr::get($chunk, 'get_reservations_endpoint_names', [])
        );
        $chunk['supports_webhooks'] = (bool) Arr::get($chunk, 'supports_webhooks', false);
        $chunk['credentials_available'] = (bool) Arr::get(
            $chunk,
            'credentials_available',
            false
        );
        $fields = Arr::get($chunk, 'fields', []);
        $chunk['fields'] = is_array($fields) ? $fields : [];
        $chunk['get_reservations_filters'] = Arr::get($chunk, 'get_reservations_filters', []);
        $chunk['get_reservations_filters'] = is_array($chunk['get_reservations_filters'])
            ? $chunk['get_reservations_filters']
            : [];
        $chunk['credential_types'] = $this->normalizeStrings(
            Arr::get($chunk, 'credential_types', [])
        );
        $chunk['get_reservations_available_filters'] = $this->normalizeStrings(
            Arr::get($chunk, 'get_reservations_available_filters', [])
        );
        $chunk['reservation_statuses'] = Arr::get($chunk, 'reservation_statuses', []);
        $chunk['notes'] = Arr::get($chunk, 'notes', []);

        foreach ($defaults['fields'] as $key => $fieldDefaults) {
            $incoming = Arr::get($chunk, "fields.{$key}", []);
            $chunk['fields'][$key] = [
                'available' => (bool) Arr::get($incoming, 'available', false),
                'source_fields' => $this->normalizeStrings(
                    Arr::get($incoming, 'source_fields', [])
                ),
                'confidence' => $this->normalizeConfidence(
                    Arr::get($incoming, 'confidence', 0)
                ),
            ];
        }

        foreach ($defaults['get_reservations_filters'] as $key => $filterDefaults) {
            $incoming = Arr::get($chunk, "get_reservations_filters.{$key}", []);
            $chunk['get_reservations_filters'][$key] = [
                'available' => (bool) Arr::get($incoming, 'available', false),
                'source_fields' => $this->normalizeStrings(
                    Arr::get($incoming, 'source_fields', [])
                ),
                'confidence' => $this->normalizeConfidence(
                    Arr::get($incoming, 'confidence', 0)
                ),
            ];
        }

        $chunk['reservation_statuses'] = $this->normalizeStrings($chunk['reservation_statuses']);
        $chunk['notes'] = $this->normalizeStrings($chunk['notes']);

        return $chunk;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultResult(): array
    {
        $fields = [
            'checkout_date',
            'check_in_date',
            'first_name',
            'last_name',
            'country',
            'mobile_phone',
            'email',
            'reservation_status',
            'special_package',
        ];

        $defaults = [];

        foreach ($fields as $field) {
            $defaults[$field] = [
                'available' => false,
                'source_fields' => [],
                'confidence' => 0.0,
            ];
        }

        return [
            'has_get_reservations_endpoint' => false,
            'pms_name' => '',
            'get_reservations_endpoint_names' => [],
            'get_reservations_endpoints' => [],
            'supports_webhooks' => false,
            'credentials_available' => false,
            'credential_types' => [],
            'get_reservations_filters' => [
                'check_in_date' => [
                    'available' => false,
                    'source_fields' => [],
                    'confidence' => 0.0,
                ],
                'check_out_date' => [
                    'available' => false,
                    'source_fields' => [],
                    'confidence' => 0.0,
                ],
                'status' => [
                    'available' => false,
                    'source_fields' => [],
                    'confidence' => 0.0,
                ],
            ],
            'get_reservations_available_filters' => [],
            'fields' => $defaults,
            'reservation_statuses' => [],
            'notes' => [],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function normalizeStrings(mixed $items): array
    {
        if (!is_array($items)) {
            $items = [$items];
        }

        return array_values(array_unique(array_filter(array_map(function ($item) {
            return trim((string) $item);
        }, $items))));
    }

    /**
     * @param array<int, string> $left
     * @param array<int, string> $right
     * @return array<int, string>
     */
    private function mergeStrings(array $left, array $right): array
    {
        return $this->normalizeStrings(array_merge($left, $right));
    }

    private function normalizeConfidence(mixed $value): float
    {
        $confidence = is_numeric($value) ? (float) $value : 0.0;

        if ($confidence < 0) {
            return 0.0;
        }

        if ($confidence > 1) {
            return 1.0;
        }

        return $confidence;
    }
}
