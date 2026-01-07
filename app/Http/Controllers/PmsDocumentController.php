<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyzePmsDocument;
use App\Models\PmsAnalysis;
use App\Models\PmsDocument;
use App\Services\PromptManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PmsDocumentController extends Controller
{
    public function __construct(private readonly PromptManager $promptManager)
    {
    }

    public function index(): Response
    {
        $documents = PmsDocument::query()
            ->with('latestAnalysis')
            ->latest()
            ->get();

        return Inertia::render('Documents/Index', [
            'documents' => $documents,
            'promptVersions' => array_keys((array) Config::get('pms_analyzer.prompts', [])),
            'defaultPromptVersion' => $this->promptManager->getDefaultVersion(),
            'defaultModel' => (string) Config::get('pms_analyzer.openai.model'),
        ]);
    }

    public function store(Request $request)
    {
        $maxMb = (int) Config::get('pms_analyzer.max_upload_mb', 20);

        $promptVersions = array_keys((array) Config::get('pms_analyzer.prompts', []));

        $validated = $request->validate([
            'document' => ['required', 'file', 'mimes:pdf', 'max:'.($maxMb * 1024)],
            'prompt_version' => ['nullable', 'string', Rule::in($promptVersions)],
            'model' => ['nullable', 'string'],
        ]);

        $file = $validated['document'];
        $disk = (string) Config::get('pms_analyzer.storage_disk', 'local');
        $path = $file->store('pms-docs', $disk);

        $document = PmsDocument::create([
            'original_name' => $file->getClientOriginalName(),
            'storage_disk' => $disk,
            'storage_path' => $path,
            'status' => 'uploaded',
        ]);

        $analysis = $this->createAnalysis($document, $validated);

        AnalyzePmsDocument::dispatchAfterResponse($analysis->id);

        return redirect()->route('documents.show', $document);
    }

    public function show(PmsDocument $document): Response
    {
        $document->load(['latestAnalysis', 'analyses' => function ($query) {
            $query->latest();
        }]);

        return Inertia::render('Documents/Show', [
            'document' => $document,
            'analysis' => $document->latestAnalysis,
            'analyses' => $document->analyses,
            'promptVersions' => array_keys((array) Config::get('pms_analyzer.prompts', [])),
            'defaultPromptVersion' => $this->promptManager->getDefaultVersion(),
            'defaultModel' => (string) Config::get('pms_analyzer.openai.model'),
        ]);
    }

    public function analyze(Request $request, PmsDocument $document)
    {
        $promptVersions = array_keys((array) Config::get('pms_analyzer.prompts', []));

        $validated = $request->validate([
            'prompt_version' => ['nullable', 'string', Rule::in($promptVersions)],
            'model' => ['nullable', 'string'],
        ]);

        $analysis = $this->createAnalysis($document, $validated);

        AnalyzePmsDocument::dispatchAfterResponse($analysis->id);

        return redirect()->route('documents.show', $document);
    }

    public function download(PmsAnalysis $analysis)
    {
        $payload = $analysis->result ?? [];
        $filename = 'pms-analysis-'.$analysis->id.'.json';

        return response()
            ->json($payload)
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    /**
     * @param array<string, mixed> $input
     */
    private function createAnalysis(PmsDocument $document, array $input): PmsAnalysis
    {
        $promptVersion = $input['prompt_version'] ?? $this->promptManager->getDefaultVersion();
        $model = $input['model'] ?? Config::get('pms_analyzer.openai.model');
        $prompt = $this->promptManager->getPrompt($promptVersion);

        return PmsAnalysis::create([
            'pms_document_id' => $document->id,
            'prompt_version' => $promptVersion,
            'prompt_hash' => $this->promptManager->getPromptHash($prompt),
            'model' => $model,
            'status' => 'queued',
            'progress' => 0,
        ]);
    }
}
