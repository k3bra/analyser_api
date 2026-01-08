<?php

namespace App\Services;

class CredentialScanner
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function scan(string $text): array
    {
        $lines = preg_split("/\R/", $text) ?: [];
        $matches = [];
        $seen = [];

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            foreach ($this->patterns() as $pattern) {
                if (!preg_match_all($pattern['regex'], $line, $results, PREG_SET_ORDER)) {
                    continue;
                }

                foreach ($results as $result) {
                    $rawValue = $this->sanitizeValue($result['value'] ?? '');
                    if ($rawValue === '') {
                        continue;
                    }

                    $isPlaceholder = $this->isPlaceholder($rawValue);
                    $confidence = $this->estimateConfidence($rawValue, $isPlaceholder);
                    $maskedValue = $this->maskValue($rawValue);
                    $maskedLine = $this->maskValueInLine($line, $rawValue, $maskedValue);

                    $fingerprint = $pattern['type'].'|'.$maskedValue;
                    if (isset($seen[$fingerprint])) {
                        continue;
                    }

                    $seen[$fingerprint] = true;
                    $matches[] = [
                        'type' => $pattern['type'],
                        'label' => $pattern['label'],
                        'value' => $maskedValue,
                        'raw_value' => $rawValue,
                        'confidence' => $confidence,
                        'is_placeholder' => $isPlaceholder,
                        'source_line' => $this->clipLine($maskedLine),
                    ];
                }
            }
        }

        return $matches;
    }

    /**
     * @return array<int, array{type: string, label: string, regex: string}>
     */
    private function patterns(): array
    {
        return [
            [
                'type' => 'username',
                'label' => 'Username',
                'regex' => '/\buser(?:name)?\b\s*[:=]\s*(?<value>[^,\s;]+)/i',
            ],
            [
                'type' => 'username',
                'label' => 'Username',
                'regex' => '/"user(?:name)?"\s*:\s*"(?<value>[^"]+)"/i',
            ],
            [
                'type' => 'password',
                'label' => 'Password',
                'regex' => '/\bpass(?:word)?\b\s*[:=]\s*(?<value>[^,\s;]+)/i',
            ],
            [
                'type' => 'password',
                'label' => 'Password',
                'regex' => '/"pass(?:word)?"\s*:\s*"(?<value>[^"]+)"/i',
            ],
            [
                'type' => 'api_key',
                'label' => 'API key',
                'regex' => '/\b(?:api[\s_-]?key|x-api-key)\b\s*[:=]\s*(?<value>[^,\s;]+)/i',
            ],
            [
                'type' => 'api_key',
                'label' => 'API key',
                'regex' => '/"api[_-]?key"\s*:\s*"(?<value>[^"]+)"/i',
            ],
            [
                'type' => 'client_id',
                'label' => 'Client ID',
                'regex' => '/\bclient[\s_-]?id\b\s*[:=]\s*(?<value>[^,\s;]+)/i',
            ],
            [
                'type' => 'client_id',
                'label' => 'Client ID',
                'regex' => '/"client[_-]?id"\s*:\s*"(?<value>[^"]+)"/i',
            ],
            [
                'type' => 'client_secret',
                'label' => 'Client secret',
                'regex' => '/\bclient[\s_-]?secret\b\s*[:=]\s*(?<value>[^,\s;]+)/i',
            ],
            [
                'type' => 'client_secret',
                'label' => 'Client secret',
                'regex' => '/"client[_-]?secret"\s*:\s*"(?<value>[^"]+)"/i',
            ],
            [
                'type' => 'token',
                'label' => 'Token',
                'regex' => '/\baccess[\s_-]?token\b\s*[:=]\s*(?<value>[^,\s;]+)/i',
            ],
            [
                'type' => 'token',
                'label' => 'Token',
                'regex' => '/"access[_-]?token"\s*:\s*"(?<value>[^"]+)"/i',
            ],
            [
                'type' => 'token',
                'label' => 'Token',
                'regex' => '/\btoken\b\s*[:=]\s*(?<value>[^,\s;]+)/i',
            ],
            [
                'type' => 'token',
                'label' => 'Token',
                'regex' => '/"token"\s*:\s*"(?<value>[^"]+)"/i',
            ],
            [
                'type' => 'authorization',
                'label' => 'Authorization bearer',
                'regex' => '/\bauthorization\b\s*:\s*bearer\s+(?<value>[A-Za-z0-9\-_.=]+)/i',
            ],
            [
                'type' => 'authorization',
                'label' => 'Authorization basic',
                'regex' => '/\bauthorization\b\s*:\s*basic\s+(?<value>[A-Za-z0-9+\/=]+)/i',
            ],
        ];
    }

    private function sanitizeValue(string $value): string
    {
        $value = trim($value);
        $value = trim($value, " \t\n\r\0\x0B\"'`,;)");
        $value = trim($value, "<>[]{}");

        return $value;
    }

    private function maskValue(string $value): string
    {
        $value = trim($value);
        $length = strlen($value);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        $start = substr($value, 0, 2);
        $end = substr($value, -2);
        $maskLength = max(3, $length - 4);

        return $start.str_repeat('*', $maskLength).$end;
    }

    private function maskValueInLine(string $line, string $rawValue, string $maskedValue): string
    {
        if ($rawValue === '') {
            return $line;
        }

        return str_replace($rawValue, $maskedValue, $line);
    }

    private function clipLine(string $line): string
    {
        $line = trim($line);
        if (strlen($line) <= 160) {
            return $line;
        }

        return substr($line, 0, 157).'...';
    }

    private function isPlaceholder(string $value): bool
    {
        $normalized = strtolower(trim($value));
        $normalized = trim($normalized, "\"'`");

        $placeholders = [
            'string',
            'integer',
            'number',
            'boolean',
            'true',
            'false',
            'null',
            'password',
            'pass',
            'username',
            'user',
            'token',
            'api_key',
            'apikey',
            'client_id',
            'client_secret',
            'example',
            'sample',
            'placeholder',
        ];

        if (in_array($normalized, $placeholders, true)) {
            return true;
        }

        if (preg_match('/^your[_-]/', $normalized)) {
            return true;
        }

        if (preg_match('/^[x\*]{3,}$/', $normalized)) {
            return true;
        }

        if (preg_match('/^[<\[]?.+[>\]]?$/', $normalized) && str_contains($normalized, '...')) {
            return true;
        }

        return false;
    }

    private function estimateConfidence(string $value, bool $isPlaceholder): float
    {
        if ($isPlaceholder) {
            return 0.3;
        }

        $length = strlen($value);
        if ($length < 6) {
            return 0.55;
        }

        if ($length > 24) {
            return 0.9;
        }

        return 0.75;
    }
}
