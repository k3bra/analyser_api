<?php

namespace App\Services;

use App\Models\PmsAnalysis;
use App\Models\PmsDocument;

class AnalysisPdfGenerator
{
    /**
     * @param array<string, mixed> $result
     */
    public function generate(PmsAnalysis $analysis, ?PmsDocument $document, array $result): string
    {
        $lines = $this->buildLines($analysis, $document, $result);

        return $this->buildPdf($lines);
    }

    /**
     * @return array<int, string>
     */
    private function buildLines(PmsAnalysis $analysis, ?PmsDocument $document, array $result): array
    {
        $lines = [];
        $lines[] = 'PMS Analysis Report';
        $lines[] = '===================';
        $lines[] = $this->labelLine('Document', $document?->original_name ?? 'Unknown');
        $lines[] = $this->labelLine('Analysis ID', (string) $analysis->id);
        $lines[] = $this->labelLine('Status', (string) $analysis->status);
        $lines[] = $this->labelLine('Prompt version', (string) $analysis->prompt_version);
        $lines[] = $this->labelLine('Model', (string) $analysis->model);
        $lines[] = $this->labelLine('Started', $analysis->started_at?->toDateTimeString() ?? 'N/A');
        $lines[] = $this->labelLine('Completed', $analysis->completed_at?->toDateTimeString() ?? 'N/A');
        $lines[] = '';
        $lines[] = 'Analysis run';
        $lines[] = '------------';
        $lines[] = $this->labelLine('Prompt', $analysis->prompt_version.' / '.$analysis->model);
        $lines[] = $this->labelLine('Status', (string) $analysis->status);
        $lines[] = $this->labelLine('Progress', (string) $analysis->progress.'%');
        $lines[] = '';
        $isBookingEngine = $this->isBookingEnginePrompt($analysis->prompt_version);

        if ($isBookingEngine) {
            $lines[] = 'Availability endpoint';
            $lines[] = '---------------------';
            $lines[] = $this->labelLine(
                'Availability support',
                ($result['has_availability_endpoint'] ?? false) ? 'Yes' : 'No'
            );
            $availabilityEndpoints = $result['availability_endpoints'] ?? [];
            if (is_array($availabilityEndpoints) && $availabilityEndpoints !== []) {
                $lines[] = $this->labelLine(
                    'Endpoint path(s)',
                    implode(', ', $availabilityEndpoints)
                );
            } else {
                $lines[] = $this->labelLine('Endpoint path(s)', 'Not listed');
            }
            $lines[] = '';
        } else {
            $lines[] = 'Capability snapshot';
            $lines[] = '-------------------';
            $lines[] = $this->labelLine(
                'Get Reservations',
                ($result['has_get_reservations_endpoint'] ?? false) ? 'Yes' : 'No'
            );
            $lines[] = $this->labelLine(
                'Webhook support',
                ($result['supports_webhooks'] ?? false) ? 'Yes' : 'No'
            );
            $credentialsAvailable = ($result['credentials_available'] ?? false) ? 'Yes' : 'No';
            $lines[] = $this->labelLine('Credentials mentioned', $credentialsAvailable);
            $credentialTypes = $result['credential_types'] ?? [];
            if (is_array($credentialTypes) && $credentialTypes !== []) {
                $lines[] = $this->labelLine('Credential types', implode(', ', $credentialTypes));
            }
            $lines[] = '';
            $filtersTitle = 'Key filters';
            $endpointLabel = $this->primaryEndpointLabel($result);
            if ($endpointLabel !== '') {
                $filtersTitle .= ' — '.$endpointLabel;
            }
            $lines[] = $filtersTitle;
            $lines[] = str_repeat('-', strlen($filtersTitle));
            $filters = $result['get_reservations_filters'] ?? [];
            if (is_array($filters) && $filters !== []) {
                $lines[] = $this->formatColumns(
                    ['Filter', 'Status', 'Confidence', 'Doc filter name(s)'],
                    [20, 10, 12, null]
                );
                $lines[] = str_repeat('-', 72);
                $filterLabels = [
                    'check_in_date' => 'check_in_date',
                    'check_out_date' => 'check_out_date',
                    'status' => 'status',
                ];
                foreach ($filterLabels as $key => $label) {
                    $filter = $filters[$key] ?? [];
                    $available = ($filter['available'] ?? false) ? 'available' : 'missing';
                    $confidence = isset($filter['confidence'])
                        ? (int) round(((float) $filter['confidence']) * 100)
                        : 0;
                    $sources = $filter['source_fields'] ?? [];
                    $sourceLabel = '-';
                    if (is_array($sources) && $sources !== []) {
                        $sourceLabel = implode(', ', $sources);
                    }
                    $lines[] = $this->formatColumns(
                        [$label, $available, $confidence.'%', $sourceLabel],
                        [20, 10, 12, null]
                    );
                }
            } else {
                $lines[] = 'No filters detected.';
            }
            $availableFilters = $result['get_reservations_available_filters'] ?? [];
            if (is_array($availableFilters) && $availableFilters !== []) {
                $lines[] = '';
                $lines[] = 'All filters: '.implode(', ', $availableFilters);
            }
            $lines[] = '';
            $lines[] = 'Field coverage';
            $lines[] = '--------------';

            $fields = $result['fields'] ?? [];
            if (is_array($fields) && $fields !== []) {
                $lines[] = $this->formatColumns(
                    ['Field', 'Status', 'Confidence', 'Source fields'],
                    [22, 10, 12, null]
                );
                $lines[] = str_repeat('-', 72);
                $availableCount = 0;
                foreach ($fields as $key => $field) {
                    if (!is_array($field)) {
                        continue;
                    }

                    $available = ($field['available'] ?? false) ? 'available' : 'missing';
                    $confidence = isset($field['confidence'])
                        ? (int) round(((float) $field['confidence']) * 100)
                        : 0;
                    if ($available === 'available') {
                        $availableCount++;
                    }
                    $sources = $field['source_fields'] ?? [];
                    $sourceLabel = '-';
                    if (is_array($sources) && $sources !== []) {
                        $sourceLabel = implode(', ', $sources);
                    }
                    $lines[] = $this->formatColumns(
                        [$key, $available, $confidence.'%', $sourceLabel],
                        [22, 10, 12, null]
                    );
                }
                $lines[] = '';
                $lines[] = $this->labelLine('Available fields', $availableCount.' / '.count($fields));
            } else {
                $lines[] = '- No fields detected.';
            }

            $lines[] = '';
            $lines[] = 'Reservation statuses';
            $lines[] = '--------------------';
            $statuses = $result['reservation_statuses'] ?? [];
            if (is_array($statuses) && $statuses !== []) {
                $lines[] = implode(', ', $statuses);
            } else {
                $lines[] = 'None detected.';
            }

            $lines[] = '';
        }
        $lines[] = 'Credentials detected';
        $lines[] = '--------------------';
        $credentials = $result['credentials'] ?? [];
        if (is_array($credentials) && $credentials !== []) {
            foreach ($credentials as $credential) {
                if (!is_array($credential)) {
                    continue;
                }

                $label = $credential['label'] ?? 'Credential';
                $value = $credential['value'] ?? '';
                $confidence = isset($credential['confidence'])
                    ? (int) round(((float) $credential['confidence']) * 100)
                    : 0;
                $lines[] = sprintf('- %s (%d%%): %s', $label, $confidence, $value);

                $source = $credential['source_line'] ?? null;
                if (is_string($source) && $source !== '') {
                    $lines[] = '  Source: '.$source;
                }
            }
        } else {
            $lines[] = 'None detected.';
        }

        $lines[] = '';
        $lines[] = 'Notes';
        $lines[] = '-----';
        $notes = $result['notes'] ?? [];
        if (is_array($notes) && $notes !== []) {
            foreach ($notes as $note) {
                $lines[] = '- '.(string) $note;
            }
        } else {
            $lines[] = 'None.';
        }

        return $this->wrapLines($lines);
    }

    private function labelLine(string $label, string $value, int $width = 20): string
    {
        return str_pad($label, $width).' : '.$value;
    }

    /**
     * @param array<string, mixed> $result
     */
    private function primaryEndpointLabel(array $result): string
    {
        $endpoints = $result['get_reservations_endpoints'] ?? [];
        if (is_array($endpoints) && $endpoints !== []) {
            return implode(', ', $endpoints);
        }

        $endpointNames = $result['get_reservations_endpoint_names'] ?? [];
        if (is_array($endpointNames) && $endpointNames !== []) {
            return implode(', ', $endpointNames);
        }

        return '';
    }

    private function isBookingEnginePrompt(string $promptVersion): bool
    {
        return str_starts_with($promptVersion, 'booking_engine');
    }

    /**
     * @param array<int, string> $columns
     * @param array<int, int|null> $widths
     */
    private function formatColumns(array $columns, array $widths): string
    {
        $parts = [];

        foreach ($columns as $index => $column) {
            $width = $widths[$index] ?? null;
            $value = (string) $column;
            $parts[] = $width ? str_pad($value, $width) : $value;
        }

        return rtrim(implode('  ', $parts));
    }

    /**
     * @param array<int, string> $lines
     * @return array<int, string>
     */
    private function wrapLines(array $lines): array
    {
        $wrapped = [];
        $maxWidth = 92;

        foreach ($lines as $line) {
            $line = $this->sanitizeText($line);
            if ($line === '') {
                $wrapped[] = '';
                continue;
            }

            $chunks = preg_split("/\R/", wordwrap($line, $maxWidth, "\n", true)) ?: [];
            foreach ($chunks as $chunk) {
                $wrapped[] = $chunk;
            }
        }

        return $wrapped;
    }

    /**
     * @param array<int, string> $lines
     */
    private function buildPdf(array $lines): string
    {
        $linesPerPage = 46;
        $pages = array_chunk($lines, $linesPerPage);
        if ($pages === []) {
            $pages = [['No content']];
        }

        $pageCount = count($pages);
        $fontId = 3 + ($pageCount * 2);
        $objects = [];

        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";

        $kids = [];
        for ($i = 0; $i < $pageCount; $i++) {
            $kids[] = (3 + ($i * 2)).' 0 R';
        }
        $objects[2] = "<< /Type /Pages /Kids [".implode(' ', $kids)."] /Count {$pageCount} >>";

        foreach ($pages as $index => $pageLines) {
            $pageId = 3 + ($index * 2);
            $contentId = 4 + ($index * 2);
            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] "
                ."/Contents {$contentId} 0 R /Resources << /Font << /F1 {$fontId} 0 R >> >> >>";

            $stream = $this->buildContentStream($pageLines);
            $objects[$contentId] = "<< /Length ".strlen($stream)." >>\nstream\n{$stream}\nendstream";
        }

        $objects[$fontId] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";

        return $this->renderPdf($objects);
    }

    /**
     * @param array<int, string> $lines
     */
    private function buildContentStream(array $lines): string
    {
        $leading = 14;
        $startX = 72;
        $startY = 720;
        $content = "BT\n/F1 12 Tf\n{$leading} TL\n{$startX} {$startY} Td\n";

        foreach ($lines as $line) {
            $content .= '('.$this->escapePdfText($line).") Tj\nT*\n";
        }

        return $content."ET\n";
    }

    /**
     * @param array<int, string> $objects
     */
    private function renderPdf(array $objects): string
    {
        $output = "%PDF-1.4\n";
        $offsets = [0];

        ksort($objects);

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($output);
            $output .= $id." 0 obj\n".$body."\nendobj\n";
        }

        $xrefOffset = strlen($output);
        $output .= "xref\n0 ".(count($offsets))."\n";
        $output .= "0000000000 65535 f \n";

        for ($i = 1; $i < count($offsets); $i++) {
            $offset = $offsets[$i] ?? 0;
            $output .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        $output .= "trailer\n<< /Size ".count($offsets)." /Root 1 0 R >>\n";
        $output .= "startxref\n{$xrefOffset}\n%%EOF";

        return $output;
    }

    private function escapePdfText(string $text): string
    {
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);

        return $text;
    }

    private function sanitizeText(string $text): string
    {
        $text = preg_replace('/[^\x20-\x7E]/', '', $text);

        return trim((string) $text);
    }
}
