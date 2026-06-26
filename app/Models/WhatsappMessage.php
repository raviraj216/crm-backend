<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappMessage extends Model
{
    protected $fillable = [
        'business_id',
        'triggers',
        'match_mode',
        'priority',
        'step',
        'next_step',
        'is_fallback',
        'type',
        'body',
        'template_name',
        'template_params',
        'collect_as',
        'label',
        'is_active',
    ];

    protected $casts = [
        'triggers'        => 'array',
        'template_params' => 'array',
        'is_fallback'     => 'boolean',
        'is_active'       => 'boolean',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
