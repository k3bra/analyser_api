<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Config;

class YouTrackClient
{
    private Client $client;
    private string $baseUrl;

    public function __construct()
    {
        $baseUri = (string) Config::get('services.youtrack.base_uri');
        if ($baseUri === '') {
            throw new \RuntimeException('YOUTRACK_BASE_URI is not configured.');
        }

        $apiBase = $this->normalizeApiBase($baseUri);
        $this->baseUrl = $this->stripApiSuffix($apiBase);

        $this->client = new Client([
            'base_uri' => $apiBase,
            'timeout' => 20,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function createIssue(string $summary, string $description): array
    {
        $token = (string) Config::get('services.youtrack.token');
        if ($token === '') {
            throw new \RuntimeException('YOUTRACK_TOKEN is not configured.');
        }

        $project = $this->projectReference();

        $payload = [
            'summary' => $summary,
            'description' => $description,
            'project' => $project,
        ];

        try {
            $response = $this->client->post('issues', [
                'query' => [
                    'fields' => 'id,idReadable,summary',
                ],
                'headers' => [
                    'Authorization' => 'Bearer '.$token,
                    'Accept' => 'application/json',
                ],
                'json' => $payload,
            ]);
        } catch (GuzzleException $exception) {
            throw new \RuntimeException('YouTrack request failed: '.$exception->getMessage(), 0, $exception);
        }

        $data = json_decode((string) $response->getBody(), true);

        return is_array($data) ? $data : [];
    }

    public function issueUrl(string $issueId): string
    {
        return $this->baseUrl.'/issue/'.$issueId;
    }

    /**
     * @return array<string, string>
     */
    private function projectReference(): array
    {
        $projectId = (string) Config::get('services.youtrack.project_id');
        $projectKey = (string) Config::get('services.youtrack.project_key');

        if ($projectId !== '') {
            return ['id' => $projectId];
        }

        if ($projectKey !== '') {
            return ['shortName' => $projectKey];
        }

        throw new \RuntimeException('YOUTRACK_PROJECT_ID or YOUTRACK_PROJECT_KEY is not configured.');
    }

    private function normalizeApiBase(string $baseUri): string
    {
        $baseUri = rtrim($baseUri, '/');

        if (!str_contains($baseUri, '/api')) {
            $baseUri .= '/api';
        }

        return rtrim($baseUri, '/').'/';
    }

    private function stripApiSuffix(string $apiBase): string
    {
        $apiBase = rtrim($apiBase, '/');
        $baseUrl = preg_replace('#/api$#', '', $apiBase);

        return rtrim($baseUrl ?? $apiBase, '/');
    }
}
