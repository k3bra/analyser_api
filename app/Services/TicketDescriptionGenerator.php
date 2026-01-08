<?php

namespace App\Services;

use App\Models\PmsAnalysis;
use Illuminate\Support\Facades\Config;

class TicketDescriptionGenerator
{
    public function __construct(private readonly OpenAiClient $openAiClient)
    {
    }

    public function generate(PmsAnalysis $analysis, ?string $summary = null): string
    {
        $result = $analysis->result ?? [];
        if (!is_array($result) || $result === []) {
            throw new \RuntimeException('Analysis result is empty.');
        }

        $documentName = $analysis->document?->original_name ?? 'Unknown';
        $model = (string) Config::get('pms_analyzer.openai.model', 'gpt-4o-mini');

        $prompt = <<<'PROMPT'
You are drafting a YouTrack issue description for a PMS API analysis result.
Use the provided JSON only. Do not invent details.
Write clear sections with short headings and bullet points where useful.
Keep the description concise and actionable.
PROMPT;

        $payload = [
            'ticket_summary' => $summary,
            'document' => $documentName,
            'analysis_id' => $analysis->id,
            'result' => $result,
        ];

        $content = json_encode($payload, JSON_PRETTY_PRINT);
        if ($content === false) {
            $content = 'Unable to encode analysis result.';
        }

        return $this->openAiClient->generateText($prompt, $content, $model);
    }
}
