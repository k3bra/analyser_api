<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmsAnalysis extends Model
{
    protected $fillable = [
        'pms_document_id',
        'prompt_version',
        'prompt_hash',
        'model',
        'status',
        'progress',
        'chunk_count',
        'chunk_results',
        'result',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'chunk_results' => 'array',
        'result' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $hidden = [
        'chunk_results',
        'prompt_hash',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(PmsDocument::class, 'pms_document_id');
    }
}
