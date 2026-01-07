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

            foreach ($result['fields'] as $key => $field) {
                $incoming = $chunk['fields'][$key] ?? $field;
                $result['fields'][$key]['available'] = $field['available'] || $incoming['available'];
                $result['fields'][$key]['confidence'] = max($field['confidence'], $incoming['confidence']);
                $result['fields'][$key]['source_fields'] = $this->mergeStrings(
                    $field['source_fields'],
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
        $chunk['supports_webhooks'] = (bool) Arr::get($chunk, 'supports_webhooks', false);
        $fields = Arr::get($chunk, 'fields', []);
        $chunk['fields'] = is_array($fields) ? $fields : [];
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
            'first_name',
            'last_name',
            'country',
            'mobile_phone',
            'email',
            'reservation_status',
            'property_name',
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
            'supports_webhooks' => false,
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
