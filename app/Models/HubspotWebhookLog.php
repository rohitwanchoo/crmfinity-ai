<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HubspotWebhookLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_type',
        'object_type',
        'object_id',
        'portal_id',
        'payload',
        'status',
        'error_message',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    /**
     * Scope to get logs for a specific portal.
     */
    public function scopeForPortal($query, string $portalId)
    {
        return $query->where('portal_id', $portalId);
    }

    /**
     * Scope to get failed logs.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Mark as processed.
     */
    public function markAsProcessed(): void
    {
        $this->update(['status' => 'processed']);
    }

    /**
     * Mark as failed.
     */
    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $error,
        ]);
    }
}
