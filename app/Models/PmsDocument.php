<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PmsDocument extends Model
{
    protected $fillable = [
        'original_name',
        'storage_disk',
        'storage_path',
        'extracted_text_path',
        'status',
        'text_extracted_at',
        'last_error',
    ];

    protected $casts = [
        'text_extracted_at' => 'datetime',
    ];

    public function analyses(): HasMany
    {
        return $this->hasMany(PmsAnalysis::class);
    }

    public function latestAnalysis(): HasOne
    {
        return $this->hasOne(PmsAnalysis::class)->latestOfMany();
    }
}
