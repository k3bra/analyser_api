<?php

namespace App\Jobs;

use App\Models\PmsAnalysis;
use App\Services\DocumentAnalyzer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AnalyzePmsDocument implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(private readonly int $analysisId)
    {
        $this->onConnection('sync');
    }

    public function handle(DocumentAnalyzer $analyzer): void
    {
        $analysis = PmsAnalysis::findOrFail($this->analysisId);
        $analyzer->analyze($analysis->document, $analysis);
    }
}
