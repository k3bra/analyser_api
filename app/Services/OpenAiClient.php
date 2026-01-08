<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Config;

class OpenAiClient
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => $this->normalizeBaseUri(
                (string) Config::get('pms_analyzer.openai.base_uri')
            ),
            'timeout' => Config::get('pms_analyzer.openai.timeout', 60),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function analyzeChunk(string $prompt, string $chunk, string $model): array
    {
        $apiKey = Config::get('pms_analyzer.openai.api_key');

        if (!$apiKey) {
            throw new \RuntimeException('OPENAI_API_KEY is not configured.');
        }

        $payload = [
            'model' => $model,
            'temperature' => 0.2,
            'text' => [
                'format' => [
                    'type' => 'json_object',
                ],
            ],
            'input' => [
                ['role' => 'system', 'content' => $prompt],
                [
                    'role' => 'user',
                    'content' => "Documentation chunk:\n\n".$chunk,
                ],
            ],
        ];

        try {
            $response = $this->client->post('responses', [
                'headers' => [
                    'Authorization' => 'Bearer '.$apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);
        } catch (GuzzleException $exception) {
            throw new \RuntimeException('OpenAI request failed: '.$exception->getMessage(), 0, $exception);
        }

        $data = json_decode((string) $response->getBody(), true);
        $content = $this->extractContent($data);

        if (!is_string($content)) {
            throw new \RuntimeException('OpenAI response missing content.');
        }

        return $this->decodeJson($content);
    }

    public function generateText(string $prompt, string $content, string $model): string
    {
        $apiKey = Config::get('pms_analyzer.openai.api_key');

        if (!$apiKey) {
            throw new \RuntimeException('OPENAI_API_KEY is not configured.');
        }

        $payload = [
            'model' => $model,
            'temperature' => 0.2,
            'input' => [
                ['role' => 'system', 'content' => $prompt],
                ['role' => 'user', 'content' => $content],
            ],
        ];

        try {
            $response = $this->client->post('responses', [
                'headers' => [
                    'Authorization' => 'Bearer '.$apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);
        } catch (GuzzleException $exception) {
            throw new \RuntimeException('OpenAI request failed: '.$exception->getMessage(), 0, $exception);
        }

        $data = json_decode((string) $response->getBody(), true);
        $responseText = $this->extractContent($data);

        if (!is_string($responseText)) {
            throw new \RuntimeException('OpenAI response missing content.');
        }

        return trim($responseText);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $content): array
    {
        $decoded = json_decode($content, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($content, '{');
        $end = strrpos($content, '}');

        if ($start === false || $end === false || $end <= $start) {
            throw new \RuntimeException('Unable to parse JSON response.');
        }

        $snippet = substr($content, $start, $end - $start + 1);
        $decoded = json_decode($snippet, true);

        if (!is_array($decoded)) {
            throw new \RuntimeException('Unable to parse JSON response.');
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractContent(array $data): ?string
    {
        if (isset($data['output_text']) && is_string($data['output_text'])) {
            return $data['output_text'];
        }

        if (isset($data['output']) && is_array($data['output'])) {
            $parts = [];

            foreach ($data['output'] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $content = $item['content'] ?? null;

                if (is_string($content)) {
                    $parts[] = $content;
                    continue;
                }

                if (!is_array($content)) {
                    continue;
                }

                foreach ($content as $segment) {
                    if (!is_array($segment)) {
                        continue;
                    }

                    $type = $segment['type'] ?? null;
                    if ($type !== null && $type !== 'output_text' && $type !== 'text') {
                        continue;
                    }

                    $text = $segment['text'] ?? null;
                    if (is_string($text)) {
                        $parts[] = $text;
                    }
                }
            }

            if ($parts !== []) {
                return implode("\n", $parts);
            }
        }

        $fallback = $data['choices'][0]['message']['content'] ?? null;

        return is_string($fallback) ? $fallback : null;
    }

    private function normalizeBaseUri(string $baseUri): string
    {
        $baseUri = rtrim($baseUri, '/');

        if (!str_ends_with($baseUri, '/v1')) {
            $baseUri .= '/v1';
        }

        return $baseUri.'/';
    }
}
