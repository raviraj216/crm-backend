<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappConversation extends Model
{
    protected $fillable = [
        'business_id',
        'mobile',
        'current_step',
        'collected_data',
        'last_message_at',
    ];

    protected $casts = [
        'collected_data'  => 'array',
        'last_message_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Check if there is an active multi-step flow in progress.
     */
    public function hasActiveStep(): bool
    {
        return !is_null($this->current_step);
    }

    /**
     * Get a single value from collected_data by key.
     * Returns $default if the key does not exist.
     */
    public function getCollected(string $key, mixed $default = null): mixed
    {
        return ($this->collected_data ?? [])[$key] ?? $default;
    }

    /**
     * Merge new key-value pairs into collected_data and save.
     */
    public function collect(array $data): void
    {
        $this->collected_data = array_merge(
            $this->collected_data ?? [],
            $data
        );
        $this->save();
    }

    /**
     * Reset the conversation completely — clears step and all collected data.
     */
    public function reset(): void
    {
        $this->current_step   = null;
        $this->collected_data = null;
        $this->save();
    }

    /**
     * Scope: conversations that have been inactive for more than $minutes.
     * Useful for a scheduled cleanup or session-expiry job.
     *
     * Usage:
     *   WhatsappConversation::stale(30)->get();
     */
    public function scopeStale($query, int $minutes = 60)
    {
        return $query->where('last_message_at', '<', now()->subMinutes($minutes))
                     ->whereNotNull('current_step');
    }
}
