<?php

namespace App\Services;

class TextChunker
{
    public function __construct(
        private readonly int $maxChars,
        private readonly int $overlapChars
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function chunk(string $text): array
    {
        $paragraphs = preg_split("/\n{2,}/", trim($text)) ?: [];
        $chunks = [];
        $current = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }

            if (strlen($paragraph) > $this->maxChars) {
                if ($current !== '') {
                    $chunks[] = $this->applyOverlap($current, end($chunks) ?: '');
                    $current = '';
                }

                foreach (str_split($paragraph, $this->maxChars) as $slice) {
                    $chunks[] = $this->applyOverlap($slice, end($chunks) ?: '');
                }

                continue;
            }

            $candidate = $current === '' ? $paragraph : $current."\n\n".$paragraph;

            if (strlen($candidate) > $this->maxChars && $current !== '') {
                $chunks[] = $this->applyOverlap($current, end($chunks) ?: '');
                $current = $paragraph;
                continue;
            }

            $current = $candidate;
        }

        if ($current !== '') {
            $chunks[] = $this->applyOverlap($current, end($chunks) ?: '');
        }

        return $chunks;
    }

    private function applyOverlap(string $chunk, string $previousChunk): string
    {
        if ($this->overlapChars <= 0 || $previousChunk === '') {
            return $chunk;
        }

        $overlap = substr($previousChunk, -$this->overlapChars);

        return trim($overlap."\n\n".$chunk);
    }
}
