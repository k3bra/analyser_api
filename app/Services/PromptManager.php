<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;

class PromptManager
{
    public function getPrompt(string $version): string
    {
        $prompts = Config::get('pms_analyzer.prompts', []);

        if (!array_key_exists($version, $prompts)) {
            throw new \InvalidArgumentException('Unknown prompt version: '.$version);
        }

        return $prompts[$version];
    }

    public function getDefaultVersion(): string
    {
        return (string) Config::get('pms_analyzer.default_prompt_version', 'v1');
    }

    public function getPromptHash(string $prompt): string
    {
        return hash('sha256', $prompt);
    }
}
