<?php

namespace App\Services;

use App\Models\PmsAnalysis;
use App\Models\PmsDocument;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentAnalyzer
{
    public function __construct(
        private readonly PdfTextExtractor $extractor,
        private readonly PromptManager $promptManager,
        private readonly OpenAiClient $openAiClient,
        private readonly AnalysisAggregator $aggregator
    ) {
    }

    public function analyze(PmsDocument $document, PmsAnalysis $analysis): void
    {
        $analysis->update([
            'status' => 'processing',
            'started_at' => now(),
            'progress' => 0,
        ]);
        $document->update(['status' => 'processing', 'last_error' => null]);

        try {
            $text = $this->getDocumentText($document);
            $chunker = new TextChunker(
                (int) Config::get('pms_analyzer.chunk_max_chars', 6000),
                (int) Config::get('pms_analyzer.chunk_overlap_chars', 300)
            );
            $chunks = $chunker->chunk($text);

            $analysis->update([
                'chunk_count' => count($chunks),
                'progress' => 0,
            ]);

            if (count($chunks) === 0) {
                $final = $this->aggregator->aggregate([]);

                $analysis->update([
                    'status' => 'completed',
                    'result' => $final,
                    'completed_at' => now(),
                    'progress' => 100,
                ]);
                $document->update(['status' => 'completed']);

                return;
            }

            $prompt = $this->promptManager->getPrompt($analysis->prompt_version);
            $chunkResults = [];

            foreach ($chunks as $index => $chunk) {
                $chunkResult = $this->openAiClient->analyzeChunk(
                    $prompt,
                    $chunk,
                    $analysis->model
                );
                $chunkResult = $this->aggregator->normalizeChunk($chunkResult);
                $chunkResults[] = $chunkResult;

                $analysis->update([
                    'chunk_results' => $chunkResults,
                    'progress' => $this->progressPercent($index + 1, count($chunks)),
                ]);
            }

            $final = $this->aggregator->aggregate($chunkResults);

            $analysis->update([
                'status' => 'completed',
                'result' => $final,
                'completed_at' => now(),
                'progress' => 100,
            ]);
            $document->update(['status' => 'completed']);
        } catch (\Throwable $exception) {
            $analysis->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ]);
            $document->update([
                'status' => 'failed',
                'last_error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function getDocumentText(PmsDocument $document): string
    {
        $disk = $document->storage_disk;
        $textPath = $document->extracted_text_path;

        if ($textPath && Storage::disk($disk)->exists($textPath)) {
            return (string) Storage::disk($disk)->get($textPath);
        }

        $text = $this->extractor->extract($disk, $document->storage_path);
        $text = $this->normalizeText($text);

        $textPath = 'pms-docs/text/'.$document->id.'-'.Str::uuid().'.txt';
        Storage::disk($disk)->put($textPath, $text);

        $document->update([
            'extracted_text_path' => $textPath,
            'text_extracted_at' => now(),
        ]);

        return $text;
    }

    private function normalizeText(string $text): string
    {
        $text = str_replace("\r\n", "\n", $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim((string) $text);
    }

    private function progressPercent(int $current, int $total): int
    {
        if ($total <= 0) {
            return 0;
        }

        return (int) min(100, round(($current / $total) * 100));
    }
}
