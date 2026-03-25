<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Media extends Model
{
    use HasFactory;

    const STATUS_PENDING    = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED  = 'completed';
    const STATUS_FAILED     = 'failed';

    protected $fillable = [
        'original_filename',
        'stored_filename',
        'mime_type',
        'file_size',
    ];

    protected $casts = [
        'outputs'  => 'array',
        'progress' => 'integer',
        'file_size' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
