<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyzePmsDocument;
use App\Models\PmsAnalysis;
use App\Models\PmsDocument;
use App\Services\AnalysisPdfGenerator;
use App\Services\PromptManager;
use App\Services\TicketDescriptionGenerator;
use App\Services\YouTrackClient;
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

    public function downloadPdf(PmsAnalysis $analysis, AnalysisPdfGenerator $generator)
    {
        $analysis->loadMissing('document');
        $payload = $analysis->result ?? [];
        $pdf = $generator->generate($analysis, $analysis->document, $payload);
        $filename = 'pms-analysis-'.$analysis->id.'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function generateYouTrackDescription(
        Request $request,
        PmsAnalysis $analysis,
        TicketDescriptionGenerator $generator
    ) {
        try {
            $analysis->loadMissing('document');

            $summary = $request->input('summary');
            $description = $generator->generate($analysis, is_string($summary) ? $summary : null);

            return response()->json([
                'description' => $description,
            ]);
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function createYouTrackIssue(Request $request, PmsAnalysis $analysis, YouTrackClient $client)
    {
        try {
            $analysis->loadMissing('document');

            $validated = $request->validate([
                'summary' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'include_result' => ['nullable', 'boolean'],
            ]);

            $description = (string) ($validated['description'] ?? '');
            if ($request->boolean('include_result')) {
                $result = $analysis->result ?? [];
                if (!is_array($result)) {
                    $result = [];
                }
                $json = json_encode($result, JSON_PRETTY_PRINT);
                if ($json === false) {
                    $json = 'Unable to encode analysis result.';
                }
                $description = trim($description);
                $description .= ($description === '' ? '' : "\n\n");
                $description .= "PMS result:\n".$json;
            }

            $issue = $client->createIssue($validated['summary'], $description);
            $issueId = $issue['idReadable'] ?? $issue['id'] ?? null;
            $issueUrl = $issueId ? $client->issueUrl((string) $issueId) : null;

            return response()->json([
                'issue_id' => $issue['id'] ?? null,
                'issue_idReadable' => $issue['idReadable'] ?? null,
                'issue_url' => $issueUrl,
            ]);
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
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
